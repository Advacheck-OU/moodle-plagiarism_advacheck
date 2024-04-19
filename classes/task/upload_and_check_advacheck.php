<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Uploading and sending documents for verification.
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @copyright based on work by 1999 Martin Dougiamas {@link http://moodle.com}
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\task;

class upload_and_check_advacheck extends \core\task\scheduled_task
{

    /**
     *
     * @var \plagiarism_advacheck\advacheck_api
     */
    private $api = null;
    private $config;
    private $s;

    /**
     * Class constructor, where we load the plugin settings.
     */
    function __construct()
    {
        $this->config = get_config('plagiarism_advacheck');
        $this->s = get_string_manager();

    }

    /**
     * Task body.
     * Performs the task of downloading and sending documents for scheduled scanning.
     *
     * @global \stdClass $CFG
     * @global \moodle_database $DB
     * @return boolean
     */

    public function execute()
    {
        global $CFG, $DB;
        if (empty($this->config->enabled)) {
            return true;
        }

        // We went to the task of downloading and sending documents for verification.
        echo PHP_EOL . $this->s->get_string('upload_and_check_enter', 'plagiarism_advacheck', null, $CFG->lang) . PHP_EOL;
        require_once ($CFG->libdir . '/filelib.php');
        require_once ($CFG->dirroot . '/mod/assign/locallib.php');
        require_once ($CFG->dirroot . "/plagiarism/advacheck/locallib.php");
        require_once ($CFG->dirroot . '/plagiarism/advacheck/constants.php');
        require_once ($CFG->dirroot . "/plagiarism/advacheck/lib.php");

        if (empty($this->config->uri) || empty($this->config->login) || empty($this->config->password)) {
            // Connection details have not been entered! I'm leaving the task.
            echo PHP_EOL . $this->s->get_string('upload_and_check_nologindata', 'plagiarism_advacheck', null, $CFG->lang) . PHP_EOL;
            return false;
        } else {
            $this->api = new \plagiarism_advacheck\advacheck_api();
        }

        $count = $this->config->cron_check_count;

        if (empty($this->config->enabled)) {
            return true;
        }

        if (empty($this->config->check_forum) && empty($this->config->check_assign) && empty($this->config->check_workshop) && empty($this->config->check_quiz)) {
            return true;
        }

        // Select responses awaiting automatic sending.
        $sql = "SELECT docs.*
          FROM {plagiarism_advacheck_docs} docs
          INNER JOIN {plagiarism_advacheck_course} cfg ON (docs.courseid = cfg.courseid AND docs.cmid = cfg.cmid )
          WHERE (status = ? AND cfg.mode = ?) OR status = ?
          ORDER BY timeadded ";
        $data = $DB->get_records_sql($sql, [PLAGIARISM_ADVACHECK_WAITUPLOAD, PLAGIARISM_ADVACHECK_AUTOMODE, PLAGIARISM_ADVACHECK_ERROR_UPLOADING], 0, $count);
        // Number of documents to upload and send for verification: count($data).
        echo $this->s->get_string('upload_and_check_countdocs', 'plagiarism_advacheck', count($data), $CFG->lang) . PHP_EOL;

        // Sql to select documents from previous user responses
        // To remove them from the index.
        $sql = "SELECT *
          FROM {plagiarism_advacheck_docs}
          WHERE
          doctype = ?
          AND userid = ?
          AND status = ?
          AND answerid != ?";
        // The cycle of uploading documents to Antiplagiarism.
        echo $this->s->get_string('upload_and_check_cycle', 'plagiarism_advacheck', null, $CFG->lang) . PHP_EOL . PHP_EOL;
        foreach ($data as $item) {

            // Document processing and attributes retrieving.
            $a = new \stdClass();
            $a->id = $item->id;
            $a->status = $item->status;
            $a->time = date('d:m:Y H:i:s');
            echo "    " . $this->s->get_string('upload_and_check_docprocessing', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;

            // We keep an event log to investigate incidents.
            plagiarism_advacheck_queue_log(
                $item->docidantplgt,
                '',
                10,
                $item->courseid,
                $item->cmid,
                $item->assignment,
                $item->discussion,
                $item->userid,
                $item->answerid,
                $item->id,
                $item->status,
                'task\upload_and_check_advacheck'
            );

            // An array for document attributes.
            $data_attr = [];
            $assignment = 0;
            $discussion = 0;
            $userid = 0;
            $filename = '';
            $content = '';

            if ((int) $item->doctype == PLAGIARISM_ADVACHECK_ASSIGN) {
                // Processing text from the assignment.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                echo "        " . $this->s->get_string('upload_and_check_assigntext', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                // Let's take the user's response by response ID.
                // We took the results of checks of the answers of previous attempts.
                $sql_p = $sql . ' AND assignment = ?';
                $checkedDocs = $DB->get_records_sql(
                    $sql_p,
                    [PLAGIARISM_ADVACHECK_ASSIGN, $item->userid, PLAGIARISM_ADVACHECK_ININDEX, $item->answerid, $item->assignment]
                );
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkedDocs);
                echo "        " . $this->s->get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $this->remove_from_index($checkedDocs);
                // We request the content and text of the response.
                $s_sql = "SELECT txt.id AS id, txt.onlinetext AS text, ass.name AS cm_name, c.fullname AS course_name
                      FROM {assignsubmission_onlinetext} AS txt
                      INNER JOIN {assign} ass ON ass.id = txt.assignment
                      INNER JOIN {course} c ON c.id = ass.course
                      WHERE txt.submission = ? AND txt.assignment = ?";
                $text = $DB->get_record_sql($s_sql, [$item->answerid, $item->assignment]);
                if ($text) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $a->id = $text->id;
                    echo "        " . $this->s->get_string('upload_and_check_gettextassign', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                    $data_attr['coursefullname'] = $text->course_name;
                    $data_attr['cm_name'] = $text->cm_name;
                    $content = strip_tags($text->text);
                    $filename .= plagiarism_advacheck_assemble_filename($content);
                } else {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . $this->s->get_string('upload_and_check_textassignnotfound', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                    $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
            } else if ((int) $item->doctype == PLAGIARISM_ADVACHECK_FORUM) {
                // Processing text from the forum.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                echo "        " . $this->s->get_string('upload_and_check_forumtext', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $sql_p = $sql . ' AND discussion = ?';
                $params = [PLAGIARISM_ADVACHECK_FORUM, $item->userid, PLAGIARISM_ADVACHECK_ININDEX, $item->answerid, $item->discussion];
                $checkedDocs = $DB->get_records_sql($sql_p, $params);
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkedDocs);
                echo "        " . $this->s->get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $this->remove_from_index($checkedDocs);
                $s_sql = "SELECT fp.id AS id, fp.message AS text, c.fullname AS course_name, f.name AS cm_name, fd.name AS d_name
                      FROM {forum_posts} AS fp
                      INNER JOIN {forum_discussions} fd ON fd.id = fp.discussion
                      INNER JOIN {forum} f ON fd.forum = f.id
                      INNER JOIN {course} c ON c.id = f.course
                      WHERE fp.id = ? AND fp.discussion = ?";

                $text = $DB->get_record_sql($s_sql, [$item->answerid, $item->discussion]);
                if ($text) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $a->id = $text->id;
                    echo "        " . $this->s->get_string('upload_and_check_gettextforum', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                    $data_attr['coursefullname'] = $text->course_name;
                    $data_attr['cm_name'] = $text->cm_name;
                    $data_attr['d_name'] = $text->d_name;
                    $content = strip_tags($text->text);
                    $filename .= plagiarism_advacheck_assemble_filename($content);
                } else {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . $this->s->get_string('upload_and_check_textforumnotfound', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                    $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
            } else if ((int) $item->doctype == PLAGIARISM_ADVACHECK_FILE) {
                // Processing file from any course module : assign/forum/workshop/essey quiz.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                echo "        " . $this->s->get_string('upload_and_check_file', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $fs = get_file_storage();
                $file = $fs->get_file_by_id($item->typeid);
                if (!$file) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . $this->s->get_string('upload_and_check_filenotfound', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                    $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
                $content = $file->get_content();
                $filename = $file->get_filename();

                if ($item->discussion == 0) {
                    $mod_ins_id = $item->assignment;
                    $where = 'assignment = ?';
                } else {
                    $mod_ins_id = $item->discussion;
                    $where = 'discussion = ?';
                }
                /*
                  If the task contains an answer only in the form of a file or a forum message of short length,
                  but there is a file, then we will take all the remaining user files from this task or forum topic.
                */
                $sql_p = $sql . " AND $where ";

                $attempt = null;
                if ($file->get_component() == 'question') {
                    $sql_p .= ' AND attemptnumber != ?';
                    $attempt = $item->attemptnumber;
                }

                $checkedDocs = $DB->get_records_sql(
                    $sql_p,
                    [PLAGIARISM_ADVACHECK_FILE, $item->userid, PLAGIARISM_ADVACHECK_ININDEX, $item->answerid, $mod_ins_id, $attempt]
                );
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkedDocs);
                echo "        " . $this->s->get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;

                $this->remove_from_index($checkedDocs);
                // We received the id of the course module from which the file was sent
                $itemid = $file->get_itemid();
                $params1 = [$itemid, $item->discussion, $itemid, $item->assignment, $itemid, $itemid, $item->userid,];
                $cm_name_sql = "
                  SELECT f.name AS cm_name , fd.name AS d_name, c.fullname AS course_name
                  FROM 		{forum_posts} fp
                  INNER JOIN {forum_discussions} fd ON fd.id = fp.discussion
                  INNER JOIN {forum} f ON f.id = fd.forum
                  INNER JOIN {course} c ON f.course = c.id
                  WHERE fp.id = ? AND fp.discussion = ?

                  UNION

                  SELECT ass.name AS cm_name, '-' AS d_name, c.fullname AS course_name
                  FROM 		{assign_submission} sub
                  INNER JOIN {assign} ass ON sub.assignment = ass.id
                  INNER JOIN {course} c ON c.id = ass.course
                  WHERE sub.id = ? AND ass.id = ?

                  UNION

                  SELECT w.name AS cm_name, '-' AS d_name, c.fullname AS course_name
                  FROM 		{workshop_submissions} ws
                  INNER JOIN {workshop} w ON w.id = ws.workshopid
                  INNER JOIN {course} c ON c.id = w.course
                  WHERE ws.id = ?

                  UNION

                  SELECT CONCAT(quiz.name, ' - ', q.name) AS cm_name, '-' AS d_name, c.fullname AS course_name
                    FROM {question_attempt_steps} qas
                    JOIN {question_attempts} qa ON qas.questionattemptid = qa.id        
                    JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                    JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                    JOIN {quiz} quiz ON quiz.id = quiza.quiz
                    JOIN {course} c ON c.id = quiz.course
                    WHERE qas.id = ? AND qas.userid = ?
                        ";
                // We got the name of the course module.
                //var_dump($params1);
                $cm_name = $DB->get_record_sql($cm_name_sql, $params1);
                $data_attr['cm_name'] = $cm_name->cm_name;
                // If we have a forum, we will write down the name of the discussion.
                if (!is_numeric($cm_name->d_name)) {
                    $data_attr['d_name'] = $cm_name->d_name;
                }
                $data_attr['userid'] = $file->get_userid();
                $data_attr['coursefullname'] = $cm_name->course_name;
            } else if ((int) $item->doctype == PLAGIARISM_ADVACHECK_WORKSHOP) {
                // Processing text from the workshop.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                echo "        " . $this->s->get_string('upload_and_check_workshoptext', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $checkedDocs = $DB->get_records_sql(
                    $sql,
                    [PLAGIARISM_ADVACHECK_WORKSHOP, $item->userid, PLAGIARISM_ADVACHECK_ININDEX, $item->answerid]
                );
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkedDocs);
                echo "        " . $this->s->get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;

                $this->remove_from_index($checkedDocs);
                // Get content and response text.
                $s_sql = "SELECT ws.id AS id, ws.content AS text, w.name AS cm_name, c.fullname AS course_name
                      FROM {workshop_submissions} AS ws
                      INNER JOIN {workshop} w ON w.id = ws.workshopid       
                      INNER JOIN {course} c ON c.id = w.course
                      WHERE ws.id = ?";
                $text = $DB->get_record_sql($s_sql, [$item->answerid]);
                if ($text) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $a->id = $text->id;
                    echo "        " . $this->s->get_string('upload_and_check_gettextworkshop', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;

                    $data_attr['coursefullname'] = $text->course_name;
                    $data_attr['cm_name'] = $text->cm_name;
                    $content = strip_tags($text->text);
                    $filename .= plagiarism_advacheck_assemble_filename($content);
                } else {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . $this->s->get_string('upload_and_check_textworkshopnotfound', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;
                    $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
            } else if ((int) $item->doctype == PLAGIARISM_ADVACHECK_QUIZ) {
                // Processing essays from the quiz.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                echo "        " . $this->s->get_string('upload_and_check_quiztext', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $p = $sql . ' AND attemptnumber != ?';
                $checkedDocs = $DB->get_records_sql(
                    $sql,
                    [PLAGIARISM_ADVACHECK_QUIZ, $item->userid, PLAGIARISM_ADVACHECK_ININDEX, $item->answerid, $item->attemptnumber]
                );
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkedDocs);
                echo "        " . $this->s->get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $this->remove_from_index($checkedDocs);
                // We request the text of the answer and the name of the test, question and course name.
                $s_sql = "SELECT qas.id, CONCAT(quiz.name, ': ', q.name) AS cm_name, c.fullname AS course_name, qa.responsesummary as text
                FROM {question_attempt_steps} qas
                JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND asd.name = 'answer'
                JOIN {question_attempts} qa ON qas.questionattemptid = qa.id
                JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                JOIN {quiz} quiz ON quiz.id = quiza.quiz
                JOIN {course} c ON c.id = quiz.course
                WHERE qas.id = ? AND qas.userid = ?";

                $text = $DB->get_record_sql($s_sql, [$item->answerid, $item->userid]);
                if ($text) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $a->id = $text->id;
                    echo "        " . $this->s->get_string('upload_and_check_gettextquiz', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                    $data_attr['coursefullname'] = $text->course_name;
                    $data_attr['cm_name'] = $text->cm_name;
                    $content = $text->text;
                    $filename .= plagiarism_advacheck_assemble_filename($content);
                } else {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . $this->s->get_string('upload_and_check_textquiznotfound', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;
                    $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
            }

            // Uploading documents to the AP and initiating verification.
            $docidantplgt = null;
            $status = null;
            $data_attr['userid'] = $item->userid;
            if (mb_strlen($content) > 0) {
                // Processing document attributes.
                $doc_attr = plagiarism_advacheck_prepare_doc_attr((object) $data_attr, $item->cmid, $item->courseid, $item->id);
                // Let's set the status to "in the process of downloading" so that the cron task does not download this document again.
                $status = PLAGIARISM_ADVACHECK_UPLOADING;
                $DB->set_field('plagiarism_advacheck_docs', 'timeupload_start', time(), ['id' => $item->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'error', '', ['id' => $item->id]);
                plagiarism_advacheck_queue_log(
                    '',
                    '',
                    2,
                    $item->courseid,
                    $item->cmid,
                    $item->assignment,
                    $item->discussion,
                    $item->userid,
                    $item->answerid,
                    $item->id,
                    $status,
                    'task\upload_and_check_advacheck'
                );
                $a = new \stdClass();
                $a->id = $item->id;
                $a->time = date('d:m:Y H:i:s');
                echo "        " . $this->s->get_string('upload_and_check_uploaddoc', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $ap_docid = $this->api->upload_doc($filename, $content, $item->courseid, 'task\upload_and_check_advacheck', $conn_error, $doc_attr->works_types);

                // Handling loading documents errors.
                if (is_string($ap_docid)) {
                    if ($conn_error) {
                        $status = PLAGIARISM_ADVACHECK_ERROR_UPLOADING;
                    } else {
                        $status = PLAGIARISM_ADVACHECK_INVALIDFILETYPE;
                    }
                    $a = new \stdClass();
                    $a->id = $item->id;
                    $a->error = $ap_docid;
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . $this->s->get_string('upload_and_check_erroruploaddoc', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;

                    $s = $ap_docid;
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $item->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                    plagiarism_advacheck_queue_log(
                        $item->docidantplgt,
                        $item->reportedit,
                        14,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        $status,
                        'task\upload_and_check_advacheck',
                        $s
                    );
                    continue;
                } else {
                    // Let's check whether Anti-Plagiarism considered the document erroneous.
                    if ($ap_docid->Reason !== 'NoError') {
                        $a = new \stdClass();
                        $a->id = $item->id;
                        $a->error = $ap_docid->FailDetails;
                        $a->time = date('d:m:Y H:i:s');
                        echo "        " . $this->s->get_string('upload_and_check_errordoc', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;

                        $status = PLAGIARISM_ADVACHECK_INVALIDFILETYPE;
                        $s = $ap_docid->FailDetails;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $item->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                        plagiarism_advacheck_queue_log(
                            $item->docidantplgt,
                            $item->reportedit,
                            14,
                            $item->courseid,
                            $item->cmid,
                            $item->assignment,
                            $item->discussion,
                            $item->userid,
                            $item->answerid,
                            $item->id,
                            $status,
                            'task\upload_and_check_advacheck',
                            $s
                        );
                        continue;
                    }
                    plagiarism_advacheck_queue_log(
                        '',
                        '',
                        11,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        $status,
                        'task\upload_and_check_advacheck'
                    );
                    $doc_attr_res = $this->api->upload_doc_attr($ap_docid->Id, $doc_attr->custom_attrs);
                    plagiarism_advacheck_queue_log(
                        '',
                        '',
                        12,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        $status,
                        'task\upload_and_check_advacheck'
                    );
                    // Handling loading documents errors.
                    if (is_string($doc_attr_res)) {
                        $a = new \stdClass();
                        $a->id = $item->id;
                        $a->error = $doc_attr_res;
                        $a->time = date('d:m:Y H:i:s');
                        echo "        " . $this->s->get_string('upload_and_check_uploaddocattrerror', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;

                        $status = PLAGIARISM_ADVACHECK_ERROR_UPLOADING;
                        $s = $doc_attr_res;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $item->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                        plagiarism_advacheck_queue_log(
                            $item->docidantplgt,
                            $item->reportedit,
                            14,
                            $item->courseid,
                            $item->cmid,
                            $item->assignment,
                            $item->discussion,
                            $item->userid,
                            $item->answerid,
                            $item->id,
                            $status,
                            'task\upload_and_check_advacheck',
                            $s
                        );
                        continue;
                    }
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . $this->s->get_string('upload_and_check_uploaddocsuccess', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;
                    $rec['timeupload_end'] = time();
                    $rec['status'] = PLAGIARISM_ADVACHECK_UPLOADED;
                    // ID in Antiplagiarism is a structure. Let's write it in serialized form.
                    $rec['docidantplgt'] = serialize($ap_docid->Id);
                    // To make it easier to find the answer, let’s save the ID field separately.
                    $rec['externalid'] = $ap_docid->Id->Id;
                    $rec['id'] = $item->id;
                    $DB->update_record('plagiarism_advacheck_docs', (object) $rec);
                    plagiarism_advacheck_queue_log(
                        $docidantplgt,
                        '',
                        3,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        PLAGIARISM_ADVACHECK_UPLOADED,
                        'task\upload_and_check_advacheck'
                    );
                    $a = new \stdClass();
                    $a->id = $item->id;
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . $this->s->get_string('upload_and_check_startingdoccheck', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;
                    $m = $this->api->start_check($ap_docid->Id);
                    // The status is "in checking", because checks can take a long time.
                    $status = PLAGIARISM_ADVACHECK_CHECKING;
                    $DB->set_field('plagiarism_advacheck_docs', 'timecheck_start', time(), ['id' => $item->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                    plagiarism_advacheck_queue_log(
                        $docidantplgt,
                        '',
                        4,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        $status,
                        'task\upload_and_check_advacheck'
                    );
                    // Handling start check errors.
                    if ($m !== true) {
                        $a = new \stdClass();
                        $a->id = $item->id;
                        $a->error = $m;
                        $a->time = date('d:m:Y H:i:s');
                        echo "        " . $this->s->get_string('upload_and_check_startingdoccheckerror', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;
                        $status = PLAGIARISM_ADVACHECK_ERROR_CHECKING;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $item->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                        plagiarism_advacheck_queue_log(
                            $item->docidantplgt,
                            $item->reportedit,
                            14,
                            $item->courseid,
                            $item->cmid,
                            $item->assignment,
                            $item->discussion,
                            $item->userid,
                            $item->answerid,
                            $item->id,
                            $status,
                            'task\upload_and_check_advacheck',
                            $m
                        );
                    }
                }
            } else {
                // The document content was not found, we do not process it, we set the status to PLAGIARISM_ADVACHECK_LESSNWORDS.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                echo "        " . $this->s->get_string('upload_and_check_emptydoc', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;
                $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_LESSNWORDS, ["id" => $item->id]);
            }
            unset($content);
            // End of document processing.
            plagiarism_advacheck_queue_log(
                $item->docidantplgt,
                $item->reportedit,
                13,
                $item->courseid,
                $item->cmid,
                $item->assignment,
                $item->discussion,
                $item->userid,
                $item->answerid,
                $item->id,
                $item->status,
                'task\upload_and_check_advacheck'
            );
            echo PHP_EOL;
        }
        echo PHP_EOL;
        return true;
    }

    /**
     * Returns a string with the name of the task.
     * @return string
     */
    public function get_name()
    {
        global $CFG;
        return $this->s->get_string('upload_and_check_advacheck', 'plagiarism_advacheck', null, $CFG->lang);
    }

    /**
     * Function Remove from index.
     * @global \moodle_database $DB
     * @param array $checkedDocs An array of documents from the queue.
     * @return void
     */
    private function remove_from_index($checkedDocs)
    {
        global $DB, $CFG;
        foreach ($checkedDocs as $doc) {

            $docid = unserialize($doc->docidantplgt);
            // If there is no ID in the anti-plagiarism, then this document was not uploaded. There is no need to remove it from the index.
            if (empty($docid->Id)) {
                $a = new \stdClass();
                $a->id = $docid->Id;
                $a->docidantplgt = $doc->docidantplgt;
                $a->time = date('d:m:Y H:i:s');
                echo "            " . $this->s->get_string('upload_and_check_emptydocid', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;
                continue;
            }
            $m = $this->api->set_index_status($docid, false);
            if ($m !== true) {
                // Handling remove from index errors.
                $a = new \stdClass();
                $a->id = $doc->id;
                $a->error = $m;
                $a->time = date('d:m:Y H:i:s');
                echo "        " . $this->s->get_string('upload_and_check_errorfromindex', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $status = PLAGIARISM_ADVACHECK_ERROR_INDEX;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $doc->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
                plagiarism_advacheck_queue_log(
                    $doc->docidantplgt,
                    $doc->reportedit,
                    14,
                    $doc->courseid,
                    $doc->cmid,
                    $doc->assignment,
                    $doc->discussion,
                    $doc->userid,
                    $doc->answerid,
                    $doc->id,
                    $status,
                    'task\upload_and_check_advacheck',
                    $m
                );
                continue;
            }
            // Let's write down what we started to remove from the index.
            plagiarism_advacheck_queue_log(
                $doc->docidantplgt,
                $doc->reportedit,
                6,
                $doc->courseid,
                $doc->cmid,
                $doc->assignment,
                $doc->discussion,
                $doc->userid,
                $doc->answerid,
                $doc->id,
                $doc->status,
                'task\upload_and_check_advacheck'
            );
            do {
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                echo "                " . $this->s->get_string('upload_and_check_cyclefromindex', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $di = $this->api->get_document_info($docid);
                if (is_string($di)) {
                    // Handling remove from index errors.
                    $a = new \stdClass();
                    $a->id = $doc->id;
                    $a->error = $di;
                    $a->time = date('d:m:Y H:i:s');
                    echo "                " . $this->s->get_string('upload_and_check_errorfromindex', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                    $status = PLAGIARISM_ADVACHECK_ERROR_INDEX;
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $di, ['id' => $doc->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
                    plagiarism_advacheck_queue_log(
                        $doc->docidantplgt,
                        $doc->reportedit,
                        14,
                        $doc->courseid,
                        $doc->cmid,
                        $doc->assignment,
                        $doc->discussion,
                        $doc->userid,
                        $doc->answerid,
                        $doc->id,
                        $status,
                        'task\upload_and_check_advacheck',
                        $di
                    );
                    return;
                }
                if (time_nanosleep(0, 400000000) === true) {
                    // Delay of 0.4. This is the minimum possible delay for polling the status of the document index.
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    echo "                " . $this->s->get_string('upload_and_check_fromindextimeout', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                }
            } while (isset($di->AddedToIndex));
            $a = new \stdClass();
            $a->id = $doc->id;
            $a->time = date('d:m:Y H:i:s');
            echo "                " . $this->s->get_string('upload_and_check_fromindexsuccess', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
            $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_CHECKED, ["id" => $doc->id]);
            // Let's record the end of deletion from the index.
            plagiarism_advacheck_queue_log(
                $doc->docidantplgt,
                $doc->reportedit,
                7,
                $doc->courseid,
                $doc->cmid,
                $doc->assignment,
                $doc->discussion,
                $doc->userid,
                $doc->answerid,
                $doc->id,
                PLAGIARISM_ADVACHECK_CHECKED,
                'task\upload_and_check_advacheck'
            );
        }
    }

}
