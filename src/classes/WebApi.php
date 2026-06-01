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
namespace recitworkplan;

require_once(dirname(__FILE__).'../../../../config.php');
require_once dirname(__FILE__).'/recitcommon/WebApi.php';
require_once dirname(__FILE__).'/PersistCtrl.php';

use Exception;
use stdClass;

class WebApi extends MoodleApi
{
    protected $ctrl = null;

    public function __construct($DB, $COURSE, $USER){
        parent::__construct($DB, $COURSE, $USER);
        $this->ctrl = PersistCtrl::getInstance($DB, $USER);
    }

    protected function getAllowedServices() {
        return [
            'getWorkPlanList', 'getWorkPlan', 'getWorkPlanFormKit',
            'deleteWorkPlan', 'getStudentList', 'getTeacherList',
            'saveAssignment', 'deleteAssignment', 'getAssignmentAdditionalHours',
            'addAssignmentAdditionalHours', 'getCatCourseSectionActivityList',
            'getTemplateFormFormKit', 'saveTemplate', 'cloneTemplate',
            'saveTplAct', 'saveTplActOrder', 'deleteTplAct', 'processWorkPlan',
        ];
    }

    /**
     * $level [a = admin | s = student]
     */
    public function canUserAccess($level){
        global $DB;
        $isTeacher = $this->ctrl->hasTeacherAccess($this->signedUser->id);

        
         // if the level is admin then the user must have access to CAPABILITY
        if(($level == 'a') && $isTeacher){
            return true;
        }
        // if the user is student then it has access only if it is accessing its own stuff
        else if(($level == 's')){
            return true;
        }
        else{
            throw new Exception(get_string('accessdenied'));
        }
    }
    
    public function getWorkPlanList($request){
        try{
            $state = clean_param($request['state'], PARAM_TEXT);
            $forStudent = boolval($request['forStudent']);
            $limit = clean_param($request['limit'], PARAM_INT);
            $offset = clean_param($request['offset'], PARAM_INT);

            $orderBy = null;
            if(isset($request['orderBy'])){
                $orderBy = clean_param($request['orderBy'], PARAM_TEXT);
                $orderBy = explode(",", $orderBy);
            }

            if ($forStudent){
                // Students may only retrieve their own workplan list
                $userId = $this->signedUser->id;
                $this->canUserAccess('s');
            } else {
                $this->canUserAccess('a');
                $userId = clean_param($request['userId'], PARAM_INT);
                $userId = ($userId == 0 ? $this->signedUser->id : $userId);
            }

            if($state == 'template'){
                $result = $this->ctrl->getTemplateList($userId, $limit, $offset);
            }
            else{
                $result = $this->ctrl->getWorkPlanList($userId, $limit, $offset, $state, $forStudent, $orderBy);
            }
            
            $this->prepareJson($result);
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function getWorkPlan($request){
        try{
            $templateId = clean_param($request['templateId'], PARAM_INT);
            $studentId = clean_param($request['studentId'], PARAM_INT);
            $userId = ($studentId == 0 ? $this->signedUser->id : $studentId);

            // Only teachers may request another user's workplan
            if ($studentId > 0 && $studentId != $this->signedUser->id) {
                $this->canUserAccess('a');
            } else {
                $this->canUserAccess('s');
            }

            $result = $this->ctrl->getWorkPlan($userId, $templateId, ($studentId > 0));

            $this->prepareJson($result);
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function getWorkPlanFormKit($request){
        try{

            $this->canUserAccess('a');

            $templateId = clean_param($request['templateId'], PARAM_INT);

            $result = new stdClass();
            $result->data = ($templateId > 0 ? $this->ctrl->getWorkPlan($this->signedUser->id, $templateId, false) : new WorkPlan());

            $this->prepareJson($result);
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function deleteWorkPlan($request){
        try{
            $this->canUserAccess('a');
            $templateId = clean_param($request['templateId'], PARAM_INT);
            $this->ctrl->deleteWorkPlan($templateId);
            return new WebApiResult(true);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function getStudentList($request){
        try{
            $this->canUserAccess('a');

            $templateId = clean_param($request['templateId'], PARAM_INT);

            $result = $this->ctrl->getUserList($templateId, RECITWORKPLAN_FOLLOW_CAPABILITY);

            $this->prepareJson($result);
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function getTeacherList($request){
        try{
            $this->canUserAccess('a');

            $templateId = clean_param($request['templateId'], PARAM_INT);

            $result = $this->ctrl->getUserList($templateId, RECITWORKPLAN_ASSIGN_CAPABILITY);

            $this->prepareJson($result);
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function saveAssignment($request){
        try{
            $this->canUserAccess('a');
            $data = json_decode(json_encode($request['data']), FALSE);
            $calendar = clean_param($request['calendar'], PARAM_TEXT);
            $result = array();

            foreach ($data as $item){
                $item->id           = clean_param($item->id           ?? 0, PARAM_INT);
                $item->templateId   = clean_param($item->templateId   ?? 0, PARAM_INT);
                $item->nbHoursPerWeek = clean_param($item->nbHoursPerWeek ?? 0, PARAM_FLOAT);
                $item->startDate    = clean_param($item->startDate    ?? 0, PARAM_INT);
                $item->endDate      = clean_param($item->endDate      ?? 0, PARAM_INT);
                $item->comment      = clean_param($item->comment      ?? '', PARAM_TEXT);
                if (isset($item->completionState)) {
                    $item->completionState = clean_param($item->completionState, PARAM_INT);
                }
                if (isset($item->user)) {
                    $item->user->id = clean_param($item->user->id ?? 0, PARAM_INT);
                }
                $result[] = $this->ctrl->saveAssignment($item);
                if ($calendar == 'update'){
                    $this->ctrl->deleteCalendarEvent($item->id, $item->user->id);
                    $this->ctrl->addCalendarEvent($item->templateId, $item->user->id);
                }
                else if ($calendar == 'delete'){
                    $this->ctrl->deleteCalendarEvent($item->id, $item->user->id);
                }
            }
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function deleteAssignment($request){
        try{
            $this->canUserAccess('a');
            $assignmentId = clean_param($request['assignmentId'], PARAM_INT);
            $this->ctrl->deleteAssignment($assignmentId);
            $this->ctrl->deleteCalendarEvent($assignmentId);
            return new WebApiResult(true);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function getAssignmentAdditionalHours($request){
        try{
            $this->canUserAccess('a');
            $assignmentId = clean_param($request['assignmentId'], PARAM_INT);
            $result = $this->ctrl->getAssignmentAdditionalHours($assignmentId);
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function addAssignmentAdditionalHours($request){
        try{
            $this->canUserAccess('a');
            $data = json_decode(json_encode($request['data']), FALSE);
            $result = array();

            foreach ($data as $item){
                $item->id                   = clean_param($item->id                   ?? 0, PARAM_INT);
                $item->templateId           = clean_param($item->templateId           ?? 0, PARAM_INT);
                $item->nbAdditionalHours    = clean_param($item->nbAdditionalHours    ?? 0, PARAM_FLOAT);
                $item->additionalHoursReason = clean_param($item->additionalHoursReason ?? '', PARAM_TEXT);
                $result[] = $this->ctrl->addAssignmentAdditionalHours($item);
            }

            if(count($data) > 0){
                $this->ctrl->processWorkPlan(current($data)->templateId);
            }
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }
    
    public function getCatCourseSectionActivityList($request){   
        try{
            $enrolled = boolval($request['enrolled']);
            $categoryId = clean_param($request['categoryId'], PARAM_INT);
            $courseId = clean_param($request['courseId'], PARAM_INT);
            
            if($enrolled){
                $this->canUserAccess('s', 0, $this->signedUser->id);
            }
            else{
                $this->canUserAccess('a');
            }
           
            $result = $this->ctrl->getCatCourseSectionActivityList($enrolled, $categoryId, $courseId);
			$this->prepareJson($result);            
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, null, $ex->GetMessage());
        }        
    }

    public function getTemplateFormFormKit($request){
        try{
            $this->canUserAccess('a');

            $templateId = clean_param($request['templateId'], PARAM_INT);

            $result = new stdClass();
            $result->data = ($templateId > 0 ? $this->ctrl->getTemplate($this->signedUser->id, $templateId) : new Template());
            $result->catCourseList = $this->ctrl->getCatCourseSectionActivityList(true);

            $this->prepareJson($result);
            
            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function saveTemplate($request){
        try{
            $this->canUserAccess('a');

            $data = json_decode(json_encode($request['data']), FALSE);

            $data->id               = clean_param($data->id               ?? 0,   PARAM_INT);
            $data->name             = clean_param($data->name             ?? '',   PARAM_TEXT);
            $data->description      = clean_param($data->description      ?? '',   PARAM_CLEANHTML);
            $data->communicationUrl = clean_param($data->communicationUrl ?? '',   PARAM_URL);
            $data->state            = clean_param($data->state            ?? 0,   PARAM_INT);
            $data->type             = clean_param($data->type             ?? 'd',  PARAM_ALPHA);
            if (!empty($data->collaboratorList)) {
                foreach ($data->collaboratorList as $u) {
                    $u->userId = clean_param($u->userId ?? 0, PARAM_INT);
                }
            }

            $result = $this->ctrl->saveTemplate($data);

            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function cloneTemplate($request){
        try{
            $this->canUserAccess('a');
            $templateId = clean_param($request['templateId'], PARAM_INT);
            $options = json_decode(json_encode($request['options']), FALSE);
            $options->state             = clean_param($options->state            ?? 0, PARAM_INT);
            $options->keepCollaborators = (bool)($options->keepCollaborators     ?? false);
            $result = $this->ctrl->cloneTemplate($templateId, $options);
            return new WebApiResult(true, array('id' => $result));
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function saveTplAct($request){
        try{
            $this->canUserAccess('a');

            $data = json_decode(json_encode($request['data']), FALSE);

            $data->id               = clean_param($data->id               ?? 0, PARAM_INT);
            $data->templateId       = clean_param($data->templateId       ?? 0, PARAM_INT);
            $data->cmId             = clean_param($data->cmId             ?? 0, PARAM_INT);
            $data->slot             = clean_param($data->slot             ?? 0, PARAM_INT);
            $data->nbHoursCompletion = clean_param($data->nbHoursCompletion ?? 0, PARAM_FLOAT);

            $result = $this->ctrl->saveTplAct($data);

            //We do not call processworkplan each time, we only call it when closing the modal
            //$this->ctrl->processWorkPlan($result->templateId);

            return new WebApiResult(true, $result);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }

    public function saveTplActOrder($request){
        try{
            $this->canUserAccess('a');

            $data = json_decode(json_encode($request['data']), FALSE);
            $data->slot = clean_param($data->slot, PARAM_INT);
            $data->tplActId = clean_param($data->tplActId, PARAM_INT);
            $data->templateId = clean_param($data->templateId, PARAM_INT);
            
            $this->ctrl->saveTplActOrder($data);
            //We do not call processworkplan each time, we only call it when closing the modal
            //$this->ctrl->processWorkPlan($data->templateId);

            return new WebApiResult(true);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    }
    
    public function deleteTplAct($request){
        try{
            $this->canUserAccess('a');
            $tplActId = clean_param($request['tplActId'], PARAM_INT);
            $this->ctrl->deleteTplAct($tplActId);
            //We do not call processworkplan each time, we only call it when closing the modal
            //$this->ctrl->processWorkPlan($templateId);
            return new WebApiResult(true);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    } 
    
    public function processWorkPlan($request){
        try{
            $this->canUserAccess('a');
            $templateId = clean_param($request['templateId'], PARAM_INT);
            $this->ctrl->processWorkPlan($templateId);
            return new WebApiResult(true);
        }
        catch(Exception $ex){
            return new WebApiResult(false, false, $ex->GetMessage());
        }
    } 
}

///////////////////////////////////////////////////////////////////////////////////

$PAGE->set_context(\context_system::instance());
$webapi = new WebApi($DB, $COURSE, $USER);
$webapi->getRequest($_REQUEST);
$webapi->processRequest();
$webapi->replyClient();