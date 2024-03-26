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

require_once ($CFG->dirroot . '/lib/formslib.php');

class plagiarism_setup_form extends moodleform
{

    var $tab = null;

    // Define the form.
    public function definition()
    {
        global $PAGE, $OUTPUT, $DB, $CFG;
        $mform = &$this->_form;
        $tab = $this->_customdata->tab;
        $this->tab = $tab;
        switch ($tab) {
            case 'common':
                $soap_enabled = extension_loaded('soap');
                if (!$soap_enabled) {
                    $soap_enabled = '0';
                } else {
                    $soap_enabled = '1';
                }

                $plugins = core_plugin_manager::instance()->get_plugins_of_type('plagiarism');
                foreach ($plugins as $plugin) {
                    if ($plugin->component == 'plagiarism_advacheck') {
                        $t = html_writer::tag('h3', get_string('pluginfullname', 'plagiarism_advacheck'));
                        $t .= html_writer::div(get_string('version', 'plagiarism_advacheck') . " $plugin->release ($plugin->versiondb)") . '<br>';
                        $mform->addElement('html', $t);
                        break;
                    }
                }

                $mform->addElement('hidden', 'soap_enabled', $soap_enabled);
                $mform->setType('soap_enabled', PARAM_INT);

                $mform->addElement('checkbox', 'enabled', get_string('useadvacheck', 'plagiarism_advacheck'));
                $mform->disabledIf('enabled', 'soap_enabled', 'eq', '0');

                $header1 = html_writer::tag('h3', get_string('settings1', 'plagiarism_advacheck'), ['class' => 'main']);
                $mform->addElement('html', $header1);

                $mform->addElement('text', 'uri', get_string('advacheck_uri', 'plagiarism_advacheck'), ['size' => 50]);
                $mform->addRule('uri', null, 'required', null, 'client');
                $mform->setType('uri', PARAM_TEXT);

                $default_url = get_string('default_uri', 'plagiarism_advacheck');
                $default_url_val = get_string('default_uri_val', 'plagiarism_advacheck');
                $default_url = html_writer::div(
                    $default_url . '<br>' . $default_url_val,
                    'form-defaultinfo text-muted text-ltr',
                    ['style' => 'font-size: .75rem;']
                );

                $mform->addElement('static', '', '', $default_url);
                $mform->addElement('text', 'login', get_string('advacheck_login', 'plagiarism_advacheck'), ['size' => 50]);
                $mform->addRule('login', null, 'required', null, 'client');
                $mform->setType('login', PARAM_TEXT);

                $default_login = get_string('default_login', 'plagiarism_advacheck');
                $default_login_val = get_string('default_login_val', 'plagiarism_advacheck');
                $default_login = html_writer::div(
                    $default_login . '<br>' . $default_login_val,
                    'form-defaultinfo text-muted text-ltr',
                    ['style' => 'font-size: .75rem;']
                );
                $mform->addElement('static', '', '', $default_login);

                $mform->addElement('text', 'password', get_string('advacheck_password', 'plagiarism_advacheck'), ['size' => 50]);
                $mform->addRule('password', null, 'required', null, 'client');
                $mform->setType('password', PARAM_TEXT);

                $default_pwd = get_string('default_password', 'plagiarism_advacheck');
                $default_pwd_val = get_string('default_password_val', 'plagiarism_advacheck');
                $default_pwd = html_writer::div(
                    $default_pwd . '<br>' . $default_pwd_val,
                    'form-defaultinfo text-muted text-ltr',
                    ['style' => 'font-size: .75rem;']
                );
                $mform->addElement('static', '', '', $default_pwd);

                $mform->addElement('text', 'soap_wsdl', get_string('advacheck_soap_wsdl', 'plagiarism_advacheck'), ['size' => 50]);
                $mform->addRule('soap_wsdl', null, 'required', null, 'client');
                $mform->setType('soap_wsdl', PARAM_TEXT);

                $default_soap_wsdl = get_string('default_soap_wsdl', 'plagiarism_advacheck');
                $default_soap_wsdl_val = get_string('default_soap_wsdl_val', 'plagiarism_advacheck');
                $default_soap_wsdl = html_writer::div(
                    $default_soap_wsdl . '<br>' . $default_soap_wsdl_val,
                    'form-defaultinfo text-muted text-ltr',
                    ['style' => 'font-size: .75rem;']
                );

                $mform->addElement('static', '', '', $default_soap_wsdl);

                $html_btn = html_writer::start_tag(
                    'input',
                    [
                        'name' => "test_tarif",
                        'type' => "button",
                        'value' => get_string('test_tarif', 'plagiarism_advacheck'),
                        'class' => "test-tarif"
                    ]
                );
                $mform->addElement('static', 'test_tarif_static', '', $html_btn);
                // For the info field.
                $mform->addElement('html', html_writer::div('', '', ['id' => 'id_tarif_info']));
                // Let's add a JS fragment that handles a click on the button.
                $PAGE->requires->js_call_amd('plagiarism_advacheck/check', 'checkTarif');

                $header2 = html_writer::tag('h3', get_string('settings2', 'plagiarism_advacheck'), ['class' => 'main']);
                $mform->addElement('html', $header2);

                $mform->addElement('text', 'cron_check_count', get_string('cron_check_count', 'plagiarism_advacheck'), array('size' => 3));
                $mform->addRule('cron_check_count', null, 'required', null, 'client');
                $mform->setType('cron_check_count', PARAM_TEXT);

                $mform->addElement(
                    'text',
                    'originality_limit',
                    get_string('originality_limit', 'plagiarism_advacheck') . ", %",
                    array('size' => 3)
                );
                $mform->addRule('originality_limit', null, 'required', null, 'client');
                $mform->setType('originality_limit', PARAM_TEXT);

                $mform->addElement('text', 'min_len_str', get_string('min_len_str', 'plagiarism_advacheck'), array('size' => 3));
                $mform->setDefault('min_len_str', '20');
                $mform->addRule('min_len_str', null, 'required', null, 'client');
                $mform->setType('min_len_str', PARAM_TEXT);

                $mform->addElement('advcheckbox', 'check_assign', get_string('check_assign', 'plagiarism_advacheck'), '', [], [0, 1]);
                $mform->addElement('advcheckbox', 'check_forum', get_string('check_forum', 'plagiarism_advacheck'), '', [], [0, 1]);
                $mform->addElement('advcheckbox', 'check_workshop', get_string('check_workshop', 'plagiarism_advacheck'), '', [], [0, 1]);
                if ($CFG->version >= 2021051700) {
                    $mform->addElement('advcheckbox', 'check_quiz', get_string('check_quiz', 'plagiarism_advacheck'), '', [], [0, 1]);
                }

                // Field for entering file extensions.
                $mform->addElement('textarea', 'allow_file_types', get_string('advacheck_allow_file_types', 'plagiarism_advacheck'));
                $mform->setDefault('allow_file_types', 'txt, docx , html, htm, pdf, rtf, odt , pptx');
                $mform->setType('allow_file_types', PARAM_TEXT);
                $mform->addHelpButton('allow_file_types', 'allow_file_types', 'plagiarism_advacheck');
                $default_file_types = get_string('default_allow_file_types', 'plagiarism_advacheck');
                $default_file_types = html_writer::div(
                    $default_file_types . '<br>' . 'txt, docx , html, htm, pdf, rtf, odt , pptx',
                    'form-defaultinfo text-muted text-ltr',
                    ['style' => 'font-size: .75rem;']
                ) . '<br></br>';
                $mform->addElement('static', '', '', $default_file_types);

                // Default settings.
                $header_default = html_writer::tag('h3', get_string('header_default', 'plagiarism_advacheck'), ['class' => 'main']);
                $mform->addElement('html', $header_default);
                // Limit of draft checks for a student.
                $mform->addElement(
                    'select',
                    'check_stud_lim_default',
                    get_string('check_stud_lim_default', 'plagiarism_advacheck'),
                    [
                        0 => '0',
                        1 => '1',
                        2 => '2',
                        3 => '3',
                        5 => '5',
                        7 => '7',
                        10 => '10'
                    ]
                );
                // Show test results to students?
                $options_notes = [
                    0 => get_string('not_display', 'plagiarism_advacheck'),
                    1 => get_string('display_short', 'plagiarism_advacheck'),
                    2 => get_string('display_full', 'plagiarism_advacheck')
                ];
                $mform->addElement('select', 'disp_notices_default', get_string('disp_notices', 'plagiarism_advacheck'), $options_notes);
                $disp_notices_default = get_config('plagiarism_advacheck', 'disp_notices_default');
                $mform->setDefault('disp_notices_default', ($disp_notices_default === false ? 1 : $disp_notices_default));

                // Action log.
                $header_log = html_writer::tag('h3', get_string('header_log', 'plagiarism_advacheck'), ['class' => 'main']);
                $mform->addElement('html', $header_log);
                $mform->addElement('selectyesno', 'log_actions', get_string('log_actions_label', 'plagiarism_advacheck'));
                $log_actions = get_config('plagiarism_advacheck', 'log_actions');
                $mform->setDefault('log_actions', ($log_actions === false ? 1 : $log_actions));

                $store_actions = [];
                $store_actions[] = &$mform->createElement('select', 'store_action_time', '', [1 => '1', 3 => '3', 6 => '6', 12 => '12']);
                $store_actions[] = &$mform->createElement('static', '', '', get_string('store_months', 'plagiarism_advacheck'));
                $mform->addGroup($store_actions, '', get_string('store_actions', 'plagiarism_advacheck'), ' ', false);
                $store_action_time = get_config('plagiarism_advacheck', 'store_action_time');
                $mform->setDefault('log_actions', ($store_action_time === false ? 1 : $store_action_time));

                $header3 = html_writer::tag('h3', get_string('addattributeshead', 'plagiarism_advacheck'), ['class' => 'main']);
                $mform->addElement('html', $header3);
                $header3 = html_writer::tag('p', get_string('addattributesdescr', 'plagiarism_advacheck'), ['class' => 'main']);
                $mform->addElement('html', $header3);
                $mform->addElement('advcheckbox', 'add_attr_system', get_string('add_attr_system', 'plagiarism_advacheck'), '', [], [0, 1]);
                $mform->setDefault('add_attr_system', 1);
                $mform->addElement('advcheckbox', 'add_attr_descr', get_string('add_attr_descr', 'plagiarism_advacheck'), '', [], [0, 1]);
                $mform->setDefault('add_attr_descr', 1);
                $mform->addElement('advcheckbox', 'add_attr_site', get_string('add_attr_site', 'plagiarism_advacheck'), '', [], [0, 1]);
                $mform->setDefault('add_attr_site', 1);
                $mform->addElement('advcheckbox', 'add_attr_course', get_string('add_attr_course', 'plagiarism_advacheck'), '', [], [0, 1]);
                $mform->setDefault('add_attr_course', 1);
                $mform->addElement('advcheckbox', 'add_attr_item', get_string('add_attr_item', 'plagiarism_advacheck'), '', [], [0, 1]);
                $mform->setDefault('add_attr_item', 1);
                $mform->addElement(
                    'advcheckbox',
                    'add_attr_discusname',
                    get_string('add_attr_discusname', 'plagiarism_advacheck'),
                    '',
                    [],
                    [0, 1]
                );
                $mform->setDefault('add_attr_discusname', 1);
                /*
                $mform->addElement(
                    'advcheckbox',
                    'add_attr_fioauthor',
                    get_string('add_attr_fioauthor', 'plagiarism_advacheck'),
                    '',
                    [],
                    [0, 1]
                );
                $mform->setDefault('add_attr_fioauthor', 0);
                $mform->addElement('advcheckbox', 'add_attr_idauthor', get_string('add_attr_idauthor', 'plagiarism_advacheck'), '', [], [0, 1]);
                $mform->setDefault('add_attr_idauthor', 1);
                */

                $buttonarray = array();
                $buttonarray[] = &$mform->createElement('html', html_writer::tag('button', get_string('save'), ['class' => 'btn btn-primary', 'type' => 'submit', 'name' => 'submitbutton']));
                $buttonarray[] = &$mform->createElement('cancel');
                $mform->addGroup($buttonarray, 'buttonar', '', '&nbsp;&nbsp;', false);
                break;
        }
    }

    /**
     * Saves settings, displays information, displays saved settings from the database.
     *
     * @param mixed $n For messages.
     * @return void
     */
    public function process(&$n)
    {
        global $DB, $CFG, $PAGE, $OUTPUT;
        if ($this->is_cancelled()) {
            redirect($CFG->wwwroot . '/admin/plagiarism.php');
        }
        $n = '';
        $soap_enabled = extension_loaded('soap');
        if (!$soap_enabled) {
            // The plugin cannot be used.
            $n .= $OUTPUT->notification(get_string('nosoapext', 'plagiarism_advacheck'), 'error');
            $soap_enabled = '0';
            enable_plug(false, 0);
        } else {
            $soap_enabled = '1';
        }

        $uri = get_config('plagiarism_advacheck', 'uri');

        switch ($this->tab) {
            case 'common':
                if (($data = $this->get_data()) && confirm_sesskey()) {
                    if (!isset ($data->enabled)) {
                        enable_plug(false, 0);
                    } else {
                        enable_plug(false, 1);
                    }
                    foreach ($data as $field => $value) {
                        // The "soap_enabled" field does not need to be written because this is a hidden helper field.
                        if ($field != 'soap_enabled') {
                            // If there is a '/' at the end of the site, then we will cut it out.
                            if ($field == 'uri') {
                                if (substr($value, -1) == '/') {
                                    $value = substr($value, 0, -1);
                                    $uri = $value;
                                }
                            }
                            set_config($field, $value, 'plagiarism_advacheck');
                        }
                    }
                    $n .= $OUTPUT->notification(get_string('savedconfigsuccess', 'plagiarism_advacheck'), 'success');
                }
                break;
        }

        $plagiarismsettings = (array) get_config('plagiarism_advacheck');
        if (count($plagiarismsettings) <= 1) {
            $plagiarismsettings = $this->get_defaults_settings();
        } else {
            // Let's add it so it doesn't reset.
            $plagiarismsettings['soap_enabled'] = $soap_enabled;
            if (!empty ($uri)) {
                $plagiarismsettings['uri'] = $uri;
            }
        }
        $this->set_data($plagiarismsettings);
    }

    private function get_defaults_settings()
    {
        $plagiarismsettings = [];
        $plagiarismsettings['uri'] = get_string('default_uri_val', 'plagiarism_advacheck');
        $plagiarismsettings['login'] = get_string('default_login_val', 'plagiarism_advacheck');
        $plagiarismsettings['password'] = get_string('default_password_val', 'plagiarism_advacheck');
        $plagiarismsettings['soap_wsdl'] = get_string('default_soap_wsdl_val', 'plagiarism_advacheck');
        return $plagiarismsettings;
    }

}
