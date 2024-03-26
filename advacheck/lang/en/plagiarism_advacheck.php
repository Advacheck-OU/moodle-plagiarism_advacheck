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

require_once ("$CFG->dirroot/plagiarism/advacheck/constants.php");

$string['pluginfullname'] = 'Moodle-' . ADVACHECK_BRANDNAME . ' Distance Learning Integration Module';
$string['pluginname'] = 'Moodle-' . ADVACHECK_BRANDNAME . ' Module';
$string['advacheck'] = 'Moodle-' . ADVACHECK_BRANDNAME . ' Module';
$string['useadvacheck'] = 'Enable the Moodle-' . ADVACHECK_BRANDNAME . ' Module';
$string['savedconfigsuccess'] = 'Settings saved';
$string['settings1'] = '' . ADVACHECK_BRANDNAME . ' System connection parameters';
$string['settings2'] = 'Parameters for checking';
$string['coursesettings'] = '' . ADVACHECK_BRANDNAME . ' settings';
$string['copy_descript'] = '<p>This tab is designed for copying test results from the Russian Moodle plugin into the Moodle-' . ADVACHECK_BRANDNAME . ' plugin</p> <br><p>If you have assignments where the parameter "Require answer locking for checking" is set to "No", before starting the copying process, set the default value for the parameter "Draft check limit for students" > 0 in the settings of the "Moodle-' . ADVACHECK_BRANDNAME . '" plugin, allowing students to check their drafts in the ' . ADVACHECK_BRANDNAME . ' system.</p>';
$string['copy1'] = 'Status of the documents queued for copying to ' . ADVACHECK_BRANDNAME . ' from Russian Moodle';
$string['copy2_text'] = '<br>Click Start Copying. The Russian Moodle plugin will be disabled, and the copying process will start</br>';
$string['copy1_cm'] = 'Number of tasks in the Distance Learning courses for which checking is enabled: {$a}';
$string['copy1_alldocs'] = 'Number of assignment responses sent to the ' . ADVACHECK_BRANDNAME . ' system for checking:{$a}';
$string['copy1_and_other'] = 'including the following statuses:';
$string['copy1_ready'] = 'checked (ready): {$a}';
$string['copy1_not_upload'] = '' . ADVACHECK_BRANDNAME . ' upload error: (not_upload): {$a}';
$string['copy1_uploaded'] = 'Uploaded to ' . ADVACHECK_BRANDNAME . ' (uploaded): {$a}';
$string['copy1_notchecked'] = 'Uploaded to ' . ADVACHECK_BRANDNAME . ' (notchecked): {$a}';
$string['copy1_processing'] = 'Checking in progress (processing): {$a}';
$string['copy1_error'] = 'Error loading file (error): {$a}';
$string['copy1_failed'] = 'Error checking file (failed): {$a}';
$string['copy1_notext'] = 'Error checking file (notext): {$a}';
$string['copy1_servererror'] = 'Error checking file (servererror): {$a}';
$string['copy1_default'] = 'Error checking file (default): {$a}';
$string['copy1_unsupportedformat'] = 'Unsupported document format (unsupportedformat): {$a}';
$string['advacheck_doc_cnt'] = 'Number of checks copied from the RM plugin at a time';
$string['advacheck:manage'] = 'Set up a check in the ' . ADVACHECK_BRANDNAME . ' system for the course';
$string['advacheck:checkadvacheck'] = 'Send it to the ' . ADVACHECK_BRANDNAME . ' system for checking';
$string['advacheck:viewfullreport'] = 'See and edit the full report in the ' . ADVACHECK_BRANDNAME . ' system';
$string['advacheck:viewfullreadreport'] = 'See the full report in the ' . ADVACHECK_BRANDNAME . ' system';
$string['advacheck:viewshortreport'] = 'See the brief report in the ' . ADVACHECK_BRANDNAME . ' system';
$string['advacheck:checkedby'] = 'Get checked in the ' . ADVACHECK_BRANDNAME . ' system';
$string['advacheck:updatereport'] = 'Update check results in the ' . ADVACHECK_BRANDNAME . ' system';
$string['modname'] = "Course element";
$string['automode'] = "Auto check";
$string['manualmode'] = "Manual check";
$string['disabledmode'] = "Checks disabled";
$string['checktext'] = 'Text check';
$string['checkfile'] = 'File check';
$string['add_to_index_info'] = 'Add response to the index?';
$string['add_to_index_info_help'] = 'If "Yes" is selected, the most recent student responses will be automatically added to the index (university collection), while previous responses in the lesson will be removed from the index. If "No" is selected, student responses will not be added to the index.';
$string['add_to_index_cln'] = 'Add to index?';
$string['disp_notices_cln'] = 'Student report';
$string['stud_limit_cln'] = 'Student checks limit';
$string['check_stud_lim'] = 'Student checks limit';
$string['check_stud_lim_default'] = 'Default student checks limit';
$string['add_to_index'] = 'Add response to the index?';
$string['disp_notices'] = 'Student report';
$string['not_display'] = 'No.';
$string['display_short'] = 'Brief';
$string['display_full'] = 'Full';
$string['display_full_edit'] = 'Editable';
$string['works_types'] = "Document type";
$string['mode'] = 'Check mode';
$string['enable'] = 'Enable';
$string['advacheck_uri'] = 'Company website in the ' . ADVACHECK_BRANDNAME . ' system';
$string['advacheck_company_name'] = "Company name";
$string['advacheck_login'] = "User email";
$string['advacheck_password'] = "User password";
$string['advacheck_soap_wsdl'] = "API access address";
$string['new_fields'] = 'New fields have been added with the update: {$a} Enter their values and save the settings.';
$string['default_uri'] = "For a test connection";
$string['default_uri_val'] = "https://testapi." . strtolower(ADVACHECK_BRANDNAME) . ".com";
$string['default_login'] = "For a test connection";
$string['default_login_val'] = "testapi@" . strtolower(ADVACHECK_BRANDNAME) . ".com";
$string['default_password'] = "For a test connection";
$string['default_password_val'] = "testapi";
$string['default_soap_wsdl'] = "For a test connection";
$string['default_soap_wsdl_val'] = "https://api." . strtolower(ADVACHECK_BRANDNAME) . ".com:4959/apiCorp/testapi?wsdl";
$string['tarifName'] = 'Tarif name:';
$string['SubscriptionDate'] = 'Subscription date:';
$string['ExpirationDate'] = 'Expiration date:';
$string['TotalChecksCount'] = 'Total checks count:';
$string['TotalChecksCount_unlimited'] = '<i>unlimited</i>';
$string['RemainedChecksCount'] = 'Remained checks count:';
$string['RemainedChecksCount_unlimited'] = '<i>unlimited</i>';
$string['CheckServices'] = 'Check services:';
$string['system_label'] = 'System';
$string['description_label'] = 'Description';
$string['system_site'] = 'Site';
$string['header_default'] = 'Default ' . ADVACHECK_BRANDNAME . ' lesson settings';
$string['header_log'] = 'Check event log settings';
$string['addattributeshead'] = 'Additional information boxes for the document';
$string['addattributesdescr'] = 'Check the boxes that should be added to each document placed in the index.';
$string['add_attr_system'] = '"System"';
$string['add_attr_descr'] = '"Description"';
$string['add_attr_site'] = '"Website"';
$string['add_attr_course'] = '"Course"';
$string['add_attr_item'] = '"Assignment"';
$string['add_attr_discusname'] = '"Forum topic"';
$string['add_attr_fioauthor'] = '"Author\'s full name"';
$string['add_attr_idauthor'] = '"Author\'s id"';
$string['log_actions_label'] = 'Enable the check event log?';
$string['store_actions'] = 'Store check events';
$string['store_months'] = 'month(s)';
$string['advacheck_allow_file_types'] = 'Supported file types for file checks';
$string['default_allow_file_types'] = 'Default value:';
$string['allow_file_types'] = 'Supported file types';
$string['allow_file_types_help'] = 'The supported file types must be listed with ",". The string must not contain any extra characters that are not part of the file extensions! If necessary, contact ' . ADVACHECK_BRANDNAME . ' for clarifications on supported file types.';
$string['not_in_queue'] = 'Error: the response is not in the check queue. Open the lesson settings and save them again.';
$string['error_upload'] = 'Error uploading to ' . ADVACHECK_BRANDNAME . ' : {$a}';
$string['error_upload_service'] = 'The service was unavailable. The upload will be repeated in 1-2 hours';
$string['error_upload_connect'] = 'There was a failure in the Internet channel. The upload will be repeated in 1-2 hours';
$string['error_checking'] = 'Error initiating check: {$a}';
$string['error_check'] = 'Error checking file: {$a}';
$string['error_filetype'] = 'Error: Incorrect file type, more info: {$a}';
$string['error_get_status'] = 'Error obtaining check status: {$a} ';
$string['error_get_report'] = 'Error obtaining check report: {$a}';
$string['error_index'] = 'Error adding to/deleting from index: {$a}';
$string['min_len_str_info'] = 'No check (word count below {$a})';
$string['wait_block1'] = 'To carry out a check in ' . ADVACHECK_BRANDNAME . ', the student must submit their submission';
$string['wait_block2'] = 'To carry out a check in ' . ADVACHECK_BRANDNAME . ', you need to make the submission uneditable';
$string['check_advacheck'] = 'Submit to ' . ADVACHECK_BRANDNAME . ' for checking';
$string['stud_check'] = 'You can check your response in ' . ADVACHECK_BRANDNAME . ' {$a} time(s)';
$string['stud_check1'] = 'After editing your answer you will be able to check it in ' . ADVACHECK_BRANDNAME . ' {$a} time(s)';
$string['stud_not_check'] = 'The teacher can check your answer in ' . ADVACHECK_BRANDNAME . '';
$string['wait_upload'] = 'Waiting for automatic upload to ' . ADVACHECK_BRANDNAME . ' {$a}';
$string['wait_upload_stud'] = 'The teacher can check your answer in ' . ADVACHECK_BRANDNAME . '';
$string['uploading'] = 'Uploading to ' . ADVACHECK_BRANDNAME . '...';
$string['uploaded'] = 'Uploaded to ' . ADVACHECK_BRANDNAME . '';
$string['checking'] = '' . ADVACHECK_BRANDNAME . ' check in progress (up to 15 min)';
$string['report'] = "Check report";
$string['edit_time'] = 'The student can make edits until {$a->t} ({$a->i} min {$a->s} sec left)';
$string['plagiarism'] = "sim.";
$string['plagiarismfull'] = "similarity";
$string['legal'] = "qu.";
$string['legalfull'] = "quotes";
$string['originality'] = "orig.";
$string['originalityfull'] = "originality";
$string['transfer_data_from_apru_table'] = "Copying check results from the Russian Moodle plugin";
$string['upload_and_check_advacheck'] = "Uploading and sending documents for checking";
$string['control_check_status_advacheck'] = "Controlling document check completion";
$string['clear_action_log'] = 'Clearing the check event log';
$string['notice1'] = 'The Moodle-' . ADVACHECK_BRANDNAME . ' module is disabled. Press the Continue to go to the settings page.';
$string['notice2'] = 'You must select the elements of the course to be checked in the ' . ADVACHECK_BRANDNAME . ' system. Press the Continue to go to the settings page.';
$string['cron_check_count'] = "Number of documents for a scheduled check";
$string['originality_limit'] = "Originality threshold";
$string['min_len_str'] = "Minimum response word count";
$string['check_assign'] = "Enable assignment checks";
$string['check_forum'] = "Enable forum checks";
$string['check_workshop'] = "Enable seminar checks";
$string['check_quiz'] = "Enable test essay checks";
$string['test_tarif'] = 'Check connection and payment plan';
$string['success_check'] = '<b>Connected successfully</b>';
$string['error_login'] = '<p><b>Connection error:</b></p><ol><li>Check your login or password</li><li>Make sure thay your link is valid "Access via SOAP+WSDL"</li><li>Make sure that the company\'s website in the ' . ADVACHECK_BRANDNAME . ' system is valid</li></ol>';
$string['check_site'] = '<p><b>To check the company\'s website address in the ' . ADVACHECK_BRANDNAME . ' system, go to:</b></p><span>'
        . "<span>&nbsp;</span><span>&nbsp;</span><span>&nbsp;</span><span>&nbsp;</span>"
        . '<span>{$a}</span></span>';
$string['updatereport'] = 'Update check results';
$string['checkresult'] = '' . ADVACHECK_BRANDNAME . ' check results';
$string['checkresult_help'] = '<p>Similarity reflect the proportion of all textual overlaps found, excluding those that the system has attributed to quotes, in relation to the total document volume.</p>
<p>Quotes reflect the proportion of textual overlaps that were not originally written by the author but are, according to the system, used correctly, in relation to the total document volume. These include: quotes formatted according to GOST requirements; common speech cliches; text fragments found in regulatory document collections.</p>
<p>Originality reflects the proportion of text fragments that cannot be found in any of the sources that the document is being checked against, in relation to the total document volume.</p>
<p>Similarity, quotes, and originality are separate metrics and add up to 100%, which corresponds to the entire text of the document being checked.</p>';
$string['suspicious'] = 'Suspicious document';
$string['downloadreport'] = 'Download check report';
$string['selfcite'] = 'text rec.';
$string['common'] = 'Common settings';
$string['version'] = 'Version:';
$string['none'] = 'None';
$string['abstract'] = 'Abstract';
$string['article'] = 'Article';
$string['book'] = 'Book';
$string['candidatedissertation'] = 'Candidate Dissertation';
$string['candidatedissertationabstract'] = 'Candidate Dissertation Abstract';
$string['collectionofexercises'] = 'Collection Of Exercises';
$string['collectionofworks'] = 'Collection Of Works';
$string['coursework'] = 'Coursework';
$string['doctoraldissertation'] = 'Doctoral Dissertation';
$string['doctoraldissertationabstract'] = 'Doctoral Dissertation Abstract';
$string['educationalvisualedition'] = 'Educational Visual Edition';
$string['finalqualifyingwork'] = 'Final Qualifying Work';
$string['graduatework'] = 'Graduate Work';
$string['guidelines'] = 'Guidelines';
$string['laboratorypractice'] = 'Laboratory Practice';
$string['masterdissertation'] = 'Master Dissertation';
$string['methodicalinstructions'] = 'Methodical Instructions';
$string['monograph'] = 'Monograph';
$string['other'] = 'Other';
$string['practicalwork'] = 'Practical Work';
$string['practicereport'] = 'Practice Report';
$string['researchreport'] = 'Research Report';
$string['scientificqualificationwork'] = 'Scientific Qualification Work';
$string['teachingguide'] = 'Teaching Guide';
$string['textbook'] = 'Textbook';
$string['tutorial'] = 'Tutorial';
$string['doctoraldissertationphd'] = 'Doctoral Dissertation PhD';
$string['doctoraldissertationabstractphd'] = 'Doctoral Dissertation Abstract PhD';
$string['diplomaproject'] = 'Diploma Project';
$string['downloadreport_error'] = 'An error occurred when uploading the verification help file: {$a}; Take a screenshot and send it to technical support.';
$string['control_check_status_complited'] = 'The task of monitoring the completion of document verification has been completed';
$string['control_check_status_enter'] = 'We have entered the task of monitoring the completion of the verification of documents';
$string['control_check_status_nologindata'] = 'Connection data has not been entered! I\'m exiting the task.';
$string['control_check_status_countdocs'] = 'Number of documents to control the completion of verification: {$a}';
$string['control_check_status_cycle'] = 'The cycle of monitoring the completion of the verification of documents from the ' . ADVACHECK_BRANDNAME;
$string['control_check_status_docprocessing'] = 'Document processing: id = {$a->id}; status = {$a->status}; time: {$a->time}';
$string['control_check_status_getcheckstatuserror'] = 'The verification status request ended with an error: {$a->error}; time: {$a->time}';
$string['control_check_status_checkready'] = 'The status is "ready", we record the test results for id = {$a->id}; time: {$a->time}';
$string['control_check_status_checkerror'] = 'An error occurred while checking the document: {$a->error}; time: {$a->time}';
$string['control_check_status_nostartcheck'] = 'Running the check (the document was not checked) for id = {$a->id}; time: {$a->time}';
$string['control_check_status_startcheckerror'] = 'An error occurred while checking the document: {$a->error}; time: {$a->time}';
$string['control_check_status_checkingerror'] = 'Document verification with id = {$a->id}; completed with status: {$a->status}; time: {$a->time}';
$string['control_check_status_addtoindex'] = 'Adding time to the index: {$a->time}';
$string['control_check_status_addtoindexerror'] = 'Error when adding to the index: {$a->error} time: {$a->time}';
$string['upload_and_check_fromindexsuccess'] = 'Removed from the index document id = {$a->id} time: {$a->time}';
$string['upload_and_check_fromindextimeout'] = 'Delay by 0.4;  time: {$a->time}';
$string['upload_and_check_errorfromindex'] = 'Error when deleting a document from the index: {$a->error} id = {$a->id} time: {$a->time}';
$string['upload_and_check_cyclefromindex'] = 'Waiting cycle for deletion from the index time: {$a->time}';
$string['upload_and_check_emptydocid'] = 'Document ID in the ' . ADVACHECK_BRANDNAME . ': \'{$a->id}\' (in serialized form: \'{$a->docidantplgt}\'), skip the document time: {$a->time}';
$string['upload_and_check_emptydoc'] = 'The contents of the document were not found, we are not processing, we set the status ADVACHECK_LESSNWORDS time: {$a->time}';
$string['upload_and_check_startingdoccheckerror'] = 'Error starting document verification id = {$a->id}: {$a->error} time: {$a->time}';
$string['upload_and_check_startingdoccheck'] = 'Starting document check id = {$a->id} time: {$a->time}';
$string['upload_and_check_uploaddocsuccess'] = 'Start checking the document time: {$a->time}';
$string['upload_and_check_uploaddocattrerror'] = 'Error loading document attributes id = {$a->id}: {$a->error} time: {$a->time}';
$string['upload_and_check_errordoc'] = 'Document with an error id = {$a->id}: {$a->error} time: {$a->time}';
$string['upload_and_check_erroruploaddoc'] = 'Document loading error id = {$a->id}: {$a->error} time: {$a->time}';
$string['upload_and_check_uploaddoc'] = 'Uploading a document id = {$a->id} time: {$a->time}';
$string['upload_and_check_textquiznotfound'] = 'The text of the essay was not found in the test, we do not process it, we set the status ADVACHECK_NOTFOUND time: {$a->time}';
$string['upload_and_check_gettextquiz'] = 'Took the text of the response id: {$a->id} time: {$a->time}';
$string['upload_and_check_removefromindexcnt'] = 'Number of documents of previous attempts to delete from the index: {$a->cnt} time: {$a->time}';
$string['upload_and_check_quiztext'] = 'This is an essay from the test time: {$a->time}';
$string['upload_and_check_textworkshopnotfound'] = 'The text of the answer was not found in the seminar, we do not process it, we set the status ADVACHECK_NOTFOUND time: {$a->time}';
$string['upload_and_check_gettextworkshop'] = 'Took the text of the answer of the workshop id: {$a->id} time: {$a->time}';
$string['upload_and_check_workshoptext'] = 'This is the text from the workshop time: {$a->time}';
$string['upload_and_check_filenotfound'] = 'File not found. time: {$a->time}';
$string['upload_and_check_file'] = 'This is a file. time: {$a->time}';
$string['upload_and_check_textforumnotfound'] = 'The text of the response was not found on the forum, we do not process it, we set the status ADVACHECK_NOTFOUND time: {$a->time}';
$string['upload_and_check_gettextforum'] = 'Took the text of the response id: {$a->id} time: {$a->time}';
$string['upload_and_check_forumtext'] = 'This is a text from the forum time: {$a->time}';
$string['upload_and_check_textassignnotfound'] = 'The response text was not found in the task, we are not processing it, we set the status ADVACHECK_NOTFOUND time: {$a->time}';
$string['upload_and_check_gettextassign'] = 'Took the text of the response id: {$a->id} time: {$a->time}';
$string['upload_and_check_assigntext'] = 'This is the text from the assignment time: {$a->time}';
$string['upload_and_check_docprocessing'] = 'Document processing id = {$a->id} time: {$a->time}';
$string['upload_and_check_cycle'] = 'Document loading cycle in the ' . ADVACHECK_BRANDNAME;
$string['upload_and_check_countdocs'] = 'Number of documents to upload and send for verification: {$a}';
$string['upload_and_check_nologindata'] = 'Connection data has not been entered! I\'m exiting the task.';
$string['upload_and_check_enter'] = 'We have entered the task of uploading and sending documents for verification';
$string['clear_action_log_cntrecfordel'] = 'Number of records to delete: {$a}';
$string['action_log_modeauto'] = 'automode';
$string['action_log_modeman'] = 'manual';
$string['action_log_modeoff'] = 'off.';
$string['action_log_typetext'] = 'text';
$string['action_log_typefile'] = 'file';
$string['action_log_indexyes'] = 'yes';
$string['action_log_indexno'] = 'no';
$string['action_log_studcancheck'] = 'The student can check';
$string['action_log_studcanotcheck'] = 'No checks for students';
$string['action_log_checkerstud'] = 'student';
$string['action_log_checkerteach'] = 'teacher';
$string['action_log_submissiondraftsno'] = 'Require pressing the "Send" button';
$string['action_log_submissiondraftsyes'] = 'Drafts are sent';
$string['action_log_assignsett'] = 'Assign settings: ';
$string['action_log_forumtype'] = 'Forum type: ';
$string['action_log_cmsettings'] = 'Course module settings: mode: {$a->mode}; documents: ; add to index: {$a->inindex}; initiator of verification: {$a->checker}; {$a->mod_settings}; {$a->check_stud_lim}';
$string['action1'] = 'Adding to the queue';
$string['action2'] = 'Start of the download';
$string['action3'] = 'End of download';
$string['action4'] = 'Check start';
$string['action5'] = 'Start of verification';
$string['action6'] = 'Start of removal from the index';
$string['action7'] = 'End of deletion from index';
$string['action8'] = 'Placement in the index';
$string['action9'] = 'The verification result has been updated';
$string['action10'] = 'Start of document processing';
$string['action11'] = 'Start loading additional information fields of the document';
$string['action12'] = 'End of loading additional information fields of the document';
$string['action13'] = 'End of document processing';
$string['action14'] = 'Error received';
$string['field1'] = 'doc_type';
$string['field2'] = 'event';
$string['field3'] = 'time_event';
$string['field4'] = 'course_name';
$string['field5'] = 'course_section';
$string['field6'] = 'course_module_name';
$string['field7'] = 'Initiator_of_the_Check';
$string['field8'] = 'student';
$string['doctype1'] = 'file';
$string['doctype2'] = 'text on forum';
$string['doctype3'] = 'text on assign';
$string['doctype4'] = 'text on workshop';
$string['doctype5'] = 'essay in test';
$string['requestheaders'] = 'Request headers: ';
$string['request'] = 'Request: ';
$string['response'] = 'Response: ';
$string['ecode'] = 'Code error: ';
$string['trace'] = 'Trace log: ';
$string['soap_wsdl'] = 'soap-wsdl url: ';
$string['nosoapext'] = 'SOAP extension for PHP is not connected!';
$string['updatereporterror'] = 'Error when updating scan results: {$a}';
$string['docnotchecked'] = 'This answer has not yet been verified!';
$string['privacy:metadata:core_files'] = 'We need a content of submission, for originality check';
$string['privacy:metadata:plagiarism_advacheck_docs'] = 'Stores information about checks';
$string['privacy:metadata:plagiarism_advacheck_docs:doctype'] = 'Document type: file, text in the assignment, text on the forum, text in the workshop, text in the quiz essay.';
$string['privacy:metadata:plagiarism_advacheck_docs:typeid'] = 'File id or sha1 signature of answer content';
$string['privacy:metadata:plagiarism_advacheck_docs:answerid'] = 'ID forum post / assign submittion';
$string['privacy:metadata:plagiarism_advacheck_docs:error'] = 'Text error';
$string['privacy:metadata:plagiarism_advacheck_docs:assignment'] = 'Assignment ID';
$string['privacy:metadata:plagiarism_advacheck_docs:discussion'] = 'Discussion ID';
$string['privacy:metadata:plagiarism_advacheck_docs:workshop'] = 'Workshop ID';
$string['privacy:metadata:plagiarism_advacheck_docs:userid'] = 'The ID of the user being checked.';
$string['privacy:metadata:plagiarism_advacheck_docs:plagiarism'] = 'Percentage of plagiarism';
$string['privacy:metadata:plagiarism_advacheck_docs:legal'] = 'Percentage of legal';
$string['privacy:metadata:plagiarism_advacheck_docs:selfcite'] = 'Percentage of selfcite';
$string['privacy:metadata:plagiarism_advacheck_docs:issuspicious'] = 'The "suspicious document" flag.';
$string['privacy:metadata:plagiarism_advacheck_docs:reportedit'] = 'A link to the report being edited.';
$string['privacy:metadata:plagiarism_advacheck_docs:reportread'] = 'A link to the full report.';
$string['privacy:metadata:plagiarism_advacheck_docs:shortreport'] = 'A link to the short report.';
$string['privacy:metadata:plagiarism_advacheck_docs:docidantplgt'] = 'The structure with the ID of the document in the plagiarism detection system.';
$string['privacy:metadata:plagiarism_advacheck_docs:externalid'] = 'The ID of the document in the plagiarism detection system.';
$string['privacy:metadata:plagiarism_advacheck_docs:status'] = 'The status of the document verification.';
$string['privacy:metadata:plagiarism_advacheck_docs:timeadded'] = 'The time when the document was added to the queue.';
$string['privacy:metadata:plagiarism_advacheck_docs:courseid'] = 'The ID of the course where the answer was added.';
$string['privacy:metadata:plagiarism_advacheck_docs:cmid'] = 'ID of the course module.';
$string['privacy:metadata:plagiarism_advacheck_docs:timeupload_start'] = 'ID of the course module.';
$string['privacy:metadata:plagiarism_advacheck_docs:timeupload_end'] = 'The end of the document download.';
$string['privacy:metadata:plagiarism_advacheck_docs:timecheck_start'] = 'The beginning of document verification.';
$string['privacy:metadata:plagiarism_advacheck_docs:timecheck_end'] = 'The end of the document verification.';
$string['privacy:metadata:plagiarism_advacheck_docs:teacherid'] = 'ID of the checking user.';
$string['privacy:metadata:plagiarism_advacheck_docs:stud_check'] = 'The number of checks available to the student.';
$string['privacy:metadata:plagiarism_advacheck_docs:type'] = 'The type of work (Article, Candidate dissertation, Monograph, Final qualifying work and etc.).';
$string['privacy:metadata:plagiarism_advacheck_docs:attemptnumber'] = 'Attemptnumber of in the assignment or in the quiz essay';
$string['privacy:metadata:plagiarism_advacheck_course'] = 'Stores the plugin settings on the course.';
$string['privacy:metadata:plagiarism_advacheck_course:courseid'] = 'Course ID.';
$string['privacy:metadata:plagiarism_advacheck_course:cmid'] = 'Module ID on the course.';
$string['privacy:metadata:plagiarism_advacheck_course:mode'] = 'Check mode (manual, automatic, off).';
$string['privacy:metadata:plagiarism_advacheck_course:checktext'] = 'Checking files?';
$string['privacy:metadata:plagiarism_advacheck_course:checkfile'] = 'Checking texts?';
$string['privacy:metadata:plagiarism_advacheck_course:check_stud_lim'] = 'The number of checks available to the student.';
$string['privacy:metadata:plagiarism_advacheck_course:add_to_index'] = 'Add to the index?';
$string['privacy:metadata:plagiarism_advacheck_course:disp_notices'] = 'Type of report for students: no, short, full.';
$string['privacy:metadata:plagiarism_advacheck_course:works_types'] = 'The type of work (Article, Candidate dissertation, Monograph, Final qualifying work and etc.).';
$string['privacy:metadata:plagiarism_advacheck_act_log'] = 'Stores the sequence of events for sending documents and verifying them for incident investigation.';
$string['privacy:metadata:plagiarism_advacheck_act_log:docid'] = 'ID of the document in the queue (plagiarism_advacheck_docs table).';
$string['privacy:metadata:plagiarism_advacheck_act_log:time_action'] = 'The time of the event in seconds.';
$string['privacy:metadata:plagiarism_advacheck_act_log:time_action_hr'] = 'The human-readable time of the event.';
$string['privacy:metadata:plagiarism_advacheck_act_log:reportedit'] = 'A link to the report being edited.';
$string['privacy:metadata:plagiarism_advacheck_act_log:action_id'] = 'Action ID in plagiarism_advacheck_action table.';
$string['privacy:metadata:plagiarism_advacheck_act_log:status'] = 'The status of the document verification.';
$string['privacy:metadata:plagiarism_advacheck_act_log:courseid'] = 'Course ID.';
$string['privacy:metadata:plagiarism_advacheck_act_log:cmid'] = 'Module ID on the course.';
$string['privacy:metadata:plagiarism_advacheck_act_log:assignment'] = 'Assignment ID';
$string['privacy:metadata:plagiarism_advacheck_act_log:discussion'] = 'Discussion ID';
$string['privacy:metadata:plagiarism_advacheck_act_log:userid'] = 'The ID of the user being checked.';
$string['privacy:metadata:plagiarism_advacheck_act_log:verifier_initiator'] = 'ID of the checking user.';
$string['privacy:metadata:plagiarism_advacheck_act_log:errormessage'] = 'Text error.';
$string['privacy:metadata:plagiarism_advacheck_act_log:cmsettings'] = 'the plugin settings on the course module.';
$string['privacy:metadata:plagiarism_advacheck_action'] = 'Stores the names of actions.';
$string['privacy:metadata:plagiarism_advacheck_action:action_name'] = 'Names of actions.';
$string['privacy:metadata:plagiarism_advacheck'] = 'Service for originality check ' . ADVACHECK_BRANDNAME;
$string['privacy:metadata:plagiarism_advacheck:file'] = 'Submission attachment for originality ' . ADVACHECK_BRANDNAME;