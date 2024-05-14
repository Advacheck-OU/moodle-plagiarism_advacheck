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
 * Processes ajax requests
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @copyright based on work by 1999 Martin Dougiamas {@link http://moodle.com}
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('AJAX_SCRIPT');
require_once ('../../config.php');
// Setting the internal encoding to which the HTTP request input data will be converted.
mb_internal_encoding("UTF-8");
require_once ($CFG->dirroot . '/plagiarism/advacheck/lib.php');
require_once ($CFG->dirroot . '/plagiarism/advacheck/locallib.php');

$action = optional_param('action', '', PARAM_TEXT);
$type = optional_param('doctype', '', PARAM_TEXT);
$typeid = optional_param('typeid', 0, PARAM_TEXT);

$courseid = optional_param('courseid', 1, PARAM_INT);
$content = optional_param('content', '', PARAM_TEXT);
$doctype = optional_param('doctype', '', PARAM_TEXT);

$assignment = optional_param("assignment", 0, PARAM_INT);
$discussion = optional_param("discussion", 0, PARAM_INT);
$userid = optional_param("userid", 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid]);
require_login($course, false);

if (!confirm_sesskey()) {
    throw new \moodle_exception('confirmsesskeybad');
}

// Checking the files.
if (($action == "checkfile") && !empty($typeid)) {
    $result = plagiarism_advacheck_start_file_verify($typeid, $courseid);
    echo json_encode($result);
    exit;
}
// Checking the text.
if (($action == "checktext") && !empty($typeid) && !empty($content)) {
    $content = hex2bin($content);
    $result = plagiarism_advacheck_start_text_verify($typeid, $content, $courseid, $userid, $assignment, $discussion, $doctype);
    echo json_encode($result);
    exit;
}

// Changing the verification mode.
if ($action == "changeMode") {
    $cm = optional_param('cm', 0, PARAM_INT);
    $mode = optional_param('mode', PLAGIARISM_ADVACHECK_DISABLEDMODE, PARAM_INT);
    $course = $DB->get_field('course_modules', 'course', ['id' => $cm]);
    if ($cm && $course) {
        if ($DB->record_exists('plagiarism_advacheck_course', ['courseid' => $course, 'cmid' => $cm])) {
            $DB->set_field('plagiarism_advacheck_course', 'mode', $mode, ['courseid' => $course, 'cmid' => $cm]);
        } else {
            $row = new stdClass();
            $row->courseid = $course;
            $row->cmid = $cm;
            $row->mode = $mode;
            $DB->insert_record('plagiarism_advacheck_course', $row);
        }
    }
    exit;
}
// Changing other module settings.
if ($action == "changetype") {
    $cm = optional_param('cm', 0, PARAM_INT);
    $type = optional_param('doctype', '', PARAM_TEXT);
    $value = optional_param('value', 0, PARAM_TEXT);
    $course = $DB->get_field('course_modules', 'course', ['id' => $cm]);
    if ($cm && $course && $type) {
        if ($DB->record_exists('plagiarism_advacheck_course', ['courseid' => $course, 'cmid' => $cm])) {
            $DB->set_field('plagiarism_advacheck_course', $type, $value, ['courseid' => $course, 'cmid' => $cm]);
        } else {
            $row = new stdClass();
            $row->courseid = $course;
            $row->cmid = $cm;
            $row->{$type} = $value;
            $DB->insert_record('plagiarism_advacheck_course', $row);
        }
    }

    exit;
}
// Checking the tariff.
if ($action == 'checktarif') {
    $login = optional_param('login', '', PARAM_TEXT);
    $password = optional_param('password', '', PARAM_TEXT);
    $soap_wsdl = optional_param('soap_wsdl', '', PARAM_TEXT);
    $url = optional_param('uri', '', PARAM_TEXT);
    if ($login == '' || $password == '' || $soap_wsdl == '' || $url == '') {
        $error = new stdClass();
        $error->message = '<div class="alert alert-danger alert-block fade in  alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button>';
        $error->message .= get_string('error_login', 'plagiarism_advacheck') . "</div>";
        echo json_encode($error);
    } else {
        echo json_encode(plagiarism_advacheck_get_advacheck_tarif_info_html($login, $password, $soap_wsdl, $url));
    }
    exit;
}
// Updated the link to the report.
if ($action == 'update_report') {
    $typeid = optional_param('typeid', '', PARAM_TEXT);
    $link = plagiarism_advacheck_update_advacheck_report($typeid);
    echo json_encode($link);
    exit;
}

