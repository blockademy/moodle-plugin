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
 * TODO describe file updateprofile_form
 *
 * @package    mod_pokcertificate
 * @copyright  2024 Moodle India Information Solutions Pvt Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/pokcertificate/locallib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/mod/pokcertificate/lib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/user/editlib.php');
/**
 * form shown while adding activity.
 */
class mod_pokcertificate_editprofile_form extends \moodleform {

    /**
     * Defines the form elements for editing a user profile.
     *
     * This method sets up the form elements required for editing a user profile.
     * It includes fields for the user's name, email, ID number, and other customisable profile fields.
     *
     * @return void
     */
    public function definition() {
        global $USER;
        $mform = $this->_form;

        $user = $this->_customdata['user'];
        $userid = $user->id;

        // Next the customisable profile fields.

        $strrequired = get_string('required');
        $stringman = get_string_manager();

        $mform->addElement('static', 'currentpicture', get_string('currentpicture'));
        // Add the necessary names.
        foreach (useredit_get_required_name_fields() as $fullname) {
            $purpose = user_edit_map_field_purpose($user->id, $fullname);
            $mform->addElement('text', $fullname,  get_string($fullname),  'maxlength="100" size="30"' . $purpose);
            if ($stringman->string_exists('missing' . $fullname, 'core')) {
                $strmissingfield = get_string('missing' . $fullname, 'core');
            } else {
                $strmissingfield = $strrequired;
            }
            $mform->addRule($fullname, $strmissingfield, 'required', null, 'client');
            $mform->setType($fullname, PARAM_NOTAGS);
        }

        $mform->addElement('text', 'email', get_string('email'), 'maxlength="100" size="30"' . $purpose);
        $mform->addRule('email', $strrequired, 'required', null, 'client');
        $mform->setType('email', PARAM_RAW_TRIMMED);

        $mform->addElement('text', 'idnumber', get_string('idnumber'), 'maxlength="255" size="25"');
        $mform->setType('idnumber', core_user::get_property_type('idnumber'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $userid);

        profile_definition($mform, $userid);

        $this->add_action_buttons(true, get_string('updatemyprofile'));

        $this->set_data($user);
    }

    /**
     * Extend the form definition after the data has been parsed.
     */
    public function definition_after_data() {
        global $USER, $CFG, $DB, $OUTPUT;

        $mform = $this->_form;
        if ($userid = $mform->getElementValue('id')) {
            $user = $DB->get_record('user', ['id' => $userid]);
        } else {
            $user = false;
        }

        // Trim required name fields.
        foreach (useredit_get_required_name_fields() as $field) {
            $mform->applyFilter($field, 'trim');
        }

        // Print picture.
        if ($user) {
            $context = context_user::instance($user->id, MUST_EXIST);
            $fs = get_file_storage();
            $hasuploadedpicture = ($fs->file_exists($context->id,
                'user', 'icon', 0, '/', 'f2.png') || $fs->file_exists(
                $context->id, 'user', 'icon', 0, '/', 'f2.jpg'));
            if (!empty($user->picture) && $hasuploadedpicture) {
                $imagevalue = $OUTPUT->user_picture($user, ['courseid' => SITEID, 'size' => 66, 'link' => false]);
            } else {
                $imagevalue = get_string('none');
            }
        }

        $imageelement = $mform->getElement('currentpicture');
        $imageelement->setValue($imagevalue);
    }

    /**
     * Validates the form data submitted by the user.
     *
     * This method is responsible for validating the form data submitted by the user.
     * It performs necessary validation checks on the data and files provided.
     *
     * @param array $data An associative array containing the form data submitted by the user.
     * @param array $files An associative array containing any files uploaded via the form.
     * @return array|bool An array of validation errors, or true if validation succeeds.
     */
    public function validation($data, $files) {
        $errors = [];
        $errors = parent::validation($data, $files);
        if (!validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail', 'mod_pokcertificate');
        }
        if (preg_match('/[^a-zA-Z0-9]/', trim($data['idnumber']))) {
            $errors['idnumber'] = get_string('invalidspechar', 'mod_pokcertificate');
        }
        if (preg_match('/[^a-zA-Z0-9]/', trim($data['firstname']))) {
            $errors['firstname'] = get_string('invalidspechar', 'mod_pokcertificate');
        }
        if (preg_match('/[^a-zA-Z0-9]/', trim($data['lastname']))) {
            $errors['lastname'] = get_string('invalidspechar', 'mod_pokcertificate');
        }
        return $errors;
    }
}
