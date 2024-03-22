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
 * Functions for working with API Moodle.
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// Get global class.
global $CFG;
require_once($CFG->dirroot . '/plagiarism/lib.php');
require_once($CFG->dirroot . '/plagiarism/advacheck/locallib.php');
require_once('constants.php');

class plagiarism_plugin_advacheck extends plagiarism_plugin
{

    /**
     * Course module plagiarism advacheck settings.
     */
    public static $cnfg = null;
    /**
     * Common lagiarism advacheck settings.
     */
    public static $plugin_cfg = null;

    /**
     * Hook to allow plagiarism specific information to be displayed beside a submission.
     * @param array $linkarraycontains all relevant information for the plugin to generate a link
     * @return string
     *
     */
    public function get_links($linkarray)
    {
        global $PAGE, $CFG, $DB;
        global $OUTPUT;

        if (isset($linkarray["component"])) {
            if ($linkarray["component"] == 'qtype_essay') {

                $sql = "SELECT cm.id, cm.course
                    FROM {context} ctx
                    JOIN {course_modules} cm ON cm.id = ctx.instanceid
                    WHERE ctx.id = {$linkarray['context']}";
                $cm = $DB->get_record_sql($sql);
                $linkarray['cmid'] = $cm->id;
                $linkarray['course'] = $cm->course;
            } else {
                return '';
            }
        }

        $course = $linkarray['course'];
        $courseid = is_object($course) ? $course->id : $course;
        $context = context_module::instance($linkarray['cmid'], MUST_EXIST);

        $userid = isset($linkarray['userid']) ? $linkarray['userid'] : null;
        $isAdmin = is_siteadmin($userid);
        if (!has_capability('plagiarism/advacheck:checkedby', $context, $userid) || $isAdmin) {
            return '';
        }

        $cmid = $linkarray['cmid'];
        $cm = $DB->get_record_sql("SELECT cm.*, m.name as modname FROM {course_modules} as cm, {modules} as m WHERE cm.id = {$cmid} AND cm.module = m.id");

        $file = isset($linkarray['file']) ? $linkarray['file'] : null;
        $content = isset($linkarray['content']) ? get_strip_text_content_hash($linkarray['content'], true) : null;

        $assignment = isset($linkarray['assignment']) ? $linkarray['assignment'] : null;
        $forum = isset($linkarray['forum']) ? $linkarray['forum'] : null;
        $workshop = $cm->modname == 'workshop' ? $cm->instance : null;
        $sub = 0;
        $discussion = 0;
        if (isset($assignment)) {
            $modulecontext = context_module::instance($cmid);
            $assign = new assign($modulecontext, false, false);
            $sub = $assign->get_user_submission($userid, false)->id;
        } else {
            $discussion = optional_param("d", 0, PARAM_TEXT);
        }

        // Loaded the plugin settings and added JS to the page.
        $this->_preLoad($cmid, $courseid);
        // If the check is not enabled, it returns an empty string.
        if (empty(self::$cnfg->mode)) {
            return '';
        }
        // Let’s take the “can check?” rights once.
        $checkcap = has_capability('plagiarism/advacheck:checkadvacheck', $context);
        // If the current user is a student and the display of scan results is disabled.
        if (!self::$cnfg->disp_notices && !$checkcap && !$isAdmin) {
            return '';
        }

        $doctype = get_module_type_by_name($cm->modname);
        // If the course module is an assignment, then we will check whether verification is enabled in the assignments.
        if ($doctype == ADVACHECK_ASSIGN) {
            if (empty(self::$plugin_cfg->check_assign)) {
                return '';
            }
        }
        // If the course module is a forum, then let's check whether checking is enabled in forums.
        if ($doctype == ADVACHECK_FORUM) {
            if (empty(self::$plugin_cfg->check_forum)) {
                return '';
            }
        }
        // If the course module is a workshop, then we will check whether verification is enabled in workshops.
        if ($doctype == ADVACHECK_WORKSHOP) {
            if (empty(self::$plugin_cfg->check_workshop)) {
                return '';
            }
        }
        // If the course module is a quiz, then we will check whether verification is enabled in quiz.
        if ($doctype == ADVACHECK_QUIZ) {
            if (empty(self::$plugin_cfg->check_quiz)) {
                return '';
            }
        }

        // If the content is a file, then we will check whether file checking is enabled.
        if ($file) {
            if (empty(self::$cnfg->checkfile)) {
                return '';
            }

            $doctype = ADVACHECK_FILE;
            $typeid = $file->get_id();

            $component = $file->get_component();
            if ($component == 'assignsubmission_file') {
                $assignment = $DB->get_record('assign_submission', ['id' => $file->get_itemid()], 'assignment')->assignment;
            } else if ($component == 'mod_forum') {
                $p_sql = "SELECT fp.discussion, fd.forum
                            FROM {forum_posts} fp
                            INNER JOIN {forum_discussions} fd ON fd.id = fp.discussion
                            WHERE fp.id = ?";

                $p = $DB->get_record_sql($p_sql, [$file->get_itemid()]);
                $discussion = $p->discussion;
                $forum = $p->forum;
            } else if ($component == 'mod_workshop') {
                $workshop = $DB->get_record('workshop_submissions', ['id' => $file->get_itemid()], 'workshopid')->workshopid;
            } else if ($component != 'question') {
                return '';
            }
        } else if ($content) {
            // If the content is text, then let's check whether text checking is enabled.
            if (empty(self::$cnfg->checktext)) {
                return '';
            }
            $typeid = get_strip_text_content_hash($linkarray['content']);
        } else {
            return '';
        }

        $AND = '';
        if ($discussion != 0) {
            $AND = " AND discussion = $discussion ";
        } else if ($assignment != 0) {
            $AND = " AND assignment = $assignment ";
        }

        // Request to receive test results.
        $sql = "SELECT *
                FROM {plagiarism_advacheck_docs}
                WHERE
                doctype = $doctype
                AND typeid = ?
                AND userid = $userid
                $AND
                ORDER BY timeadded";
        $data = $DB->get_record_sql($sql, [$typeid], IGNORE_MULTIPLE);

        // If we have results with a hash that was calculated with the old algorithm.
        if (!$data && isset($content)) {
            $data = $DB->get_record_sql($sql, [sha1($linkarray['content'])], IGNORE_MULTIPLE);
            if ($data) {
                // Write new hash
                $DB->set_field('plagiarism_advacheck_docs', 'typeid', $typeid, ['id' => $data->id]);
            }
        }

        // Variable for html.
        $output = '';
        if ($data) {
            switch ((int) $data->status) {
                // Waits for blocking - unless "Require students to click the submit button" is enabled in the task.
                case ADVACHECK_WAITBLOCK:
                    if ($checkcap) {
                        // If Require students to click the submit button.
                        $submissiondrafts = $DB->get_record('assign', ['id' => $assignment], 'submissiondrafts')->submissiondrafts;
                        if ($submissiondrafts) {
                            // Info for the teacher: the student must submit an answer for verification.
                            $msg = get_string('wait_block1', 'plagiarism_advacheck');
                        } else {
                            // For the teacher: You should be prohibited from changing the answer.
                            $msg = get_string('wait_block2', 'plagiarism_advacheck');
                        }
                        $output = get_html_block_info($msg, 'advacheck-blue');
                    } else {
                        // Information for students: let's check the limit of checks and how many have been checked.
                        if ((int) $data->stud_check >= (int) self::$cnfg->check_stud_lim) {
                            $output = get_html_block_info(get_string('stud_not_check', 'plagiarism_advacheck'), 'advacheck-green');
                        } else {
                            $c = (int) self::$cnfg->check_stud_lim - (int) $data->stud_check;
                            $output = get_html_block_info(
                                get_string('stud_check', 'plagiarism_advacheck', $c),
                                "advacheck-green stud_check-$typeid"
                            );
                            // Receive html buttons for sending for verification.
                            $output .= $this->get_check_button_html(
                                true,
                                $context,
                                $courseid,
                                $doctype,
                                $content,
                                $userid,
                                $assignment,
                                0,
                                $typeid,
                                $data->id
                            );
                        }
                    }
                    break;
                // The document is waiting to be sent.
                case ADVACHECK_WAITUPLOAD:
                    // We calculate the adding time.
                    $age = time() - (int) $data->timeadded;
                    // For forums, let's calculate whether the editing time has passed?
                    if (!empty($forum) && $age < $CFG->maxeditingtime) {
                        $a = new stdClass();
                        $a->t = time();
                        // We calculate the time to complete editing.
                        $t = ((int) $data->timeadded + (int) $CFG->maxeditingtime) - $a->t;
                        $a->i = intval($t / 60);
                        $a->s = $t - $a->i * 60;
                        $a->t = date('H:i', $a->t + (int) $CFG->maxeditingtime);
                        $s = get_string('edit_time', 'plagiarism_advacheck', $a);
                        $output = get_html_block_info(get_string('edit_time', 'plagiarism_advacheck', $a), 'advacheck-green');
                    } else if (!$checkcap && $data->workshop != 0) {
                        // At the workshop, just like in the assignment, if the check limit for a student has not been reached, then we display a button.
                        if ((int) $data->stud_check >= (int) self::$cnfg->check_stud_lim) {
                            $output = get_html_block_info(get_string('stud_not_check', 'plagiarism_advacheck'), 'advacheck-green');
                        } else {
                            $c = (int) self::$cnfg->check_stud_lim - (int) $data->stud_check;
                            $output = get_html_block_info(
                                get_string('stud_check', 'plagiarism_advacheck', $c),
                                "advacheck-green stud_check-$typeid"
                            );
                            // Receive html buttons for sending for verification.
                            $output .= $this->get_check_button_html(
                                true,
                                $context,
                                $courseid,
                                $doctype,
                                $content,
                                $userid,
                                0,
                                0,
                                $typeid,
                                $data->id
                            );
                        }
                    } else { // If the editing time has expired and automatic checking is enabled, then display the corresponding information.
                        if (self::$cnfg->mode == ADVACHECK_AUTOMODE) {
                            $output = get_html_block_info(
                                get_string('wait_upload', 'plagiarism_advacheck', ''),
                                "advacheck-green check_notice-$typeid"
                            );
                        } else if (!$checkcap) {
                            // Information for students about the end of the check limit.
                            $output = get_html_block_info(
                                get_string('stud_not_check', 'plagiarism_advacheck'),
                                "advacheck-green check_notice-$typeid"
                            );
                        }
                        if ($checkcap) {
                            $output .= $this->get_check_button_html(
                                $checkcap,
                                $context,
                                $courseid,
                                $doctype,
                                $content,
                                $userid,
                                $assignment,
                                $discussion,
                                $typeid,
                                $data->id
                            );
                        }
                    }
                    break;
                // The document is in the process of being uploaded.
                case ADVACHECK_UPLOADING:
                    $output = get_html_block_info(get_string('uploading', 'plagiarism_advacheck'), 'advacheck-green');
                    break;
                // The document has been uploaded.
                case ADVACHECK_UPLOADED:
                    $output = get_html_block_info(get_string('uploaded', 'plagiarism_advacheck'), 'advacheck-green');
                    break;
                // The document is in the process of being reviewed.
                case ADVACHECK_CHECKING:
                    $output = html_writer::span(
                        get_string('checking', 'plagiarism_advacheck'),
                        'advacheck-green',
                        ['style' => 'display: inline-grid; font-size: 80%;']
                    );
                    // Animation of the verification process.
                    $output .= html_writer::img(
                        get_icn_advacheck('loader'),
                        get_string('checking', 'plagiarism_advacheck'),
                        ['class' => 'advacheck-loader checking_img', 'style' => 'width: 20px;']
                    );
                    break;
                // Insufficient number of words.
                case ADVACHECK_LESSNWORDS:
                    // Display a message at the time of queuing, because the number of words could have changed.
                    $output = get_html_block_info($data->error, 'advacheck-gray');
                    break;
                // There is no right to be verified - we don’t show anything, for example, a teacher’s answer on the forum.
                case ADVACHECK_NORIGHTCHECKEDBY:
                    $output = '';
                    break;
                case ADVACHECK_INVALIDFILETYPE:
                    $output = get_html_block_info(get_string('error_filetype', 'plagiarism_advacheck', $data->error), 'advacheck-red');
                    break;
                // Error when trying to upload.
                case ADVACHECK_ERROR_UPLOADING:
                    $output = get_html_block_info(get_string('error_upload', 'plagiarism_advacheck', $data->error), 'advacheck-red');
                    break;
                // Error when initiating document verification.
                case ADVACHECK_ERROR_CHECKING:
                    $output = get_html_block_info(get_string('error_checking', 'plagiarism_advacheck', $data->error), 'advacheck-red');
                    break;
                // An error occurred during the verification process.   
                case ADVACHECK_ERROR_CHECK:
                    $output = get_html_block_info(get_string('error_check', 'plagiarism_advacheck', $data->error), 'advacheck-red');
                    break;
                // Error when trying to get status.
                case ADVACHECK_ERROR_GET_STATUS:
                    $output = get_html_block_info(get_string('error_get_status', 'plagiarism_advacheck', $data->error), 'advacheck-red');
                    break;
                // When trying to get a report.
                case ADVACHECK_ERROR_GET_REPORT:
                    $output = get_html_block_info(get_string('error_get_report', 'plagiarism_advacheck', $data->error), 'advacheck-red');
                    break;
                // Error when trying to add/remove to index.
                case ADVACHECK_ERROR_INDEX:
                    $output = get_html_block_info(get_string('error_index', 'plagiarism_advacheck', $data->error), 'advacheck-red');
                    break;
                // In other cases, we display the results of the check.
                default:
                    $output = $this->get_result_html($data, $context);
                    break;
            }
        } else {
            // The case when the document is not added to the queue.
            // We show the message only to those who have the right to check documents.
            if ($checkcap) {
                $output = get_html_block_info(get_string('not_in_queue', 'plagiarism_advacheck'), 'advacheck-red');
            }
        }

        if ($output != '') {
            $output = html_writer::div($output);
        }

        return $output;
    }

    /**
     * Loads general plugin settings and validation settings for a given course module.
     * Adds JS to the page.
     *
     * @global moodle_page $PAGE
     * @global moodle_database $DB
     * @param int $cmid
     * @param int $courseid
     */
    private function _preLoad($cmid, $courseid)
    {
        global $PAGE, $DB;

        if (self::$plugin_cfg == null) {
            self::$plugin_cfg = get_config('plagiarism_advacheck');
        }

        if (self::$cnfg == null) {
            self::$cnfg = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $cmid, 'courseid' => $courseid]);
        }
        $PAGE->requires->js_call_amd('plagiarism_advacheck/check', 'addPlagiarismCtrlButtons');
    }

    /**
     * Collects the html code of the verification results.
     *
     * @global object $OUTPUT
     * @param stdClass $data Structure with test results.
     * @param context $context
     * @return string
     */
    private function get_result_html($data, $context)
    {
        global $OUTPUT, $USER;
        $output = '';

        $plagiarism = isset($data->plagiarism) ? $data->plagiarism : 0;
        $legal = isset($data->legal) ? $data->legal : 0;
        $selfcite = isset($data->selfcite) ? $data->selfcite : 0;

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

        $orig = 100 - $plagiarism - $legal - $selfcite;

        if ($orig == round($orig, 0)) {
            $orig = round($orig, 0);
        } else {
            $orig = round($orig, 2);
        }

        $report = "";
        $report_img = html_writer::img(get_icn_advacheck('report_link'), get_string('report', 'plagiarism_advacheck'));

        if (has_capability('plagiarism/advacheck:viewfullreport', $context)) {
            // If you have the right to view the full report
            if (!empty($data->reportedit)) {
                $report = html_writer::link(
                    self::$plugin_cfg->uri . $data->reportedit,
                    $report_img,
                    ['target' => '_blank', 'title' => get_string('report', 'plagiarism_advacheck')]
                );
            }
        } else if (
            // If you have the right to view the full report or report output mode = view the full report or work by the student checking in the workshop.
            has_capability('plagiarism/advacheck:viewfullreadreport', $context) || (int) self::$cnfg->disp_notices == 2 ||
            ($data->discussion == 0 && $data->assignment == 0 && $USER->id != $data->userid)
        ) {
            if (!empty($data->reportread)) {
                $report = html_writer::link(
                    self::$plugin_cfg->uri . $data->reportread,
                    $report_img,
                    ['target' => '_blank', 'title' => get_string('report', 'plagiarism_advacheck')]
                );
            }
        } else if (has_capability('plagiarism/advacheck:viewshortreport', $context) || (int) self::$cnfg->disp_notices == 1) {
            // If you have the right to view a short report or water notification mode = short report.
            if (!empty($data->shortreport)) {
                $report = html_writer::link(
                    self::$plugin_cfg->uri . $data->shortreport,
                    $report_img,
                    ['target' => '_blank', 'title' => get_string('report', 'plagiarism_advacheck')]
                );
            }
        }
        // For the html icon "refresh report".
        $update_report_div = '';
        if (has_capability('plagiarism/advacheck:updatereport', $context) && !empty($data->docidantplgt)) {
            $alt = get_string('updatereport', 'plagiarism_advacheck');
            $update_report = html_writer::img(get_icn_advacheck('refresh'), $alt);
            $update_report_btn = html_writer::link('#', $update_report, ['class' => "update_report", 'title' => $alt]);
            $update_report_div = html_writer::span(
                html_writer::span($data->typeid, 'typeid'),
                "advacheck-data"
            ) . $update_report_btn;
        }
        // For the html icon "Upload help".
        $download_btn = '';
        // If there is an “upload certificate” option for this brand.
        if (VIEW_CERTIFICATE) {
            $alt = get_string('downloadreport', 'plagiarism_advacheck');
            $download = html_writer::img(get_icn_advacheck('download'), $alt);
            $download_lnk = new moodle_url("/plagiarism/advacheck/downloadfile.php", ['userid' => $data->userid, 'docid' => $data->id]);
            $download_btn = html_writer::link($download_lnk, $download, ['class' => "download", 'title' => $alt]);
        }

        $plag_html = html_writer::span(get_string('plagiarism', 'plagiarism_advacheck'), 'advacheck-value-plag');
        $plag_html .= html_writer::span($plagiarism . '% ', "plagiarism-$data->typeid");

        $selfcite_html = html_writer::span(get_string('selfcite', 'plagiarism_advacheck'), 'advacheck-value-selfcite');
        $selfcite_html .= html_writer::span($selfcite . '% ', "selfcite-$data->typeid");

        $legal_html = html_writer::span(get_string('legal', 'plagiarism_advacheck'), 'advacheck-value-legal');
        $legal_html .= html_writer::span($legal . '% ', "legal-$data->typeid");

        $orig_html = html_writer::span(get_string('originality', 'plagiarism_advacheck'), 'advacheck-value-orig');
        $orig_html .= html_writer::span($orig . '% ', "originality-$data->typeid");

        if (!$data->issuspicious) {
            $cls = "$data->typeid advacheck-suspiciousoff";
        } else {
            $cls = "$data->typeid advacheck-suspiciouson";
        }
        $suspicious_img = html_writer::img(get_icn_advacheck('suspicious'), get_string('suspicious', 'plagiarism_advacheck'));
        $link = '';
        if (has_capability('plagiarism/advacheck:viewfullreport', $context)) {
            $link = self::$plugin_cfg->uri . $data->reportedit;
        } else if (
            has_capability('plagiarism/advacheck:viewfullreadreport', $context) || (int) self::$cnfg->disp_notices == 2 ||
            ($data->discussion == 0 && $data->assignment == 0 && $USER->id != $data->userid)
        ) {
            $link = self::$plugin_cfg->uri . $data->reportread;
        } else if (has_capability('plagiarism/advacheck:viewshortreport', $context) || (int) self::$cnfg->disp_notices == 1) {
            $link = self::$plugin_cfg->uri . $data->shortreport;
        }

        $suspicious_link = html_writer::link(
            $link,
            $suspicious_img,
            ['target' => '_blank', 'title' => get_string('suspicious', 'plagiarism_advacheck'), 'class' => "advacheck-suspicious_lnk $data->typeid"]
        );
        $suspicious_html = html_writer::span($suspicious_link, $cls);

        $res = $plag_html . ' ' . $selfcite_html . ' ' . $legal_html . ' ' . $orig_html . $report . ' ' . $update_report_div . ' ' . $download_btn . ' ' . $suspicious_html;

        $class = "advacheck $data->typeid";
        if ($orig >= self::$plugin_cfg->originality_limit) {
            $class .= " advacheck-green";
        } else {
            $class .= " advacheck-red";
        }
        $output = html_writer::div($res, $class) . $OUTPUT->help_icon('checkresult', 'plagiarism_advacheck');
        $output = html_writer::div($output);
        return $output;
    }

    /**
     * Collects the html code of the submit button for review.
     *
     * @param bool $checkcap
     * @param context $context
     * @param int $courseid
     * @param int $doctype
     * @param string $content
     * @param int $userid
     * @param int $assignment
     * @param int $discussion
     * @param string $hash
     * @return string
     */
    private function get_check_button_html(
        $checkcap,
        $context,
        $courseid,
        $doctype,
        $content,
        $userid,
        $assignment,
        $discussion,
        $typeid,
        $docid
    ) {
        global $OUTPUT;
        $output = '';
        $class_btn = "advacheck-checkbtn $typeid";

        $properties = ['class' => $class_btn, 'title' => get_string('check_advacheck', 'plagiarism_advacheck')];
        $checkBtn = "";
        if ($checkcap) {
            // For html button "Check".
            $icon_name = strtolower(ADVACHECK_BRANDNAME);
            $checkBtn = html_writer::link(
                '#',
                html_writer::img(
                    get_icn_advacheck($icon_name),
                    get_string('check_advacheck', 'plagiarism_advacheck')
                ),
                $properties
            );
            // For html button "report".
            $report = "";
            $report_img = html_writer::img(get_icn_advacheck('report_link'), get_string('report', 'plagiarism_advacheck'));
            if (has_capability('plagiarism/advacheck:viewshortreport', $context)) {
                if (has_capability('plagiarism/advacheck:checkadvacheck', $context)) {
                    $report = html_writer::link(
                        "#",
                        $report_img,
                        [
                            'class' => "advacheck-report-$typeid",
                            'target' => '_blank',
                            'title' => get_string(
                                'report',
                                'plagiarism_advacheck'
                            ),
                        ]
                    );
                } else {
                    $report = html_writer::link(
                        "#",
                        $report_img,
                        [
                            'class' => "advacheck-report-$typeid",
                            'target' => '_blank',
                            'title' => get_string(
                                'report',
                                'plagiarism_advacheck'
                            ),
                        ]
                    );
                }
            }
            // For html button "update report".
            $update_report_div = '';
            if (has_capability('plagiarism/advacheck:updatereport', $context)) {
                $alt = get_string('updatereport', 'plagiarism_advacheck');
                $update_report = html_writer::img(get_icn_advacheck('refresh'), $alt);
                $update_report_btn = html_writer::link('#', $update_report, ['class' => "update_report", 'title' => $alt]);
                $update_report_div = html_writer::span(
                    html_writer::span($typeid, 'typeid'),
                    "advacheck-data $typeid"
                ) . $update_report_btn;
            }

            // For the html icon "Upload help".
            $download_btn = '';
            // If there is an “upload certificate” option for this brand.
            if (VIEW_CERTIFICATE) {
                $alt = get_string('downloadreport', 'plagiarism_advacheck');
                $download = html_writer::img(get_icn_advacheck('download'), $alt);
                $download_lnk = new moodle_url("/plagiarism/advacheck/downloadfile.php", ['userid' => $userid, 'docid' => $docid]);
                $download_btn = html_writer::link($download_lnk, $download, ['class' => "download", 'title' => $alt]);
            }

            $plag_html = html_writer::span(get_string('plagiarism', 'plagiarism_advacheck'), 'advacheck-value-plag');
            $plag_html .= html_writer::span("-", "plagiarism-$typeid");

            $selfcite_html = html_writer::span(get_string('selfcite', 'plagiarism_advacheck'), 'advacheck-value-selfcite');
            $selfcite_html .= html_writer::span('-', "selfcite-$typeid");

            $legal_html = html_writer::span(get_string('legal', 'plagiarism_advacheck'), 'advacheck-value-legal');
            $legal_html .= html_writer::span('-', "legal-$typeid");

            $orig_html = html_writer::span(get_string('originality', 'plagiarism_advacheck'), 'advacheck-value-orig');
            $orig_html .= html_writer::span('-', "originality-$typeid");

            $suspicious_img = html_writer::img(get_icn_advacheck('suspicious'), get_string('suspicious', 'plagiarism_advacheck'));
            $suspicious_link = html_writer::link(
                '#',
                $suspicious_img,
                ['title' => get_string('suspicious', 'plagiarism_advacheck'), 'class' => "advacheck-suspicious_lnk $typeid", 'target' => '_blank']
            );
            $suspicious_html = html_writer::span($suspicious_link, "advacheck-suspicious-$typeid advacheck-hidden", ['style' => 'display:none']);
            // Blank with a percentage of originality, hidden style.
            $res = html_writer::div($plag_html . ' ' . $selfcite_html . ' ' . $legal_html . ' ' . $orig_html . " " . $report . ' '
                . $update_report_div . ' ' . $download_btn . ' ' . $suspicious_html, "advacheck $typeid advacheck-hidden", ['style' => "display:none"]);

            $help = html_writer::span($OUTPUT->help_icon('checkresult', 'plagiarism_advacheck'), "advacheck_help $typeid");
            // Let's clear the content from tags in order to add it to the page in a hidden form, so that when the button is clicked, it is sent for verification.
            if (!isset($content)) {
                $content = '';
            }
            // Let's add the metadata necessary to send a document for review from the button.
            $output = html_writer::div(
                html_writer::div(
                    html_writer::span($courseid, 'courseid') .
                    html_writer::span($doctype, 'doctype') .
                    html_writer::span($content, 'content') .
                    html_writer::span($userid, 'userid') .
                    html_writer::span($assignment, 'assignment') .
                    html_writer::span($discussion, 'discussion') .
                    html_writer::span($typeid, 'typeid'),
                    "advacheck-data $typeid"
                ) .
                html_writer::span(get_string('checking', 'plagiarism_advacheck'), "advacheck-data advacheck-green checking $typeid") .
                html_writer::img(
                    get_icn_advacheck('loader'),
                    get_string('checking', 'plagiarism_advacheck'),
                    ['class' => "advacheck-loader $typeid advacheck-hidden", 'style' => "display:none; width: 20px;"]
                ) .
                $res . $help .
                $checkBtn,
                "advacheck $typeid"
            );
        }
        return $output;
    }
}

/**
 * Hook to add plagiarism specific settings to a module settings page
 *
 * @param moodleform $formwrapper
 * @param MoodleQuickForm $mform
 */
function plagiarism_advacheck_coursemodule_standard_elements($formwrapper, $mform)
{
    global $DB, $COURSE, $CFG, $ADVACHECK_WORKS_TYPES;

    $plugin_cfg = get_config('plagiarism_advacheck');

    if (!$plugin_cfg->enabled) {
        return;
    }

    $cmid = null;
    if ($cm = $formwrapper->get_coursemodule()) {
        $cmid = $cm->id;
    }

    $courseid = $formwrapper->get_course()->id;

    $courseid = $courseid ? $courseid : $COURSE->id;
    $coursecontext = context_course::instance($courseid, MUST_EXIST);

    $hasCapability = has_capability('plagiarism/advacheck:manage', $coursecontext);

    // Check in which modules we have document checking enabled.
    $allowmodules = [];

    $matches = [];
    preg_match('/^mod_([^_]+)_mod_form$/', get_class($formwrapper), $matches);
    $modulename = "mod_" . $matches[1];

    if (!empty($plugin_cfg->check_forum)) {
        $allowmodules[] = "mod_forum";
    }
    if (!empty($plugin_cfg->check_assign)) {
        $allowmodules[] = "mod_assign";
    }

    if (!empty($plugin_cfg->check_workshop)) {
        $allowmodules[] = "mod_workshop";
    }

    if ($CFG->version >= 2021051700) {
        if (!empty($plugin_cfg->check_quiz)) {
            $allowmodules[] = "mod_quiz";
        }
    }

    if (in_array($modulename, $allowmodules) && $hasCapability) {
        // Header of the settings block for checking in Anti-plagiarism.
        $mform->addElement(
            'header',
            'plugin_header',
            get_string('coursesettings', 'plagiarism_advacheck')
        );

        // Check mode.
        $options = [
            ADVACHECK_DISABLEDMODE => get_string('disabledmode', 'plagiarism_advacheck'),
            ADVACHECK_MANUALMODE => get_string('manualmode', 'plagiarism_advacheck'),
            ADVACHECK_AUTOMODE => get_string('automode', 'plagiarism_advacheck'),
        ];
        $mod_settings = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $cmid]);

        $mform->addElement('select', 'advacheck_mode', get_string('mode', 'plagiarism_advacheck'), $options);

        $mform->addElement(
            'advcheckbox',
            'advacheck_checktext',
            get_string('checktext', 'plagiarism_advacheck'),
            get_string('enable', 'plagiarism_advacheck'),
            [],
            [0, 1]
        );
        $mform->disabledIf('advacheck_checktext', 'advacheck_mode', 'eq', 0);

        $mform->addElement(
            'advcheckbox',
            'advacheck_checkfile',
            get_string('checkfile', 'plagiarism_advacheck'),
            get_string('enable', 'plagiarism_advacheck'),
            [],
            [0, 1]
        );
        $mform->disabledIf('advacheck_checkfile', 'advacheck_mode', 'eq', 0);

        // Add to index.
        $mform->addElement('selectyesno', 'add_to_index', get_string('add_to_index', 'plagiarism_advacheck'));
        $mform->disabledIf('add_to_index', 'advacheck_mode', 'eq', 0);
        $mform->addHelpButton('add_to_index', 'add_to_index_info', 'plagiarism_advacheck');
        $add_to_index = isset($mod_settings->add_to_index) ? $mod_settings->add_to_index : 1;
        $mform->setDefault('add_to_index', $add_to_index);

        $options_notes = [
            0 => get_string('not_display', 'plagiarism_advacheck'),
            1 => get_string('display_short', 'plagiarism_advacheck'),
            2 => get_string('display_full', 'plagiarism_advacheck'),
        ];
        // Mode for displaying test results.
        $mform->addElement('select', 'disp_notices', get_string('disp_notices', 'plagiarism_advacheck'), $options_notes);
        $mform->disabledIf('disp_notices', 'advacheck_mode', 'eq', 0);
        // Either as set in the general settings of the plugin or output as a short report.
        $disp_notices_default = isset($plugin_cfg->disp_notices_default) ? $plugin_cfg->disp_notices_default : 1;
        $disp_notices = isset($mod_settings->disp_notices) ? $mod_settings->disp_notices : $disp_notices_default;
        $mform->setDefault('disp_notices', $disp_notices);

        // In an assignment/workshop, there is a limit for checking drafts for a student.
        if ($modulename != 'mod_forum' && $modulename != 'mod_quiz') {
            $mform->addElement(
                'select',
                'check_stud_lim',
                get_string('check_stud_lim', 'plagiarism_advacheck'),
                [
                    0 => '0',
                    1 => '1',
                    2 => '2',
                    3 => '3',
                    5 => '5',
                    7 => '7',
                    10 => '10',
                ]
            );
            $mform->disabledIf('check_stud_lim', 'advacheck_mode', 'eq', 0);
            // By default, either from the general plugin settings or 0.
            $check_stud_lim_default_global = isset($plugin_cfg->check_stud_lim_default) ? $plugin_cfg->check_stud_lim_default : 0;
            $check_stud_lim_default = isset($mod_settings->check_stud_lim) ? $mod_settings->check_stud_lim : $check_stud_lim_default_global;
            $mform->setDefault('check_stud_lim', $check_stud_lim_default);
        }
        // Type of document being checked.
        $mform->addElement('select', 'works_types', get_string('works_types', 'plagiarism_advacheck'), $ADVACHECK_WORKS_TYPES);
        $mform->disabledIf('works_types', 'advacheck_mode', 'eq', 0);
        if (isset($mod_settings->works_types)) {
            $mform->setDefault('works_types', $mod_settings->works_types);
        }

        if ($mod_settings) {
            $mform->setDefault('advacheck_mode', $mod_settings->mode);
            if ($mod_settings->mode > 0) {
                $mform->setDefault('advacheck_checktext', $mod_settings->checktext);
                $mform->setDefault('advacheck_checkfile', $mod_settings->checkfile);
            }
        }
    }
}

/**
 * Hook to save plagiarism specific settings on a module settings page
 *
 * @param stdClass $data
 * @param stdClass $course
 */
function plagiarism_advacheck_coursemodule_edit_post_actions($data, $course)
{
    global $DB;
    $plugin_cfg = get_config('plagiarism_advacheck');

    if (!$plugin_cfg->enabled) {
        return $data;
    }

    $allowmodules = [];
    $instance = $data->instance;
    $modulename = $data->modulename;
    $cmid = $data->coursemodule;
    $courseid = $data->course;
    if (!empty($plugin_cfg->check_forum)) {
        $allowmodules[] = "forum";
    }
    if (!empty($plugin_cfg->check_assign)) {
        $allowmodules[] = "assign";
    }
    if (!empty($plugin_cfg->check_workshop)) {
        $allowmodules[] = "workshop";
    }
    if (!empty($plugin_cfg->check_quiz)) {
        $allowmodules[] = "quiz";
    }

    $coursecontext = context_course::instance($data->course, MUST_EXIST);
    $hasCapability = has_capability('plagiarism/advacheck:manage', $coursecontext);

    if ($hasCapability && in_array($data->modulename, $allowmodules)) {
        $row = new stdClass();
        $row->courseid = $data->course;
        $row->cmid = $data->coursemodule;
        $row->mode = 0;
        $row->checktext = 0;
        $row->checkfile = 0;

        if (isset($data->advacheck_mode)) {
            $row->mode = $data->advacheck_mode;
        }

        if (isset($data->advacheck_checktext)) {
            $row->checktext = $data->advacheck_checktext;
        }

        if (isset($data->advacheck_checkfile)) {
            $row->checkfile = $data->advacheck_checkfile;
        }

        if (isset($data->check_stud_lim)) {
            $row->check_stud_lim = $data->check_stud_lim;
        }

        if (isset($data->add_to_index)) {
            $row->add_to_index = $data->add_to_index;
        }

        if (isset($data->disp_notices)) {
            $row->disp_notices = $data->disp_notices;
        }

        if (isset($data->works_types)) {
            $row->works_types = $data->works_types;
        }

        if ($r = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $row->courseid, 'cmid' => $row->cmid])) {
            $row->id = $r->id;
            $DB->update_record('plagiarism_advacheck_course', $row);
        } else {
            $DB->insert_record('plagiarism_advacheck_course', $row);
        }
        // Adding answers to the queue if checking was enabled after students added answers.
        if (!empty($data->advacheck_mode)) {
            $modcontext = context_module::instance($cmid);
            if ($modulename == 'forum') {
                $posts = $DB->get_record_sql("SELECT COUNT(id) AS cnt FROM {forum_discussions} fd WHERE fd.forum = $instance");
                $docs = $DB->get_record_sql("SELECT COUNT(id) AS cnt FROM {plagiarism_advacheck_docs} d WHERE d.cmid = $cmid");
                if ($posts->cnt != $docs->cnt) {
                    unset($posts);
                    $sql = "SELECT
                    fp.id,
                    fp.message,
                    fp.userid,
                    fp.attachment,
                    fp.discussion,
                    fp.modified AS time
                FROM  {forum_posts} fp
                    INNER JOIN {forum_discussions} fd ON fp.discussion = fd.id
                WHERE fd.forum = $instance";
                    $posts = $DB->get_records_sql($sql);
                    foreach ($posts as $p) {
                        if (empty($p->message)) {
                            continue;
                        }
                        if (!empty($data->advacheck_checktext)) {
                            add_to_queue_forum_text($coursecontext, $modcontext, $plugin_cfg, $p, $courseid, $cmid);
                        }
                        if (!empty($data->advacheck_checkfile) && !empty($p->attachment)) {
                            $fs = get_file_storage();
                            $files = $fs->get_area_files($modcontext->id, 'mod_forum', 'attachment', $p->id);
                            add_to_queue_file($files, $p, $modulename, $coursecontext, $courseid, $cmid);
                        }
                    }
                }
            } else if ($modulename == "assign") {
                unset($subs);
                $sql = "SELECT
                    sub.id,
                    sub_text.onlinetext AS txt,
                    sub.userid,
                    sub.assignment AS assign,
                    sub.status,
                    sub_file.numfiles,
                    sub.timemodified AS time
                FROM {assign_submission} sub
                    LEFT JOIN {assignsubmission_onlinetext} sub_text ON sub.id = sub_text.submission
                    LEFT JOIN {assignsubmission_file} sub_file ON sub.id = sub_file.submission
                WHERE sub.assignment = $instance";
                $subs = $DB->get_records_sql($sql);
                foreach ($subs as $s) {
                    if (empty($s->txt)) {
                        continue;
                    }
                    if (!empty($data->advacheck_checktext)) {
                        add_to_queue_assign_text($coursecontext, $modcontext, $plugin_cfg, $s, $courseid, $cmid);
                    }
                    if (!empty($data->advacheck_checkfile) && !empty($s->numfiles)) {
                        $fs = get_file_storage();
                        $files = $fs->get_area_files($modcontext->id, 'assignsubmission_file', ASSIGNSUBMISSION_FILE_FILEAREA, $s->id);
                        add_to_queue_file($files, $s, $modulename, $coursecontext, $courseid, $cmid);
                    }
                }
            } else if ($modulename == 'workshop') {
                $sql = "SELECT
                    sub.id,
                    sub.content AS txt,
                    sub.authorid AS userid,
                    sub.workshopid,
                    sub.published,
                    sub.timemodified AS time
                FROM {workshop_submissions} sub
                WHERE sub.workshopid = $instance";
                $subs = $DB->get_records_sql($sql);
                foreach ($subs as $s) {
                    if (empty($s->txt)) {
                        continue;
                    }
                    if (!empty($data->advacheck_checktext)) {
                        add_to_queue_workshop_text($coursecontext, $modcontext, $plugin_cfg, $s, $courseid, $cmid);
                    }
                    if (!empty($data->advacheck_checkfile)) {
                        $fs = get_file_storage();
                        $files = $fs->get_area_files($modcontext->id, 'mod_workshop', 'submission_attachment', $s->id);
                        if (count($files) != 0) {
                            add_to_queue_file($files, $s, $modulename, $coursecontext, $courseid, $cmid);
                        }
                    }
                }
            } else if ($modulename == 'quiz') {
                if (!empty($data->advacheck_checktext)) {
                    $sql = "SELECT DISTINCT qas.id, qa.responsesummary as txt, qa.timemodified as time, qas.userid
                        FROM {question_attempts} qa 
                        JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                        JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND asd.name = 'answer'
                        JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                        JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                        WHERE quiza.quiz = $instance";
                    $attempts_t = $DB->get_records_sql($sql);
                    foreach ($attempts_t as $at) {
                        if (empty($at->txt)) {
                            continue;
                        }
                        add_to_queue_quiz_text($coursecontext, $modcontext, $plugin_cfg, $at, $courseid, $cmid);
                    }
                }
                if (!empty($data->advacheck_checkfile)) {
                    $sql = "SELECT DISTINCT qas.id, qa.responsesummary as txt, qa.timemodified as time, qas.userid
                        FROM {question_attempts} qa 
                        JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                        JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND asd.name = 'attachments'
                        JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                        JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                        WHERE quiza.quiz = $instance";
                    $attempts_f = $DB->get_records_sql($sql);
                    foreach ($attempts_f as $af) {
                        $attempt_files = get_file_storage()->get_area_files($modcontext->id, 'question', 'response_attachments', $af->id);
                        if (count($attempt_files) != 0) {
                            add_to_queue_file($attempt_files, $af, $modulename, $coursecontext, $courseid, $cmid);
                        }
                    }
                }
            }
        }
    }

    return $data;
}

/**
 * Turn on/off the module for different versions of moodle
 *
 * @global stdClass $CFG
 * @param bool $set_get true- get; false - write down.
 * @param int $val on/off value
 * @return mixed int | bool
 */
function enable_plug($set_get, $val = 0)
{
    global $CFG;
    if ($set_get) {
        if ($CFG->version < 2020061500) {
            return get_config('plagiarism', 'advacheck_use');
        }
        return get_config('plagiarism_advacheck', 'enabled');
    } else {
        if ($CFG->version < 2020061500) {
            set_config('advacheck_use', $val, 'plagiarism');
        }
        set_config('enabled', $val, 'plagiarism_advacheck');
    }
}

/**
 * Returns html labels with info with an error or explanation.
 *
 * @param string $string
 * @param string $class
 * @return string
 */
function get_html_block_info($string, $class)
{
    $res = html_writer::div($string);
    $class = "advacheck " . $class;
    return html_writer::div(html_writer::div($res), $class);
}

/**
 * Adds a link to the navigation block.
 *
 * @param object $navigation
 * @param stdClass $course
 * @param context $context
 */
function plagiarism_advacheck_extend_navigation_course($navigation, $course, $context)
{
    $antiplugiat_enable = enable_plug(true);
    if (has_capability('plagiarism/advacheck:manage', $context) && $antiplugiat_enable) {
        $url = new moodle_url('/plagiarism/advacheck/coursesettings/', ['courseid' => $course->id]);
        $navigation->add(
            get_string('coursesettings', 'plagiarism_advacheck'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Creates a view in the database for working with the event log.
 */
function add_log_view()
{
    global $DB, $CFG;

    delete_view();

    $field1 = get_string_manager()->get_string("field1", 'plagiarism_advacheck', null, $CFG->lang);
    $field2 = get_string_manager()->get_string("field2", 'plagiarism_advacheck', null, $CFG->lang);
    $field3 = get_string_manager()->get_string("field3", 'plagiarism_advacheck', null, $CFG->lang);
    $field4 = get_string_manager()->get_string("field4", 'plagiarism_advacheck', null, $CFG->lang);
    $field5 = get_string_manager()->get_string("field5", 'plagiarism_advacheck', null, $CFG->lang);
    $field6 = get_string_manager()->get_string("field6", 'plagiarism_advacheck', null, $CFG->lang);
    $field7 = get_string_manager()->get_string("field7", 'plagiarism_advacheck', null, $CFG->lang);
    $field8 = get_string_manager()->get_string("field8", 'plagiarism_advacheck', null, $CFG->lang);

    $doctype1 = get_string_manager()->get_string("doctype1", 'plagiarism_advacheck', null, $CFG->lang);
    $doctype2 = get_string_manager()->get_string("doctype2", 'plagiarism_advacheck', null, $CFG->lang);
    $doctype3 = get_string_manager()->get_string("doctype3", 'plagiarism_advacheck', null, $CFG->lang);
    $doctype4 = get_string_manager()->get_string("doctype4", 'plagiarism_advacheck', null, $CFG->lang);
    $doctype5 = get_string_manager()->get_string("doctype5", 'plagiarism_advacheck', null, $CFG->lang);

    // Depending on the subdatabase, we will select a view.
    if ($CFG->dbtype == 'pgsql') {
        $view = "CREATE VIEW {plagiarism_advacheck_log_view}  AS
                SELECT
               al.id AS id,
               al.docid AS docsid,
               (
                   CASE d.doctype WHEN 1 THEN '$doctype1' WHEN 2 THEN '$doctype2' WHEN 3 THEN '$doctype3' WHEN 4 THEN '$doctype4' WHEN 5 THEN '$doctype5'
               END
           ) AS $field1,
           d.doctype AS doctypeid,
           a.action_name AS $field2,
           al.status AS docstatus,
           a.id AS eventid,
           to_timestamp(al.time_action) AS $field3,
           c.fullname AS $field4,
           c.id AS courseid,
           COALESCE(cs.name, '-') AS $field5,
           (
               SELECT
                   f.name
               FROM
                   {forum} f
               WHERE
                   f.id = cm.instance AND cm.module =(
                   SELECT
                       id
                   FROM
                       {modules}
                   WHERE NAME
                       = 'forum'
               )
           UNION
           SELECT
               ass.name
           FROM
               {assign} ass
           WHERE
               ass.id = cm.instance AND cm.module =(
               SELECT
                   id
               FROM
                   {modules}
               WHERE NAME
                   = 'assign'
           )
           ) AS $field6,
           d.cmid AS cmid,
           d.discussion AS discussion,
           (
               CASE
                   WHEN al.assignment = 0 THEN '-'
                   ELSE(
                       SELECT
                           CAST( (attemptnumber + 1) AS CHAR)
                       FROM
                           {assign_submission}
                       WHERE
                           id = al.answerid
                   )
               END
           ) AS attemptnumber,
           d.answerid AS answerid,
           al.verifier_initiator AS $field7,
           CONCAT(stu.lastname, ' ', stu.firstname) AS $field8,
           al.reportedit AS ap_report,
           al.docidantplgt AS ap_docid,
           d.error AS ap_error
           FROM
               {plagiarism_advacheck_act_log} al
           LEFT JOIN {plagiarism_advacheck_action} a ON
               al.action_id = a.id
           LEFT JOIN {course} c ON
               al.courseid = c.id
           LEFT JOIN {course_modules} cm ON
               al.cmid = cm.id
           LEFT JOIN {course_sections} cs ON
               cm.section = cs.id
           LEFT JOIN {user} stu ON
               al.userid = stu.id
           LEFT JOIN {plagiarism_advacheck_docs} d ON
               al.docid = d.id
           ORDER BY al.id DESC";
    } else if ($CFG->dbtype == 'sqlsrv') {
        // We get the time zone offset.
        $t = date('Y-m-d h:i:s', 0);
        $view = "CREATE VIEW {plagiarism_advacheck_log_view}  AS
                SELECT
                    al.id AS id,
                    al.docid AS docsid,
                    (
                        CASE d.doctype WHEN 1 THEN '$doctype1' WHEN 2 THEN '$doctype2' WHEN 3 THEN '$doctype3' WHEN 4 THEN '$doctype4' WHEN 5 THEN '$doctype5'
                    END
                ) AS $field1,
                d.doctype AS doctypeid,
                a.action_name AS $field2,
                al.status AS docstatus,
                a.id AS eventid,
                (SELECT DATEADD(second,al.time_action,'$t')) AS $field3,
                c.fullname AS $field4,
                c.id AS courseid,
                ISNULL(cs.name, '-') AS $field5,
                (
                    SELECT
                        TOP 1
                        f.name
                    FROM
                        {forum} f
                    WHERE
                        f.id = cm.instance AND cm.module =(
                        SELECT
                            id
                        FROM
                           {modules}
                        WHERE NAME
                            = 'forum'
                    )
                    UNION
                    SELECT
                        TOP 1
                        ass.name
                    FROM
                        {assign} ass
                    WHERE
                        ass.id = cm.instance AND cm.module IN (
                        SELECT
                            id
                        FROM
                            {modules}
                        WHERE NAME
                            = 'assign'
                    )
                ) AS $field6,

                d.cmid AS cmid,
                d.discussion AS discussion,
                (
                    CASE al.assignment WHEN 0 THEN '-' ELSE(
                    SELECT
                        attemptnumber + 1
                    FROM
                        {assign_submission}
                    WHERE
                        id = al.answerid
                )
                END
                ) AS attemptnumber,
                d.answerid AS answerid,
                al.verifier_initiator AS $field7,
                ISNULL(stu.lastname+ ' ','')  + ISNULL(stu.firstname,'') AS $field8,
                -------------------------------------------------------
                al.reportedit AS ap_report,
                al.docidantplgt AS ap_docid,
                d.error AS ap_error
                FROM
                    {plagiarism_advacheck_act_log} al
                LEFT JOIN {plagiarism_advacheck_action} a ON
                    al.action_id = a.id
                LEFT JOIN {course} c ON
                    al.courseid = c.id
                LEFT JOIN {course_modules} cm ON
                    al.cmid = cm.id
                LEFT JOIN {course_sections} cs ON
                    cm.section = cs.id
                LEFT JOIN {user} stu ON
                    al.userid = stu.id
                LEFT JOIN {plagiarism_advacheck_docs} d ON
                    al.docid = d.id";
    } else {
        // For mysql.
        $view = "CREATE VIEW {plagiarism_advacheck_log_view}  AS
             SELECT
            al.id AS id,
            al.docid AS docsid,
            (
                CASE d.doctype WHEN 1 THEN '$doctype1' WHEN 2 THEN '$doctype2' WHEN 3 THEN '$doctype3' WHEN 4 THEN '$doctype4' WHEN 5 THEN '$doctype5'
            END
        ) AS $field1,
        d.doctype AS doctypeid,
        a.action_name AS $field2,
        al.status AS docstatus,
        a.id AS eventid,
        DATE_FORMAT(
            FROM_UNIXTIME(al.time_action),
            '%d:%m:%Y %H:%i:%s'
        ) AS $field3,
        c.fullname AS $field4,
        c.id AS courseid,
        IFNULL(cs.name, '-') AS $field5,
        (
            SELECT
                f.name
            FROM
               {forum} f
            WHERE
                f.id = cm.instance AND cm.module =(
                SELECT
                    id
                FROM
                    {modules}
                WHERE NAME
                    = 'forum'
            )
        UNION
        SELECT
            ass.name
        FROM
            {assign} ass
        WHERE
            ass.id = cm.instance AND cm.module =(
            SELECT
                id
            FROM
                {modules}
            WHERE NAME
                = 'assign'
        )
        ) AS $field6,
        d.cmid AS cmid,
        d.discussion AS discussion,
        (
            CASE al.assignment WHEN 0 THEN '-' ELSE(
            SELECT
                attemptnumber + 1
            FROM
                {assign_submission}
            WHERE
                id = al.answerid
        )
        END
        ) AS attemptnumber,
        d.answerid AS answerid,
        al.verifier_initiator AS $field7,
        CONCAT(stu.lastname, ' ', stu.firstname) AS $field8,
        al.reportedit AS ap_report,
        al.docidantplgt AS ap_docid,
        d.error AS ap_error
        FROM
            {plagiarism_advacheck_act_log} al
        LEFT JOIN {plagiarism_advacheck_action} a ON
           al.action_id =  a.id
        LEFT JOIN {course} c ON
            al.courseid = c.id
        LEFT JOIN {course_modules} cm ON
            al.cmid = cm.id
        LEFT JOIN {course_sections} cs ON
            cm.section = cs.id
        LEFT JOIN {user} stu ON
            al.userid = stu.id
        LEFT JOIN {plagiarism_advacheck_docs} d ON
            al.docid = d.id
        ORDER BY al.id DESC";
    }

    $DB->execute($view);
}

function delete_view()
{
    global $DB, $CFG;

    if ($CFG->dbtype == 'sqlsrv') {
        $sql = "SELECT OBJECT_ID('{plagiarism_advacheck_log_view}')AS exist";
        $view = $DB->get_record_sql($sql);
        if ($view->exist) {
            $delete_view = "DROP VIEW {plagiarism_advacheck_log_view}";
            $DB->execute($delete_view);
        }
    } else {
        $delete_view = "DROP VIEW IF EXISTS {plagiarism_advacheck_log_view}";
        $DB->execute($delete_view);
    }
}
