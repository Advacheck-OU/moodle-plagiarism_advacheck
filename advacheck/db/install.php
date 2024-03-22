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
 *
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/plagiarism/advacheck/lib.php");

function xmldb_plagiarism_advacheck_install()
{
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    // Let's add a view.
    add_log_view();
    // Let's add a list of events to the events table.
    $table = new xmldb_table('plagiarism_advacheck_action');
    if ($dbman->table_exists($table)) {
        // Let's collect a string with action values from the language pack.
        $values = '';
        for ($i = 1; $i < 15; $i++) {
            $values .= "($i, '" . get_string_manager()->get_string("action$i", 'plagiarism_advacheck', null, $CFG->lang) . "'),";
        }
        // Let's cut off the extra comma at the end.
        $values = mb_substr($values, 0, -1);
        $sql = "INSERT INTO {plagiarism_advacheck_action} (id, action_name) VALUES
                $values";
        $DB->execute($sql);
    }
    return true;
}
