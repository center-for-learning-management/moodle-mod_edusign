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
 * Privacy class for requesting user data.
 *
 * @package    edusignfeedback_file
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace edusignfeedback_file\privacy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/edusign/locallib.php');

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\contextlist;
use \mod_edusign\privacy\edusign_plugin_request_data;
use mod_edusign\privacy\useridlist;

/**
 * Privacy class for requesting user data.
 *
 * @package    edusignfeedback_file
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \mod_edusign\privacy\edusignfeedback_provider,
        \mod_edusign\privacy\edusignfeedback_user_provider {

    /**
     * Return meta data about this plugin.
     *
     * @param collection $collection A list of information to add to.
     * @return collection Return the collection after adding to it.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->link_subsystem('core_files', 'privacy:metadata:filepurpose');
        return $collection;
    }

    /**
     * No need to fill in this method as all information can be acquired from the edusign_grades table in the mod edusign
     * provider.
     *
     * @param int $userid The user ID.
     * @param contextlist $contextlist The context list.
     */
    public static function get_context_for_userid_within_feedback(int $userid, contextlist $contextlist) {
        // This uses the edusign_grade table.
    }

    /**
     * This also does not need to be filled in as this is already collected in the mod edusign provider.
     *
     * @param useridlist $useridlist A list of user IDs
     */
    public static function get_student_user_ids(useridlist $useridlist) {
        // Not required.
    }

    /**
     * If you have tables that contain userids and you can generate entries in your tables without creating an
     * entry in the edusign_grades table then please fill in this method.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist object
     */
    public static function get_userids_from_context(\core_privacy\local\request\userlist $userlist) {
        // Not required.
    }

    /**
     * Export all user data for this plugin.
     *
     * @param edusign_plugin_request_data $exportdata Data used to determine which context and user to export and other useful
     * information to help with exporting.
     */
    public static function export_feedback_user_data(edusign_plugin_request_data $exportdata) {
        $currentpath = $exportdata->get_subcontext();
        $currentpath[] = get_string('privacy:path', 'edusignfeedback_file');
        $edusign = $exportdata->get_edusign();
        $plugin = $edusign->get_plugin_by_type('edusignfeedback', 'file');
        $gradeid = $exportdata->get_pluginobject()->id;
        $filefeedback = $plugin->get_file_feedback($gradeid);
        if ($filefeedback) {
            $fileareas = $plugin->get_file_areas();
            foreach ($fileareas as $filearea => $notused) {
                \core_privacy\local\request\writer::with_context($exportdata->get_context())->export_area_files($currentpath,
                    'edusignfeedback_file', $filearea, $gradeid);
            }
        }
    }

    /**
     * Any call to this method should delete all user data for the context defined in the deletion_criteria.
     *
     * @param edusign_plugin_request_data $requestdata Data useful for deleting user data from this sub-plugin.
     */
    public static function delete_feedback_for_context(edusign_plugin_request_data $requestdata) {

        $edusign = $requestdata->get_edusign();
        $plugin = $edusign->get_plugin_by_type('edusignfeedback', 'file');
        $fileareas = $plugin->get_file_areas();
        $fs = get_file_storage();
        foreach ($fileareas as $filearea => $notused) {
            // Delete feedback files.
            $fs->delete_area_files($requestdata->get_context()->id, 'edusignfeedback_file', $filearea);
        }
        $plugin->delete_instance();
    }

    /**
     * Calling this function should delete all user data associated with this grade.
     *
     * @param edusign_plugin_request_data $requestdata Data useful for deleting user data.
     */
    public static function delete_feedback_for_grade(edusign_plugin_request_data $requestdata) {
        $requestdata->set_userids([$requestdata->get_user()->id]);
        $requestdata->populate_submissions_and_grades();
        self::delete_feedback_for_grades($requestdata);
    }

    /**
     * Deletes all feedback for the grade ids / userids provided in a context.
     * edusign_plugin_request_data contains:
     * - context
     * - edusign object
     * - grade ids (pluginids)
     * - user ids
     *
     * @param edusign_plugin_request_data $deletedata A class that contains the relevant information required for deletion.
     */
    public static function delete_feedback_for_grades(edusign_plugin_request_data $deletedata) {
        global $DB;

        if (empty($deletedata->get_gradeids())) {
            return;
        }

        $edusign = $deletedata->get_edusign();
        $plugin = $edusign->get_plugin_by_type('edusignfeedback', 'file');
        $fileareas = $plugin->get_file_areas();
        $fs = get_file_storage();
        list($sql, $params) = $DB->get_in_or_equal($deletedata->get_gradeids(), SQL_PARAMS_NAMED);
        $params['edusignment'] = $deletedata->get_edusignid();
        foreach ($fileareas as $filearea => $notused) {
            // Delete feedback files.
            $fs->delete_area_files_select($deletedata->get_context()->id, 'edusignfeedback_file', $filearea, $sql, $params);
        }

        // Delete table entries.
        $DB->delete_records_select('edusignfeedback_file', "edusignment = :edusignment AND grade $sql", $params);
    }
}
