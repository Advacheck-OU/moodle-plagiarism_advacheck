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

function xmldb_plagiarism_advacheck_upgrade($oldversion)
{
    global $DB, $CFG, $OUTPUT;
    $dbman = $DB->get_manager();
    if ($oldversion < 2023100600) {

        // Starting from version 2023091920, the API link must be entered differently.
        $settings = get_config('plagiarism_advacheck');
        if (!empty($settings->soap_wsdl) && !empty($settings->company)) {
            set_config('soap_wsdl', "$settings->soap_wsdl/apiCorp/$settings->company?singleWsdl", 'plagiarism_advacheck');
            // The company field is no longer used.
            set_config('company', null, 'plagiarism_advacheck');
        }
        // Apgtru savepoint reached.
        upgrade_plugin_savepoint(true, 2023100600, 'plagiarism', 'advacheck');
    }

    if ($oldversion < 2024020902) {
        // refresh view
        add_log_view();
        // Define field id to be added to plagiarism_advacheck_docs.
        $table = new xmldb_table('plagiarism_advacheck_docs');
        $field = new xmldb_field('attemptnumber', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'type');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Apgtru savepoint reached.
        upgrade_plugin_savepoint(true, 2024020902, 'plagiarism', 'advacheck');
    }

    return true;
}
