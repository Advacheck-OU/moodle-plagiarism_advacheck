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
 * Module settings page.
 *
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/plagiarismlib.php');
require_once($CFG->dirroot . '/plagiarism/advacheck/lib.php');
require_once($CFG->dirroot . '/plagiarism/advacheck/classes/plagiarism_form.php');

require_login();

admin_externalpage_setup('plagiarismadvacheck');

$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");
$tmptab = optional_param('tab', 'common', PARAM_RAW);

$row = $tabs = [];
$url_common = new moodle_url('/plagiarism/advacheck/settings.php', ['tab' => 'common']);
$row[] = new tabobject('common', $url_common, get_string('common', 'plagiarism_advacheck'));
// Added the first row of tabs.
$tabs[] = $row;

$customdata = new stdClass();
$customdata->tab = $tmptab;
$url_form = new moodle_url('/plagiarism/advacheck/settings.php', ['tab' => $tmptab]);
$mform = new plagiarism_setup_form($url_form, $customdata);

$notice = false;

$mform->process($notice);

echo $OUTPUT->header();
echo print_tabs($tabs, $tmptab, null, null, true);
echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
if ($notice) {
    echo $notice;
}
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
