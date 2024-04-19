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
 * @copyright © 2023 onwards Advacheck OU
 * @copyright based on work by 1999 Martin Dougiamas {@link http://moodle.com}
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_submitted', // Submitting an essay in a test for verification.
        'callback' => '\plagiarism_advacheck\observer::mod_quiz_assessable_uploaded',
    ],
    [
        'eventname' => '\mod_workshop\event\assessable_uploaded', // Sending a response from the seminar for verification.
        'callback' => '\plagiarism_advacheck\observer::mod_workshop_assessable_uploaded',
    ],
    [
        'eventname' => '\mod_forum\event\assessable_uploaded', // Sending a response from the forum for review.
        'callback' => '\plagiarism_advacheck\observer::mod_forum_assessable_uploaded',
    ],
    [
        'eventname' => '\assignsubmission_file\event\assessable_uploaded', // Uploading a file in a task.
        'callback' => '\plagiarism_advacheck\observer::assignsubmission_file_assessable_uploaded',
    ],
    [
        'eventname' => '\assignsubmission_onlinetext\event\assessable_uploaded', // Loading a text answer in a task.
        'callback' => '\plagiarism_advacheck\observer::assignsubmission_onlinetext_assessable_uploaded',
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted', // After confirmation of sending for verification.
        'callback' => '\plagiarism_advacheck\observer::mod_assign_assessable_submitted',
    ],
    [
        'eventname' => '\mod_assign\event\submission_locked', // The answer is blocked by the teacher.
        'callback' => '\plagiarism_advacheck\observer::mod_assign_submission_locked',
    ],
    [
        'eventname' => '\mod_assign\event\submission_unlocked', // The answer is unlocked by the teacher.
        'callback' => '\plagiarism_advacheck\observer::mod_assign_submission_unlocked',
    ],
];
