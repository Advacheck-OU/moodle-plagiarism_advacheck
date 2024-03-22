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
 * A file with constants
 * @package  plagiarism
 * @subpackage advacheck
 * @copyright © 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
// Developer's certificate from the Advacheck (AP). Without it, the server will not accept the document.
define('DeveloperID', 'b5a00098-9691-494a-b56b-b1a3e846c3c3');
// Test modes in classes.
// 2 - Check in automatic mode AUTO.
define('ADVACHECK_AUTOMODE', 2);
// 1 - Check in MANUAL mode.
define('ADVACHECK_MANUALMODE', 1);
// 0 - Disable the DISABLED check.
define('ADVACHECK_DISABLEDMODE', 0);
// The brand name of the loan search system.
//define('ADVACHECK_BRANDNAME', 'Advacheck');
//define('ADVACHECK_BRANDNAME', 'eAarjav');
define('ADVACHECK_BRANDNAME', 'Advacheck');
// The upload help option. Not all brands have this option.
define('VIEW_CERTIFICATE', 0);
// To check before unloading.
// Allowed file types
$ADVACHECK_ALLOW_FILE_TYPES = get_config('plagiarism_advacheck', 'allow_file_types') . ',';
$ADVACHECK_ALLOW_FILE_TYPES = str_replace(' ', '', mb_strtolower($ADVACHECK_ALLOW_FILE_TYPES));
define('ADVACHECK_ALLOW_FILE_TYPES', $ADVACHECK_ALLOW_FILE_TYPES);

// A global array of identifier strings for valid document types.
global $ADVACHECK_WORKS_TYPES;
$ADVACHECK_WORKS_TYPES = [
    "none" => get_string('none', 'plagiarism_advacheck'),
    "Abstract" => get_string('abstract', 'plagiarism_advacheck'),
    "Article" => get_string('article', 'plagiarism_advacheck'),
    "Book" => get_string('book', 'plagiarism_advacheck'),
    "CandidateDissertation" => get_string('candidatedissertation', 'plagiarism_advacheck'),
    "CandidateDissertationAbstract" => get_string('candidatedissertationabstract', 'plagiarism_advacheck'),
    "CollectionOfExercises" => get_string('collectionofexercises', 'plagiarism_advacheck'),
    "CollectionOfWorks" => get_string('collectionofworks', 'plagiarism_advacheck'),
    "Coursework" => get_string('coursework', 'plagiarism_advacheck'),
    "DoctoralDissertation" => get_string('doctoraldissertation', 'plagiarism_advacheck'),
    "DoctoralDissertationAbstract" => get_string('doctoraldissertationabstract', 'plagiarism_advacheck'),
    "EducationalVisualEdition" => get_string('educationalvisualedition', 'plagiarism_advacheck'),
    "FinalQualifyingWork" => get_string('finalqualifyingwork', 'plagiarism_advacheck'),
    "GraduateWork" => get_string('graduatework', 'plagiarism_advacheck'),
    "Guidelines" => get_string('guidelines', 'plagiarism_advacheck'),
    "LaboratoryPractice" => get_string('laboratorypractice', 'plagiarism_advacheck'),
    "MasterDissertation" => get_string('masterdissertation', 'plagiarism_advacheck'),
    "MethodicalInstructions" => get_string('methodicalinstructions', 'plagiarism_advacheck'),
    "Monograph" => get_string('monograph', 'plagiarism_advacheck'),
    "Other" => get_string('other', 'plagiarism_advacheck'),
    "PracticalWork" => get_string('practicalwork', 'plagiarism_advacheck'),
    "PracticeReport" => get_string('practicereport', 'plagiarism_advacheck'),
    "ResearchReport" => get_string('researchreport', 'plagiarism_advacheck'),
    "ScientificQualificationWork" => get_string('scientificqualificationwork', 'plagiarism_advacheck'),
    "TeachingGuide" => get_string('teachingguide', 'plagiarism_advacheck'),
    "Textbook" => get_string('teachingguide', 'plagiarism_advacheck'),
    "Tutorial" => get_string('textbook', 'plagiarism_advacheck'),
    "DoctoralDissertationPhD" => get_string('tutorial', 'plagiarism_advacheck'),
    "DoctoralDissertationAbstractPhD" => get_string('doctoraldissertationabstractphd', 'plagiarism_advacheck'),
    "DiplomaProject" => get_string('diplomaproject', 'plagiarism_advacheck'),
];
// Checking/unloading status.
// Response text or file not found.
define('ADVACHECK_NOTFOUND', 0);
// The response text or file was not found.
define('ADVACHECK_WAITBLOCK', 1);
// It is waiting to be uploaded to the AP.
define('ADVACHECK_WAITUPLOAD', 2);
// Uploaded to the AP.
define('ADVACHECK_UPLOADED', 3);
// Uploaded and verified in AP.
define('ADVACHECK_CHECKED', 4);
// Loaded in the AP index.
define('ADVACHECK_ININDEX', 5);
// In the process of loading and waiting for verification results.
define('ADVACHECK_UPLOADING', 6);
// In the process of verification.
define('ADVACHECK_CHECKING', 7);
// Text less than N words.
define('ADVACHECK_LESSNWORDS', -1);
// Invalid file type.
define('ADVACHECK_INVALIDFILETYPE', -2);
// Invalid file type.
define('ADVACHECK_NORIGHTCHECKEDBY', -3);
// Verification error in AP, as a verification status.
define('ADVACHECK_ERROR_CHECK', -4);

// Error constants.
// Error while trying to download.
define('ADVACHECK_ERROR_UPLOADING', -101);
// An error occurred while attempting to initiate a scan.
define('ADVACHECK_ERROR_CHECKING', -102);
// There was an error trying to get the verification status.
define('ADVACHECK_ERROR_GET_STATUS', -103);
// There was an error trying to get the scan report.
define('ADVACHECK_ERROR_GET_REPORT', -104);
// Error when trying to add/remove to index.
define('ADVACHECK_ERROR_INDEX', -105);

// Types of documents being checked.
// Text in the task.
define('ADVACHECK_ASSIGN', 3);
// Text in the forum.
define('ADVACHECK_FORUM', 2);
// File.
define('ADVACHECK_FILE', 1);
// The answer is in the workshop.
define('ADVACHECK_WORKSHOP', 4);
// The answer is ecce in the quiz.
define('ADVACHECK_QUIZ', 5);
