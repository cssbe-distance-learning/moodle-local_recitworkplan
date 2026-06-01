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
 *
 * @package   local_recitworkplan
 * @copyright RÉCIT 2019
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace recitworkplan;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once dirname(__FILE__)."/classes/PersistCtrl.php";

defined('MOODLE_INTERNAL') || die();

use moodle_url;

class MainView{
    public $cfg = null;
    public $user = null;
    public $page = null;
    public $output = null;
    public $selectedCourseId = 0;

    public function __construct($cfg, $page, $user, $output){
        $this->cfg = $cfg;
        $this->user = $user;
        $this->page = $page;
        $this->output = $output;
    }

    public function display(){
        $studentId = $this->user->id;
        $mode = $this->isTeacher() ? 'a' : 's';
        $workplanId = 0;
        if (isset($_GET['id'])){
            $workplanId = clean_param($_GET['id'], PARAM_INT);
        }
        echo sprintf("<div id='recit_workplan' class='recit_workplan' data-user-id='%d' data-mode='%s' data-workplanid='%d'></div>",
            $studentId, $mode, $workplanId);
    }

    public function isTeacher(){
        global $DB, $USER;
        $ctrl = PersistCtrl::getInstance($DB, $USER);
        return $ctrl->hasTeacherAccess($this->user->id);
    }
}

require_login();

// Globals.
$PAGE->set_url("/local/recitworkplan/view.php"); 
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/recitworkplan/react/build/index.css?201'), true);
$PAGE->requires->js(new moodle_url($CFG->wwwroot . '/local/recitworkplan/react/build/index.js?201'), true);

// Set page context.
$PAGE->set_context(\context_system::instance());

// Set page layout.
$PAGE->set_pagelayout('standard');

$PAGE->set_title(get_string('pluginname', 'local_recitworkplan'));
$PAGE->set_heading(get_string('pluginname', 'local_recitworkplan'));

echo $OUTPUT->header();
$recitDashboard = new MainView($CFG, $PAGE, $USER, $OUTPUT);
$recitDashboard->display();

echo $OUTPUT->footer();