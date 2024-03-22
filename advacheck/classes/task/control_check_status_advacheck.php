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
 * Monitoring the completion of document verification.
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\task;

class control_check_status_advacheck extends \core\task\scheduled_task
{

    /**
     *
     * @var \plagiarism_advacheck\advacheck_api  for connecting to AP
     */
    private $api = null;
    private $config;

    function __construct()
    {
        $this->config = get_config('plagiarism_advacheck');

    }

    /**
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

        echo PHP_EOL . get_string_manager()->get_string('control_check_status_enter', 'plagiarism_advacheck', null, $CFG->lang) . PHP_EOL;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->dirroot . "/plagiarism/advacheck/locallib.php");
        require_once($CFG->dirroot . '/plagiarism/advacheck/constants.php');
        require_once($CFG->dirroot . "/plagiarism/advacheck/lib.php");

        if (empty($this->config->uri) || empty($this->config->login) || empty($this->config->password)) {
            echo PHP_EOL . get_string_manager()->get_string('control_check_status_nologindata', 'plagiarism_advacheck', null, $CFG->lang) . PHP_EOL;
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
        // We will request all documents awaiting verification or being checked, sorted by the time the response occurred.
        $sql = "SELECT docs.*, cfg.add_to_index
          FROM {plagiarism_advacheck_docs} docs
          INNER JOIN {plagiarism_advacheck_course} cfg ON (docs.courseid = cfg.courseid AND docs.cmid = cfg.cmid)
          WHERE (docs.status = ? OR docs.status = ? OR docs.status = ? OR docs.status = ? OR docs.status = ?)
          ORDER BY timeadded ";
        // In the statuses: uploaded, being checked, verification request error, error receiving verification status,
        // Error receiving a report, error adding to the index.
        $data = $DB->get_records_sql(
            $sql,
            [
                ADVACHECK_UPLOADED,
                ADVACHECK_CHECKING,
                ADVACHECK_ERROR_CHECKING,
                ADVACHECK_ERROR_GET_STATUS,
                ADVACHECK_ERROR_INDEX,
            ],
            0,
            $count
        );
        echo get_string_manager()->get_string('control_check_status_countdocs', 'plagiarism_advacheck', count($data), $CFG->lang) . PHP_EOL;

        $orig = 0;
        echo get_string_manager()->get_string('control_check_status_cycle', 'plagiarism_advacheck', null, $CFG->lang) . PHP_EOL;
        // The cycle of uploaded documents to Antiplagiarism.
        foreach ($data as $item) {

            $docid = unserialize($item->docidantplgt);
            $res = new \stdClass();
            $res->id = $item->id;
            $a = new \stdClass();
            $a->id = $item->id;
            $a->status = $item->status;
            $a->time = date('d:m:Y H:i:s');
            echo "    " . get_string_manager()->get_string('control_check_status_docprocessing', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
            queue_log(
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
                'task\control_check_status_advacheck'
            );
            // If there was an error installing into the index, then we will install it into the index without requesting the results.
            if ($item->status == ADVACHECK_ERROR_INDEX) {
                $this->add_to_index($item);
                continue;
            }
            $check_status = $this->api->get_current_check_status($docid);
            if (isset($check_status->error)) {
                // Handling getting current check status errors.
                $a = new \stdClass();
                $a->error = $check_status->error;
                $a->time = date('d:m:Y H:i:s');
                echo "        " . get_string_manager()->get_string('control_check_status_getcheckstatuserror', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL . PHP_EOL;
                $status = ADVACHECK_ERROR_GET_STATUS;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $check_status->error, ['id' => $item->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                queue_log(
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
                    'task\control_check_status_advacheck',
                    $check_status->error
                );
                continue;
            }
            if ($check_status->status === "Ready") {
                // The document has been checked, we record the results of the check and links to reports.
                $a = new \stdClass();
                $a->id = $item->id;
                $a->time = date('d:m:Y H:i:s');
                echo "       " . get_string_manager()->get_string('control_check_status_checkready', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                // Rounding values.
                calc_orig($check_status->plagiarism, $check_status->legal, $check_status->selfcite, $orig);
                $res->reportedit = $check_status->reportedit;
                $res->reportread = $check_status->reportread;
                $res->shortreport = $check_status->shortreport;
                $res->timecheck_end = $check_status->timecheck_end;
                $res->plagiarism = $check_status->plagiarism;
                $res->legal = $check_status->legal;
                $res->issuspicious = $check_status->issuspicious;
                $res->selfcite = $check_status->selfcite;
                $res->status = ADVACHECK_CHECKED;
                $DB->update_record('plagiarism_advacheck_docs', $res);
                queue_log(
                    $item->docidantplgt,
                    $check_status->reportedit,
                    5,
                    $item->courseid,
                    $item->cmid,
                    $item->assignment,
                    $item->discussion,
                    $item->userid,
                    $item->answerid,
                    $item->id,
                    ADVACHECK_CHECKED,
                    'task\control_check_status_advacheck'
                );
                $this->add_to_index($item);

                // End of document processing.
                queue_log(
                    $item->docidantplgt,
                    $check_status->reportedit,
                    13,
                    $item->courseid,
                    $item->cmid,
                    $item->assignment,
                    $item->discussion,
                    $item->userid,
                    $item->answerid,
                    $item->id,
                    $item->status,
                    'task\control_check_status_advacheck'
                );
            } else if ($check_status->status === "Failed") {
                // An error occurred while checking the document.
                $a = new \stdClass();
                $a->error = $check_status->msg;
                $a->time = date('d:m:Y H:i:s');
                echo "        " . get_string_manager()->get_string('control_check_status_checkerror', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $status = ADVACHECK_ERROR_CHECK;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $check_status->msg, ['id' => $item->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                queue_log(
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
                    'task\control_check_status_advacheck',
                    $check_status->msg
                );
            } else if ($check_status->status === 'None') {
                // Starting the check (the document was not checked).
                $a = new \stdClass();
                $a->id = $item->id;
                $a->time = date('d:m:Y H:i:s');
                echo "        " . get_string_manager()->get_string('control_check_status_nostartcheck', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $m = $this->api->start_check($docid);
                $status = ADVACHECK_CHECKING;
                $DB->set_field('plagiarism_advacheck_docs', 'timecheck_start', time(), ['id' => $item->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                queue_log(
                    $item->docidantplgt,
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
                    'task\control_check_status_advacheck'
                );
                if ($m !== true) {
                    // Handling getting current check status errors.
                    $a = new \stdClass();
                    $a->error = $m;
                    $a->time = date('d:m:Y H:i:s');
                    echo "        " . get_string_manager()->get_string('control_check_status_startcheckerror', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                    $status = ADVACHECK_ERROR_CHECKING;
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $item->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                    queue_log(
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
                        'task\control_check_status_advacheck',
                        $m
                    );
                }
            } else if ($check_status->status !== 'InProgress') {
                // Document verification failed.
                $a = new \stdClass();
                $a->id = $item->id;
                $a->status = $item->status;
                $a->time = date('d:m:Y H:i:s');
                $m = get_string_manager()->get_string('control_check_status_checkingerror', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                echo "        " . $m . PHP_EOL;
                $status = ADVACHECK_ERROR_CHECK;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $item->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                queue_log(
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
                    'task\control_check_status_advacheck',
                    $m
                );
            }
            echo PHP_EOL;
        }
        echo get_string_manager()->get_string('control_check_status_complited', 'plagiarism_advacheck', null, $CFG->lang) . PHP_EOL . PHP_EOL;
        return true;
    }

    /**
     *
     * @return string
     */
    public function get_name()
    {
        global $CFG;
        return get_string_manager()->get_string('control_check_status_advacheck', 'plagiarism_advacheck', null, $CFG->lang);
    }

    /**
     * Adds a document to the index.
     *
     * @global \stdClass $CFG
     * @global \moodle_database $DB
     * @return void
     */

    private function add_to_index(&$doc)
    {
        global $DB, $CFG;
        // If the course module has indexing enabled.
        if ($doc->add_to_index != 0) {
            $docid = unserialize($doc->docidantplgt);
            $a = new \stdClass();
            $a->time = date('d:m:Y H:i:s');
            echo "       " . get_string_manager()->get_string('control_check_status_addtoindex', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
            $m = $this->api->set_index_status($docid, true);
            if ($m !== true) {
                // Error handling when adding a document to the index.
                $a = new \stdClass();
                $a->error = $m;
                $a->time = date('d:m:Y H:i:s');
                echo "       " . get_string_manager()->get_string('control_check_status_addtoindexerror', 'plagiarism_advacheck', $a, $CFG->lang) . PHP_EOL;
                $status = ADVACHECK_ERROR_INDEX;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $doc->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
                queue_log(
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
                return;
            }
            $doc->status = ADVACHECK_ININDEX;
            $DB->set_field('plagiarism_advacheck_docs', 'status', ADVACHECK_ININDEX, ['id' => $doc->id]);
            queue_log(
                $doc->docidantplgt,
                $doc->reportedit,
                8,
                $doc->courseid,
                $doc->cmid,
                $doc->assignment,
                $doc->discussion,
                $doc->userid,
                $doc->answerid,
                $doc->id,
                ADVACHECK_ININDEX,
                'task\control_check_status_advacheck'
            );
        }
    }

}
