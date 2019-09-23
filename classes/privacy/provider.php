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
 * @package    mod_edusign
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_edusign\privacy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/edusign/locallib.php');

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\helper;
use \core_privacy\local\request\userlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\manager;

/**
 * Privacy class for requesting user data.
 *
 * @package    mod_edusign
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\user_preference_provider,
        \core_privacy\local\request\core_userlist_provider {

    /** Interface for all edusign submission sub-plugins. */
    const edusignSUBMISSION_INTERFACE = 'mod_edusign\privacy\edusignsubmission_provider';

    /** Interface for all edusign submission sub-plugins. This allows for deletion of users with a context. */
    const edusignSUBMISSION_USER_INTERFACE = 'mod_edusign\privacy\edusignsubmission_user_provider';

    /** Interface for all edusign feedback sub-plugins. This allows for deletion of users with a context. */
    const edusignFEEDBACK_USER_INTERFACE = 'mod_edusign\privacy\edusignfeedback_user_provider';

    /** Interface for all edusign feedback sub-plugins. */
    const edusignFEEDBACK_INTERFACE = 'mod_edusign\privacy\edusignfeedback_provider';

    /**
     * Provides meta data that is stored about a user with mod_edusign
     *
     * @param  collection $collection A collection of meta data items to be added to.
     * @return  collection Returns the collection of metadata.
     */
    public static function get_metadata(collection $collection) : collection {
        $edusigngrades = [
                'userid' => 'privacy:metadata:userid',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'timemodified',
                'grader' => 'privacy:metadata:grader',
                'grade' => 'privacy:metadata:grade',
                'attemptnumber' => 'attemptnumber'
        ];
        $edusignoverrides = [
                'groupid' => 'privacy:metadata:groupid',
                'userid' => 'privacy:metadata:userid',
                'allowsubmissionsfromdate' => 'allowsubmissionsfromdate',
                'duedate' => 'duedate',
                'cutoffdate' => 'cutoffdate'
        ];
        $edusignsubmission = [
                'userid' => 'privacy:metadata:userid',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'timemodified',
                'status' => 'gradingstatus',
                'groupid' => 'privacy:metadata:groupid',
                'attemptnumber' => 'attemptnumber',
                'latest' => 'privacy:metadata:latest'
        ];
        $edusignuserflags = [
                'userid' => 'privacy:metadata:userid',
                'edusignment' => 'privacy:metadata:edusignmentid',
                'locked' => 'locksubmissions',
                'mailed' => 'privacy:metadata:mailed',
                'extensionduedate' => 'extensionduedate',
                'workflowstate' => 'markingworkflowstate',
                'allocatedmarker' => 'allocatedmarker'
        ];
        $edusignusermapping = [
                'edusignment' => 'privacy:metadata:edusignmentid',
                'userid' => 'privacy:metadata:userid'
        ];
        $collection->add_database_table('edusign_grades', $edusigngrades, 'privacy:metadata:edusigngrades');
        $collection->add_database_table('edusign_overrides', $edusignoverrides, 'privacy:metadata:edusignoverrides');
        $collection->add_database_table('edusign_submission', $edusignsubmission, 'privacy:metadata:edusignsubmissiondetail');
        $collection->add_database_table('edusign_user_flags', $edusignuserflags, 'privacy:metadata:edusignuserflags');
        $collection->add_database_table('edusign_user_mapping', $edusignusermapping, 'privacy:metadata:edusignusermapping');
        $collection->add_user_preference('edusign_perpage', 'privacy:metadata:edusignperpage');
        $collection->add_user_preference('edusign_filter', 'privacy:metadata:edusignfilter');
        $collection->add_user_preference('edusign_markerfilter', 'privacy:metadata:edusignmarkerfilter');
        $collection->add_user_preference('edusign_workflowfilter', 'privacy:metadata:edusignworkflowfilter');
        $collection->add_user_preference('edusign_quickgrading', 'privacy:metadata:edusignquickgrading');
        $collection->add_user_preference('edusign_downloadasfolders', 'privacy:metadata:edusigndownloadasfolders');

        // Link to subplugins.
        $collection->add_plugintype_link('edusignsubmission', [],'privacy:metadata:edusignsubmissionpluginsummary');
        $collection->add_plugintype_link('edusignfeedback', [], 'privacy:metadata:edusignfeedbackpluginsummary');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:edusignmessageexplanation');

        return $collection;
    }

    /**
     * Returns all of the contexts that has information relating to the userid.
     *
     * @param  int $userid The user ID.
     * @return contextlist an object with the contexts related to a userid.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $params = ['modulename' => 'edusign',
                   'contextlevel' => CONTEXT_MODULE,
                   'userid' => $userid,
                   'graderid' => $userid,
                   'aouserid' => $userid,
                   'asnuserid' => $userid,
                   'aufuserid' => $userid,
                   'aumuserid' => $userid];

        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                  JOIN {edusign} a ON cm.instance = a.id
                  JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {edusign_grades} ag ON a.id = ag.edusignment AND (ag.userid = :userid OR ag.grader = :graderid)";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                  JOIN {edusign} a ON cm.instance = a.id
                  JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {edusign_overrides} ao ON a.id = ao.edusignid
                 WHERE ao.userid = :aouserid";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                  JOIN {edusign} a ON cm.instance = a.id
                  JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {edusign_submission} asn ON a.id = asn.edusignment
                 WHERE asn.userid = :asnuserid";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                  JOIN {edusign} a ON cm.instance = a.id
                  JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {edusign_user_flags} auf ON a.id = auf.edusignment
                 WHERE auf.userid = :aufuserid";

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                  JOIN {edusign} a ON cm.instance = a.id
                  JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {edusign_user_mapping} aum ON a.id = aum.edusignment
                 WHERE aum.userid = :aumuserid";

        $contextlist->add_from_sql($sql, $params);

        manager::plugintype_class_callback('edusignfeedback', self::edusignFEEDBACK_INTERFACE,
                'get_context_for_userid_within_feedback', [$userid, $contextlist]);
        manager::plugintype_class_callback('edusignsubmission', self::edusignSUBMISSION_INTERFACE,
                'get_context_for_userid_within_submission', [$userid, $contextlist]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $params = [
            'modulename' => 'edusign',
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_MODULE
        ];

        $sql = "SELECT g.userid, g.grader
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {edusign} a ON a.id = cm.instance
                  JOIN {edusign_grades} g ON a.id = g.edusignment
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);
        $userlist->add_from_sql('grader', $sql, $params);

        $sql = "SELECT o.userid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {edusign} a ON a.id = cm.instance
                  JOIN {edusign_overrides} o ON a.id = o.edusignid
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT s.userid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {edusign} a ON a.id = cm.instance
                  JOIN {edusign_submission} s ON a.id = s.edusignment
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT uf.userid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {edusign} a ON a.id = cm.instance
                  JOIN {edusign_user_flags} uf ON a.id = uf.edusignment
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT um.userid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {edusign} a ON a.id = cm.instance
                  JOIN {edusign_user_mapping} um ON a.id = um.edusignment
                 WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);

        manager::plugintype_class_callback('edusignsubmission', self::edusignSUBMISSION_USER_INTERFACE,
                'get_userids_from_context', [$userlist]);
        manager::plugintype_class_callback('edusignfeedback', self::edusignFEEDBACK_USER_INTERFACE,
                'get_userids_from_context', [$userlist]);
    }

    /**
     * Write out the user data filtered by contexts.
     *
     * @param approved_contextlist $contextlist contexts that we are writing data out from.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            // Check that the context is a module context.
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $user = $contextlist->get_user();
            $edusigndata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);

            writer::with_context($context)->export_data([], $edusigndata);
            $edusign = new \edusign($context, null, null);

            // I need to find out if I'm a student or a teacher.
            if ($userids = self::get_graded_users($user->id, $edusign)) {
                // Return teacher info.
                $currentpath = [get_string('privacy:studentpath', 'mod_edusign')];
                foreach ($userids as $studentuserid) {
                    $studentpath = array_merge($currentpath, [$studentuserid->id]);
                    static::export_submission($edusign, $studentuserid, $context, $studentpath, true);
                }
            }

            static::export_overrides($context, $edusign, $user);
            static::export_submission($edusign, $user, $context, []);
            // Meta data.
            self::store_edusign_user_flags($context, $edusign, $user->id);
            if ($edusign->is_blind_marking()) {
                $uniqueid = $edusign->get_uniqueid_for_user_static($edusign->get_instance()->id, $contextlist->get_user()->id);
                if ($uniqueid) {
                    writer::with_context($context)
                            ->export_metadata([get_string('blindmarking', 'mod_edusign')], 'blindmarkingid', $uniqueid,
                                    get_string('privacy:blindmarkingidentifier', 'mod_edusign'));
                }
            }
        }
    }

    /**
     * Delete all use data which matches the specified context.
     *
     * @param \context $context The module context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('edusign', $context->instanceid);
            if ($cm) {
                // Get the edusignment related to this context.
                $edusign = new \edusign($context, null, null);
                // What to do first... Get sub plugins to delete their stuff.
                $requestdata = new edusign_plugin_request_data($context, $edusign);
                manager::plugintype_class_callback('edusignsubmission', self::edusignSUBMISSION_INTERFACE,
                    'delete_submission_for_context', [$requestdata]);
                $requestdata = new edusign_plugin_request_data($context, $edusign);
                manager::plugintype_class_callback('edusignfeedback', self::edusignFEEDBACK_INTERFACE,
                    'delete_feedback_for_context', [$requestdata]);
                $DB->delete_records('edusign_grades', ['edusignment' => $edusign->get_instance()->id]);

                // Delete advanced grading information.
                $gradingmanager = get_grading_manager($context, 'mod_edusign', 'submissions');
                $controller = $gradingmanager->get_active_controller();
                if (isset($controller)) {
                    \core_grading\privacy\provider::delete_instance_data($context);
                }

                // Time to roll my own method for deleting overrides.
                static::delete_overrides_for_users($edusign);
                $DB->delete_records('edusign_submission', ['edusignment' => $edusign->get_instance()->id]);
                $DB->delete_records('edusign_user_flags', ['edusignment' => $edusign->get_instance()->id]);
                $DB->delete_records('edusign_user_mapping', ['edusignment' => $edusign->get_instance()->id]);
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            // Get the edusign object.
            $edusign = new \edusign($context, null, null);
            $edusignid = $edusign->get_instance()->id;

            $submissions = $DB->get_records('edusign_submission', ['edusignment' => $edusignid, 'userid' => $user->id]);
            foreach ($submissions as $submission) {
                $requestdata = new edusign_plugin_request_data($context, $edusign, $submission, [], $user);
                manager::plugintype_class_callback('edusignsubmission', self::edusignSUBMISSION_INTERFACE,
                        'delete_submission_for_userid', [$requestdata]);
            }

            $grades = $DB->get_records('edusign_grades', ['edusignment' => $edusignid, 'userid' => $user->id]);
            $gradingmanager = get_grading_manager($context, 'mod_edusign', 'submissions');
            $controller = $gradingmanager->get_active_controller();
            foreach ($grades as $grade) {
                $requestdata = new edusign_plugin_request_data($context, $edusign, $grade, [], $user);
                manager::plugintype_class_callback('edusignfeedback', self::edusignFEEDBACK_INTERFACE,
                        'delete_feedback_for_grade', [$requestdata]);
                // Delete advanced grading information.
                if (isset($controller)) {
                    \core_grading\privacy\provider::delete_instance_data($context, $grade->id);
                }
            }

            static::delete_overrides_for_users($edusign, [$user->id]);
            $DB->delete_records('edusign_user_flags', ['edusignment' => $edusignid, 'userid' => $user->id]);
            $DB->delete_records('edusign_user_mapping', ['edusignment' => $edusignid, 'userid' => $user->id]);
            $DB->delete_records('edusign_grades', ['edusignment' => $edusignid, 'userid' => $user->id]);
            $DB->delete_records('edusign_submission', ['edusignment' => $edusignid, 'userid' => $user->id]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param  approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $userids = $userlist->get_userids();

        $edusign = new \edusign($context, null, null);
        $edusignid = $edusign->get_instance()->id;
        $requestdata = new edusign_plugin_request_data($context, $edusign);
        $requestdata->set_userids($userids);
        $requestdata->populate_submissions_and_grades();
        manager::plugintype_class_callback('edusignsubmission', self::edusignSUBMISSION_USER_INTERFACE, 'delete_submissions',
                [$requestdata]);
        manager::plugintype_class_callback('edusignfeedback', self::edusignFEEDBACK_USER_INTERFACE, 'delete_feedback_for_grades',
                [$requestdata]);

        // Update this function to delete advanced grading information.
        $gradingmanager = get_grading_manager($context, 'mod_edusign', 'submissions');
        $controller = $gradingmanager->get_active_controller();
        if (isset($controller)) {
            $gradeids = $requestdata->get_gradeids();
            // Careful here, if no gradeids are provided then all data is deleted for the context.
            if (!empty($gradeids)) {
                \core_grading\privacy\provider::delete_data_for_instances($context, $gradeids);
            }
        }

        static::delete_overrides_for_users($edusign, $userids);
        list($sql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['edusignment'] = $edusignid;
        $DB->delete_records_select('edusign_user_flags', "edusignment = :edusignment AND userid $sql", $params);
        $DB->delete_records_select('edusign_user_mapping', "edusignment = :edusignment AND userid $sql", $params);
        $DB->delete_records_select('edusign_grades', "edusignment = :edusignment AND userid $sql", $params);
        $DB->delete_records_select('edusign_submission', "edusignment = :edusignment AND userid $sql", $params);
    }

    /**
     * Deletes edusignment overrides in bulk
     *
     * @param  \edusign $edusign  The edusignment object
     * @param  array   $userids An array of user IDs
     */
    protected static function delete_overrides_for_users(\edusign $edusign, array $userids = []) {
        global $DB;
        $edusignid = $edusign->get_instance()->id;

        $usersql = '';
        $params = ['edusignid' => $edusignid];
        if (!empty($userids)) {
            list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $params = array_merge($params, $userparams);
            $overrides = $DB->get_records_select('edusign_overrides', "edusignid = :edusignid AND userid $usersql", $params);
        } else {
            $overrides = $DB->get_records('edusign_overrides', $params);
        }
        if (!empty($overrides)) {
            $params = ['modulename' => 'edusign', 'instance' => $edusignid];
            if (!empty($userids)) {
                $params = array_merge($params, $userparams);
                $DB->delete_records_select('event', "modulename = :modulename AND instance = :instance AND userid $usersql",
                        $params);
                // Setting up for the next query.
                $params = $userparams;
                $usersql = "AND userid $usersql";
            } else {
                $DB->delete_records('event', $params);
                // Setting up for the next query.
                $params = [];
            }
            list($overridesql, $overrideparams) = $DB->get_in_or_equal(array_keys($overrides), SQL_PARAMS_NAMED);
            $params = array_merge($params, $overrideparams);
            $DB->delete_records_select('edusign_overrides', "id $overridesql $usersql", $params);
        }
    }

    /**
     * Find out if this user has graded any users.
     *
     * @param  int $userid The user ID (potential teacher).
     * @param  edusign $edusign The edusignment object.
     * @return array If successful an array of objects with userids that this user graded, otherwise false.
     */
    protected static function get_graded_users(int $userid, \edusign $edusign) {
        $params = ['grader' => $userid, 'edusignid' => $edusign->get_instance()->id];

        $sql = "SELECT DISTINCT userid AS id
                  FROM {edusign_grades}
                 WHERE grader = :grader AND edusignment = :edusignid";

        $useridlist = new useridlist($userid, $edusign->get_instance()->id);
        $useridlist->add_from_sql($sql, $params);

        // Call sub-plugins to see if they have information not already collected.
        manager::plugintype_class_callback('edusignsubmission', self::edusignSUBMISSION_INTERFACE, 'get_student_user_ids',
                [$useridlist]);
        manager::plugintype_class_callback('edusignfeedback', self::edusignFEEDBACK_INTERFACE, 'get_student_user_ids', [$useridlist]);

        $userids = $useridlist->get_userids();
        return ($userids) ? $userids : false;
    }

    /**
     * Writes out various user meta data about the edusignment.
     *
     * @param  \context $context The context of this edusignment.
     * @param  \edusign $edusign The edusignment object.
     * @param  int $userid The user ID
     */
    protected static function store_edusign_user_flags(\context $context, \edusign $edusign, int $userid) {
        $datatypes = ['locked' => get_string('locksubmissions', 'mod_edusign'),
                      'mailed' => get_string('privacy:metadata:mailed', 'mod_edusign'),
                      'extensionduedate' => get_string('extensionduedate', 'mod_edusign'),
                      'workflowstate' => get_string('markingworkflowstate', 'mod_edusign'),
                      'allocatedmarker' => get_string('allocatedmarker_help', 'mod_edusign')];
        $userflags = (array)$edusign->get_user_flags($userid, false);

        foreach ($datatypes as $key => $description) {
            if (isset($userflags[$key]) && !empty($userflags[$key])) {
                $value = $userflags[$key];
                if ($key == 'locked' || $key == 'mailed') {
                    $value = transform::yesno($value);
                } else if ($key == 'extensionduedate') {
                    $value = transform::datetime($value);
                }
                writer::with_context($context)->export_metadata([], $key, $value, $description);
            }
        }
    }

    /**
     * Formats and then exports the user's grade data.
     *
     * @param  \stdClass $grade The edusign grade object
     * @param  \context $context The context object
     * @param  array $currentpath Current directory path that we are exporting to.
     */
    protected static function export_grade_data(\stdClass $grade, \context $context, array $currentpath) {
        $gradedata = (object)[
            'timecreated' => transform::datetime($grade->timecreated),
            'timemodified' => transform::datetime($grade->timemodified),
            'grader' => transform::user($grade->grader),
            'grade' => $grade->grade,
            'attemptnumber' => ($grade->attemptnumber + 1)
        ];
        writer::with_context($context)
                ->export_data(array_merge($currentpath, [get_string('privacy:gradepath', 'mod_edusign')]), $gradedata);
    }

    /**
     * Formats and then exports the user's submission data.
     *
     * @param  \stdClass $submission The edusign submission object
     * @param  \context $context The context object
     * @param  array $currentpath Current directory path that we are exporting to.
     */
    protected static function export_submission_data(\stdClass $submission, \context $context, array $currentpath) {
        $submissiondata = (object)[
            'timecreated' => transform::datetime($submission->timecreated),
            'timemodified' => transform::datetime($submission->timemodified),
            'status' => get_string('submissionstatus_' . $submission->status, 'mod_edusign'),
            'groupid' => $submission->groupid,
            'attemptnumber' => ($submission->attemptnumber + 1),
            'latest' => transform::yesno($submission->latest)
        ];
        writer::with_context($context)
                ->export_data(array_merge($currentpath, [get_string('privacy:submissionpath', 'mod_edusign')]), $submissiondata);
    }

    /**
     * Stores the user preferences related to mod_edusign.
     *
     * @param  int $userid The user ID that we want the preferences for.
     */
    public static function export_user_preferences(int $userid) {
        $context = \context_system::instance();
        $edusignpreferences = [
            'edusign_perpage' => ['string' => get_string('privacy:metadata:edusignperpage', 'mod_edusign'), 'bool' => false],
            'edusign_filter' => ['string' => get_string('privacy:metadata:edusignfilter', 'mod_edusign'), 'bool' => false],
            'edusign_markerfilter' => ['string' => get_string('privacy:metadata:edusignmarkerfilter', 'mod_edusign'), 'bool' => true],
            'edusign_workflowfilter' => ['string' => get_string('privacy:metadata:edusignworkflowfilter', 'mod_edusign'),
                    'bool' => true],
            'edusign_quickgrading' => ['string' => get_string('privacy:metadata:edusignquickgrading', 'mod_edusign'), 'bool' => true],
            'edusign_downloadasfolders' => ['string' => get_string('privacy:metadata:edusigndownloadasfolders', 'mod_edusign'),
                    'bool' => true]
        ];
        foreach ($edusignpreferences as $key => $preference) {
            $value = get_user_preferences($key, null, $userid);
            if ($preference['bool']) {
                $value = transform::yesno($value);
            }
            if (isset($value)) {
                writer::with_context($context)->export_user_preference('mod_edusign', $key, $value, $preference['string']);
            }
        }
    }

    /**
     * Export overrides for this edusignment.
     *
     * @param  \context $context Context
     * @param  \edusign $edusign The edusign object.
     * @param  \stdClass $user The user object.
     */
    public static function export_overrides(\context $context, \edusign $edusign, \stdClass $user) {

        $overrides = $edusign->override_exists($user->id);
        // Overrides returns an array with data in it, but an override with actual data will have the edusign ID set.
        if (isset($overrides->edusignid)) {
            $data = new \stdClass();
            if (!empty($overrides->duedate)) {
                $data->duedate = transform::datetime($overrides->duedate);
            }
            if (!empty($overrides->cutoffdate)) {
                $data->cutoffdate = transform::datetime($overrides->cutoffdate);
            }
            if (!empty($overrides->allowsubmissionsfromdate)) {
                $data->allowsubmissionsfromdate = transform::datetime($overrides->allowsubmissionsfromdate);
            }
            if (!empty($data)) {
                writer::with_context($context)->export_data([get_string('overrides', 'mod_edusign')], $data);
            }
        }
    }

    /**
     * Exports edusignment submission data for a user.
     *
     * @param  \edusign         $edusign           The edusignment object
     * @param  \stdClass        $user             The user object
     * @param  \context_module $context          The context
     * @param  array           $path             The path for exporting data
     * @param  bool|boolean    $exportforteacher A flag for if this is exporting data as a teacher.
     */
    protected static function export_submission(\edusign $edusign, \stdClass $user, \context_module $context, array $path,
            bool $exportforteacher = false) {
        $submissions = $edusign->get_all_submissions($user->id);
        $teacher = ($exportforteacher) ? $user : null;
        $gradingmanager = get_grading_manager($context, 'mod_edusign', 'submissions');
        $controller = $gradingmanager->get_active_controller();
        foreach ($submissions as $submission) {
            // Attempt numbers start at zero, which is fine for programming, but doesn't make as much sense
            // for users.
            $submissionpath = array_merge($path,
                    [get_string('privacy:attemptpath', 'mod_edusign', ($submission->attemptnumber + 1))]);

            $params = new edusign_plugin_request_data($context, $edusign, $submission, $submissionpath ,$teacher);
            manager::plugintype_class_callback('edusignsubmission', self::edusignSUBMISSION_INTERFACE,
                    'export_submission_user_data', [$params]);
            if (!isset($teacher)) {
                self::export_submission_data($submission, $context, $submissionpath);
            }
            $grade = $edusign->get_user_grade($user->id, false, $submission->attemptnumber);
            if ($grade) {
                $params = new edusign_plugin_request_data($context, $edusign, $grade, $submissionpath, $teacher);
                manager::plugintype_class_callback('edusignfeedback', self::edusignFEEDBACK_INTERFACE, 'export_feedback_user_data',
                        [$params]);

                self::export_grade_data($grade, $context, $submissionpath);
                // Check for advanced grading and retrieve that information.
                if (isset($controller)) {
                    \core_grading\privacy\provider::export_item_data($context, $grade->id, $submissionpath);
                }
            }
        }
    }
}
