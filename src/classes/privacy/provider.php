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
 * @package   local_recitworkplan
 * @copyright 2019 RÉCIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recitworkplan\privacy;
require_once dirname(__FILE__)."/../PersistCtrl.php";
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use \core_privacy\local\request\approved_userlist;

defined('MOODLE_INTERNAL') || die();

class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'recit_wp_tpl',
            [
                'id' => 'privacy:metadata:recit_wp_tpl_assign:templateid',
                'creatorid' => 'privacy:metadata:recit_wp_tpl:creatorid',
                'collaboratorids' => 'privacy:metadata:recit_wp_tpl:collaboratorids',
                'name' => 'privacy:metadata:recit_wp_tpl:name',
                'description' => 'privacy:metadata:recit_wp_tpl:description',
                'communication_url' => 'privacy:metadata:recit_wp_tpl:communication_url',
                'options' => 'privacy:metadata:recit_wp_tpl:options',
                'state' => 'privacy:metadata:recit_wp_tpl:state',
                'lastupdate' => 'privacy:metadata:recit_wp_tpl:lastupdate',
            ],
            'privacy:metadata:recit_wp_tpl'
        );
        $collection->add_database_table(
            'recit_wp_tpl_assign',
            [
                'templateid' => 'privacy:metadata:recit_wp_tpl_assign:templateid',
                'userid' => 'privacy:metadata:recit_wp_tpl_assign:userid',
                'nb_hours_per_week' => 'privacy:metadata:recit_wp_tpl_assign:nb_hours_per_week',
                'startdate' => 'privacy:metadata:recit_wp_tpl_assign:startdate',
                'completionstate' => 'privacy:metadata:recit_wp_tpl_assign:completionstate',
                'assignorid' => 'privacy:metadata:recit_wp_tpl_assign:assignorid',
                'comment' => 'privacy:metadata:recit_wp_tpl_assign:comment',
                'lastupdate' => 'privacy:metadata:recit_wp_tpl_assign:lastupdate',
            ],
            'privacy:metadata:recit_wp_tpl_assign'
        );
        $collection->add_database_table(
            'recit_wp_additional_hours',
            [
                'assignmentid' => 'privacy:metadata:recit_wp_tpl_assign:templateid',
                'nb_additional_hours' => 'privacy:metadata:recit_wp_additional_hours:nb_additional_hours',
                'assignorid' => 'privacy:metadata:recit_wp_tpl_assign:assignorid',
                'comment' => 'privacy:metadata:recit_wp_tpl_assign:comment',
                'lastupdate' => 'privacy:metadata:recit_wp_tpl_assign:lastupdate',
            ],
            'privacy:metadata:recit_wp_additional_hours'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid) : contextlist {
        $params = ['userid' => $userid, 'contextuser' => CONTEXT_USER];
        $sql = "SELECT id
                  FROM {context}
                 WHERE instanceid = :userid and contextlevel = :contextuser";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_user) {
            return;
        }

        $sql = "SELECT userid
                  FROM {recit_wp_tpl_assign}
                 WHERE userid = ?";
        $params = [$context->instanceid];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $subcontext = [get_string('pluginname', 'local_recitworkplan')];

        // Student assignments.
        $instances = $DB->get_records_sql(
            "SELECT * FROM {recit_wp_tpl_assign} WHERE userid = :userid",
            ['userid' => $userid]
        );
        foreach ($instances as $instance) {
            writer::with_context($context)->export_data($subcontext, $instance);
        }

        // Templates created by this user.
        $instances = $DB->get_records_sql(
            "SELECT * FROM {recit_wp_tpl} WHERE creatorid = :userid",
            ['userid' => $userid]
        );
        foreach ($instances as $instance) {
            writer::with_context($context)->export_data($subcontext, $instance);
        }

        // Additional hours added by this user (as teacher/assignor).
        $instances = $DB->get_records_sql(
            "SELECT * FROM {recit_wp_additional_hours} WHERE assignorid = :userid",
            ['userid' => $userid]
        );
        foreach ($instances as $instance) {
            writer::with_context($context)->export_data($subcontext, $instance);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        self::deleteUserData($DB, $userid);
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            self::deleteUserData($DB, $context->instanceid);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        self::deleteUserData($DB, $userid);
    }

    /**
     * Delete all personal data for a user: assignments, additional hours, created
     * templates, and presence in collaborator lists.
     */
    protected static function deleteUserData($DB, $userid) {
        // Delete additional hours linked to the user's own assignments.
        $DB->execute(
            "DELETE FROM {recit_wp_additional_hours}
              WHERE assignmentid IN (SELECT id FROM {recit_wp_tpl_assign} WHERE userid = ?)",
            [$userid]
        );

        // Delete the user's assignments.
        $DB->delete_records('recit_wp_tpl_assign', ['userid' => $userid]);

        // Delete additional hours entries where this user is the assignor/teacher.
        $DB->delete_records('recit_wp_additional_hours', ['assignorid' => $userid]);

        // Delete calendar events for this user.
        $DB->delete_records('event', ['userid' => $userid, 'eventtype' => 'planformation']);

        // Delete templates created by this user (and their activities/assignments).
        $templates = $DB->get_records('recit_wp_tpl', ['creatorid' => $userid]);
        foreach ($templates as $plan) {
            self::deletePlan($plan->id);
        }

        // Remove this user from the collaboratorids list of any templates they appear in.
        self::removeFromCollaborators($DB, $userid);
    }

    /**
     * Remove a user ID from the comma-separated collaboratorids field of all templates.
     */
    protected static function removeFromCollaborators($DB, $userid) {
        $templates = $DB->get_records_sql(
            "SELECT id, collaboratorids FROM {recit_wp_tpl} WHERE collaboratorids != '' AND collaboratorids IS NOT NULL"
        );
        foreach ($templates as $template) {
            $ids = array_filter(array_map('intval', explode(',', $template->collaboratorids)));
            if (in_array((int)$userid, $ids)) {
                $newIds = array_values(array_filter($ids, function($id) use ($userid) {
                    return $id !== (int)$userid;
                }));
                $DB->set_field('recit_wp_tpl', 'collaboratorids', implode(',', $newIds), ['id' => $template->id]);
            }
        }
    }

    public static function deletePlan($id) {
        global $DB, $USER;
        $ctrl = \recitworkplan\PersistCtrl::getInstance($DB, $USER);
        return $ctrl->deleteWorkPlan($id);
    }
}
