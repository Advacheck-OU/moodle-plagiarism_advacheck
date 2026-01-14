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
 * Clearing old log entries.
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\task;

use \plagiarism_advacheck\local\advacheck_api;

class patch_remove_from_index extends \core\task\scheduled_task
{
    private $config;
    private $api;
    public function __construct()
    {
        $this->config = get_config('plagiarism_advacheck');
    }

    public function get_name()
    {
        return get_string('patch_remove_from_index', 'plagiarism_advacheck');
    }

    public function execute()
    {
        global $DB;

        if (empty($this->config->uri) || empty($this->config->login) || empty($this->config->password)) {
            // Connection details have not been entered! I'm leaving the task.
            mtrace(PHP_EOL . get_string('upload_and_check_nologindata', 'plagiarism_advacheck', null));
            return false;
        } else {
            $this->api = new advacheck_api();
        }

        $sql =
            "SELECT * FROM (
            SELECT cmid, userid, COUNT(id) as cnt
            FROM {plagiarism_advacheck_docs}
            WHERE status = 5
            GROUP BY cmid, userid
        ) AS t1 
        WHERE t1.cnt > 1";
        $docs = $DB->get_records_sql($sql, null);
        $msg = get_string('patch_remove_from_index_docs_count', 'plagiarism_advacheck', count($docs));
        mtrace($msg);
        $logpath = __DIR__ . '/docs_deleted_from_index.log';
        file_put_contents($logpath, date('d:m:Y H:i:s') . " $msg" . PHP_EOL, FILE_APPEND);
        foreach ($docs as $doc) {
            $drs = $DB->get_records_sql(
                'SELECT id, externalid FROM {plagiarism_advacheck_docs} WHERE cmid = ? AND userid = ? ORDER BY timeadded DESC',
                [$doc->cmid, $doc->userid]
            );
            $first = true;
            foreach ($drs as $dr) {
                if ($first) {
                    $first = false;
                    continue;
                }
                $m = $this->api->set_index_status($dr->externalid, false);
                if ($m !== true) {
                    // Handling remove from index errors.
                    $a = new \stdClass();
                    $a->id = $doc->id;
                    $a->error = $m;
                    $a->time = date('d:m:Y H:i:s');
                    $msg = get_string('upload_and_check_errorfromindex', 'plagiarism_advacheck', $a);
                    mtrace("    " . $msg);
                    file_put_contents($logpath, date('d:m:Y H:i:s') . " $msg" . PHP_EOL, FILE_APPEND);
                    continue;
                }
                do {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $msg = get_string('upload_and_check_cyclefromindex', 'plagiarism_advacheck', $a);
                    mtrace("        " . $msg);
                    file_put_contents($logpath, date('d:m:Y H:i:s') . " $msg" . PHP_EOL, FILE_APPEND);
                    $di = $this->api->get_document_info($dr->externalid);
                    if (time_nanosleep(0, 400000000) === true) {
                        // Delay of 0.4. This is the minimum possible delay for polling the status of the document index.
                        $a = new \stdClass();
                        $a->time = date('d:m:Y H:i:s');
                        $msg = get_string('upload_and_check_fromindextimeout', 'plagiarism_advacheck', $a);
                        mtrace("            " . $msg);
                        file_put_contents($logpath, date('d:m:Y H:i:s') . " $msg" . PHP_EOL, FILE_APPEND);
                    }

                } while (isset($di->AddedToIndex));
                $DB->set_field('plagiarism_advacheck_docs', 'status', 4, ['id' => $dr->id]);
                $msg = get_string('patch_remove_from_index_success', 'plagiarism_advacheck', $dr->externalid);
                mtrace("    " . $msg);
                file_put_contents($logpath, date('d:m:Y H:i:s') . " $msg" . PHP_EOL, FILE_APPEND);
            }
        }

        return true;
    }

}
