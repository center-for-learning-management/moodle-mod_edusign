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
 * Restore date tests.
 *
 * @package    mod_edusign
 * @copyright  2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore date tests.
 *
 * @package    mod_edusign
 * @copyright  2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_edusign_restore_date_testcase extends restore_date_testcase {

    /**
     * Test restore dates.
     */
    public function test_restore_dates() {
        global $DB, $USER;

        $record = ['cutoffdate' => 100, 'allowsubmissionsfromdate' => 100, 'duedate' => 100, 'timemodified' => 100];
        list($course, $edusign) = $this->create_course_and_module('edusign', $record);
        $cm = $DB->get_record('course_modules', ['course' => $course->id, 'instance' => $edusign->id]);
        $edusignobj = new mod_edusign_testable_edusign(context_module::instance($cm->id), $cm, $course);
        $submission = $edusignobj->get_user_submission($USER->id, true);
        $grade = $edusignobj->get_user_grade($USER->id, true);

        // User override.
        $override = (object)[
            'edusignid' => $edusign->id,
            'groupid' => 0,
            'userid' => $USER->id,
            'sortorder' => 1,
            'allowsubmissionsfromdate' => 100,
            'duedate' => 200,
            'cutoffdate' => 300
        ];
        $DB->insert_record('edusign_overrides', $override);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $newedusign = $DB->get_record('edusign', ['course' => $newcourseid]);

        $this->assertFieldsNotRolledForward($edusign, $newedusign, ['timemodified']);
        $props = ['allowsubmissionsfromdate', 'duedate', 'cutoffdate'];
        $this->assertFieldsRolledForward($edusign, $newedusign, $props);

        $newsubmission = $DB->get_record('edusign_submission', ['edusignment' => $newedusign->id]);
        $newoverride = $DB->get_record('edusign_overrides', ['edusignid' => $newedusign->id]);
        $newgrade = $DB->get_record('edusign_grades', ['edusignment' => $newedusign->id]);

        // edusign submission time checks.
        $this->assertEquals($submission->timecreated, $newsubmission->timecreated);
        $this->assertEquals($submission->timemodified, $newsubmission->timemodified);

        // edusign override time checks.
        $diff = $this->get_diff();
        $this->assertEquals($override->duedate + $diff, $newoverride->duedate);
        $this->assertEquals($override->cutoffdate + $diff, $newoverride->cutoffdate);
        $this->assertEquals($override->allowsubmissionsfromdate + $diff, $newoverride->allowsubmissionsfromdate);

        // edusign grade time checks.
        $this->assertEquals($grade->timecreated, $newgrade->timecreated);
        $this->assertEquals($grade->timemodified, $newgrade->timemodified);

    }
}
