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
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\task;

class clear_action_log extends \core\task\scheduled_task
{

    public function get_name()
    {
        global $CFG;
        return get_string_manager()->get_string('clear_action_log', 'plagiarism_advacheck', null, $CFG->lang);
    }

    public function execute()
    {
        global $DB, $CFG;
        $store_action_time = get_config('plagiarism_advacheck', 'store_action_time');
        // Current time - (number of months) * (seconds in month).
        $time_store = time() - $store_action_time * 2592000;
        $r = $DB->get_record_sql("SELECT COUNT(id) AS cnt FROM {plagiarism_advacheck_act_log} WHERE $time_store > time_action");
        echo get_string_manager()->get_string('clear_action_log_cntrecfordel', 'plagiarism_advacheck', $r->cnt, $CFG->lang) . PHP_EOL;
        // We will delete all entries before the specified date.
        $DB->execute("DELETE FROM {plagiarism_advacheck_act_log} WHERE $time_store > time_action");
        return true;
    }

}
