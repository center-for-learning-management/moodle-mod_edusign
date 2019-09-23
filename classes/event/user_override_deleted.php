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
 * The mod_edusign user override deleted event.
 *
 * @package    mod_edusign
 * @copyright  2016 Ilya Tregubov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_edusign\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_edusign user override deleted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int edusignid: the id of the edusign.
 * }
 *
 * @package    mod_edusign
 * @since      Moodle 3.2
 * @copyright  2016 Ilya Tregubov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_override_deleted extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'edusign_overrides';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventoverridedeleted', 'mod_edusign');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted the override with id '$this->objectid' for the edusign with " .
            "course module id '$this->contextinstanceid' for the user with id '{$this->relateduserid}'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/edusign/overrides.php', array('cmid' => $this->contextinstanceid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['edusignid'])) {
            throw new \coding_exception('The \'edusignid\' value must be set in other.');
        }
    }

    /**
     * Get objectid mapping
     */
    public static function get_objectid_mapping() {
        return array('db' => 'edusign_overrides', 'restore' => 'edusign_override');
    }

    /**
     * Get other mapping
     */
    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['edusignid'] = array('db' => 'edusign', 'restore' => 'edusign');

        return $othermapped;
    }
}
