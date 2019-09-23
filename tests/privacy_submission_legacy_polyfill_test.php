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
 * Unit tests for the privacy legacy polyfill for mod_edusign.
 *
 * @package     mod_edusign
 * @category    test
 * @copyright   2018 Adrian Greeve <adriangreeve.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/edusign/submissionplugin.php');
require_once($CFG->dirroot . '/mod/edusign/submission/comments/locallib.php');

/**
 * Unit tests for the edusignment submission subplugins API's privacy legacy_polyfill.
 *
 * @copyright   2018 Adrian Greeve <adriangreeve.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_edusignsubmission_privacy_legacy_polyfill_test extends advanced_testcase {

    /**
     * Convenience function to create an instance of an edusignment.
     *
     * @param array $params Array of parameters to pass to the generator
     * @return edusign The edusign class.
     */
    protected function create_instance($params = array()) {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_edusign');
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('edusign', $instance->id);
        $context = \context_module::instance($cm->id);
        return new \edusign($context, $cm, $params['course']);
    }

    /**
     * Test the get_context_for_userid_within_submission shim.
     */
    public function test_get_context_for_userid_within_submission() {
        $userid = 21;
        $contextlist = new \core_privacy\local\request\contextlist();
        $mock = $this->createMock(test_edusignsubmission_legacy_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_get_context_for_userid_within_submission', [$userid, $contextlist]);
        test_legacy_polyfill_submission_provider::$mock = $mock;
        test_legacy_polyfill_submission_provider::get_context_for_userid_within_submission($userid, $contextlist);
    }

    /**
     * Test the get_student_user_ids shim.
     */
    public function test_get_student_user_ids() {
        $teacherid = 107;
        $edusignid = 15;
        $useridlist = new \mod_edusign\privacy\useridlist($teacherid, $edusignid);
        $mock = $this->createMock(test_edusignsubmission_legacy_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_get_student_user_ids', [$useridlist]);
        test_legacy_polyfill_submission_provider::$mock = $mock;
        test_legacy_polyfill_submission_provider::get_student_user_ids($useridlist);
    }

    /**
     * Test the export_submission_user_data shim.
     */
    public function test_export_submission_user_data() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance(['course' => $course]);
        $context = context_system::instance();
        $subplugin = new edusign_submission_comments($edusign, 'comment');
        $requestdata = new \mod_edusign\privacy\edusign_plugin_request_data($context, $edusign);
        $mock = $this->createMock(test_edusignsubmission_legacy_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_export_submission_user_data', [$requestdata]);
        test_legacy_polyfill_submission_provider::$mock = $mock;
        test_legacy_polyfill_submission_provider::export_submission_user_data($requestdata);
    }

    /**
     * Test the delete_submission_for_context shim.
     */
    public function test_delete_submission_for_context() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance(['course' => $course]);
        $context = context_system::instance();
        $subplugin = new edusign_submission_comments($edusign, 'comment');
        $requestdata = new \mod_edusign\privacy\edusign_plugin_request_data($context, $edusign);
        $mock = $this->createMock(test_edusignsubmission_legacy_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_delete_submission_for_context', [$requestdata]);
        test_legacy_polyfill_submission_provider::$mock = $mock;
        test_legacy_polyfill_submission_provider::delete_submission_for_context($requestdata);
    }

    /**
     * Test the delete submission for grade shim.
     */
    public function test_delete_submission_for_userid() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $edusign = $this->create_instance(['course' => $course]);
        $context = context_system::instance();
        $subplugin = new edusign_submission_comments($edusign, 'comment');
        $requestdata = new \mod_edusign\privacy\edusign_plugin_request_data($context, $edusign);
        $mock = $this->createMock(test_edusignsubmission_legacy_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_delete_submission_for_userid', [$requestdata]);
        test_legacy_polyfill_submission_provider::$mock = $mock;
        test_legacy_polyfill_submission_provider::delete_submission_for_userid($requestdata);
    }
}
/**
 * Legacy polyfill test class for the edusignsubmission_provider.
 *
 * @copyright   2018 Adrian Greeve <adriangreeve.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_legacy_polyfill_submission_provider implements \mod_edusign\privacy\edusignsubmission_provider {
    use \mod_edusign\privacy\submission_legacy_polyfill;
    /**
     * @var test_legacy_polyfill_submission_provider $mock.
     */
    public static $mock = null;

    /**
     * Retrieves the contextids associated with the provided userid for this subplugin.
     * NOTE if your subplugin must have an entry in the edusign_grade table to work, then this
     * method can be empty.
     *
     * @param  int $userid The user ID to get context IDs for.
     * @param  contextlist $contextlist Use add_from_sql with this object to add your context IDs.
     */
    public static function _get_context_for_userid_within_submission(int $userid,
            \core_privacy\local\request\contextlist $contextlist) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Returns student user ids related to the provided teacher ID. If it is possible that a student ID will not be returned by
     * the sql query in \mod_edusign\privacy\provider::find_grader_info() Then you need to provide some sql to retrive those
     * student IDs. This is highly likely if you had to fill in get_context_for_userid_within_submission above.
     *
     * @param  useridlist $useridlist A list of user IDs of students graded by this user.
     */
    public static function _get_student_user_ids(\mod_edusign\privacy\useridlist $useridlist) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * This method is used to export any user data this sub-plugin has using the edusign_plugin_request_data object to get the
     * context and userid.
     * edusign_plugin_request_data contains:
     * - context
     * - grade object
     * - current path (subcontext)
     * - user object
     *
     * @param  edusign_plugin_request_data $exportdata Contains data to help export the user information.
     */
    public static function _export_submission_user_data(\mod_edusign\privacy\edusign_plugin_request_data $exportdata) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Any call to this method should delete all user data for the context defined in the deletion_criteria.
     * edusign_plugin_request_data contains:
     * - context
     * - edusign object
     *
     * @param  edusign_plugin_request_data $requestdata Data useful for deleting user data from this sub-plugin.
     */
    public static function _delete_submission_for_context(\mod_edusign\privacy\edusign_plugin_request_data $requestdata) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * A call to this method should delete user data (where practicle) from the userid and context.
     * edusign_plugin_request_data contains:
     * - context
     * - grade object
     * - user object
     * - edusign object
     *
     * @param  edusign_plugin_request_data $requestdata Data useful for deleting user data.
     */
    public static function _delete_submission_for_userid(\mod_edusign\privacy\edusign_plugin_request_data $requestdata) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }
}
/**
 * Called inside the polyfill methods in the test polyfill provider, allowing us to ensure these are called with correct params.
 *
 * @copyright   2018 Adrian Greeve <adriangreeve.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_edusignsubmission_legacy_polyfill_mock_wrapper {
    /**
     * Get the return value for the specified item.
     */
    public function get_return_value() {
    }
}
