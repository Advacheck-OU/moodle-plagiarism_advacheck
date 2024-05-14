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
 * Internal functions of the plugin.
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @copyright based on work by 1999 Martin Dougiamas {@link http://moodle.com}
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Checks files by clicking on a button from the course module page.
 *
 * @global moodle_database $DB
 * @param int $type Document type file/texts in an assignment/forum/workshop/essay from a quiz.
 * @param string $typeid File ID or hash of the response text.
 * @param int $courseid
 * @return \stdClass
 */
function plagiarism_advacheck_start_file_verify($typeid, $courseid)
{
    global $DB;
    $context = context_course::instance($courseid, MUST_EXIST);
    $plugin_cfg = get_config('plagiarism_advacheck');
    // Get a record from the database that was added after sending the response.
    $data = $DB->get_record('plagiarism_advacheck_docs', ['doctype' => PLAGIARISM_ADVACHECK_FILE, 'typeid' => $typeid]);

    if (!$data) {
        return plagiarism_advacheck_get_error_structure(get_string('error_index', 'plagiarism_advacheck', get_string('not_in_queue', 'plagiarism_advacheck')));
    }


    plagiarism_advacheck_queue_log(
        $data->docidantplgt,
        '',
        10,
        $data->courseid,
        $data->cmid,
        $data->assignment,
        $data->discussion,
        $data->userid,
        $data->answerid,
        $data->id,
        $data->status
    );

    // If there is a discussion ID or a task ID, then we will indicate it to search for documents.
    $where = '';
    $mod_ins_id = null;
    if ($data->assignment != 0) {
        $mod_ins_id = $data->assignment;
        $where = ' AND assignment = ?';
    } else if ($data->discussion != 0) {
        $mod_ins_id = $data->discussion;
        $where = ' AND discussion = ?';
    }

    $sql = "SELECT *
          FROM {plagiarism_advacheck_docs}
          WHERE
          doctype = ?
          AND userid = ?
          AND status = ?
          AND answerid != ?
          $where ";

    $params = [PLAGIARISM_ADVACHECK_FILE, $data->userid, PLAGIARISM_ADVACHECK_ININDEX, $data->answerid];

    if (isset($mod_ins_id)) {
        $params[] = $mod_ins_id;
    }
    $fs = get_file_storage();
    $file = $fs->get_file_by_id($typeid);

    $content = $file->get_content();
    $filename = $file->get_filename();
    // Let's determine which course module the file is from.
    $component = $file->get_component();
    $itemid = $file->get_itemid();
    // We will receive the response documents from the previous attempt.
    $p_sql = $sql . ' AND cmid = ?';
    $attempt = null;
    if ($component == 'question') {
        $p_sql .= ' AND attemptnumber != ?';
        $attempt = $data->attemptnumber;
    }
    $prevfiles = $DB->get_records_sql($p_sql, array_merge($params, [$data->cmid, $attempt]));

    foreach ($prevfiles as $f) {
        plagiarism_advacheck_remove_from_index($f);
    }

    // We form the document attributes.
    $data_attr['coursefullname'] = $DB->get_record('course', ['id' => $courseid], 'fullname')->fullname;

    $sql_params = [];
    $sql_params[] = $itemid;
    if ($component == 'assignsubmission_file') {
        $cm_name_sql = "SELECT ass.name AS cm_name, sub.id
                  FROM 		{assign_submission} sub
                  INNER JOIN {assign} ass ON sub.assignment = ass.id
                  WHERE sub.id = ?";
    } else if ($component == 'mod_forum') {
        $cm_name_sql = "SELECT f.name AS cm_name , fd.name AS d_name
                  FROM 		{forum_posts} fp
                  INNER JOIN {forum_discussions} fd ON fd.id = fp.discussion
                  INNER JOIN {forum} f ON f.id = fd.forum
                  WHERE fp.id = ?";
    } else if ($component == 'mod_workshop') {
        $cm_name_sql = "SELECT w.name AS cm_name
                    FROM {workshop} w 
                    JOIN {workshop_submissions} ws ON ws.workshopid = w.id 
                    WHERE ws.id = ?";
    } else if ($component == 'question') {
        $cm_name_sql = "SELECT CONCAT(quiz.name, ': ', q.name) AS cm_name, '-' AS d_name, c.fullname AS course_name
                    FROM {question_attempt_steps} qas 
                    JOIN {question_attempts} qa ON qas.questionattemptid = qa.id                    
                    JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                    JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                    JOIN {quiz} quiz ON quiz.id = quiza.quiz
                    JOIN {course} c ON c.id = quiz.course
                    WHERE qas.id = ? AND qas.userid = ?";
        $sql_params[] = $data->userid;
    }
    // Let's get the name of the course module.
    $cm_name = $DB->get_record_sql($cm_name_sql, $sql_params);
    $data_attr['cm_name'] = $cm_name->cm_name;
    // If the file is from a forum, then write down the name of the topic.
    if ($data->discussion != 0) {
        $data_attr['d_name'] = $cm_name->d_name;
    }
    $data_attr['userid'] = $file->get_userid();

    return plagiarism_advacheck_start_doc_verify($filename, $content, false, $data, $context, $plugin_cfg, (object) $data_attr);
}

/**
 * Removes a document from the AP index for manual mode.
 *
 * @global moodle_database $DB
 * @param object $doc
 */
function plagiarism_advacheck_remove_from_index($doc)
{
    global $DB;
    $api = new plagiarism_advacheck\advacheck_api();
    if (empty($doc->docidantplgt)) {
        return;
    }
    $docid = unserialize($doc->docidantplgt);
    $m = $api->set_index_status($docid, false);
    if ($m !== true) {
        // Processing error when trying to get the status of deleting a document from the index.
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
            'task\control_check_status_advacheck',
            $m
        );
    } else {
        // A record indicating the start of deletion from the index.
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
            $doc->status
        );
        // cycle while waiting for deletion from index.
        do {
            $di = $api->get_document_info($docid);
            if (is_string($di)) {
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
                    'task\control_check_status_advacheck',
                    $di
                );
            }
            // Minimum possible AP server polling frequency.
            time_nanosleep(0, 400000000);
        } while (isset($di->AddedToIndex));
        // Set the status to deleted from the index.
        $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_CHECKED, ["id" => $doc->id]);
        // A record indicating the completion of deletion from the index.
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
            PLAGIARISM_ADVACHECK_CHECKED
        );
    }
}

/**
 * Checks the text by clicking on a button from the forum page or evaluating answers to a task.
 *
 * @global moodle_database $DB
 * @param string $hash Hash of the response text.
 * @param string $content Answer text.
 * @param int $courseid
 * @param int $userid
 * @param int $assignment assignment ID.
 * @param int $discussion discussion ID.
 * @param int $doctype Document type file/texts in an assignment/forum/workshop/essay from a quiz.
 * @return \stdClass
 */
function plagiarism_advacheck_start_text_verify($hash, $content, $courseid, $userid, $assignment, $discussion, $doctype)
{
    global $DB;
    $context = context_course::instance($courseid, MUST_EXIST);
    $data_attr['coursefullname'] = $DB->get_record('course', ['id' => $courseid], 'fullname')->fullname;
    $data_attr['userid'] = $userid;
    $plugin_cfg = get_config('plagiarism_advacheck');
    $filename = '';
    $is_long_str = count_words($content) < (int) $plugin_cfg->min_len_str;

    $params = ['userid' => $userid];
    if ($discussion != 0) {
        $params["discussion"] = $discussion;
    }
    if ($assignment != 0) {
        $params["assignment"] = $assignment;
    }

    $params['doctype'] = $doctype;
    $params['typeid'] = $hash;

    // Get a record from the database that was added after sending the response.
    $data = $DB->get_record('plagiarism_advacheck_docs', $params);
    if (!$data) {
        return plagiarism_advacheck_get_error_structure(get_string('not_in_queue', 'plagiarism_advacheck') . var_export($params, true));
    }

    if (!$is_long_str) {
        // Select documents from previous responses to remove them from the index.
        $sql = "SELECT *
          FROM {plagiarism_advacheck_docs}
          WHERE
          doctype = ?
          AND userid = ?
          AND status = ?
          AND answerid !=  ?";
        if ($doctype == PLAGIARISM_ADVACHECK_ASSIGN) {
            if ($data) {
                // A record of the start of document processing.
                plagiarism_advacheck_queue_log(
                    $data->docidantplgt,
                    '',
                    10,
                    $data->courseid,
                    $data->cmid,
                    $data->assignment,
                    $data->discussion,
                    $data->userid,
                    $data->answerid,
                    $data->id,
                    $data->status
                );
                // If there is data from previous responses, we will remove it from the index.
                $sql_p = $sql . ' AND assignment = ?';
                $checkedSubs = $DB->get_records_sql($sql_p, [PLAGIARISM_ADVACHECK_ASSIGN, $data->userid, PLAGIARISM_ADVACHECK_ININDEX, $data->answerid, $assignment]);

                foreach ($checkedSubs as $sub) {
                    plagiarism_advacheck_remove_from_index($sub);
                }
            }
            $data_attr['cm_name'] = $DB->get_record('assign', ['id' => $assignment], 'name')->name;
        }

        if ($doctype == PLAGIARISM_ADVACHECK_FORUM) {
            if ($data) {
                // A record of the start of document processing.
                plagiarism_advacheck_queue_log(
                    $data->docidantplgt,
                    '',
                    10,
                    $data->courseid,
                    $data->cmid,
                    $data->assignment,
                    $data->discussion,
                    $data->userid,
                    $data->answerid,
                    $data->id,
                    $data->status
                );
                // If there is data from previous responses, we will remove it from the index.
                $sql_p = $sql . ' AND discussion = ?';
                $checkedPosts = $DB->get_records_sql($sql_p, [PLAGIARISM_ADVACHECK_FORUM, $data->userid, PLAGIARISM_ADVACHECK_ININDEX, $data->answerid, $discussion]);

                foreach ($checkedPosts as $post) {
                    plagiarism_advacheck_remove_from_index($post);
                }
            }
            $sql_info = "SELECT fd.name AS d_name, f.name AS f_name
                    FROM {forum_discussions} fd
                    INNER JOIN {forum} f ON fd.forum = f.id
                    WHERE fd.id = ?";
            $res = $DB->get_record_sql($sql_info, [$discussion]);
            if ($res) {
                $data_attr['cm_name'] = $res->f_name;
                $data_attr['d_name'] = $res->d_name;
            } else {
                $sql_info = "SELECT f.name AS f_name
                    FROM {forum} f
                    JOIN {course_modules} cm ON cm.instance = f.id
                    WHERE cm.id = ?";
                $res = $DB->get_record_sql($sql_info, [$data->cmid]);
                $data_attr['cm_name'] = $res->f_name;
            }
        }

        if ($doctype == PLAGIARISM_ADVACHECK_WORKSHOP) {
            if ($data) {
                // A record of the start of document processing.
                plagiarism_advacheck_queue_log(
                    $data->docidantplgt,
                    '',
                    10,
                    $data->courseid,
                    $data->cmid,
                    0,
                    0,
                    $data->userid,
                    $data->answerid,
                    $data->id,
                    $data->status
                );

                $checkedSubs = $DB->get_records_sql($sql, [PLAGIARISM_ADVACHECK_WORKSHOP, $data->userid, PLAGIARISM_ADVACHECK_ININDEX, $data->answerid]);

                foreach ($checkedSubs as $sub) {
                    plagiarism_advacheck_remove_from_index($sub);
                }
            }
            $sql_wname = "SELECT w.name FROM {workshop} w JOIN {workshop_submissions} ws ON ws.workshopid = w.id WHERE ws.id = ?";
            $data_attr['cm_name'] = $DB->get_record_sql($sql_wname, [$data->answerid])->name;
        }
        if ($doctype == PLAGIARISM_ADVACHECK_QUIZ) {
            if ($data) {
                // A record of the start of document processing.
                plagiarism_advacheck_queue_log(
                    $data->docidantplgt,
                    '',
                    10,
                    $data->courseid,
                    $data->cmid,
                    0,
                    0,
                    $data->userid,
                    $data->answerid,
                    $data->id,
                    $data->status
                );

                $sql_p = $sql . ' AND attemptnumber != ?';

                $checkedSubs = $DB->get_records_sql($sql_p, [PLAGIARISM_ADVACHECK_QUIZ, $data->userid, PLAGIARISM_ADVACHECK_ININDEX, $data->answerid, $data->attemptnumber]);

                foreach ($checkedSubs as $sub) {
                    plagiarism_advacheck_remove_from_index($sub);
                }

                $sql_qname = "SELECT CONCAT(quiz.name, ': ', q.name) AS cm_name, '-' AS d_name, c.fullname AS course_name
                    FROM {question_attempt_steps} qas
                    JOIN {question_attempts} qa ON qas.questionattemptid = qa.id
                    JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                    JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                    JOIN {quiz} quiz ON quiz.id = quiza.quiz
                    JOIN {course} c ON c.id = quiz.course
                    WHERE qas.id = ? AND qas.userid = ? ";

                $data_attr['cm_name'] = $DB->get_record_sql($sql_qname, [$data->answerid, $data->userid])->cm_name;
            }
        }

        $filename .= plagiarism_advacheck_assemble_filename($content);
    }
    return plagiarism_advacheck_start_doc_verify($filename, $content, $is_long_str, $data, $context, $plugin_cfg, (object) $data_attr);
}

/**
 * Prepares an array with document attributes.
 *
 * @global moodle_database $DB
 * @param object $info_data Attribute data.
 * @param int $cmid
 * @param int $courseid
 * @param int $docid Document ID in the database.
 * @return object
 */
function plagiarism_advacheck_prepare_doc_attr($info_data, $cmid, $courseid, $docid)
{
    global $DB, $CFG;
    $plugin_cfg = get_config('plagiarism_advacheck');
    $works_types = $DB->get_field('plagiarism_advacheck_course', 'works_types', ['cmid' => $cmid, 'courseid' => $courseid]);
    $DB->set_field('plagiarism_advacheck_docs', 'type', $works_types, ['id' => $docid]);
    $a = new stdClass();
    $a->works_types = ["Type" => $works_types];
    // Let's get the name of the site.
    $site = $DB->get_record('course', ['id' => 1], 'fullname,  summary');
    $site_name = $site->fullname;
    $site_description = strip_tags($site->summary);

    $attr = [];
    if (!empty($plugin_cfg->add_attr_system)) {
        $attr[] = ["AttrName" => get_string('add_attr_system', 'plagiarism_advacheck'), "AttrValue" => $site_name];
    }
    if (!empty($plugin_cfg->add_attr_descr)) {
        $attr[] = ["AttrName" => get_string('add_attr_descr', 'plagiarism_advacheck'), "AttrValue" => $site_description];
    }
    if (!empty($plugin_cfg->add_attr_site)) {
        $attr[] = ["AttrName" => get_string('add_attr_site', 'plagiarism_advacheck'), "AttrValue" => $CFG->wwwroot];
    }
    if (!empty($plugin_cfg->add_attr_course)) {
        $attr[] = ["AttrName" => get_string('add_attr_course', 'plagiarism_advacheck'), "AttrValue" => $info_data->coursefullname];
    }
    if (!empty($plugin_cfg->add_attr_item)) {
        $attr[] = ["AttrName" => get_string('add_attr_item', 'plagiarism_advacheck'), "AttrValue" => $info_data->cm_name];
    }
    if (isset($info_data->d_name) && !empty($plugin_cfg->add_attr_discusname)) {
        $attr[] = ["AttrName" => get_string('add_attr_discusname', 'plagiarism_advacheck'), "AttrValue" => $info_data->d_name];
    }
    if (!empty($plugin_cfg->add_attr_idauthor)) {
        $attr[] = ["AttrName" => get_string('add_attr_idauthor', 'plagiarism_advacheck'), "AttrValue" => $info_data->userid];
    }

    $a->custom_attrs = ["Custom" => $attr];

    return $a;
}

/**
 * Sends a document for verification and processes the result of checking texts and files.
 *
 * @global moodle_database $DB
 * @param string $filename
 * @param string $content Contents of the response/file.
 * @param bool $is_long_str Is the number of words sufficient?
 * @param stdClass $data An entry from the database that was added after the response was sent.
 * @param context $context
 * @param stdClass $plugin_cfg
 * @param stdClass $data_attr Document attributes.
 * @return stdClass
 */
function plagiarism_advacheck_start_doc_verify($filename, $content, $is_long_str, $data, $context, $plugin_cfg, $data_attr)
{
    global $DB, $USER;
    $result = new stdClass();
    $ap_docid = null;
    $originality = 0;
    $api = new plagiarism_advacheck\advacheck_api();

    // Structure for displaying test results on the course module page.
    $api_data = new stdClass();
    $api_data->error = '';
    $api_data->plagiarism = 0;
    $api_data->legal = 0;
    $api_data->issuspicious = 0;
    $api_data->selfcite = 0;
    $api_data->reportedit = '';
    $api_data->reportread = '';
    $api_data->shortreport = '';

    if (!$is_long_str) {
        $attributes = plagiarism_advacheck_prepare_doc_attr((object) $data_attr, $data->cmid, $data->courseid, $data->id);
        if (!has_capability('plagiarism/advacheck:checkadvacheck', $context)) {
            // Increment check counter for student and record.
            $stud_check = (int) $data->stud_check + 1;
            $DB->set_field(
                'plagiarism_advacheck_docs',
                'stud_check',
                $stud_check,
                ['cmid' => $data->cmid, 'userid' => $data->userid, 'doctype' => $data->doctype]
            );
        }
        // If it has already been downloaded, but the scan results have not been uploaded.
        if ((int) $data->status == PLAGIARISM_ADVACHECK_UPLOADED || (int) $data->status == PLAGIARISM_ADVACHECK_CHECKING) {
            $ap_docid = unserialize($data->docidantplgt);
            $api_data->docidantplgt = $data->docidantplgt;
        } else if ((int) $data->status == PLAGIARISM_ADVACHECK_WAITUPLOAD || (int) $data->status == PLAGIARISM_ADVACHECK_WAITBLOCK) {
            // Requires unloading and running the verify.
            // Start uploading.
            plagiarism_advacheck_upload_man($ap_docid, $result, $api_data, $data, $api, $filename, $content, $attributes);
            // Output information in case of errors.
            if (empty($ap_docid)) {
                return $result;
            }
            // Start checking.
            $m = plagiarism_advacheck_start_check_man($api_data, $ap_docid, $data, $api, $result);
            if (!$m) {
                return $result;
            }
        }

        if (isset($ap_docid)) {
            // Let's wait 5 seconds and ask for the verification status; if the answer is not large, then it could have been verified.
            sleep(5);
            // Query verification status. 
            $st_curr = $api->get_current_check_status($ap_docid);

            if (isset($st_curr->error)) {
                // Error handling when trying to request a check status.
                $status = PLAGIARISM_ADVACHECK_ERROR_GET_STATUS;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $st_curr->error, ['id' => $data->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
                plagiarism_advacheck_queue_log(
                    $data->docidantplgt,
                    $data->reportedit,
                    14,
                    $data->courseid,
                    $data->cmid,
                    $data->assignment,
                    $data->discussion,
                    $data->userid,
                    $data->answerid,
                    $data->id,
                    $status,
                    false,
                    $st_curr->error
                );
                return plagiarism_advacheck_get_error_structure($st_curr->error);
            } else {
                if ($st_curr->status === 'Failed') {
                    // Handling errors during document verification.
                    $status = PLAGIARISM_ADVACHECK_ERROR_CHECK;
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $st_curr->msg, ['id' => $data->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
                    plagiarism_advacheck_queue_log(
                        $data->docidantplgt,
                        $data->reportedit,
                        14,
                        $data->courseid,
                        $data->cmid,
                        $data->assignment,
                        $data->discussion,
                        $data->userid,
                        $data->answerid,
                        $data->id,
                        $status,
                        false,
                        $st_curr->msg
                    );
                    return plagiarism_advacheck_get_error_structure($st_curr->msg);
                } else if ($st_curr->status === 'InProgress' || $st_curr->status === 'None') {
                    // If the document was downloaded, but the check failed to run, we will run it again.
                    if ($st_curr->status === 'None') {
                        $m = plagiarism_advacheck_start_check_man($api_data, $ap_docid, $data, $api, $result);
                        if (!$m) {
                            return $result;
                        }
                    }
                }
                // If the check completion status is "ready".
                if ($st_curr->status === 'Ready') {
                    // Rounding values.
                    plagiarism_advacheck_calc_orig($st_curr->plagiarism, $st_curr->legal, $st_curr->selfcite, $originality);
                    $api_data->plagiarism = $st_curr->plagiarism;
                    $api_data->legal = $st_curr->legal;
                    $api_data->issuspicious = $st_curr->issuspicious;
                    $api_data->selfcite = $st_curr->selfcite;
                    $api_data->reportedit = $st_curr->reportedit;
                    $api_data->reportread = $st_curr->reportread;
                    $api_data->shortreport = $st_curr->shortreport;
                    $api_data->timecheck_end = $st_curr->timecheck_end;
                    $api_data->status = PLAGIARISM_ADVACHECK_CHECKED;
                    $api_data->id = $data->id;
                    // Let's write down the results.
                    $DB->update_record('plagiarism_advacheck_docs', $api_data);
                    plagiarism_advacheck_queue_log(
                        $api_data->docidantplgt,
                        $api_data->reportedit,
                        5,
                        $data->courseid,
                        $data->cmid,
                        $data->assignment,
                        $data->discussion,
                        $data->userid,
                        $data->answerid,
                        $data->id,
                        PLAGIARISM_ADVACHECK_CHECKED
                    );

                    // Getting course module settings: add to index or not?
                    $i = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $data->courseid, 'cmid' => $data->cmid], 'add_to_index');
                    if ($i->add_to_index != 0) {
                        $m = $api->set_index_status($ap_docid, true);
                        if ($m !== true) {
                            $status = PLAGIARISM_ADVACHECK_ERROR_INDEX;
                            $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $data->id]);
                            $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
                            plagiarism_advacheck_queue_log(
                                $data->docidantplgt,
                                $data->reportedit,
                                14,
                                $data->courseid,
                                $data->cmid,
                                $data->assignment,
                                $data->discussion,
                                $data->userid,
                                $data->answerid,
                                $data->id,
                                $status,
                                false,
                                $m
                            );
                            return plagiarism_advacheck_get_error_structure($m);
                        }
                        $api_data->status = PLAGIARISM_ADVACHECK_ININDEX;
                        $DB->set_field('plagiarism_advacheck_docs', 'status', PLAGIARISM_ADVACHECK_ININDEX, ['id' => $data->id]);
                        // A record of adding a document to the index.
                        plagiarism_advacheck_queue_log(
                            $api_data->docidantplgt,
                            $api_data->reportedit,
                            8,
                            $data->courseid,
                            $data->cmid,
                            $data->assignment,
                            $data->discussion,
                            $data->userid,
                            $data->answerid,
                            $data->id,
                            PLAGIARISM_ADVACHECK_ININDEX
                        );
                    }
                    // A record of the completion of document processing.
                    plagiarism_advacheck_queue_log(
                        $api_data->docidantplgt,
                        $api_data->reportedit,
                        13,
                        $data->courseid,
                        $data->cmid,
                        $data->assignment,
                        $data->discussion,
                        $data->userid,
                        $data->answerid,
                        $data->id,
                        $api_data->status
                    );
                } elseif (!empty($st_curr->msg)) {
                    // Any status other than the above is an error in the verification process.
                    $status = PLAGIARISM_ADVACHECK_ERROR_CHECK;
                    $m = "Проверка завершилась со статусом $st_curr->status, ошибка: $st_curr->msg";
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $st_curr->msg, ['id' => $data->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
                    plagiarism_advacheck_queue_log(
                        $data->docidantplgt,
                        $data->reportedit,
                        14,
                        $data->courseid,
                        $data->cmid,
                        $data->assignment,
                        $data->discussion,
                        $data->userid,
                        $data->answerid,
                        $data->id,
                        $status,
                        false,
                        $m
                    );
                    return plagiarism_advacheck_get_error_structure($m);
                }
            }
        } else {
            // Displays the current state of the document.
            switch ((int) $data->status) {
                case PLAGIARISM_ADVACHECK_UPLOADING:
                    return plagiarism_advacheck_get_error_structure(get_string('uploading', 'plagiarism_advacheck'), 'advacheck-green');

                case PLAGIARISM_ADVACHECK_INVALIDFILETYPE:
                    return plagiarism_advacheck_get_error_structure(get_string('error_filetype', 'plagiarism_advacheck', $data->error), 'advacheck-gray');

                case PLAGIARISM_ADVACHECK_ERROR_UPLOADING:
                    return plagiarism_advacheck_get_error_structure(get_string('error_upload', 'plagiarism_advacheck', $data->error));
                // Error when initiating the checking.
                case PLAGIARISM_ADVACHECK_ERROR_CHECKING:
                    return plagiarism_advacheck_get_error_structure(get_string('error_checking', 'plagiarism_advacheck', $data->error));
                // An error occurred during the check process.
                case PLAGIARISM_ADVACHECK_ERROR_CHECK:
                    return plagiarism_advacheck_get_error_structure(get_string('error_check', 'plagiarism_advacheck', $data->error));

                case PLAGIARISM_ADVACHECK_ERROR_GET_STATUS:
                    return plagiarism_advacheck_get_error_structure(get_string('error_get_status', 'plagiarism_advacheck', $data->error));

                case PLAGIARISM_ADVACHECK_ERROR_GET_REPORT:
                    return plagiarism_advacheck_get_error_structure(get_string('error_get_report', 'plagiarism_advacheck', $data->error));

                case PLAGIARISM_ADVACHECK_ERROR_INDEX:
                    return plagiarism_advacheck_get_error_structure(get_string('error_index', 'plagiarism_advacheck', $data->error));
                // In other cases, we display the results of the check.
                default:
                    plagiarism_advacheck_calc_orig($data->plagiarism, $data->legal, $data->selfcite, $originality);
                    $api_data->plagiarism = $data->plagiarism;
                    $api_data->legal = $data->legal;
                    $api_data->issuspicious = (bool) $data->issuspicious;
                    $api_data->selfcite = $data->selfcite;
                    $api_data->reportedit = $data->reportedit;
                    $api_data->reportread = $data->reportread;
                    $api_data->shortreport = $data->shortreport;
                    $api_data->docidantplgt = $data->docidantplgt;
                    break;
            }
        }
    } else {
        $DB->set_field('plagiarism_advacheck_docs', 'status', PLAGIARISM_ADVACHECK_LESSNWORDS, ['id' => $data->id]);
    }

    if ($is_long_str) {
        $result->class = "advacheck-gray";
        $result->originality = get_string('min_len_str_info', 'plagiarism_advacheck', (int) $plugin_cfg->min_len_str);
        $result->report = '#';
    } else {
        $result->plagiarism = $api_data->plagiarism . '%';
        $result->legal = $api_data->legal . '%';
        $result->selfcite = $api_data->selfcite . '%';
        $result->originality = $originality . "%";
        $result->docid = $api_data->docidantplgt;
        $result->issuspicious = $api_data->issuspicious;

        // Taking the module settings on the course.
        $cm_sett = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $data->cmid, 'courseid' => $data->courseid]);
        // Summary report display mode is enabled.
        if ((int) $cm_sett->disp_notices == 1) {
            $result->report = $plugin_cfg->uri . $api_data->shortreport;
        }
        // If you have the right to view the full report or the mode for displaying the full report is turned on, or the results are viewed by the student checking at the workshop.
        if (
            has_capability('plagiarism/advacheck:viewfullreadreport', $context) ||
            ((int) $cm_sett->disp_notices == 2 && !has_capability('plagiarism/advacheck:checkadvacheck', $context)) ||
            ($data->discussion == 0 && $data->assignment == 0 && $USER->id != $data->userid)
        ) {
            $result->report = $plugin_cfg->uri . $api_data->reportread;
        } // If you have permission to view the full report with editing.
        if (has_capability('plagiarism/advacheck:viewfullreport', $context)) {
            $result->report = $plugin_cfg->uri . $api_data->reportedit;
        }
        // If you don’t have the rights to check, then we’ll add a block with the number of checks in the course module.
        if (!has_capability('plagiarism/advacheck:checkadvacheck', $context)) {
            // We received a limit for draft checks in a assignment.
            $check_stud_lim = (int) $cm_sett->check_stud_lim;

            $c = $check_stud_lim - $stud_check;

            if ($c == 0) {
                // If there are no checks left, then we do not output anything.
                $result->check_studs = 'none';
            } else {
                $result->check_studs = get_string('stud_check1', 'plagiarism_advacheck', $c);
            }
        }
        if ($originality >= $plugin_cfg->originality_limit) {
            $result->class = "advacheck-green";
        } else {
            $result->class = "advacheck-red";
        }
    }
    $result->status = $DB->get_field('plagiarism_advacheck_docs', 'status', ['id' => $data->id]);
    return $result;
}

/**
 * Returns a structure to display the error-block or info-block.
 *
 * @param string $msg
 * @return \stdClass
 */
function plagiarism_advacheck_get_error_structure($msg, $class = "advacheck-red")
{
    $result = new stdClass();
    $result->class = $class;
    $result->error = $msg;
    return $result;
}

/**
 * Uploads files, writes to the log, records statuses, and document ID from the AP.
 * Error handling PLAGIARISM_ADVACHECK_ERROR_UPLOADING/
 *
 * @global moodle_database $DB
 * @param stdClass $ap_docid For the structure of the document id in the Anti-Plagiarism system.
 * @param stdClass $result To report an error.
 * @param stdClass $api_data Structure for document information from AP.
 * @param stdClass $data Structure with current information about the document.
 * @param plagiarism_advacheck\advacheck_api $api
 * @param string $filename Name of the document to upload.
 * @param string $content Contents of the document.
 * @param stdClass $data_attr Document attributes.
 */
function plagiarism_advacheck_upload_man(&$ap_docid, &$result, &$api_data, $data, $api, $filename, $content, $data_attr)
{
    global $DB, $USER;
    // Let's set the status to "PLAGIARISM_ADVACHECK_UPLOADING" so that the cron task does not upload this document again when the upload starts.
    $status = PLAGIARISM_ADVACHECK_UPLOADING;
    $DB->set_field('plagiarism_advacheck_docs', 'timeupload_start', time(), ['id' => $data->id]);
    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
    plagiarism_advacheck_queue_log(
        '',
        '',
        2,
        $data->courseid,
        $data->cmid,
        $data->assignment,
        $data->discussion,
        $data->userid,
        $data->answerid,
        $data->id,
        $status
    );

    if (mb_strlen($content) == 0) {
        $DB->set_field("plagiarism_advacheck_docs", "status", PLAGIARISM_ADVACHECK_LESSNWORDS, ["id" => $data->id]);
        $s = '';
        $result = plagiarism_advacheck_get_html_block_info(
            get_string('min_len_str_info', 'plagiarism_advacheck', get_config('plagiarism_advacheck', 'min_len_str')),
            'advacheck-gray'
        );
        $ap_docid = null;
        return;
    }
    // Let's write down the ID of the person who started the check, this is needed to download the pdf certificate.
    $DB->set_field('plagiarism_advacheck_docs', 'teacherid', $USER->id, ['id' => $data->id]);
    $ap_docid = $api->upload_doc($filename, $content, $data->courseid, false, $conn_error, $data_attr->works_types);
    // Error handling during unloading.
    if (is_string($ap_docid)) {
        $s = $ap_docid;
        if ($conn_error) {
            $status = PLAGIARISM_ADVACHECK_ERROR_UPLOADING;
            $result = plagiarism_advacheck_get_error_structure(get_string('error_upload', 'plagiarism_advacheck', $s));
        } else {
            $status = PLAGIARISM_ADVACHECK_INVALIDFILETYPE;
            $result = plagiarism_advacheck_get_error_structure(get_string('error_filetype', 'plagiarism_advacheck', $s));
        }
        $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $data->id]);
        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
        plagiarism_advacheck_queue_log(
            '',
            '',
            14,
            $data->courseid,
            $data->cmid,
            $data->assignment,
            $data->discussion,
            $data->userid,
            $data->answerid,
            $data->id,
            $status,
            false,
            $s
        );
        $ap_docid = null;
        return;
    } else {
        // Let's check whether Anti-Plagiarism considered the document erroneous.
        if ($ap_docid->Reason !== 'NoError') {
            $status = PLAGIARISM_ADVACHECK_INVALIDFILETYPE;
            $s = $ap_docid->FailDetails;
            $result = plagiarism_advacheck_get_error_structure(get_string('error_filetype', 'plagiarism_advacheck', $s));
            $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $data->id]);
            $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
            plagiarism_advacheck_queue_log(
                '',
                '',
                14,
                $data->courseid,
                $data->cmid,
                $data->assignment,
                $data->discussion,
                $data->userid,
                $data->answerid,
                $data->id,
                $status,
                false,
                $s
            );
            $ap_docid = null;
            return;
        }
        plagiarism_advacheck_queue_log(
            '',
            '',
            11,
            $data->courseid,
            $data->cmid,
            $data->assignment,
            $data->discussion,
            $data->userid,
            $data->answerid,
            $data->id,
            $status
        );
        // Let's load the document attributes.
        $doc_attr_res = $api->upload_doc_attr($ap_docid->Id, $data_attr->custom_attrs);
        plagiarism_advacheck_queue_log(
            '',
            '',
            12,
            $data->courseid,
            $data->cmid,
            $data->assignment,
            $data->discussion,
            $data->userid,
            $data->answerid,
            $data->id,
            $status
        );
        // Error while trying to load attributes.
        if (is_string($doc_attr_res)) {
            $status = PLAGIARISM_ADVACHECK_ERROR_UPLOADING;
            $s = $doc_attr_res;
            $result = plagiarism_advacheck_get_error_structure(get_string('error_upload', 'plagiarism_advacheck', $s));
            $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $data->id]);
            $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
            plagiarism_advacheck_queue_log(
                '',
                '',
                14,
                $data->courseid,
                $data->cmid,
                $data->assignment,
                $data->discussion,
                $data->userid,
                $data->answerid,
                $data->id,
                $status,
                false,
                $s
            );
            $ap_docid = null;
            return;
        }
        // Let's rewrite it so that it matches the further logic of the code.
        $ap_docid = $ap_docid->Id;
        $api_data->docidantplgt = serialize($ap_docid);
        $rec['timeupload_end'] = time();
        $rec['status'] = PLAGIARISM_ADVACHECK_UPLOADED;
        $rec['docidantplgt'] = $api_data->docidantplgt;
        $rec['externalid'] = $ap_docid->Id;
        $rec['id'] = $data->id;
        $DB->update_record('plagiarism_advacheck_docs', (object) $rec);
        plagiarism_advacheck_queue_log(
            $api_data->docidantplgt,
            '',
            3,
            $data->courseid,
            $data->cmid,
            $data->assignment,
            $data->discussion,
            $data->userid,
            $data->answerid,
            $data->id,
            PLAGIARISM_ADVACHECK_UPLOADED
        );
    }
}

/**
 * Initiates a scan, records the status "being checked", the start time of the scan, and makes a log entry.
 * Handles an error PLAGIARISM_ADVACHECK_ERROR_CHECKING
 *
 * @global moodle_database $DB
 * @param stdClass $api_data Structure for document information from AP.
 * @param stdClass $ap_docid Structure with ID in the Antiplagiarism system.
 * @param stdClass $data Recording a document in the database.
 * @param plagiarism_advacheck\advacheck_api $api
 * @param stdClass $result To record information about errors.
 * @return boolean true - in case of success, false - in case of errors
 */
function plagiarism_advacheck_start_check_man($api_data, $ap_docid, $data, $api, &$result)
{
    global $DB;
    $m = $api->start_check($ap_docid);

    // The status is “PLAGIARISM_ADVACHECK_CHECKING”, because checks can take a long time.
    $status = PLAGIARISM_ADVACHECK_CHECKING;
    $DB->set_field('plagiarism_advacheck_docs', 'timecheck_start', time(), ['id' => $data->id]);
    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
    plagiarism_advacheck_queue_log(
        $api_data->docidantplgt,
        '',
        4,
        $data->courseid,
        $data->cmid,
        $data->assignment,
        $data->discussion,
        $data->userid,
        $data->answerid,
        $data->id,
        $status
    );
    // Handling an error in obtaining the verification status.
    if ($m !== true) {
        $status = PLAGIARISM_ADVACHECK_ERROR_CHECKING;
        $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $data->id]);
        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $data->id]);
        plagiarism_advacheck_queue_log(
            $data->docidantplgt,
            $data->reportedit,
            14,
            $data->courseid,
            $data->cmid,
            $data->assignment,
            $data->discussion,
            $data->userid,
            $data->answerid,
            $data->id,
            $status,
            false,
            $m
        );
        $result = plagiarism_advacheck_get_error_structure($m);
        return false;
    } else {
        return true;
    }
}

/**
 * Makes connections to the AP service and receives information about the tariff.
 *
 * @return stdClass Information about the user's tariff or an error message.
 */
function plagiarism_advacheck_get_advacheck_tarif_info_html($login, $password, $soap_wsdl, $url)
{
    // Structure for sending a response to a page.
    $tariff_info_html = new stdClass();
    $tariff_info = plagiarism_advacheck\advacheck_api::check_tarif($login, $password, $soap_wsdl);
    if (!empty($tariff_info->error)) {
        $a = $tariff_info->error;
        $tariff_info_html->message = '<div class="alert alert-danger alert-block fade in  alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button>';
        $tariff_info_html->message .= get_string('error_check', 'plagiarism_advacheck', $a) . "</div>";
        return $tariff_info_html;
    }

    $tariff_info_html->message = '<div class="alert alert-success alert-block fade in  alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button>';
    $tariff_info_html->message .= get_string('success_check', 'plagiarism_advacheck') . '<p>';

    $tariff_info_html->message .= \html_writer::div(
        \html_writer::span("&nbsp;&nbsp;&nbsp;&nbsp;" . get_string('tarifName', 'plagiarism_advacheck') . "&nbsp;&nbsp;&nbsp;") .
        \html_writer::span($tariff_info->tarif->Name)
    );

    $tariff_info_html->message .= \html_writer::div(
        \html_writer::span("&nbsp;&nbsp;&nbsp;&nbsp;" . get_string('SubscriptionDate', 'plagiarism_advacheck') . "&nbsp;&nbsp;&nbsp;") .
        \html_writer::span($tariff_info->tarif->SubscriptionDate)
    );

    $tariff_info_html->message .= \html_writer::div(
        \html_writer::span("&nbsp;&nbsp;&nbsp;&nbsp;" . get_string('ExpirationDate', 'plagiarism_advacheck') . "&nbsp;&nbsp;&nbsp;") .
        \html_writer::span($tariff_info->tarif->ExpirationDate)
    );

    if ($tariff_info->tarif->TotalChecksCount == '') {
        $cnt = get_string('TotalChecksCount_unlimited', 'plagiarism_advacheck');
    } else {
        $cnt = $tariff_info->tarif->TotalChecksCount;
    }

    $tariff_info_html->message .= \html_writer::div(
        \html_writer::span("&nbsp;&nbsp;&nbsp;&nbsp;" . get_string('TotalChecksCount', 'plagiarism_advacheck') . "&nbsp;&nbsp;&nbsp;") .
        \html_writer::span($cnt)
    );

    if ($tariff_info->tarif->RemainedChecksCount == '') {
        $cnt = get_string('RemainedChecksCount_unlimited', 'plagiarism_advacheck');
    } else {
        $cnt = $tariff_info->tarif->RemainedChecksCount;
    }

    $tariff_info_html->message .= \html_writer::div(
        \html_writer::span("&nbsp;&nbsp;&nbsp;&nbsp;" . get_string('RemainedChecksCount', 'plagiarism_advacheck') . "&nbsp;&nbsp;&nbsp;") .
        \html_writer::span($cnt)
    );

    $tariff_info_html->message .= \html_writer::div(
        \html_writer::span("<p><b>" . get_string('CheckServices', 'plagiarism_advacheck') . "</b></p> ")
    );
    foreach ($tariff_info->tarif->CheckServices as $checkServices) {
        foreach ($checkServices as $checkService) {
            $tariff_info_html->message .= \html_writer::div(
                \html_writer::span('&nbsp;&nbsp;&nbsp;&nbsp;' . $checkService->Code . ':&nbsp;&nbsp;&nbsp;') .
                \html_writer::span('&nbsp;&nbsp;&nbsp;&nbsp;' . $checkService->Description)
            );
        }
    }
    $a = html_writer::link($url, $url, ['target' => '_blank', 'title' => $url]);
    $str = get_string('check_site', 'plagiarism_advacheck', $a);
    $tariff_info_html->message .= html_writer::span($str) . '</p>';
    $tariff_info_html->class = 'alert alert-success alert-block fade in ';
    return $tariff_info_html;
}

/**
 * Updates the check result.
 *
 * @global moodle_database $DB
 * @param string $docid Document ID in the Anti-Plagiarism system.
 * @return \stdClass
 */
function plagiarism_advacheck_update_advacheck_report($typeid)
{
    global $DB, $USER;
    $plugin_cfg = get_config('plagiarism_advacheck');
    $sql = "SELECT * FROM {plagiarism_advacheck_docs} WHERE typeid = ?";
    $data = $DB->get_record('plagiarism_advacheck_docs', ['typeid' => $typeid]);
    $result = new stdClass();
    $result->status = $DB->get_field('plagiarism_advacheck_docs', 'status', ['id' => $data->id]);
    $docid = unserialize($data->docidantplgt);
    $api = new plagiarism_advacheck\advacheck_api();
    if ($result->status == PLAGIARISM_ADVACHECK_ININDEX || $result->status == PLAGIARISM_ADVACHECK_CHECKED) {
        $api_data = $api->get_check_report($docid);
        $api_data->status = "Ready";
    } else {
        $api_data = $api->get_current_check_status($docid);
    }

    $originality = 0;
    if (!isset($api_data->error) && $api_data->status === "Ready") {
        plagiarism_advacheck_calc_orig($api_data->plagiarism, $api_data->legal, $api_data->selfcite, $originality);
        $data->plagiarism = $api_data->plagiarism;
        $data->legal = $api_data->legal;
        $data->issuspicious = $api_data->issuspicious;
        $data->selfcite = $api_data->selfcite;
        $data->originality = $originality;
        $data->timecheck_end = $api_data->timecheck_end;
        $data->reportedit = $api_data->reportedit;
        $data->reportread = $api_data->reportread;
        $data->shortreport = $api_data->shortreport;
        $data->timecheck_end = $api_data->timecheck_end;
        $DB->update_record('plagiarism_advacheck_docs', $data);
        plagiarism_advacheck_queue_log(
            $data->docidantplgt,
            $data->reportedit,
            9,
            $data->courseid,
            $data->cmid,
            $data->assignment,
            $data->discussion,
            $data->userid,
            $data->answerid,
            $data->id,
            $data->status
        );
    } elseif (!empty($api_data->error)) {
        $result->error = $api_data->error;
        $DB->set_field("plagiarism_advacheck_docs", "error", $api_data->error, ["id" => $data->id]);
        plagiarism_advacheck_queue_log(
            $data->docidantplgt,
            $data->reportedit,
            14,
            $data->courseid,
            $data->cmid,
            $data->assignment,
            $data->discussion,
            $data->userid,
            $data->answerid,
            $data->id,
            (int) $data->status,
            false,
            get_string('updatereporterror', 'plagiarism_advacheck', $api_data->error),
        );
    }
    if (($result->status == PLAGIARISM_ADVACHECK_ININDEX || $result->status == PLAGIARISM_ADVACHECK_CHECKED)) {
        $result->plagiarism = $data->plagiarism . '% ';
        $result->selfcite = $data->selfcite . '%';
        $result->legal = $data->legal . '% ';
        $result->originality = $data->originality . '% ';
        $result->issuspicious = $api_data->issuspicious;

        $context = context_course::instance($data->courseid, MUST_EXIST);
        if (
            has_capability('plagiarism/advacheck:viewfullreadreport', $context) ||
            ($data->discussion == 0 && $data->assignment == 0 && $USER->id != $data->userid)
        ) {
            $result->report = $plugin_cfg->uri . $api_data->reportread;
        }
        if (has_capability('plagiarism/advacheck:viewfullreport', $context)) {
            $result->report = $plugin_cfg->uri . $api_data->reportedit;
        }

        if ($data->originality >= $plugin_cfg->originality_limit) {
            $result->class = "advacheck-green";
        } else {
            $result->class = "advacheck-red";
        }
    } else if (!$data) {
        $result->error = get_string('docnotchecked', 'plagiarism_advacheck');
    }
    return $result;
}

/**
 * Matches string names of course module type and numeric constants and vice versa
 *
 * @param mixed $mod
 * @return mixed
 */
function plagiarism_advacheck_get_module_type_by_name($mod, $rev = true)
{
    if ($rev) {
        switch ($mod) {
            case 'forum':
                return PLAGIARISM_ADVACHECK_FORUM;
            case 'assign':
                return PLAGIARISM_ADVACHECK_ASSIGN;
            case 'workshop':
                return PLAGIARISM_ADVACHECK_WORKSHOP;
            case 'quiz':
                return PLAGIARISM_ADVACHECK_QUIZ;
        }
    } else {
        switch ($mod) {
            case PLAGIARISM_ADVACHECK_FORUM:
                return 'forum';
            case PLAGIARISM_ADVACHECK_ASSIGN:
                return 'assign';
            case PLAGIARISM_ADVACHECK_WORKSHOP:
                return 'workshop';
            case PLAGIARISM_ADVACHECK_QUIZ:
                return 'quiz';
        }
    }
}

/**
 * Collects the filename from the first three words of the content, up to a maximum of 30 characters.
 *
 * @param string $content
 * @return string
 */
function plagiarism_advacheck_assemble_filename($content)
{
    $filename = '';
    $str_name = mb_substr($content, 0, 30);
    $arr = preg_split('/[^\w]+/u', $str_name);
    if (empty($arr[1])) {
        $arr[1] = '';
    }
    if (empty($arr[2])) {
        $arr[2] = '';
    }
    if ((mb_strlen($arr[0]) + mb_strlen($arr[1]) + mb_strlen($arr[2])) > 16) {
        if ((mb_strlen($arr[0]) + mb_strlen($arr[1])) > 16) {
            $filename = $arr[0];
        } else {
            $filename = $arr[0] . '_' . $arr[1];
        }
    } else {
        $filename = $arr[0] . '_' . $arr[1] . '_' . $arr[2];
    }
    return $filename . '.txt';
}

/**
 * Calls the icon display method depending on the moodle version.
 *
 * @global object $OUTPUT
 * @global stdClass $CFG
 * @param string $img_name Icon name.
 * @return string Html icon.
 */
function plagiarism_advacheck_get_icn_advacheck($img_name)
{
    global $OUTPUT, $CFG;
    $html = '';
    // If Moodle version is 3.3 and lower.
    if ($CFG->version < 2017051500) {
        $html = $OUTPUT->pix_url($img_name, 'plagiarism_advacheck');
    } else {

        $html = $OUTPUT->image_url($img_name, 'plagiarism_advacheck');
    }

    return $html;
}

/**
 * Rounds the % of originality in the same way as in the report on the Anti-Plagiarism page.
 *
 * @param float $plagiarism
 * @param float $legal
 * @param float $originality
 */
function plagiarism_advacheck_calc_orig(&$plagiarism, &$legal, &$selfcite, &$originality)
{

    if ($plagiarism == round($plagiarism, 0)) {
        $plagiarism = round($plagiarism, 0);
    } else {
        $plagiarism = round($plagiarism, 2);
    }

    if ($legal == round($legal, 0)) {
        $legal = round($legal, 0);
    } else {
        $legal = round($legal, 2);
    }

    if ($selfcite == round($selfcite, 0)) {
        $selfcite = round($selfcite, 0);
    } else {
        $selfcite = round($selfcite, 2);
    }

    $originality = 100 - $plagiarism - $legal - $selfcite;

    if ($originality == round($originality, 0)) {
        $originality = round($originality, 0);
    } else {
        $originality = round($originality, 2);
    }
}

/**
 * Selects the teacher ID of the given course (first by name)
 * @global moodle_database $DB
 * @global stdClass $USER
 * @param int $courseid
 * @param bool $auto The type of check in the course module is false - manual, true - automatic.
 * @return stdClass
 */
function plagiarism_advacheck_get_teacher_from_cournseid($courseid, $auto = false)
{
    global $DB, $USER;
    $t = new stdClass();
    if ($auto) {
        $sql = "SELECT u.id
            FROM {user} AS u
            LEFT JOIN {context} AS ctx ON ctx.instanceid = ?
            JOIN {role_assignments}  AS tra ON tra.contextid = ctx.id AND u.id = tra.userid
            WHERE  tra.roleid = 3
            ORDER BY u.lastname";

        $t = $DB->get_record_sql($sql, [$courseid], IGNORE_MULTIPLE);
        // If there are no teachers in the course, then write ExternalUserID=0.
        if (!$t) {
            $t->id = 0;
        }
    } else {
        $t->id = $USER->id;
    }

    return $t;
}

/**
 * We keep an event log to investigate incidents.
 * Adds a record of an event to the queue log.
 *
 * @global moodle_database $DB
 * @param string $docidantplgt ID of the document in the AP system.
 * @param string $reportedit Link to inspection report.
 * @param int $action_id ID of the action in the table with actions.
 * @param int $courseid
 * @param int $cmid
 * @param int $assignment assignment id
 * @param int $discussion discussion id
 * @param int $userid ID of the user being checked.
 * @param int $objectid Message/response ID to search for.
 * @param int $docid ID of the document in the queue.
 * @param int $status The status of the document at the time of the current action.
 * @param string|bool $auto False - manual mode, take the ID of the person who pressed the button. in Atoremode we get the name of the task according to the schedule.
 * @param string $errormessage Error text.
 * @return void
 */
function plagiarism_advacheck_queue_log(
    $docidantplgt,
    $reportedit,
    $action_id,
    $courseid,
    $cmid,
    $assignment,
    $discussion,
    $userid,
    $objectid,
    $docid,
    $status,
    $auto = false,
    $errormessage = ''
) {
    global $DB, $USER, $CFG;
    $sm = get_string_manager();
    if (!get_config('plagiarism_advacheck', 'log_actions')) {
        return;
    }
    if (!$auto) {
        $initid = $USER->id;
    } else {
        $initid = $auto;
    }
    // We enhance human-readable time with microseconds.
    $mt = microtime(true);
    $mt_int = intval($mt);
    $ms = round($mt - $mt_int, 4);
    $k = explode('.', $ms . '');
    $k[1] = isset($k[1]) ? $k[1] : 0;
    // Receiving settings for checking the current course module.
    $cm_sett = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $cmid, 'courseid' => $courseid]);

    if ($cm_sett->mode == PLAGIARISM_ADVACHECK_AUTOMODE) {
        $mode = $sm->get_string('action_log_modeauto', 'plagiarism_advacheck', null, $CFG->lang);
    } else if ($cm_sett->mode == PLAGIARISM_ADVACHECK_MANUALMODE) {
        $mode = $sm->get_string('action_log_modeman', 'plagiarism_advacheck', null, $CFG->lang);
    } else {
        $mode = $sm->get_string('action_log_modeoff', 'plagiarism_advacheck', null, $CFG->lang);
    }
    $text = '-';
    $file = '-';
    if ($cm_sett->checktext) {
        $text = $sm->get_string('action_log_typetext', 'plagiarism_advacheck', null, $CFG->lang);
    }
    if ($cm_sett->checkfile) {
        $file = $sm->get_string('action_log_typefile', 'plagiarism_advacheck', null, $CFG->lang);
    }
    $doctype = "$text:$file";

    if ($cm_sett->add_to_index) {
        $inindex = $sm->get_string('action_log_indexyes', 'plagiarism_advacheck', null, $CFG->lang);
    } else {
        $inindex = $sm->get_string('action_log_indexno', 'plagiarism_advacheck', null, $CFG->lang);
    }

    if ($cm_sett->check_stud_lim) {
        $check_stud_lim = $sm->get_string('action_log_studcancheck', 'plagiarism_advacheck', null, $CFG->lang);
    } else {
        $check_stud_lim = $sm->get_string('action_log_studcanotcheck', 'plagiarism_advacheck', null, $CFG->lang);
    }

    $context = context_course::instance($courseid, MUST_EXIST);
    if (has_capability('plagiarism/advacheck:checkadvacheck', $context)) {
        $checker = $sm->get_string('action_log_checkerteach', 'plagiarism_advacheck', null, $CFG->lang);
    } else {
        $checker = $sm->get_string('action_log_checkerstud', 'plagiarism_advacheck', null, $CFG->lang);
    }
    // If there is a task, get the answer parameters, the number of checks available for students.
    // If forum, then get the forum type.
    $a = $DB->get_record('assign', ['id' => $assignment], 'submissiondrafts');
    $mod_settings = '';
    if ($a) {
        if ($a->submissiondrafts) {
            $mod_settings = $sm->get_string('action_log_submissiondraftsno', 'plagiarism_advacheck', null, $CFG->lang);
        } else {
            $mod_settings = $sm->get_string('action_log_submissiondraftsyes', 'plagiarism_advacheck', null, $CFG->lang);
        }
        $mod_settings = $sm->get_string('action_log_assignsett', 'plagiarism_advacheck', null, $CFG->lang) . $mod_settings;
    }

    $sql = "SELECT f.type
            FROM {forum} AS f
            JOIN {forum_discussions} AS fd ON fd.forum = f.id
            WHERE fd.id = ?";
    $f = $DB->get_record_sql($sql, [$discussion]);

    if ($f) {
        $mod_settings = $sm->get_string('action_log_forumtype', 'plagiarism_advacheck', null, $CFG->lang) . $f->type;
    }

    $a = new \stdClass();
    $a->mode = $mode;
    $a->doctype = $doctype;
    $a->inindex = $inindex;
    $a->checker = $checker;
    $a->mod_settings = $mod_settings;
    $a->check_stud_lim = $check_stud_lim;

    $m = $sm->get_string('action_log_cmsettings', 'plagiarism_advacheck', $a, $CFG->lang);

    $action_str = '';
    switch ($action_id) {
        case 1:
            $action_str = $sm->get_string("action_add_to_queue", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 2:
            $action_str = $sm->get_string("action_start_download", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 3:
            $action_str = $sm->get_string("action_end_download", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 4:
            $action_str = $sm->get_string("action_start_check", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 5:
            $action_str = $sm->get_string("action_verification_start", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 6:
            $action_str = $sm->get_string("action_start_removing_from_index", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 7:
            $action_str = $sm->get_string("action_end_deleting_from_index", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 8:
            $action_str = $sm->get_string("action_add_to_index", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 9:
            $action_str = $sm->get_string("action_result_updated", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 10:
            $action_str = $sm->get_string("action_start_doc_processing", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 11:
            $action_str = $sm->get_string("action_start_loading_doc_fields", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 12:
            $action_str = $sm->get_string("action_end_loading_doc_fields", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 13:
            $action_str = $sm->get_string("action_end_doc_processing", 'plagiarism_advacheck', null, $CFG->lang);
            break;
        case 14:
            $action_str = $sm->get_string("action_error_received", 'plagiarism_advacheck', null, $CFG->lang);
            break;
    }

    $row['docid'] = $docid;
    $row['docidantplgt'] = empty($docidantplgt) ? '' : $docidantplgt;
    $row['reportedit'] = $reportedit;
    $row['time_action'] = $mt;
    $row['time_action_hr'] = date('d-m-Y H:i:s', $mt_int) . ".$k[1]";
    $row['status'] = $status;
    $row['action'] = $action_str;
    $row['courseid'] = $courseid;
    $row['cmid'] = $cmid;
    $row['assignment'] = $assignment;
    $row['answerid'] = $objectid;
    $row['discussion'] = $discussion;
    $row['userid'] = $userid;
    $row['verifier_initiator'] = $initid;
    $row['errormessage'] = $errormessage;
    $row['cmsettings'] = $m;
    $DB->insert_record('plagiarism_advacheck_act_log', (object) $row);
}

/**
 * Adds the message text to the queue for verify for forums.
 * After saving the module settings, if the check was enabled after students added answers.
 *
 * @global moodle_database $DB
 * @param context $crs_ctxt Course context.
 * @param context $mod_ctxt Course module context.
 * @param stdClass $plugin_cfg Plugin settings.
 * @param stdClass $p Structure with a message.
 * @param int $courseid
 * @param int $cmid
 * @return void
 */
function plagiarism_advacheck_add_to_queue_forum_text($crs_ctxt, $mod_ctxt, $plugin_cfg, $p, $courseid, $cmid)
{
    global $DB;
    $hash = plagiarism_advacheck_get_strip_text_content_hash($p->message);
    // Checking if there is a record with this hash.
    $rec = $DB->get_record(
        'plagiarism_advacheck_docs',
        ['typeid' => $hash, 'doctype' => PLAGIARISM_ADVACHECK_FORUM, 'userid' => $p->userid, 'answerid' => $p->id]
    );

    if ($rec) {
        return;
    }

    // Checking if there is a record with this hash by old algorithm
    $content = file_rewrite_pluginfile_urls($p->message, 'pluginfile.php', $mod_ctxt->id, 'mod_forum', 'post', $p->id);
    $content = trim($content);
    $rec = $DB->get_record(
        'plagiarism_advacheck_docs',
        ['typeid' => sha1($content), 'doctype' => PLAGIARISM_ADVACHECK_FORUM, 'userid' => $p->userid, 'answerid' => $p->id]
    );
    if ($rec) {
        // Write new hash only and return from function
        $DB->set_field('plagiarism_advacheck_docs', 'typeid', $hash, ['id' => $rec->id]);
        return;
    }

    // Checking whether this message (from a teacher or administrator) needs to be checked in AP?
    if (plagiarism_advacheck\observer::can_not_checked_by($crs_ctxt, $p->userid)) {
        // Add to the queue with the status - no right to be verified.
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_FORUM,
            $hash,
            PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
            $p->id,
            0,
            $p->userid,
            0,
            $p->discussion,
            $courseid,
            $cmid
        );
        return;
    }
    if (count_words($p->message) < (int) $plugin_cfg->min_len_str) {
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_FORUM,
            $hash,
            PLAGIARISM_ADVACHECK_LESSNWORDS,
            $p->id,
            0,
            $p->userid,
            0,
            $p->discussion,
            $courseid,
            $cmid
        );
        return;
    }
    plagiarism_advacheck\observer::add_to_queue(
        PLAGIARISM_ADVACHECK_FORUM,
        $hash,
        PLAGIARISM_ADVACHECK_WAITUPLOAD,
        $p->id,
        $p->time,
        $p->userid,
        0,
        $p->discussion,
        $courseid,
        $cmid
    );
}

/**
 * Adds the essay response text to the review queue.
 *
 * @global stdClass $CFG
 * @global moodle_database $DB
 * @param context $crs_ctxt Course context.
 * @param context $mod_ctxt Course module context.
 * @param stdClass $plugin_cfg Plugin settings.
 * @param stdClass $a Essay attempt option.
 * @param int $courseid
 * @param int $cmid
 * @return void
 */
function plagiarism_advacheck_add_to_queue_quiz_text($crs_ctxt, $mod_ctxt, $plugin_cfg, $a, $courseid, $cmid)
{
    global $CFG, $DB;

    if (!isset($a->txt)) {
        $a->txt = '';
    }

    $hash = plagiarism_advacheck_get_strip_text_content_hash($a->txt);
    // Checking if there is a record with this hash.
    $rec = $DB->get_record(
        'plagiarism_advacheck_docs',
        ['typeid' => $hash, 'doctype' => PLAGIARISM_ADVACHECK_QUIZ, 'userid' => $a->userid, 'answerid' => $a->id]
    );

    if ($rec) {
        return;
    }

    // Checking if there is a record with hash of old algorithm.
    $rec = $DB->get_record(
        'plagiarism_advacheck_docs',
        ['typeid' => sha1($a->txt), 'doctype' => PLAGIARISM_ADVACHECK_QUIZ, 'userid' => $a->userid, 'answerid' => $a->id]
    );
    if ($rec) {
        // Write new hash only and return from function
        $DB->set_field('plagiarism_advacheck_docs', 'typeid', $hash, ['id' => $rec->id]);
        return;
    }

    // Checking whether this message (from a teacher or administrator) needs to be checked in AP?
    if (plagiarism_advacheck\observer::can_not_checked_by($crs_ctxt, $a->userid)) {
        // Add to the queue with the status - no right to be verified.
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_QUIZ,
            $hash,
            PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
            $a->id,
            0,
            $a->userid,
            0,
            0,
            $courseid,
            $cmid
        );
        return;
    }
    if (count_words($a->txt) < (int) $plugin_cfg->min_len_str) {
        // Adding to the queue with status - few words.
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_QUIZ,
            $hash,
            PLAGIARISM_ADVACHECK_LESSNWORDS,
            $a->id,
            0,
            $a->userid,
            0,
            0,
            $courseid,
            $cmid
        );
        return;
    }

    $status = PLAGIARISM_ADVACHECK_WAITUPLOAD;
    plagiarism_advacheck\observer::add_to_queue(PLAGIARISM_ADVACHECK_QUIZ, $hash, $status, $a->id, $a->time, $a->userid, 0, 0, $courseid, $cmid);
}

/**
 * Adds the text of the workshop response to the queue for review
 *
 * @global stdClass $CFG
 * @global moodle_database $DB
 * @param context $crs_ctxt Course context.
 * @param context $mod_ctxt Course module context.
 * @param stdClass $plugin_cfg Plugin settings.
 * @param stdClass $s Structure with answer.
 * @param int $courseid
 * @param int $cmid
 * @return void
 */
function plagiarism_advacheck_add_to_queue_workshop_text($crs_ctxt, $mod_ctxt, $plugin_cfg, $s, $courseid, $cmid)
{
    global $CFG, $DB;

    $hash = plagiarism_advacheck_get_strip_text_content_hash($s->txt);
    // Checking if there is a record with this hash.
    $rec = $DB->get_record(
        'plagiarism_advacheck_docs',
        ['typeid' => $hash, 'doctype' => PLAGIARISM_ADVACHECK_WORKSHOP, 'userid' => $s->userid, 'answerid' => $s->id]
    );

    if ($rec) {
        return;
    }

    // Checking if there is a record with hash of old algorithm.
    $rec = $DB->get_record(
        'plagiarism_advacheck_docs',
        ['typeid' => sha1($s->txt), 'doctype' => PLAGIARISM_ADVACHECK_WORKSHOP, 'userid' => $s->userid, 'answerid' => $s->id]
    );
    if ($rec) {
        // Write new hash only and return from function
        $DB->set_field('plagiarism_advacheck_docs', 'typeid', $hash, ['id' => $rec->id]);
        return;
    }

    // Checking whether this message (from a teacher or administrator) needs to be checked in AP?
    if (plagiarism_advacheck\observer::can_not_checked_by($crs_ctxt, $s->userid)) {
        // Add to the queue with the status - no right to be verified.
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_WORKSHOP,
            $hash,
            PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
            $s->id,
            0,
            $s->userid,
            0,
            0,
            $courseid,
            $cmid
        );
        return;
    }
    if (count_words($s->txt) < (int) $plugin_cfg->min_len_str) {
        // Adding to the queue with status - few words.
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_WORKSHOP,
            $hash,
            PLAGIARISM_ADVACHECK_LESSNWORDS,
            $s->id,
            0,
            $s->userid,
            0,
            0,
            $courseid,
            $cmid
        );
        return;
    }

    $status = PLAGIARISM_ADVACHECK_WAITUPLOAD;
    plagiarism_advacheck\observer::add_to_queue(PLAGIARISM_ADVACHECK_WORKSHOP, $hash, $status, $s->id, $s->time, $s->userid, 0, 0, $courseid, $cmid);
}

/**
 * Adds the text of the response to the task to the queue for verification.
 *
 * @global \stdClass $CFG
 * @global moodle_database $DB
 * @param context $crs_ctxt Course context.
 * @param context $mod_ctxt Course module context.
 * @param \stdClass $plugin_cfg Plugin settings.
 * @param \stdClass $s Structure with answer.
 * @param int $courseid
 * @param int $cmid
 * @return mixed
 */
function plagiarism_advacheck_add_to_queue_assign_text($crs_ctxt, $mod_ctxt, $plugin_cfg, $s, $courseid, $cmid)
{
    global $CFG, $DB;

    $hash = plagiarism_advacheck_get_strip_text_content_hash($s->txt);
    // Checking if there is a record with this hash.
    $rec = $DB->get_record(
        'plagiarism_advacheck_docs',
        ['typeid' => $hash, 'doctype' => PLAGIARISM_ADVACHECK_ASSIGN, 'userid' => $s->userid, 'answerid' => $s->id]
    );

    if ($rec) {
        return;
    }

    // Checking if there is a record with hash of old algorithm.
    // If Moodle 3.3.3 and below.
    if ($CFG->version < 2017051504) {
        require_once ($CFG->dirroot . '/mod/assign/locallib.php');
        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'assign');
        $assign = new assign($mod_ctxt, $cm, $course);
        $content = $assign->render_editor_content(
            ASSIGNSUBMISSION_ONLINETEXT_FILEAREA,
            $s->id,
            'onlinetext',
            'onlinetext',
            'assignsubmission_onlinetext'
        );
        $content = trim($content);
    } else {
        // If Moodle 3.3.4 and higher
        if (!isset($s->txt)) {
            $s->txt = '';
        }
        $content = trim($s->txt);
    }
    // Checking if there is a record with this hash.
    $rec = $DB->get_record(
        'plagiarism_advacheck_docs',
        ['typeid' => sha1($content), 'doctype' => PLAGIARISM_ADVACHECK_ASSIGN, 'userid' => $s->userid, 'answerid' => $s->id]
    );

    if ($rec) {
        // Write new hash only and return from function
        $DB->set_field('plagiarism_advacheck_docs', 'typeid', $hash, ['id' => $rec->id]);
        return;
    }

    // Checking whether this message (from a teacher or administrator) needs to be checked in AP?
    if (plagiarism_advacheck\observer::can_not_checked_by($crs_ctxt, $s->userid)) {
        // Add to the queue with the status - no right to be verified.
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_ASSIGN,
            $hash,
            PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
            $s->id,
            0,
            $s->userid,
            $s->assign,
            0,
            $courseid,
            $cmid
        );
        return;
    }
    if (count_words($s->txt) < (int) $plugin_cfg->min_len_str) {
        // Adding to the queue with status - few words.
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_ASSIGN,
            $hash,
            PLAGIARISM_ADVACHECK_LESSNWORDS,
            $s->id,
            0,
            $s->userid,
            $s->assign,
            0,
            $courseid,
            $cmid
        );
        return;
    }
    // Draft submitted.
    switch ($s->status) {
        case 'draft':
            $status = PLAGIARISM_ADVACHECK_WAITBLOCK;
            break;
        case 'submitted':
            $status = PLAGIARISM_ADVACHECK_WAITUPLOAD;
            break;
    }
    plagiarism_advacheck\observer::add_to_queue(PLAGIARISM_ADVACHECK_ASSIGN, $hash, $status, $s->id, $s->time, $s->userid, $s->assign, 0, $courseid, $cmid);
}

/**
 * Adds files to the queue for sending.
 *
 * @global moodle_database $DB
 * @param array $files
 * @param object $inst Structure with answer.
 * @param string $modname Module type names.
 * @param context $crs_ctxt Course context.
 * @param int $courseid
 * @param int $cmid
 */
function plagiarism_advacheck_add_to_queue_file($files, $inst, $modname, $crs_ctxt, $courseid, $cmid)
{
    global $DB;
    $assignment = 0;
    $discussion = 0;
    $cond = [];
    switch ($modname) {
        case 'assign':
            $assignment = $inst->assign;
            $cond['assignment'] = $assignment;
            break;
        case 'forum':
            $discussion = $inst->discussion;
            $cond['discussion'] = $discussion;
            break;
        case 'workshop':
            $cond['discussion'] = $discussion;
            $cond['assignment'] = $assignment;
            break;
        case 'quiz':
            $cond['discussion'] = $discussion;
            $cond['assignment'] = $assignment;
            break;
    }
    foreach ($files as $f) {
        if (!$f || $f->is_directory()) {
            continue;
        }
        // If an entry with such a file ID, such a user, and such a document type is already in the queue, then we will not add it.
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            array_merge($cond, ['typeid' => $f->get_id(), 'doctype' => PLAGIARISM_ADVACHECK_FILE, 'userid' => $f->get_userid()])
        );
        if ($rec) {
            continue;
        }
        $filetype = substr(strrchr($f->get_filename(), "."), 1) . ',';
        // Check the file extension for valid file types to scan.
        if (mb_strpos(PLAGIARISM_ADVACHECK_ALLOW_FILE_TYPES, mb_strtolower($filetype)) === false) {
            // Add to the queue with the status invalid file type.
            plagiarism_advacheck\observer::add_to_queue(
                PLAGIARISM_ADVACHECK_FILE,
                $f->get_id(),
                PLAGIARISM_ADVACHECK_INVALIDFILETYPE,
                $inst->id,
                0,
                $f->get_userid(),
                $assignment,
                $discussion,
                $courseid,
                $cmid
            );
            continue;
        }
        if (plagiarism_advacheck\observer::can_not_checked_by($crs_ctxt, $f->get_userid())) {
            // Add to the queue with the status - no right to be verified.
            plagiarism_advacheck\observer::add_to_queue(
                PLAGIARISM_ADVACHECK_FILE,
                $f->get_id(),
                PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                $inst->id,
                0,
                $f->get_userid(),
                $assignment,
                $discussion,
                $courseid,
                $cmid
            );
            continue;
        }
        $status = PLAGIARISM_ADVACHECK_WAITUPLOAD;
        if ($assignment) {
            // Draft submitted.
            if ($inst->status == 'draft') {
                $status = PLAGIARISM_ADVACHECK_WAITBLOCK;
            }
        }
        plagiarism_advacheck\observer::add_to_queue(
            PLAGIARISM_ADVACHECK_FILE,
            $f->get_id(),
            $status,
            $inst->id,
            $inst->time,
            $f->get_userid(),
            $assignment,
            $discussion,
            $courseid,
            $cmid
        );
    }
}

/**
 * Selects the initials of the answer author.
 *
 * @global moodle_database $DB
 * @param int $userid
 * @return string
 */
function plagiarism_advacheck_get_autor_fio($userid)
{
    global $DB;
    $u = $DB->get_record('user', ['id' => $userid], 'id, lastname, firstname');
    $afio = "$u->lastname $u->firstname";
    return $afio;
}

/**
 * Returns the initials of the reviewer.
 * If the answer was checked automatically, the names of all course teachers should be displayed.
 *
 * @global moodle_database $DB
 * @param int $courseid
 * @param int $uid userid
 * @return string
 */
function plagiarism_advacheck_get_verifier_fio($courseid, $uid = null)
{
    global $DB;
    $vfio = '';

    if (isset($uid)) {
        $t = $DB->get_record('user', ['id' => $uid], 'id, lastname, firstname');
        $vfio = "$t->lastname $t->firstname";
    } else {
        $sql = "SELECT u.id,
        u.lastname,
        u.firstname
            FROM {user} AS u
            LEFT JOIN {context} AS ctx ON ctx.instanceid = ?
            JOIN {role_assignments}  AS tra ON tra.contextid = ctx.id AND u.id = tra.userid
            WHERE  tra.roleid = 3
            ORDER BY u.lastname, u.firstname";
        $ts = $DB->get_records_sql($sql, [$courseid]);

        $vfio = '';
        foreach ($ts as $t) {
            $vfio .= "$t->lastname $t->firstname, ";
        }
        // Remove the last 2 characters ', '.
        $vfio = substr($vfio, 0, -2);
    }
    return $vfio;
}
/**
 * Clear text of html tags and special characters. Trims extra spaces. Line break tags are replaced with end-of-line (EOL) characters.
 * Calculates Sha1-hash. 
 * @param string $textcontent Text from the editor.
 * @param bool $getcleartext Get clear text. 
 * @return string Sha1-hash of clear text, if $getcleartext is false, else clear text, converted binary data into hexadecimal representation
 */
function plagiarism_advacheck_get_strip_text_content_hash($textcontent, $getcleartext = false)
{
    $cleartext = trim(str_replace("\n", ' ', html_entity_decode(strip_tags(nl2br($textcontent)))));
    if ($getcleartext) {
        return bin2hex($cleartext);
    } else {
        return sha1($cleartext);
    }
}