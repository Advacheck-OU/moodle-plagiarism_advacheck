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

require_once ($CFG->dirroot . "/plagiarism/advacheck/lib.php");

function xmldb_plagiarism_advacheck_uninstall()
{
    global $DB;

    // First, let's delete all the tables that the view depends on.
    $DB->execute('DROP TABLE {plagiarism_advacheck_act_log}');
    $DB->execute('DROP TABLE {plagiarism_advacheck_docs}');
    $DB->execute('DROP TABLE {plagiarism_advacheck_action}');
    // Delete plugin settings.
    $DB->delete_records('config_plugins', ['plugin' => 'plagiarism']);
    $DB->delete_records('config_plugins', ['plugin' => 'plagiarism_advacheck']);

    return true;
}
