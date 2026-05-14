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

require_once "$CFG->dirroot/user/externallib.php";
require_once "$CFG->dirroot/calendar/lib.php";
require_once __DIR__ . '/../lib.php';
require_once __DIR__ . '/recitcommon/PersistCtrl.php';

use DateTime;
use Exception;
use stdClass;

/**
 * Singleton class
 */
class PersistCtrl extends MoodlePersistCtrl
{
    protected static $instance = null;
    
    /**
     * @param MySQL Resource
     * @return PersistCtrl
     */
    public static function getInstance($mysqlConn = null, $signedUser = null)
    {
        if(!isset(self::$instance)) {
            self::$instance = new self($mysqlConn, $signedUser);
        }
        return self::$instance;
    }
    
    protected function __construct($mysqlConn, $signedUser){
        parent::__construct($mysqlConn, $signedUser);
    }
    
    public function getContextAccessIds($userId, $capabilities, $contextlevel){
        global $DB;
        
        $ids = array();
        if ($contextlevel == 40){//Categories
            $categories = $DB->get_records_sql("SELECT id FROM {course_categories}");
            foreach ($categories as $cat){
                $hasAccess = false;
                $ccontext = \context_coursecat::instance($cat->id);
                foreach ($capabilities as $c){
                    if (has_capability($c, $ccontext, $userId, false)) {
                        $hasAccess = true;
                    }
                }
                if ($hasAccess){
                    $ids[] = $cat->id;
                }
            }
        }else if ($contextlevel == 50){//Courses
            $courses = enrol_get_users_courses($userId, false, 'id, shortname');
            foreach ($courses as $course){
                $hasAccess = false;
                $ccontext = \context_course::instance($course->id);
                foreach ($capabilities as $c){
                    if (has_capability($c, $ccontext, $userId, false)) {
                        $hasAccess = true;
                    }
                }
                if ($hasAccess){
                    $ids[] = $course->id;
                }
            }
        }
        $ids = implode(',', $ids);
        if (empty($ids)) $ids = '0';
        return $ids;
    }

    public function hasTeacherAccess($userId){
        $capabilities = array(RECITWORKPLAN_ASSIGN_CAPABILITY, RECITWORKPLAN_MANAGE_CAPABILITY);
        $isTeacher = $this->getContextAccessIds($userId, $capabilities, 50) != '0' || $this->getContextAccessIds($userId, $capabilities, 40) != '0';
        return $isTeacher;
    }

    public function getCatCourseSectionActivityList($enrolled = false, $categoryId = 0, $courseId = 0){
        global $DB;

        $enrolledStmt = "";
        $extraFields = "";
        $extraJoin = "";
        $whereStmt = " true ";
        $extraOrder = "";
        $params = array();

        if($enrolled){
            $enrolledStmt = ",(select st2.id from {enrol} st1 inner join {user_enrolments}  st2 on st1.id = st2.enrolid where st1.courseid = t2.id and st2.userid =:userid1 limit 1) enrolled";
            $whereStmt .= " and (enrolled is not null) ";
            $params['userid1'] = $this->signedUser->id;
        }

        if($categoryId > 0){
            $whereStmt .= " and (categoryid =:categoryid) ";
            $params['categoryid'] = $categoryId;
        }

        if($courseId > 0){
            $whereStmt .= " and (courseid =:courseid)";
            $params['courseid'] = $courseId;
        }

        $query = "select * from
        (select ". $this->sql_uniqueid() ." uniqueid, t1.id categoryid, t1.name categoryname, t2.id courseid, t2.shortname coursename, t1.parent, t1.depth, t2.sortorder sortorder
        $extraFields
        $enrolledStmt
        from {course_categories} t1
        left join {course} t2 on t1.id = t2.category and t2.visible = 1
        $extraJoin) tab
        where $whereStmt
        order by depth, sortorder asc".$extraOrder;
        
        $rst = $DB->get_records_sql($query, $params);

        $result = array();
		foreach($rst as $item){
            $catcontext = \context_coursecat::instance($item->categoryid);
            $item->categoryroles = '';
            if (has_capability(RECITWORKPLAN_MANAGE_CAPABILITY, $catcontext, $this->signedUser->id, false)) {
                $item->categoryroles = 'm';
            }
            if (!isset($result[$item->categoryid])){
                $result[$item->categoryid] = MoodleCategory::create($item);
            }
            $ccontext = \context_course::instance($item->courseid);
            if (has_capability(RECITWORKPLAN_MANAGE_CAPABILITY, $ccontext, $this->signedUser->id, false) || has_capability(RECITWORKPLAN_ASSIGN_CAPABILITY, $ccontext, $this->signedUser->id, false)) {
                $item->roles = 'm';
                $course = MoodleCourse::create($item);
                $result[$item->categoryid]->addCourse($course);

                if($courseId > 0){
                    $modinfo = get_fast_modinfo($item->courseid);
                    $course->addCourseData($modinfo);
                }
            }
            
        }
        

        return array_values($result);
    }

    public function getTemplateList($userId, $limit = 0, $offset = 0){
        
        $capabilities = array(RECITWORKPLAN_ASSIGN_CAPABILITY, RECITWORKPLAN_MANAGE_CAPABILITY);
        $catIds = $this->getContextAccessIds($userId, $capabilities, 40);
        $courseIds = $this->getContextAccessIds($userId, $capabilities, 50);
        $where = "and (t3.id is null or (t7.category in (".$catIds.") or t5.course in (".$courseIds.")))";
        $wherenoaccess = "and (t2.creatorid = ? and (t7.category not in (".$catIds.") and t5.course not in (".$courseIds.")))";

        $subquery = "select ". $this->sql_uniqueid() ." uniqueid, t2.id templateid, t2.creatorid, t2.name templatename, t2.state templatestate, t2.tpltype templatetype, t7.fullname coursename, t7.id courseid,
        t2.description templatedesc, t2.communication_url communicationurl, t2.lastupdate lastupdate, t3.cmid, t3.nb_hours_completion nbhourscompletion, 
        t8.name categoryname, t3.id tplactid, %s has_access
        from  {recit_wp_tpl} t2 
        left join {recit_wp_tpl_act} t3 on t3.templateid = t2.id
        left join {course_modules} t5 on t3.cmid = t5.id
        left join {course} t7 on t7.id = t5.course
        left join {course_categories} t8 on t7.category = t8.id
        where t2.state = 1 %s";
        
        //Fetch templates that user has category / course access with flag '1' union with templates that user created but does not have access anymore with flag '0'
        $query = sprintf($subquery, '1', $where) . " union ".sprintf($subquery, '0', $wherenoaccess);

        if ($limit > 0){
            $offsetsql = $offset * $limit;
            //$query .= " LIMIT $limit OFFSET $offsetsql";
        }

        $rst = $this->getRecordsSQL($query, [$userId]);

        $workPlanList = array();
        $total_count = 0;
		foreach($rst as $item){
            if(!isset($workPlanList[$item->templateid])){
                $workPlanList[$item->templateid] = new WorkPlan();
            }
            $workPlanList[$item->templateid]->addTemplateActivity($item);
        }  

        $pagination = new Pagination();
        $pagination->items = array_values($workPlanList);
        $pagination->current_offset = $offset;
        $pagination->total_count = $total_count;

        return $pagination;
    }

    public function getTemplate($userId, $templateId){
        $capabilities = array(RECITWORKPLAN_ASSIGN_CAPABILITY, RECITWORKPLAN_MANAGE_CAPABILITY);
        $where = "and (t4.id is null or (t4.category in (".$this->getContextAccessIds($userId, $capabilities, 40).") or t3.course in (".$this->getContextAccessIds($userId, $capabilities, 50).")))";

        $query = "select ". $this->sql_uniqueid() ." uniqueid, t1.id templateid, t1.creatorid, t1.name templatename, t1.state templatestate, t1.tpltype templatetype, t1.communication_url communicationurl, t1.description templatedesc, (case when t1.lastupdate > 0 then t1.lastupdate else null end) lastupdate, t4.fullname coursename, 
        t2.id tplactid, t2.cmid, t2.nb_hours_completion nbhourscompletion, t2.slot, coalesce(t4.id,0) courseid, t4.shortname coursename, t5.id categoryid, t5.name categoryname, t1.collaboratorids, t1.options templateoptions
        from {recit_wp_tpl} t1
        left join {recit_wp_tpl_act} t2 on t1.id = t2.templateid
        left join {course_modules} t3 on t2.cmid = t3.id
        left join {course} t4 on t3.course = t4.id
        left join {course_categories} t5 on t4.category = t5.id
        where t1.id = ? $where
        order by t4.id asc";//--left join for templates with no activities, otherwise it'd return null

        $rst = $this->getRecordsSQL($query, [$templateId]);

        $modinfo = null;
        $result = null;
		foreach($rst as $item){           
            if($result == null){
                $result = Template::create($item);
            }          
            
            //if($item->courseid == 0){ continue;}
            if($item->cmid == 0){ continue;}

            if(($modinfo == null || $modinfo->__get('courseid') != $item->courseid) && ($item->courseid > 0)){
                $modinfo = get_fast_modinfo($item->courseid);
            }
            
            $item->cmname = $this->getCmNameFromCmId($item->cmid, $item->courseid, $modinfo);
            
            $result->addActivity($item);
        }

        if ($result){
            $result->orderBySlot();
        }

        return $result;
    }

    public function saveTemplate($data){
        try{	
            $templateId = $data->id;
            $collaboratorids = array();
            if (!empty($data->collaboratorList)){
                foreach ($data->collaboratorList as $u){
                    $collaboratorids[] = $u->userId;
                }
            }
            $collaboratorids = implode(',', $collaboratorids);
            $values = array('name' => $data->name, 'description' => $data->description, 'communication_url' => $data->communicationUrl, 'collaboratorids' => $collaboratorids, 'lastupdate' => time(), 'state' => $data->state, 'tpltype' => $data->type, 'options' => json_encode($data->options));

            if($data->id == 0){
                $values['creatorid'] = $this->signedUser->id;

                $this->mysqlConn->insert_record("recit_wp_tpl", $values);

                $templateId = $this->mysqlConn->get_record_sql("select id from {recit_wp_tpl} order by id desc limit 1")->id;
            }
            else{
                $values['id'] = $data->id;
                $this->mysqlConn->update_record("recit_wp_tpl", $values);
            }

            return $this->getTemplate($this->signedUser->id, $templateId);
        }
        catch(\Exception $ex){
            throw $ex;
        }
    }

    public function cloneTemplate($templateId, $options){
        try{
            $state = (is_numeric($options->state) ? $options->state : 'state');
            $collaboratorids = ($options->keepCollaborators ? 'collaboratorids' : "''");
            
            $query = "insert into {recit_wp_tpl} (creatorid, collaboratorids, name, description, communication_url, lastupdate, state, tpltype, options) 
                        select {$this->signedUser->id}, $collaboratorids, ". $this->mysqlConn->sql_concat('name', "'".get_string('cloned', 'local_recitworkplan')."'").", description, 
                        communication_url, " . time() . ", $state, tpltype, options from {recit_wp_tpl} where id = $templateId";

            $this->execSQL($query);
            $newTemplateId = $this->mysqlConn->get_record_sql("select id from {recit_wp_tpl} order by id desc limit 1")->id;

            $query = "insert into {recit_wp_tpl_act} (templateid, cmid, nb_hours_completion, slot) select $newTemplateId, cmid, nb_hours_completion, slot from {recit_wp_tpl_act} where templateid = $templateId";
            $this->execSQL($query);

            return $newTemplateId;
        }
        catch(\Exception $ex){
            throw $ex;
        }  
    }

    public function saveTplAct($data){
        try{	
            if($data->templateId == 0){
                $template = new Template();
                $data->templateId = $this->saveTemplate($template);
            }
            else{
                $fields = array("lastupdate" => time(), 'id' => $data->templateId);
                $this->mysqlConn->update_record("recit_wp_tpl", $fields);
            }

            $values = array('slot' => $data->slot, 'templateid' => $data->templateId, 'cmid' => $data->cmId, 'nb_hours_completion' => $data->nbHoursCompletion);

            if($data->id == 0){
                $data->id = $this->mysqlConn->insert_record("recit_wp_tpl_act", $values);
            }
            else{
                $values['id'] = $data->id;
                $this->mysqlConn->update_record("recit_wp_tpl_act", $values);
            }

            $result = new StdClass();
            $result->templateId = $data->templateId;
            $result->tplActId = $data->id;

            return $result;
        }
        catch(\Exception $ex){
            throw $ex;
        }
    }

    public function saveTplActOrder($data){
        try{
            $this->mysqlConn->update_record("recit_wp_tpl_act", array('id' => $data->tplActId, 'slot' => $data->slot));

            $query = "SELECT id, slot FROM {recit_wp_tpl_act} where templateid = ? and id != ? order by slot asc";
            $activityList = $this->getRecordsSQL($query, [$data->templateId, $data->tplActId]);
                                    
            $slot = 1;
            foreach($activityList as $item){
                if($slot == $data->slot){
                    $slot++;
                }

                $this->mysqlConn->update_record("recit_wp_tpl_act", array('id' => $item->id, 'slot' => $slot));

                $slot++;
            }

            return true;
        }
        catch(\Exception $ex){
            throw $ex;
        }
    }

    public function deleteTplAct($tplActId){
        try{
            $this->execSQL("delete from {recit_wp_tpl_act} where id = ?", [$tplActId]);
            return true;
        }
        catch(\Exception $ex){
            throw $ex;
        }  
    }

    public function getUserList($templateId, $cap){
        global $DB, $OUTPUT;

        $result = array();

        if($templateId == 0 ){
            return $result;
        }

        $query = "select ". $this->sql_uniqueid() ." uniqueid, t1.id, t1.firstname, t1.lastname, t1.picture, t3.courseid,
        t1.firstnamephonetic, t1.lastnamephonetic, t1.middlename, t1.alternatename, t1.imagealt, t1.email,
        ". $this->sql_group_concat('t10.name')." grouplist
        from {user} t1
        inner join {user_enrolments} t2 on t1.id = t2.userid
        inner join {enrol} t3 on t2.enrolid = t3.id
        left join (select g.name,gm.userid,g.courseid from {groups_members} gm
        inner join {groups} g on gm.groupid = g.id) t10 on t10.courseid = t3.courseid and t10.userid = t2.userid
        where t3.courseid in (select st2.course from {recit_wp_tpl_act} st1 
                             inner join {course_modules} st2 on st1.cmid = st2.id 
                             where st1.templateid = ?)
        group by t1.id
        order by firstname, lastname asc";

        $rst = $DB->get_records_sql($query, [$templateId]);

        
		foreach($rst as $item){
            $ccontext = \context_course::instance($item->courseid);
            if (has_capability($cap, $ccontext, $item->id, false)){
                $obj = new stdClass();
                $obj->avatar = $OUTPUT->user_picture($item, array('size'=>30));
                $obj->userUrl = (new \moodle_url('/user/profile.php', array('id' => $item->id)))->out();
                $obj->userId = $item->id;

                $obj->groupList = array();
                if($item->grouplist){
                    $obj->groupList = explode(',',$item->grouplist);
                }
                
                $obj->firstName = $item->firstname;
                $obj->lastName = $item->lastname;
                $obj->fullname = "$obj->lastName $obj->firstName";
                $result[] = $obj;
            }
        } 

        return $result;
    }

    protected function getWorkPlanStats($templateId, $userId = 0){
        global $DB;

        $args = array('tpl' => $templateId);
        $where = "t2.id = :tpl";
        if ($userId > 0){
            $where .= " and t1.userid = :userid";
            $args['userid'] = $userId;
        }

        $tmpTableName = "tmlworkplan_$templateId"."_$userId";

        $query = "select t1.nb_hours_per_week nbhoursperweek,
        t1.completionstate wpcompletionstate, t2.id templateid, t2.creatorid creatorid, t3.cmid, t3.nb_hours_completion nbhourscompletion,
        t1.userid, t6.completionstate activitycompletionstate, t2.state templatestate, t2.tpltype as templatetype, t1.startdate, t1.enddate
        from {recit_wp_tpl} t2
        left join {recit_wp_tpl_assign} t1 on t1.templateid = t2.id
        left join {recit_wp_tpl_act} t3 on t3.templateid = t2.id
        left join {course_modules} t5 on t3.cmid = t5.id
        left join {course} t7 on t7.id = t5.course
        left join {course_categories} t8 on t7.category = t8.id
        left join {course_modules_completion} t6 on t5.id = t6.coursemoduleid and t6.userid = t1.userid
        where $where";
        $DB->execute("CREATE TEMPORARY TABLE IF NOT EXISTS $tmpTableName $query", $args);

        $vars = array($templateId);
        $query = "select count(distinct cmid) nbactivities, count(DISTINCT userid) nbstudents
            from $tmpTableName where templateid = ? and nbhourscompletion > 0 group by templateid";

        $result = $DB->get_record_sql($query, $vars);
        $stats = new stdClass();
        $stats->activitycompleted = array();
        $stats->workplanprogress = array();

        if (!$result){
            $stats->nbActivities = 0;
            $stats->nbStudents = 0;
        }else{
            $stats->nbActivities = intval($result->nbactivities);
            $stats->nbStudents = intval($result->nbstudents);
        }

        $query = "SELECT COUNT(DISTINCT userid) count FROM $tmpTableName WHERE wpcompletionstate = 3 AND templateid=?";

        $rst = $DB->get_record_sql($query, $vars);
        if ($rst){
            $stats->workPlanCompletion = intval($rst->count);
        }else{
            $stats->workPlanCompletion = 0;
        }

        $query = "SELECT COUNT(DISTINCT userid) count FROM $tmpTableName WHERE wpcompletionstate = 2 and (enddate > 0 and unix_timestamp() < enddate) AND templateid=?";
        $rst = $DB->get_record_sql($query, $vars);
        $stats->nbLateStudents = intval($rst->count);

        $query = "SELECT COUNT(DISTINCT userid) count FROM $tmpTableName WHERE wpcompletionstate in (0, 2) and (enddate > 0 and unix_timestamp() > enddate) AND templateid=?";
        $rst = $DB->get_record_sql($query, $vars);
        $stats->nbFailedStudents = intval($rst->count);

        $query = "select ". $this->sql_uniqueid() ." uniqueid, count(distinct userid) count, cmid from $tmpTableName where activitycompletionstate in (1,2,3) and templateid = ? group by cmid";

        $rst = $DB->get_records_sql($query, $vars);
        foreach($rst as $r){
            $stats->activitycompleted[intval($r->cmid)] = intval($r->count);
        }
        
        $query = "select uniqueid, userid, nbhourscompletion / totalnbhours * 100 as progressbyhours from 
                (select ". $this->sql_uniqueid() ." uniqueid, userid, sum(nbhourscompletion) as totalnbhours,
                sum(if(find_in_set(activitycompletionstate, '1,2,3') > 0, nbhourscompletion, 0)) as nbhourscompletion 
                from $tmpTableName where nbhourscompletion > 0 and templateid = ? group by userid) as tab";

        $rst = $DB->get_records_sql($query, $vars);
        foreach($rst as $r){
            $stats->workplanprogress[intval($r->userid)] = round(floatval($r->progressbyhours),1);
        }

        $DB->execute("drop table $tmpTableName");

        return $stats;
    }

    public function getWorkFollowUpStmt($templateId){
        global $CFG;
        /* Followup
            1 = à corriger
            2 = rétroaction
            */
        $stmt = "(SELECT t3.id cm_Id, t1.name cm_Name, min(tuser.timemodified) time_Modified, count(*) nb_Items, tuser.userid,
        1 followup
        FROM {assign} t1
        inner join {assign_submission} tuser on t1.id = tuser.assignment
        inner join {course_modules} t3 on t1.id = t3.instance and t3.module = (select id from {modules} where name = 'assign') and t1.course = t3.course
        left join {assign_grades} t4 on t4.assignment = tuser.assignment and t4.userid = tuser.userid and t4.attemptnumber = tuser.attemptnumber
        where t3.id in (select cmid from {recit_wp_tpl_act} where templateid = $templateId) 
                and tuser.status = 'submitted' and tuser.status = 'submitted' and tuser.latest = 1
                and (coalesce(t4.grade,0) <= 0 or tuser.timemodified > coalesce(t4.timemodified,0))
        group by t3.id, t1.id, tuser.userid)
        union
        (select cm_Id, min(cm_Name), time_Modified, count(*) nb_Items, userid, min(followup) from 
        (SELECT distinct t1.id cm_Id, t2.name cm_Name, max(t3.timemodified) time_Modified, t3.userid, min(t3.attempt) quizAttempt, t4.questionusageid, ". $this->sql_group_concat('t5.state', ",", "t5.id asc")." states,
        1 followup
        FROM 
        {course_modules} t1 
        inner join {quiz} t2 on t2.id = t1.instance and t1.module = (select id from {modules} where name = 'quiz') and t2.course = t1.course
        inner join {quiz_attempts} t3 on t3.quiz = t2.id 
        inner join {question_attempts} t4 on  t4.questionusageid = t3.uniqueid
        inner join {question_attempt_steps} t5 on t4.id = t5.questionattemptid
        where t1.id in (select cmid from {recit_wp_tpl_act} where templateid = $templateId)
        group by t1.id, t2.id, t3.userid, t4.id) tab 
        where right(states, 12) = 'needsgrading'
        group by cm_Id, time_Modified, userid)";

        if(file_exists("{$CFG->dirroot}/mod/recitcahiertraces/")){
            $version = get_config('mod_recitcahiertraces')->version;
            $ctid_field = "ctid";
            if ($version > 2022100102) $ctid_field = "ct_id";
            $stmt .= "union
            (SELECT t3.id cm_Id, ".$this->sql_castutf8('t1.name')." cm_Name, t1.timemodified time_Modified, count(*) nb_Items, tuser.userid,
            2 followup
            FROM {recitcahiertraces} t1
            inner join {recitct_groups} t2 on t1.id = t2.$ctid_field
            left join {recitct_notes} t4 on t2.id = t4.gid
            left join {recitct_user_notes} tuser on t4.id = tuser.nid
            inner join {course_modules} t3 on t1.id = t3.instance and t3.module = (select id from {modules} where name = 'recitcahiertraces') and t1.course = t3.course
            where (case when tuser.id > 0 and length(tuser.note) > 0 and (length(REGEXP_REPLACE(trim(coalesce(tuser.feedback, '')), '<[^>]*>+', '')) = 0) then 1 else 0 end) = 1 
            and t3.id in (select cmid from {recit_wp_tpl_act} where templateid = $templateId) and t4.notifyteacher = 1
            group by t3.id, t1.id, tuser.userid, t1.timemodified)";
        }
        
        return $stmt;
    }

    public function getWorkGradeStmt($templateId){
        $stmt = "SELECT t3.id cmid, t2.userid, t2.finalgrade, ". $this->mysqlConn->sql_concat('ROUND(t2.rawgrade,2)',"'/'",'ROUND(t2.rawgrademax,2)')." grade, t1.itemname, (case when t2.finalgrade is null then -1 else (case when t2.finalgrade >= t1.gradepass then 1 else 0 end) end) passed 
        FROM {grade_items} t1
        INNER JOIN {grade_grades} t2 ON t2.itemid = t1.id and t1.itemtype = 'mod'
        INNER JOIN {course_modules} t3 ON t1.iteminstance = t3.instance and t1.courseid = t3.course and t3.module = (select id from {modules} where name = t1.itemmodule)
        where t3.id in (select cmid from {recit_wp_tpl_act} where templateid = $templateId) and t1.gradepass > 0 and t2.rawgrade is not null order by t2.id desc
        ";
         
        return $stmt;
    }

    public function getWorkPlan($userId, $templateId, $isStudent = false){
        $roles = array(RECITWORKPLAN_ASSIGN_CAPABILITY, RECITWORKPLAN_MANAGE_CAPABILITY);
        $where = "";

        if ($isStudent){
            $roles = array(RECITWORKPLAN_FOLLOW_CAPABILITY);
            $where = " and t1.userid = $userId";
        }
        $where .= " and (t7.category in (".$this->getContextAccessIds($userId, $roles, 40).") or t5.course in (".$this->getContextAccessIds($userId, $roles, 50)."))";

        $query = "select ". $this->sql_uniqueid() ." uniqueid, t1.id, t1.nb_hours_per_week nbhoursperweek, t1_1.nb_additional_hours nbadditionalhours, t1.startdate startdate, t1.enddate, 
        t1.completionstate wpcompletionstate, t2.id templateid, t2.creatorid, t2.name templatename, t2.state templatestate, t2.tpltype as templatetype, t2.options templateoptions,
        t7.fullname coursename, t7.id courseid, t2.description templatedesc, t2.lastupdate lastupdate, t3.cmid,
        t3.nb_hours_completion nbhourscompletion, t6.completionstate activitycompletionstate, 
        t1.assignorid, t2.collaboratorids, t1.nbhourslate,
        assignor.picture assignorpicture, assignor.imagealt assignorimagealt, assignor.email assignoremail, assignor.alternatename assignoralternatename, assignor.firstname assignorfirstname, assignor.lastname assignorlastname, assignor.lastnamephonetic assignorlastnamephonetic, assignor.firstnamephonetic assignorfirstnamephonetic, 
        t8.name categoryname, t3.id tplactid, t1.comment, t2.communication_url communicationurl, fup.followup, COALESCE(grade.passed, -1) passed, grade.grade, t3.slot,
        t1.userid, users.firstname, users.lastname, users.picture, users.imagealt, users.email, users.firstnamephonetic, users.lastnamephonetic, users.alternatename, users.lastaccess lastaccess, g.grouplist
        from {recit_wp_tpl} t2
        left join {recit_wp_tpl_assign} t1 on t1.templateid = t2.id
        left join (select sum(nb_additional_hours) nb_additional_hours,assignmentid from {recit_wp_additional_hours} group by assignmentid) t1_1 on t1_1.assignmentid = t1.id
        left join {recit_wp_tpl_act} t3 on t3.templateid = t2.id
        left join {course_modules} t5 on t3.cmid = t5.id
        left join (".$this->getWorkFollowUpStmt($templateId).") fup on t3.cmid = fup.cm_Id and t1.userid = fup.userid
        left join (".$this->getWorkGradeStmt($templateId).") grade on t3.cmid = grade.cmid and t1.userid = grade.userid
        left join {course} t7 on t7.id = t5.course
        left join {course_categories} t8 on t7.category = t8.id
        left join {user} users on users.id = t1.userid
        left join {user} assignor on t1.assignorid = assignor.id
        left join (select t9.userid userid, ".$this->sql_group_concat('t10.name')." grouplist, t10.courseid
            from {groups_members} t9
            left join {groups} t10 on t9.groupid = t10.id
            group by t9.userid, t10.courseid) g on g.userid = t1.userid and g.courseid = t5.course
        left join {course_modules_completion} t6 on t5.id = t6.coursemoduleid and t6.userid = users.id
        where t2.id = ? $where
        order by t7.id, t7.id asc, users.firstname asc, users.lastname asc ";

        $rst = $this->getRecordsSQL($query, [$templateId]);

        $result = new WorkPlan();

        if(empty($rst)){
            $result->template = $this->getTemplate($userId, $templateId);
        }
        else{
            $modinfo = null;
            foreach($rst as $item){
                if($item->courseid > 0){
                    if($modinfo == null || $modinfo->__get('courseid') != $item->courseid){
                        $modinfo = get_fast_modinfo($item->courseid);
                    }
                    
                    $item->cmname = $this->getCmNameFromCmId($item->cmid, $item->courseid, $modinfo);
                }
     
                $result->addTemplateActivity($item);
                $result->addAssignment($item);
            }  
    
            $result->stats = $this->getWorkPlanStats($templateId, ($isStudent ? $userId : 0));
        }

        if ($result->template){
            $result->template->orderBySlot();
        }

        $result->orderAssignmentsByStudentFullname();

        return $result; 
    }

    public function getWorkPlanList($userId, $limit = 0, $offset = 0, $state = 'ongoing', $forStudent = false, $orderBy = null){
        global $DB;
        $whereaccess = "true";
        $where = "";
        if ($state == 'ongoing'){
            $whereaccess .= " and ((t1.completionstate in (0,2,3,4) and t2.state = 0) or (t1.completionstate is null and t2.state = 0))";
        }else if ($state == 'archive'){
            $whereaccess .= " and (t1.completionstate = 1 and t2.state = 0)";
        }
        
        $capabilities = array();
        $capabilitySmt = "";
        if ($forStudent){
            $where .= " and t1.userid = $userId";
            $capabilities[] = RECITWORKPLAN_FOLLOW_CAPABILITY;
            $whereaccess .= " and (t5.course is null or t5.course in (".$this->getContextAccessIds($userId, $capabilities, 50)."))";

            if ($state == 'ongoing'){
                $where .= " and (t1.startdate <= unix_timestamp())";
            }
            else if ($state == 'upcoming'){
                $where .= " and (t1.startdate > unix_timestamp())";
            }

        }else if (in_array($state, array('ongoing','archive'))){
            $where .= " and (t2.creatorid = $userId or ".$this->sql_find_in_set($userId, 't2.collaboratorids').")";
            $capabilities[] = RECITWORKPLAN_ASSIGN_CAPABILITY;
            $capabilities[] = RECITWORKPLAN_MANAGE_CAPABILITY;
            $capabilitySmt = "left join {course} t7 on t7.id = t5.course";
            $whereaccess .= " and (t5.course is null or t5.course in (".$this->getContextAccessIds($userId, $capabilities, 50).") or t7.category in (".$this->getContextAccessIds($userId, $capabilities, 40)."))";
        }else if (in_array($state, array('manager'))){
            $capabilities[] = RECITWORKPLAN_MANAGE_CAPABILITY;
            $capabilitySmt = "left join {course} t7 on t7.id = t5.course";
            $whereaccess = " (t1.completionstate in (0,2,3,4)) and (t7.category in (".$this->getContextAccessIds($userId, $capabilities, 40).") or t5.course in (".$this->getContextAccessIds($userId, $capabilities, 50)."))";
        }

        $planListWithAccess = "select distinct t2.id
        from {recit_wp_tpl} t2
        left join {recit_wp_tpl_assign} t1 on t1.templateid = t2.id
        left join {recit_wp_tpl_act} t3 on t3.templateid = t2.id
        left join {course_modules} t5 on t3.cmid = t5.id
        $capabilitySmt
        where $whereaccess";

        $query = "select ". $this->sql_uniqueid() ." uniqueid, t1.id, t1.nb_hours_per_week nbhoursperweek, t1_1.nb_additional_hours nbadditionalhours, t1.startdate startdate, t1.enddate,
        t1.completionstate wpcompletionstate, t2.id templateid, t2.creatorid creatorid, t2.name templatename, t1.nbhourslate, t2.options templateoptions,
        t7.fullname coursename, t7.id courseid, t2.description templatedesc, t2.communication_url communicationurl, 
        t2.lastupdate lastupdate, t3.cmid, t3.nb_hours_completion nbhourscompletion,
        t4.id userid, t4.email, t4.firstname, t4.alternatename, t4.lastname,
        t1.assignorid, assignor.picture assignorpicture, assignor.imagealt assignorimagealt, assignor.firstnamephonetic assignorfirstnamephonetic, assignor.alternatename assignoralternatename, assignor.lastnamephonetic assignorlastnamephonetic, assignor.email assignoremail, assignor.firstname assignorfirstname, assignor.lastname assignorlastname, 
        t6.completionstate activitycompletionstate, t8.name categoryname, t3.id tplactid, t2.state templatestate, t2.tpltype templatetype, t1.comment, t2.collaboratorids collaboratorids
        from {recit_wp_tpl} t2
        left join {recit_wp_tpl_assign} t1 on t1.templateid = t2.id
        left join {recit_wp_tpl_act} t3 on t3.templateid = t2.id
        left join (select sum(nb_additional_hours) nb_additional_hours,assignmentid from {recit_wp_additional_hours} group by assignmentid) t1_1 on t1_1.assignmentid = t1.id
        left join {user} t4 on t1.userid = t4.id
        left join {user} assignor on t1.assignorid = assignor.id
        left join {course_modules} t5 on t3.cmid = t5.id
        left join {course} t7 on t7.id = t5.course
        left join {course_categories} t8 on t7.category = t8.id
        left join {course_modules_completion} t6 on t5.id = t6.coursemoduleid and t6.userid = t4.id
        where t2.id in ($planListWithAccess) $where";

        if ($limit > 0){
            $offsetsql = $offset * $limit;
            //$query .= " LIMIT $limit OFFSET $offsetsql";
        }

        if($orderBy != null){
            if(in_array($orderBy[0], array('templateid', 'templatename', 'lastupdate')) && in_array($orderBy[1],  array('asc', 'desc'))){
                $query .= " order by " . $orderBy[0] . " " . $orderBy[1];
            }
        }

        $rst = $DB->get_records_sql($query);
        $workPlanList = array();
        $total_count = 0;
		foreach($rst as $item){
            if(!isset($workPlanList[$item->templateid])){
                $workPlanList[$item->templateid] = new WorkPlan();
            }
            $workPlanList[$item->templateid]->addTemplateActivity($item);
            $workPlanList[$item->templateid]->addAssignment($item);
            //$total_count = $item->total_count;
        }  

        foreach($workPlanList as $templateId => $item){
            //$item->stats = $this->getWorkPlanStats($templateId);
            $item->template->orderBySlot();
            if (!$item->template->name){
                unset($workPlanList[$templateId]);
            }
        }

        $pagination = new Pagination();
        $pagination->items = array_values($workPlanList);
        $pagination->current_offset = $offset;
        $pagination->total_count = $total_count;

        return $pagination;
    }

    public function deleteWorkPlan($templateId){
        try{

            $args = [$templateId];
            
            $this->execSQL("delete from {recit_wp_tpl_assign} where templateid = ?", $args);

            $this->execSQL("delete from {recit_wp_tpl_act} where templateid = ?", $args);

            $this->execSQL("delete from {recit_wp_tpl} where id = ?", $args);

            return true;
        }
        catch(\Exception $ex){
            throw $ex;
        }  
    }

    public function saveAssignment($data){
        try{
            $values = array(
                'templateid' => $data->templateId, 
                'userid' => $data->user->id, 
                'assignorid' => $this->signedUser->id, 
                'nb_hours_per_week' => $data->nbHoursPerWeek, 
                'startdate' => $data->startDate, 
                'enddate' =>  $data->endDate, 
                'lastupdate' => time(), 
                'comment' => $data->comment
            );

            if (isset($data->completionState)){
                $values['completionstate'] = $data->completionState;
            }

            if($data->id == 0){
                $data->id = $this->mysqlConn->insert_record("recit_wp_tpl_assign", $values);
            }
            else{
                $values['id'] = $data->id;
                $this->mysqlConn->update_record("recit_wp_tpl_assign", $values);
            }

            return $data->id;
        }
        catch(\Exception $ex){
            throw $ex;
        }
    }

    public function addAssignmentAdditionalHours($data){
        try{

            $values = array('assignmentid' => $data->id, 'assignorid' => $this->signedUser->id, 'nb_additional_hours' => $data->nbAdditionalHours, 'lastupdate' => time(), 'comment' => $data->additionalHoursReason);

            $this->mysqlConn->insert_record("recit_wp_additional_hours", $values);

            return true;
        }
        catch(\Exception $ex){
            throw $ex;
        }
    }

    public function getAssignmentAdditionalHours($assignmentId){
        try{
            $rst = $this->mysqlConn->get_records_sql('select t1.id, '. $this->mysqlConn->sql_concat('users.firstname', "' '", 'users.lastname').' assignorname, t1.nb_additional_hours, t1.lastupdate, t1.comment from {recit_wp_additional_hours} t1
            inner join {user} as users on users.id = t1.assignorid
            where assignmentid=:id order by t1.lastupdate', array('id' => $assignmentId));
            return array_values($rst);
        }
        catch(\Exception $ex){
            throw $ex;
        }
    }

    public function deleteAssignment($assignmentId){
        try{
            $this->execSQL("delete from {recit_wp_tpl_assign} where id = ?", [$assignmentId]);
            return true;
        }
        catch(\Exception $ex){
            throw $ex;
        }  
    }

    public function setAssignmentCompletionState($userId, $cmId, $tplId = 0){
        try{
            $args = array();
            if($tplId > 0){
                $args['tplid'] = $tplId;
                $whereStmt1 = "t1.templateid = :tplid";
                $whereStmt2 = "true";
            }
            else{
                $args['userid'] = $userId;
                $whereStmt1 = "t1.userid = :userid";
                $whereStmt2 = $this->sql_find_in_set($cmId,'cmids');
            }

            /**
             * WHERE statement: 
             * t1.completionstate not in (1,4) is to avoid archived/inactive assignments
             * t2.nbhourscompletion > 0 is to ignore activities assigned with 0h 
             */
            $query = "select *,  
                        (case 
                            when nb_incomplete_act = 0 then 3 
                            when nb_incomplete_act > 0 and nbhourslate > 0 then 2 
                            else 0 end) completionstate
                            
                        from 
                        (select assignmentid, 
                            (case 
                                when nb_incomplete_act = 0 then 0
                                when enddate < unix_timestamp() then 0
                                when templatetype = 'd' and nb_hours_per_week > 0 
                                and (nb_hours_completed < (nbWeeksElapsed*nb_hours_per_week) or nb_hours_completed > (nbWeeksElapsed*(nb_hours_per_week+1))) then greatest(nbWeeksElapsed - nb_additional_hours, 0) - nb_hours_completed
                                when templatetype = 's' then (nb_hours_completion * (unix_timestamp() - startdate) / (enddate - startdate)) - nb_hours_completed
                                else 0 end) as nbhourslate, 
                                nb_incomplete_act
                            from
                                (select t1.id assignmentid, t1.templateid, min(t1.startdate) startdate, enddate, t4.tpltype as templatetype,
                                    sum((case when coalesce(t3.completionstate,0) = 0 then 1 else 0 end)) nb_incomplete_act,
                                    group_concat(t2.cmid) cmids, t1.nb_hours_per_week,  t1.userid,
                                    sum(t2.nb_hours_completion) as nb_hours_completion,
                                    coalesce((select sum(t5.nb_additional_hours) from  {recit_wp_additional_hours} t5 where t1.id = t5.assignmentid),0) as nb_additional_hours,
                                    sum(if(t3.completionstate != 0 and t3.coursemoduleid = t2.cmid, t2.nb_hours_completion, 0)) as nb_hours_completed,
                                    (case 
                                    when t4.tpltype = 'd' then floor(timestampdiff(WEEK, FROM_UNIXTIME(t1.startdate), now())) * t1.nb_hours_per_week
                                    when t4.tpltype = 's' then 0
                                    else 0 end) as nbWeeksElapsed
                                    from {recit_wp_tpl_assign} t1 
                                    inner join {recit_wp_tpl_act} t2 on t1.templateid = t2.templateid 
                                    inner join {recit_wp_tpl} as t4 on t2.templateid = t4.id
                                    left join {course_modules_completion} t3 on t2.cmid = t3.coursemoduleid and t1.userid = t3.userid           
                                    where t1.completionstate not in (1,4) and $whereStmt1 and t2.nb_hours_completion > 0
                                    group by t1.userid, t1.id) as tab
                                    where $whereStmt2
                                    ) as tab2";
            
            $rst = $this->getRecordsSQL($query, $args);

            if(!empty($rst)){
                foreach($rst as $obj){
                    $value = array('completionstate' => $obj->completionstate, 'nbhourslate' => $obj->nbhourslate, 'id' => $obj->assignmentid);
                    $query = $this->mysqlConn->update_record("recit_wp_tpl_assign", $value);
                }
            }

            return true;
        }
        catch(\Exception $ex){
            throw $ex;
        }
    }

    /**
     * This function only applies to dynamic work plans
     */
    public function setAssignmentEndDate($templateId){
        try{
            $secsInAWeek = 604800;
            $query = "select coalesce(assignmentId, 0) as assignmentid, coalesce(startdate + (nbWeeks * $secsInAWeek), 0) as enddate from
                        (select t1.id assignmentId, min(t1.startdate) as startdate,
                            CEILING(
                                    (sum(t2.nb_hours_completion) + coalesce((select sum(t3.nb_additional_hours) from  {recit_wp_additional_hours} t3 where t1.id = t3.assignmentid),0))
                                    / 
                                    min(t1.nb_hours_per_week)
                                    ) nbWeeks
                        from {recit_wp_tpl_assign} t1 
                        inner join {recit_wp_tpl_act} t2 on t1.templateid = t2.templateid 
                        inner join {recit_wp_tpl} as t4 on t2.templateid = t4.id
                        where t1.completionstate not in (1,4) and t1.templateid = :tplid and t4.tpltype = 'd' and t2.nb_hours_completion > 0 and t1.nb_hours_per_week > 0
                        group by t1.id) tab";

            $args['tplid'] = $templateId;
            $rst = $this->getRecordsSQL($query, $args);

            if(!empty($rst)){
                foreach($rst as $obj){
                    $value = array('enddate' => $obj->enddate, 'id' => $obj->assignmentid);
                    $query = $this->mysqlConn->update_record("recit_wp_tpl_assign", $value);
                }
            }
            return true;
        }
        catch(\Exception $ex){
            throw $ex;
        }
    }

    public function addCalendarEvent($templateId, $studentId){
        global $CFG;
        $workPlan = $this->getWorkPlan($studentId, $templateId, true);
        if ($workPlan == null){ return; }
        if ($workPlan->template == null){ return; }

        $name = "Fin du plan ".$workPlan->template->name;
        $desc = "<a href='".$CFG->wwwroot."/local/recitworkplan/view.php'>".$name."</a>"; 
        
        if (!isset($workPlan->assignments[0]->endDate)){ return; } 

        $event = new stdClass();
        $event->eventtype = 'planformation';
        $event->type = CALENDAR_EVENT_TYPE_ACTION; // This is used for events we only want to display on the calendar, and are not needed on the block_myoverview.
        $event->name = $name;
        $event->description = $desc;
        $event->format = FORMAT_HTML;
        $event->courseid = 0;
        $event->groupid = 0;
        $event->userid = $studentId;
        $event->modulename = '';
        $event->instance = $workPlan->assignments[0]->id;
        $event->timestart = $workPlan->assignments[0]->endDate;
        $event->timeend = $workPlan->assignments[0]->endDate;
        $event->visible = TRUE;
        $event->timeduration = 0;

        \calendar_event::create($event, false);
    }

    public function deleteCalendarEvent($assignmentId = 0, $userId = 0){
        global $DB;
        $where = array('eventtype' => 'planformation');
        if ($assignmentId > 0){
            $where['instance'] = $assignmentId;
        }
        if ($userId > 0){
            $where['userid'] = $userId;
        }
        $DB->delete_records('event', $where);
    }
    
    public function recalculateCalendarEvents($tplId, $assignments = false){
        global $DB;
        if (!$assignments){
            $assignments = $DB->get_records('recit_wp_tpl_assign', array('templateid' => $tplId));
        }

        $DB->execute("DELETE FROM {event} WHERE instance IN (SELECT id FROM {recit_wp_tpl_assign} WHERE templateid = ?) AND userid IN (SELECT userid FROM {recit_wp_tpl_assign} WHERE templateid = ?)", [$tplId, $tplId]);

        foreach($assignments as $assignment){
            $this->addCalendarEvent($assignment->templateid, $assignment->userid);
        }
    }

    public function processWorkPlan($tplId){
        $this->setAssignmentEndDate($tplId);
        $this->recalculateCalendarEvents($tplId);
        $this->setAssignmentCompletionState(0, 0, $tplId); 
    }
}

class Template{
    public $id = 0;
    public $name = "";
    public $description = "";
    public $communicationUrl = "";
    public $creator = array();
    public $collaboratorList = array();
    public $state = 0;
    public $type = 'd';
    public $hasAccess = 1;
    public $lastUpdate = null;
    //@array of TemplateActivity
    public $activities = array();
    public $options = null;

    public function __construct(){
        $this->options = new TemplateOptions();
    }
 
    public static function create($dbData){
        global $DB, $OUTPUT;

        $result = new Template();
        $result->id = intval($dbData->templateid);
        $result->name = $dbData->templatename;
        $result->description = $dbData->templatedesc; 
        $result->communicationUrl = $dbData->communicationurl;
        $result->lastUpdate = $dbData->lastupdate;
        $result->state = intval($dbData->templatestate);
        
        if (isset($dbData->templatetype)){
            $result->type = $dbData->templatetype;
        } 
        if (isset($dbData->has_access)) $result->hasAccess = $dbData->has_access;
        if (isset($dbData->templateoptions)){
            try {
                $result->options = new TemplateOptions();
                $tmp = json_decode($dbData->templateoptions);
                Utils::copySharedAttributes($tmp, $result->options);

            }catch(\Exception $e){
            }
        }
        
        if((isset($dbData->creatorid)) && ($dbData->creatorid != 0)){
            $creator = $DB->get_record('user', array('id' => $dbData->creatorid));
            $result->creator = new stdClass();
            $result->creator->id = $dbData->creatorid;
            $result->creator->firstName = $creator->firstname;
            $result->creator->lastName = $creator->lastname;
            $result->creator->url = (new \moodle_url('/user/profile.php', array('id' => $creator->id)))->out();
            $result->creator->avatar = $OUTPUT->user_picture($creator, array('size'=> 50));
        }

        if((isset($dbData->collaboratorids)) && (!empty($dbData->collaboratorids))){
            $result->loadCollaborators($dbData->collaboratorids);
        }

        return $result;
    }

    public function loadCollaborators($collaborators){
        global $DB;
        $rst = $DB->get_records_sql("select * from {user} where id in ($collaborators)");
        foreach ($rst as $user){
            $collaborator = new stdClass();
            $collaborator->userId = $user->id;
            $collaborator->firstName = $user->firstname;
            $collaborator->lastName = $user->lastname;
            $this->collaboratorList[] = $collaborator;
        }
    }

    public function addActivity($dbData){
        if(!isset($dbData->cmid) || !isset($dbData->tplactid) || $dbData->cmid <= 0){ return; }

        foreach($this->activities as $item){
            if($item->id == $dbData->tplactid){
                return;
            }
        }

        $this->activities[] = TemplateActivity::create($dbData);
    }

    public function orderBySlot(){
        usort($this->activities, 
            function ($a, $b) {
                return ($a->slot - $b->slot);
            }
        );
    }
}

class TemplateOptions{
    public $showHoursLate = false;
    public $showCategory = true;
    public $showStudentWorkPlan = '0';
    public $userLimitWorkPlan = 35;
}

class TemplateActivity{
    public $id = 0;
    public $cmId = 0;
    public $cmName = "";
    public $cmUrl = "";
    public $courseId = 0;
    public $slot = 0;
    public $courseName = "";
    public $categoryId = 0;
    public $categoryName = "";
    public $nbHoursCompletion = 0;

    public static function create($dbData){
        $result = new TemplateActivity();
        $result->id = (isset($dbData->tplactid) ? intval($dbData->tplactid) : $result->id);
        $result->cmId = (isset($dbData->cmid) ? intval($dbData->cmid) : $result->cmId);
        $result->cmName = (isset($dbData->cmname) ? $dbData->cmname : $result->cmName);
        $result->slot = (isset($dbData->slot) ? intval($dbData->slot) : $result->slot);
        $result->courseId = (isset($dbData->courseid) ? intval($dbData->courseid) : $result->courseId);
        $result->courseName = (isset($dbData->coursename) ? $dbData->coursename : $result->courseName);
        $result->categoryId = (isset($dbData->categoryid) ? intval($dbData->categoryid) : $result->categoryId);
        $result->categoryName = (isset($dbData->categoryname) ? $dbData->categoryname : $result->categoryName);
        $result->nbHoursCompletion = (isset($dbData->nbhourscompletion) ? floatval($dbData->nbhourscompletion) : $result->nbHoursCompletion);


        //Get cm url
        if ($result->cmId > 0){
            try {
                list ($course, $cm) = get_course_and_cm_from_cmId($result->cmId, '', $result->courseId);
                $url = $cm->__get('url');
                // if user has permission
                if($url){
                    $result->cmUrl = $cm->__get('url')->out();
                    $result->cmName = $cm->name;
                }
            }catch(\Exception $e){
                //cm does not exist
            }
        }

        return $result;
    }
}

class Assignment{
    public $id = 0;
    public $user = null;
    public $assignor = null;
    public $startDate = 0;
    public $endDate = 0;
    public $nbHoursPerWeek = 0;
    public $nbAdditionalHours = 0;
    public $nbHoursLate = 0;
    public $templateId = 0;
    public $comment = "";
    /**
     * 0 = ongoing, 1 = archived, 2 = late, 3= completed, 4 = inactive
     */
    public $completionState = 0;

    public function __construct(){
        $this->startDate = time();   
    }

    public static function create($dbData){
        global $OUTPUT;

        if(!isset($dbData->id) || $dbData->id == null || $dbData->id == 0){
            return null;
        }

        $result = new Assignment();
        $result->id = $dbData->id;
        $result->templateId = $dbData->templateid;
      
        if((isset($dbData->userid)) && ($dbData->userid != 0)){
            $result->user = new stdClass();
            $result->user->id = $dbData->userid;
            $result->user->firstName = $dbData->firstname;
            $result->user->lastName = $dbData->lastname;
            $result->user->fullname = "{$result->user->lastName} {$result->user->firstName}";
            $result->user->groupList = (isset($dbData->grouplist) ? $dbData->grouplist : ''); 
            $result->user->lastAccess = (isset($dbData->lastaccess) ? $dbData->lastaccess : ''); 

            $tmp = new stdClass();
            if (isset($dbData->picture)){
                $tmp->id = $dbData->userid;
                $tmp->firstname = $dbData->firstname;
                $tmp->lastname = $dbData->lastname;
                $tmp->alternatename = $dbData->alternatename;
                $tmp->middlename = $dbData->alternatename;
                $tmp->picture = $dbData->picture;
                $tmp->imagealt = $dbData->imagealt;
                $tmp->firstnamephonetic = $dbData->firstnamephonetic;
                $tmp->lastnamephonetic = $dbData->lastnamephonetic;
                $tmp->email = $dbData->email;
                $result->user->avatar = $OUTPUT->user_picture($tmp, array('size'=> 50));
                 // $result->user->url = (new \moodle_url('/user/profile.php', array('id' => $result->user->id)))->out();
            }
            $result->user->activities = array();
        }
        
        if((isset($dbData->assignorid)) && ($dbData->assignorid != 0)){
            $tmp = new stdClass();
            $tmp->id = $dbData->assignorid;
            $tmp->firstname = $dbData->assignorfirstname;
            $tmp->firstnamephonetic = $dbData->assignorfirstnamephonetic;
            $tmp->lastnamephonetic = $dbData->assignorlastnamephonetic;
            $tmp->alternatename = $dbData->assignoralternatename;
            $tmp->middlename = $dbData->assignoralternatename;
            $tmp->lastname = $dbData->assignorlastname;
            $tmp->picture = $dbData->assignorpicture;
            $tmp->imagealt = $dbData->assignorimagealt;
            $tmp->email = $dbData->assignoremail;
            $result->assignor = new stdClass();
            $result->assignor->id = $dbData->assignorid;
            $result->assignor->firstName = $tmp->firstname;
            $result->assignor->lastName = $tmp->lastname;
            $result->assignor->url = (new \moodle_url('/user/profile.php', array('id' => $tmp->id)))->out();
            $result->assignor->avatar = $OUTPUT->user_picture($tmp, array('size'=> 50));
        }

        $result->startDate = intval($dbData->startdate);
        $result->endDate = intval($dbData->enddate);

        $result->nbHoursLate = (isset($dbData->nbhourslate) ? floatval($dbData->nbhourslate) : 0.0);
        $result->nbHoursPerWeek = floatval($dbData->nbhoursperweek);
        $result->nbAdditionalHours = floatval($dbData->nbadditionalhours);
        $result->comment = $dbData->comment;
        $result->completionState = $dbData->wpcompletionstate;

        return $result;
    }

    public function addUserActivity($dbData){
        /**
         * Whether or not the user has completed the activity. 
         * Available states: 0 = not completed if there's no row in this table, that also counts as 0 = not completed, 1 = completed (passed or failed), 2 = completed (passed graded), 3 = completed (failed graded)
         */

        if(!isset($dbData->cmid)){
            return;
        }

        // activity already exists
        foreach($this->user->activities as $item){
            if($item->cmId == $dbData->cmid){
                return;
            }
        }

        $item = new stdClass();
        $item->completionState = intval($dbData->activitycompletionstate);
        $item->followup = (isset($dbData->followup) ? intval($dbData->followup) : 0);
        $item->passed = (isset($dbData->passed) ? intval($dbData->passed) : -1);
        $item->grade = (isset($dbData->grade) ? $dbData->grade : null);
        $item->cmId = intval($dbData->cmid);
        $item->cmUrl = $this->getCustomCmUrl($dbData);
     
        $this->user->activities[] = $item;
    }

    public function getCustomCmUrl($result){
        //Get cm url
        $cmUrl = "";
        $modName = "";
        if ($result->cmid > 0){
            try {
                list ($course, $cm) = get_course_and_cm_from_cmId($result->cmid, '', $result->courseid);
                $url = $cm->__get('url');
                // if user has permission
                if($url){
                    $cmUrl = $cm->__get('url')->out();
                    $modName = $cm->modname;
                }
            }catch(\Exception $e){
                //cm does not exist
            }
        }

        switch ($modName){
            case 'assign':
                $cmUrl = $cmUrl . '&rownum=0&action=grader&userid='.$result->userid;
        }

        return $cmUrl;
    }
}

class WorkPlan{
    //@Template
    public $template = null;
    public $assignments = array();
    public $stats = null;
 
    public function __construct(){
        $this->template = new Template();
    }

    public function addAssignment($dbData){   
        foreach($this->assignments as $item){
            if($item->id == $dbData->id){
                $item->addUserActivity($dbData);
                return;
            }
        }

        $obj = Assignment::create($dbData);
        if($obj != null){
            $obj->addUserActivity($dbData);
            $this->assignments[] = $obj;
        }
    }

    public function addTemplateActivity($dbData){   
        if($this->template->id == 0){
            $this->template = Template::create($dbData);
        }

        $this->template->addActivity($dbData);
    }

    public function orderAssignmentsByStudentFullname(){        
        usort($this->assignments, "recitworkplan\orderStudentsByFullname");
    }
}

class Pagination {
    public $total_count;
    public $current_offset;
    public $items;
}

class MoodleCategory {
    public $id;
    public $name;
    public $roles;
    public $parent;
    public $depth;
    public $courseList = array();


    public static function create($dbData){
        $cat = new MoodleCategory();
        $cat->id = intval($dbData->categoryid);
        $cat->name = $dbData->categoryname;
        $cat->roles = $dbData->categoryroles;
        $cat->parent = $dbData->parent;
        $cat->depth = $dbData->depth;
        return $cat;
    }

    public function addCourse($course){
        $this->courseList[$course->id] = $course;
    }

}

class MoodleCourse {
    public $id;
    public $name;
    public $roles;
    public $sectionList = array();


    public static function create($dbData){
        $c = new MoodleCourse();
        $c->id = intval($dbData->courseid);
        $c->name = $dbData->coursename;
        $c->roles = $dbData->roles;
        return $c;
    }

    public function addCourseData($modinfo){
        foreach($modinfo->get_section_info_all() as $sec){
            if (isset($modinfo->sections[$sec->section])){
                
                $section = MoodleSection::create($sec, $this->id);
                $this->sectionList[$section->id] = $section;
                foreach ($modinfo->sections[$sec->section] as $modnumber){
                    $cm = $modinfo->cms[$modnumber];
                    $mod = MoodleCourseModule::create($cm);
                    $section->addCm($mod);
                }
            }
        }
    }
}

class MoodleSection {
    public $id;
    public $name;
    public $cmList = array();


    public static function create($section, $courseId){
        $c = new MoodleSection();
        $c->id = intval($section->section);
        $c->name = get_section_name($courseId, $section->section);
        return $c;
    }

    public function addCm($cm){
        $this->cmList[] = $cm;
    }
}

class MoodleCourseModule {
    public $id;
    public $name;
    public $completion;
    public $pixUrl;
    public $url;
    
    public static function create($cm){
        $c = new MoodleCourseModule();
        $c->name = $cm->name;
        $c->id = intval($cm->id);
        $c->completion = $cm->completion;
        if ($cm->url){
            $c->url = $cm->url->out();
            $c->pixUrl = $cm->get_icon_url()->out();
        }
        return $c;
    }
}

function orderStudentsByFullname($a, $b) {
    return strnatcasecmp("{$a->user->fullname}", "{$b->user->fullname}");
}

class Utils{
    static function copySharedAttributes(object $source, object $target): object {
        foreach ($source as $key => $value) {
            if (property_exists($target, $key)) {
                $target->$key = $value;
            }
        }
        return $target;
    }
}