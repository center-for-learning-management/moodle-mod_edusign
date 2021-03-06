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
 * This file contains the class for restore of this submission plugin
 *
 * @package edusignsubmission_signing
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore subplugin class.
 *
 * Provides the necessary information needed to restore
 * one edusign_submission subplugin.
 *
 * @package edusignsubmission_signing
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_edusignsubmission_signing_subplugin extends restore_subplugin {

    /**
     * Returns array the paths to be handled by the subplugin at edusignment level
     *
     * @return array
     */
    protected function define_submission_subplugin_structure() {

        $paths = array();

        $elename = $this->get_namefor('submission');

        // We used get_recommended_name() so this works.
        $elepath = $this->get_pathfor('/submission_signing');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Processes one edusignsubmission_signing element
     *
     * @param mixed $data
     */
    public function process_edusignsubmission_signing_submission($data) {
        global $DB;

        $data = (object) $data;
        $data->edusignment = $this->get_new_parentid('edusign');
        $oldsubmissionid = $data->submission;
        // The mapping is set in the restore for the core edusign activity
        // when a submission node is processed.
        $data->submission = $this->get_mappingid('submission', $data->submission);

        $DB->insert_record('edusignsubmission_signing', $data);

        $this->add_related_files('edusignsubmission_signing', 'submissions_signing', 'submission', null, $oldsubmissionid);
    }

}
