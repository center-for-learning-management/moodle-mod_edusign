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
 * This page handles editing and creation of edusign overrides
 *
 * @package   mod_edusign
 * @copyright 2016 Ilya Tregubov
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/edusign/lib.php');
require_once($CFG->dirroot . '/mod/edusign/locallib.php');
require_once($CFG->dirroot . '/mod/edusign/override_form.php');

$cmid = optional_param('cmid', 0, PARAM_INT);
$overrideid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);
$reset = optional_param('reset', false, PARAM_BOOL);

$pagetitle = get_string('editoverride', 'edusign');

$override = null;
if ($overrideid) {

    if (!$override = $DB->get_record('edusign_overrides', array('id' => $overrideid))) {
        print_error('invalidoverrideid', 'edusign');
    }

    list($course, $cm) = get_course_and_cm_from_instance($override->edusignid, 'edusign');

} else if ($cmid) {
    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'edusign');

} else {
    print_error('invalidcoursemodule');
}

$url = new moodle_url('/mod/edusign/overrideedit.php');
if ($action) {
    $url->param('action', $action);
}
if ($overrideid) {
    $url->param('id', $overrideid);
} else {
    $url->param('cmid', $cmid);
}

$PAGE->set_url($url);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
$edusign = new edusign($context, $cm, $course);
$edusigninstance = $edusign->get_instance();

// Add or edit an override.
require_capability('mod/assign:manageoverrides', $context);

if ($overrideid) {
    // Editing an override.
    $data = clone $override;

    if ($override->groupid) {
        if (!groups_group_visible($override->groupid, $course, $cm)) {
            print_error('invalidoverrideid', 'edusign');
        }
    } else {
        if (!groups_user_groups_visible($course, $override->userid, $cm)) {
            print_error('invalidoverrideid', 'edusign');
        }
    }
} else {
    // Creating a new override.
    $data = new stdClass();
}

// Merge edusign defaults with data.
$keys = array('duedate', 'cutoffdate', 'allowsubmissionsfromdate');
foreach ($keys as $key) {
    if (!isset($data->{$key}) || $reset) {
        $data->{$key} = $edusigninstance->{$key};
    }
}

// True if group-based override.
$groupmode = !empty($data->groupid) || ($action === 'addgroup' && empty($overrideid));

// If we are duplicating an override, then clear the user/group and override id
// since they will change.
if ($action === 'duplicate') {
    $override->id = $data->id = null;
    $override->userid = $data->userid = null;
    $override->groupid = $data->groupid = null;
    $pagetitle = get_string('duplicateoverride', 'edusign');
}

$overridelisturl = new moodle_url('/mod/edusign/overrides.php', array('cmid' => $cm->id));
if (!$groupmode) {
    $overridelisturl->param('mode', 'user');
}

// Setup the form.
$mform = new edusign_override_form($url, $cm, $edusign, $context, $groupmode, $override);
$mform->set_data($data);

if ($mform->is_cancelled()) {
    redirect($overridelisturl);

} else if (optional_param('resetbutton', 0, PARAM_ALPHA)) {
    $url->param('reset', true);
    redirect($url);

} else if ($fromform = $mform->get_data()) {
    // Process the data.
    $fromform->edusignid = $edusigninstance->id;

    // Replace unchanged values with null.
    foreach ($keys as $key) {
        if (($fromform->{$key} == $edusigninstance->{$key})) {
            $fromform->{$key} = null;
        }
    }

    // See if we are replacing an existing override.
    $userorgroupchanged = false;
    if (empty($override->id)) {
        $userorgroupchanged = true;
    } else if (!empty($fromform->userid)) {
        $userorgroupchanged = $fromform->userid !== $override->userid;
    } else {
        $userorgroupchanged = $fromform->groupid !== $override->groupid;
    }

    if ($userorgroupchanged) {
        $conditions = array(
                'edusignid' => $edusigninstance->id,
                'userid' => empty($fromform->userid) ? null : $fromform->userid,
                'groupid' => empty($fromform->groupid) ? null : $fromform->groupid);
        if ($oldoverride = $DB->get_record('edusign_overrides', $conditions)) {
            // There is an old override, so we merge any new settings on top of
            // the older override.
            foreach ($keys as $key) {
                if (is_null($fromform->{$key})) {
                    $fromform->{$key} = $oldoverride->{$key};
                }
            }

            $edusign->delete_override($oldoverride->id);
        }
    }

    // Set the common parameters for one of the events we may be triggering.
    $params = array(
            'context' => $context,
            'other' => array(
                    'edusignid' => $edusigninstance->id
            )
    );
    if (!empty($override->id)) {
        $fromform->id = $override->id;
        $DB->update_record('edusign_overrides', $fromform);

        // Determine which override updated event to fire.
        $params['objectid'] = $override->id;
        if (!$groupmode) {
            $params['relateduserid'] = $fromform->userid;
            $event = \mod_edusign\event\user_override_updated::create($params);
        } else {
            $params['other']['groupid'] = $fromform->groupid;
            $event = \mod_edusign\event\group_override_updated::create($params);
        }

        // Trigger the override updated event.
        $event->trigger();
    } else {
        unset($fromform->id);
        $fromform->id = $DB->insert_record('edusign_overrides', $fromform);
        if ($groupmode) {
            $fromform->sortorder = 1;

            $overridecountgroup = $DB->count_records('edusign_overrides',
                    array('userid' => null, 'edusignid' => $edusigninstance->id));
            $overridecountall = $DB->count_records('edusign_overrides', array('edusignid' => $edusigninstance->id));
            if ((!$overridecountgroup) && ($overridecountall)) { // No group overrides and there are user overrides.
                $fromform->sortorder = 1;
            } else {
                $fromform->sortorder = $overridecountgroup;

            }

            $DB->update_record('edusign_overrides', $fromform);
            reorder_group_overrides($edusigninstance->id);
        }

        // Determine which override created event to fire.
        $params['objectid'] = $fromform->id;
        if (!$groupmode) {
            $params['relateduserid'] = $fromform->userid;
            $event = \mod_edusign\event\user_override_created::create($params);
        } else {
            $params['other']['groupid'] = $fromform->groupid;
            $event = \mod_edusign\event\group_override_created::create($params);
        }

        // Trigger the override created event.
        $event->trigger();
    }

    edusign_update_events($edusign, $fromform);

    if (!empty($fromform->submitbutton)) {
        redirect($overridelisturl);
    }

    // The user pressed the 'again' button, so redirect back to this page.
    $url->remove_params('cmid');
    $url->param('action', 'duplicate');
    $url->param('id', $fromform->id);
    redirect($url);

}

// Print the form.
$PAGE->navbar->add($pagetitle);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($edusigninstance->name, true, array('context' => $context)));

$mform->display();

echo $OUTPUT->footer();
