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
 * Event observers.
 *
 * @package   local_recitworkplan
 * @copyright 2019 RÉCIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recitworkplan;

defined('MOODLE_INTERNAL') || die();

class observer {
    /**
     * Update the workplan assignment state when a completion state changes.
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event): void {
        global $CFG, $DB, $USER; // $CFG must be in scope: the legacy class file uses it at file level.

        require_once($CFG->dirroot . '/local/recitworkplan/classes/PersistCtrl.php');

        \recitworkplan\PersistCtrl::getInstance($DB, $USER)
            ->setAssignmentCompletionState($event->relateduserid, $event->contextinstanceid);
    }

    /**
     * Remove template activities linked to a deleted course module.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        $DB->delete_records('recit_wp_tpl_act', ['cmid' => $event->contextinstanceid]);
    }
}
