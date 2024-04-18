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
 * Page external API
 *
 * @package    mod_pokcertificate
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

use core_course\external\helper_for_get_mods_by_courses;
use core_external\external_api;
use core_external\external_files;
use core_external\external_format_value;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;

use mod_pokcertificate\api;


/**
 * Page external functions
 *
 * @package    mod_pokcertificate
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_pokcertificate_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_pokcertificate_parameters() {
        return new external_function_parameters(
            array(
                'pokcertificateid' => new external_value(PARAM_INT, 'pokcertificate instance id')
            )
        );
    }

    /**
     * Simulate the pokcertificate/view.php web interface pokcertificate: trigger events, completion, etc...
     *
     * @param int $pokcertificateid the pokcertificate instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_pokcertificate($pokcertificateid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/pokcertificate/lib.php");

        $params = self::validate_parameters(
            self::view_pokcertificate_parameters(),
            array(
                'pokcertificateid' => $pokcertificateid
            )
        );
        $warnings = array();

        // Request and permission validation.
        $pokcertificate = $DB->get_record('pokcertificate', array('id' => $params['pokcertificateid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($pokcertificate, 'pokcertificate');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/pokcertificate:view', $context);

        // Call the pokcertificate/lib API.
        pokcertificate_view($pokcertificate, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.0
     */
    public static function view_pokcertificate_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_pokcertificates_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_pokcertificates_by_courses_parameters() {
        return new external_function_parameters(
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course id'),
                    'Array of course ids',
                    VALUE_DEFAULT,
                    array()
                ),
            )
        );
    }

    /**
     * Returns a list of pokcertificates in a provided list of courses.
     * If no list is provided all pokcertificates that the user can view will be returned.
     *
     * @param array $courseids course ids
     * @return array of warnings and pokcertificates
     * @since Moodle 3.3
     */
    public static function get_pokcertificates_by_courses($courseids = array()) {

        $warnings = array();
        $returnedpokcertificates = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_pokcertificates_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = util::validate_courses($params['courseids'], $mycourses);

            // Get the pokcertificates in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $pokcertificates = get_all_instances_in_courses("pokcertificate", $courses);
            foreach ($pokcertificates as $pokcertificate) {
                helper_for_get_mods_by_courses::format_name_and_intro($pokcertificate, 'mod_pokcertificate');

                $context = context_module::instance($pokcertificate->coursemodule);
                list($pokcertificate->content, $pokcertificate->contentformat) = \core_external\util::format_text(
                    $pokcertificate->content,
                    $pokcertificate->contentformat,
                    $context,
                    'mod_pokcertificate',
                    'content',
                    $pokcertificate->revision,
                    ['noclean' => true]
                );
                $pokcertificate->contentfiles = util::get_area_files($context->id, 'mod_pokcertificate', 'content');

                $returnedpokcertificates[] = $pokcertificate;
            }
        }

        $result = array(
            'pokcertificates' => $returnedpokcertificates,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_pokcertificates_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_pokcertificates_by_courses_returns() {
        return new external_single_structure(
            array(
                'pokcertificates' => new external_multiple_structure(
                    new external_single_structure(array_merge(
                        helper_for_get_mods_by_courses::standard_coursemodule_elements_returns(),
                        [
                            'content' => new external_value(PARAM_RAW, 'Page content'),
                            'contentformat' => new external_format_value('content', VALUE_REQUIRED, 'Content format'),
                            'contentfiles' => new external_files('Files in the content'),
                            'legacyfiles' => new external_value(PARAM_INT, 'Legacy files flag'),
                            'legacyfileslast' => new external_value(PARAM_INT, 'Legacy files last control flag'),
                            'display' => new external_value(PARAM_INT, 'How to display the pokcertificate'),
                            'displayoptions' => new external_value(PARAM_RAW, 'Display options (width, height)'),
                            'revision' => new external_value(PARAM_INT, 'Incremented when after each file changes, to avoid cache'),
                            'timemodified' => new external_value(PARAM_INT, 'Last time the pokcertificate was modified'),
                        ]
                    ))
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    public static function verify_authentication_parameters() {
        return new external_function_parameters(
            array(
                'prodtype' => new external_value(PARAM_INT, get_string('prodtype', 'mod_pokcertificate')),
                'authtoken' => new external_value(PARAM_RAW, get_string('authtoken', 'mod_pokcertificate')),
                'institution' => new external_value(PARAM_TEXT, get_string('institution', 'mod_pokcertificate')),
                'domain' => new external_value(PARAM_TEXT, get_string('domain', 'mod_pokcertificate')),
            )
        );
    }

    /**
     * request to verify authentication
     * @param  [type] $prodtype   [description]
     * @param  [type] $authtoken   [description]
     * @param  [type] $institution [description]
     * @param  [type] $domain [description]
     * @return [type]           [description]
     * //7cb608d4-0bb6-4641-aa06-594f2fedf2a0
     */
    public static function verify_authentication($prodtype, $authtoken, $institution, $domain) {
        global $CFG;

        set_config('prodtype', $prodtype, 'mod_pokcertificate');
        require_once($CFG->dirroot . '/mod/pokcertificate/lib.php');
        $params = self::validate_parameters(
            self::verify_authentication_parameters(),
            array('prodtype' => $prodtype, 'authtoken' => $authtoken, "institution" => $institution, 'domain' => $domain)
        );

        $result = pokcertificate_validate_apikey($authtoken);

        if ($result) {
            $response = (new mod_pokcertificate\api)->get_organization();

            $creditsresp = (new mod_pokcertificate\api)->get_credits();

            $certificatecount = (new mod_pokcertificate\api)->count_certificates();

            $msg = get_string("success");
            return array("status" => 0, "msg" => $msg, "response" => $response);
        } else {
            $msg = get_string("error");
            return array("status" => 1, "msg" => $msg, "response" => '');
        }
    }

    public static function verify_authentication_returns() {
        return new external_single_structure(
            array(
                'status'  => new external_value(PARAM_TEXT, get_string('status', 'mod_pokcertificate')),
                'msg'  => new external_value(PARAM_RAW, get_string('errormsg', 'mod_pokcertificate')),
                'response'  => new external_value(PARAM_RAW, get_string('response', 'mod_pokcertificate'))
            )
        );
    }
}
