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
 * Class for working with the Anti-Plagiarism API
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\local;

require_once "constants.php";

/**
 * A class that search and remove documents from index
 */
class index_manager
{
    /**
     * @var mixed Object to connect external service 
     */
    private $api;
    private $cmid;
    private $userid;
    private $doctype;
    private $discussion;
    private $questionid;
    private $timeadded;
    private $docarr = [];
    private $logobject;

    /**     
     *
     * @param int $cmid
     * @param int $userid
     * @param int $doctype
     * @param int $discussion
     * @param int $timeadded
     * @param int $discussion
     * @param int $questionid
     */
    public function __construct($cmid = 0, $userid = 0, $doctype = 0, $timeadded = 0, $discussion = 0, $questionid = 0)
    {
        $this->api = new advacheck_api();
        $this->logobject = new queue_log_manager();
        $this->cmid = $cmid;
        $this->userid = $userid;
        $this->doctype = $doctype;
        $this->discussion = $discussion;
        $this->timeadded = $timeadded;
        $this->questionid = $questionid;
    }
    /**
     * Searches for documents to be deleted from the index.
     */
    private function get_documents()
    {
        global $DB;
        $sql = "SELECT *
        FROM {plagiarism_advacheck_docs}
       WHERE doctype = ?
             AND userid = ?
             AND status = ?
             AND cmid = ?
             AND (timeadded  - ?) <> 0
     ";
        $docsparams = [
            'doctype' => $this->doctype,
            'userid' => $this->userid,
            'status' => advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX,
            'cmid' => $this->cmid,
            'timeadded' => $this->timeadded
        ];
        if ($this->discussion) {
            $sql .= " AND discussion = ?";
            $docsparams['discussion'] = $this->discussion;
        }
        if ($this->questionid) {
            $sql .= " AND questionid = ?";
            $docsparams['questionid'] = $this->questionid;
        }
        $this->docarr = $DB->get_records_sql($sql, $docsparams);
    }
    /**
     * Removes a document from the AP index for manual mode.
     *
     * @global \moodle_database $DB
     * @param bool $mtrace Display trace log for schedule task.
     * @param bool|string $auto For log record, string - taskname 
     */
    public function remove_from_index($mtrace = false, $auto = false)
    {
        global $DB;
        $this->get_documents();

        if ($mtrace) {
            $a = new \stdClass();
            $a->time = date('d:m:Y H:i:s');
            $a->cnt = count($this->docarr);
            mtrace("        " . get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a));
        }
        foreach ($this->docarr as $doc) {
            if (empty($doc->externalid)) {
                return;
            }
            $docid = $doc->externalid;
            $m = $this->api->set_index_status($doc->externalid, false);
            if ($m !== true) {
                // Processing error when trying to get the status of deleting a document from the index.
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $doc->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
                $this->logobject->add_log_record(
                    $doc->externalid,
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
                    $auto,
                    $m
                );
            } else {
                // A record indicating the start of deletion from the index.
                $this->logobject->add_log_record(
                    $doc->externalid,
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
                    $auto
                );
                // cycle while waiting for deletion from index.
                do {
                    $di = $this->api->get_document_info($docid);
                    if (is_string($di)) {
                        $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $di, ['id' => $doc->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
                        $this->logobject->add_log_record(
                            $doc->externalid,
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
                            $auto,
                            $di
                        );
                    }
                    // Minimum possible AP server polling frequency.
                    time_nanosleep(0, 400000000);
                } while (isset($di->AddedToIndex));
                // Set the status to deleted from the index.
                $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED, ["id" => $doc->id]);
                // A record indicating the completion of deletion from the index.
                $this->logobject->add_log_record(
                    $doc->externalid,
                    $doc->reportedit,
                    7,
                    $doc->courseid,
                    $doc->cmid,
                    $doc->assignment,
                    $doc->discussion,
                    $doc->userid,
                    $doc->answerid,
                    $doc->id,
                    advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED,
                    $auto
                );
            }
        }
    }
    /**
     * Add document to index 
     * 
     * @param mixed $externalid Document id in plagiarism system.
     */
    public function add_to_index($externalid, $mtrace = false, $auto = false)
    {
        global $DB;
        $docrecord = $DB->get_record('plagiarism_advacheck_docs', ['externalid' => $externalid]);
        $sql =
            "SELECT *
        FROM {plagiarism_advacheck_docs}
        WHERE doctype = ?
            AND userid = ?
            AND status = ?
            AND cmid = ?
            AND (timeadded  - ?) <> 0
        ";
        $docsparams = [
            'doctype' => $docrecord->doctype,
            'userid' => $docrecord->userid,
            'status' => advacheck_constants::PLAGIARISM_ADVACHECK_CHECKING,
            'cmid' => $docrecord->cmid,
            'timeadded' => $docrecord->timeadded
        ];
        if ($docrecord->discussion) {
            $sql .= " AND discussion = ?";
            $docsparams['discussion'] = $docrecord->discussion;
        }
        if ($docrecord->questionid) {
            $sql .= " AND questionid = ?";
            $docsparams['questionid'] = $docrecord->questionid;
        }
        $checkingdocs = $DB->get_records_sql($sql, $docsparams);
        // If the author of the response does not have the "checking" status on this course module, 
        // then adds the response to the index.
        $m = true;
        if (count($checkingdocs) == 0) {
            if ($mtrace) {
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                mtrace("       " . get_string('control_check_status_addtoindex', 'plagiarism_advacheck', $a));
            }
            $m = $this->api->set_index_status($externalid, true);
            if ($m !== true) {
                if ($mtrace) {
                    // Error handling when adding a document to the index.
                    $a = new \stdClass();
                    $a->error = $m;
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("       " . get_string('control_check_status_addtoindexerror', 'plagiarism_advacheck', $a));
                }
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $docrecord->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $docrecord->id]);
                $this->logobject->add_log_record(
                    $docrecord->externalid,
                    $docrecord->reportedit,
                    14,
                    $docrecord->courseid,
                    $docrecord->cmid,
                    $docrecord->assignment,
                    $docrecord->discussion,
                    $docrecord->userid,
                    $docrecord->answerid,
                    $docrecord->id,
                    $status,
                    $auto,
                    $m
                );
            } else {

                $DB->set_field('plagiarism_advacheck_docs', 'status', advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX, ['id' => $docrecord->id]);
                // A record of adding a document to the index.
                $this->logobject->add_log_record(
                    $docrecord->externalid,
                    $docrecord->reportedit,
                    8,
                    $docrecord->courseid,
                    $docrecord->cmid,
                    $docrecord->assignment,
                    $docrecord->discussion,
                    $docrecord->userid,
                    $docrecord->answerid,
                    $docrecord->id,
                    advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX,
                    $auto
                );
                return advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX;
            }
        }
        return $m;
    }
}