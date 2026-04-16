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
import React, { Component } from 'react';
import { Card, Button, Form, OverlayTrigger, Tooltip, ButtonGroup, Collapse} from 'react-bootstrap';
import { JsNx, UtilsDateTime } from '../libs/utils/Utils';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faInfoCircle, faSyncAlt, faTasks } from '@fortawesome/free-solid-svg-icons';
import {$glVars, WorkPlanUtils} from '../common/common';
import { FeedbackCtrl } from '../libs/components/Feedback';

export class UserActivityList extends Component{
    static defaultProps = {        
        data: null,
        user: null,
        isTeacher: false,
    };

    constructor(props){
        super(props);
    }

    render(){
        let item = this.props.data;
        let userActivity = JsNx.getItem(this.props.user.activities, 'cmId', item.cmId, []);
        let className = (item.nbHoursCompletion === 0 ? 'bg-secondary' : '');

        let main = 
            <Card className={`rounded mt-2 mb-2`}>
                <Card.Body className={`${className} p-2`} style={{backgroundColor: "#ffffff"}}>
                    <div className='d-flex align-items-center' style={{justifyContent: 'space-between'}}>
                        <div>
                            <a className='font-weight-bold' href={this.props.isTeacher ? userActivity.cmUrl : item.cmUrl} target="_blank">{`${item.categoryName}/${item.courseName}/${item.cmName}`}</a>
                            <span className='font-weight-bold'>{` (${UtilsDateTime.formatHours2Clocktime(item.nbHoursCompletion)})`}</span>
                        </div>
                        <div className='d-flex align-items-center flex-wrap'>
                            {userActivity.grade != null && <CustomBadge variant='bg-info' text={userActivity.grade}/>}
                            {userActivity.completionState > 0 && <CustomBadge variant="completed"/>}
                            {userActivity.followup == 1 && <CustomBadge variant="correction"/>}
                            {userActivity.followup == 2 && <CustomBadge variant="feedback"/>}
                            {userActivity.passed == 0 && <CustomBadge variant="failure"/>}
                        </div>
                    </div>
                </Card.Body>
            </Card>

        return main;
    }
}

export class CustomCard extends Component{
    static defaultProps = {        
        children: null,
        progressValue: '',
        progressText: '',
        progressColor: ''
    };

    constructor(props){
        super(props);
    }

    render(){
        let main =
            <Card className='rounded m-2 mb-4'>
                <div className={this.props.progressColor} title={this.props.progressText} style={{backgroundColor: '#0f6fc5', width: this.props.progressValue, height: '5px', maxWidth: "100%"}}></div>
                <Card.Body style={{backgroundColor: "#f0f0f0"}} className='p-2'>
                    {this.props.children}
                </Card.Body>
            </Card>;

        return main;
    }
}

export class WorkPlanCustomCard extends Component{
    static defaultProps = {        
        children: null,
        data: null
    };
    render(){
        return null;
    }
}

export class WorkPlanCollapsible extends Component{
    static defaultProps = {   
        data: null,     
        buttons: null,
        contentCollapsible: null,
        studentId: 0,
        onDetail: null,
        onClick: null,
        progress: null
    };

    constructor(props){
        super(props);

        this.onCollapse = this.onCollapse.bind(this);

        this.state = {collapse: false}
    }

    componentDidUpdate(prevProps){
        if(JSON.stringify(prevProps.data.template.id) !== JSON.stringify(this.props.data.template.id)){
            this.setState({collapse: false});
        }
    }
    
    render(){
        let workPlan = this.props.data;
        let hasAccess = workPlan.template.hasAccess == 1;
        let progress = this.props.progress;
        
        let main =
            <CustomCard progressColor={progress.color} progressText={`${progress.text}`} progressValue={`${progress.value}%`}>
                <div className='d-flex mb-2' style={{justifyContent: 'space-between'}}>
                    {hasAccess && <a href='#' onClick={this.props.onClick} className='h5'>{workPlan.template.name}</a>}
                    
                    {!hasAccess && <span className='h4 text-muted'>{workPlan.template.name} <OverlayTrigger overlay={
                                            <Tooltip>Vous êtes créateur de ce plan, mais vous n'avez pas accès aux cours.</Tooltip>}>
                                                <a><FontAwesomeIcon icon={faInfoCircle}/> </a>
                                                </OverlayTrigger></span>}

                    <ButtonGroup style={{height: 40}} >
                        <Button size='sm' className='text-wrap' variant={(this.state.collapse ? 'primary' : 'outline-primary')} 
                                onClick={this.onCollapse} title={"Suivi des activités"}
                                aria-controls={`collapse-${workPlan.template.id}`} aria-expanded={this.state.collapse}>
                                <FontAwesomeIcon icon={faTasks}/>
                        </Button>

                        {this.props.buttons}
                    </ButtonGroup>
                </div>

                <Collapse in={this.state.collapse}>
                    <div id={`collapse-${workPlan.template.id}`} className='row align-items-center justify-content-center p-1'>
                        {this.props.contentCollapsible}
                    </div>
                </Collapse>
            </CustomCard>;

        return main;
    }

    onCollapse(){
        if(!this.state.collapse){
            this.getDetail();
        }
       
        this.setState({collapse: !this.state.collapse})
    }

    getDetail(){
        let studentId = this.props.studentId;

        let that = this;
        $glVars.webApi.getWorkPlan(this.props.data.template.id, studentId, (result) =>{
            if(!result.success){
                FeedbackCtrl.instance.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            let workPlan = result.data;
            workPlan.lastUpdate = new Date();

            if(that.props.onDetail){
                that.props.onDetail(workPlan);
            }
        });
    }
}

export class CustomHeader extends Component{
    static defaultProps = {        
        children: null,
        title: '',
        btnBefore: null,
        btnAfter: null
    };

    constructor(props){
        super(props);
    }

    render(){
        let main =
        <div className='d-flex d-block-mobile flex-wrap' style={{justifyContent: "space-between", alignItems: "center"}}>
            <div className='d-flex' style={{alignItems: "center"}}>
                {this.props.btnBefore}
                {this.props.title.length > 0 && <span className='h2 me-3 ms-3'>{this.props.title}</span>}
                {this.props.btnAfter}
            </div>
            <div>
                {this.props.children}
            </div>
        </div> ;

        return main;
    }
}

export class CustomButton extends Component{
    static defaultProps = {        
        title: '',
        onClick: null,
        children: null,
        faIcon: null,
        disabled: false,
        className: '',
        rounded: true
    };

    render(){
        let className = (this.props.rounded ? 'rounded-circle' : 'rounded') + ' ' + this.props.className;

        let main =
            <Button disabled={this.props.disabled} size='sm' variant='outline-primary' className={className} title={this.props.title} onClick={this.props.onClick} >
                {this.props.faIcon && <FontAwesomeIcon icon={this.props.faIcon}/>}
                {this.props.children}
            </Button>;

        return main;
    }
}

export class CustomBadge extends Component{
    static defaultProps = {        
        faIcon: null,
        text: '',
        variant: '',
        nbIndicator: 0
    };

    render(){
        let variant = this.props.variant;
        let text = this.props.text;

        switch(variant){
            case 'completed': 
                variant = 'bg-success'; 
                text = <>Complété</>;
                break;
            case 'correction': 
                variant = 'bg-info'; 
                text = <>En attente de correction</>;
                break;
            case 'feedback': 
                variant = 'bg-warning'; 
                text = <>Rétroactions attendues</>;
                break;
            case 'failure': 
                variant = 'bg-danger'; 
                text = 
                <OverlayTrigger overlay={<Tooltip>Dans une activité QUIZ, dès que la note de passage n’est pas atteinte et que l’achèvement y est liée.</Tooltip>}>
                  <span className="d-inline-block">
                    Risque d'échec <FontAwesomeIcon icon={faInfoCircle}/>
                  </span>
                </OverlayTrigger>;
                break;
            case 'late': 
                variant = 'bg-info';
                text = <>Retard possible</>;
                break;
        }

        text = (this.props.nbIndicator > 1 ? <>{text}: {this.props.nbIndicator}</> : text);


        let main = 
            <span className={`badge rounded m-1 ${variant}`}>
                {this.props.faIcon !== null && <FontAwesomeIcon icon={this.props.faIcon}/>}
                {text}
            </span>;

        return main;
    }
}

export class CustomBadgeCompletion extends Component{
    static defaultProps = {        
        title: '',
        stats: '',
        className: '',
        label: 'Achèvement: '
    };

    render(){
        if(this.props.stats.length === 0){ return null; }

        let main = 
            <span title={this.props.title}  className={this.props.className}>
                <span className="text-muted">{this.props.label}</span>
                <span className='ms-2 me-2 text-muted'>
                    <b>{`${this.props.stats} `}</b>
                    <FontAwesomeIcon className='text-success' icon={faCheck}/>
                </span>
            </span>;

        return main;
    }
}

export class CustomFormControl extends Component{
    static defaultProps = {        
        onChange: null,
        disabled: false,
        name: '',
        className: '',
        style: null,
        placeholder: '',
        value: '',
        onBlur: null,
        type: '',
        as: '',
        rows: null,
        max: 0,
        required: false,
        size: ''
    };

    render(){
        let spreadAttr = {required: this.props.required};
        if (this.props.max > 0){
            spreadAttr.maxLength = this.props.max;
        }
        let textArea = <Form.Control as={this.props.as} rows={this.props.rows} className={`rounded ${this.props.className}`} style={this.props.style} disabled={this.props.disabled}
                placeholder={this.props.placeholder} value={this.props.value} {...spreadAttr}
                onBlur={this.props.onBlur} name={this.props.name} onChange={this.props.onChange} />;

        let input = <Form.Control className={`rounded ${this.props.className}`} style={this.props.style} {...spreadAttr} disabled={this.props.disabled}
                type={this.props.type} placeholder={this.props.placeholder} value={this.props.value} 
                onBlur={this.props.onBlur} name={this.props.name} onChange={this.props.onChange} />;

        return (this.props.as === 'textarea' ? textArea : input);
    }
}

export class WorkPlanFollowUp extends Component{
    static defaultProps = {        
        data: null,
    };

    render(){
        let main = null;

        if(this.props.data?.stats){
            let workPlan = this.props.data;
            let actStats = WorkPlanUtils.getActivityStats(workPlan);

            /*let noResult = !(
                        (workPlan.stats && workPlan.stats.nbLateStudents > 0) || 
                        (actStats.nbAwaitingGrade > 0) || 
                        (actStats.nbFails > 0));*/

            let notArchived = !WorkPlanUtils.isArchived(JsNx.at(workPlan.assignments, 0, null));

            main =
                <>
                    {notArchived && workPlan.stats && workPlan.stats.nbFailedStudents > 0 && <CustomBadge variant="bg-warning" text="Échu" nbIndicator={workPlan.stats.nbFailedStudents}/>}
                    {notArchived && workPlan.stats && workPlan.stats.nbLateStudents > 0 && <CustomBadge variant="late" nbIndicator={workPlan.stats.nbLateStudents}/>}
                    {notArchived && actStats.nbAwaitingGrade > 0 && <CustomBadge variant="correction" nbIndicator={actStats.nbAwaitingGrade}/>}
                    {notArchived && actStats.nbFails > 0 && <CustomBadge variant="failure" nbIndicator={actStats.nbFails}/>}
                </>;

                //{noResult && <span className='m-1 text-muted'>{`Aucun suivi à faire.`}</span>}
        }

        return main;
    }
}

export class AssignmentFollowUp extends Component{
    static defaultProps = {        
        data: null,
        assignmentId: 0
    };

    render(){
        let main =
            <div>
                {this.getWorkPlanState()}
                {this.getActivitiesFollowup()}
            </div>;

        return main;
    }

    getActivitiesFollowup(){
        let result = [];
        let assignment = JsNx.getItem(this.props.data.assignments, 'id', this.props.assignmentId, null);

        if(assignment === null){
            return result;
        }

        let activities = assignment.user.activities;

        let el = JsNx.getItem(activities, 'followup', 1, null);

        if(el){
            result.push(<CustomBadge key={result.length} variant="correction"/>);
        }
        
        el =  JsNx.getItem(activities, 'followup', 2, null);
        
        if(el){
            result.push(<CustomBadge key={result.length} variant="feedback"/>);
        }
        
        el = JsNx.getItem(activities, 'passed', 0, null);
        
        if(el){
            result.push(<CustomBadge key={result.length} variant="failure"/>);
        }

        if(assignment.comment.length > 0){
            result.push(<CustomBadge key={result.length} variant="bg-danger" 
                text={<OverlayTrigger overlay={<Tooltip>{assignment.comment}</Tooltip>}>
                        <span className="d-inline-block">Commentaire <FontAwesomeIcon icon={faInfoCircle}/></span>
                    </OverlayTrigger>}/>);
        }

        return result;
    }

    getWorkPlanState(){
        let item = JsNx.getItem(this.props.data.assignments, 'id', this.props.assignmentId, null);

        if(item === null){
            return null;
        }

        if(item.completionState == 1){
            return <CustomBadge variant="bg-info" text="Archivé"/>;
        }
        else if(item.completionState == 4){
            return <CustomBadge variant="bg-info" text="Inactif"/>
        }
        else{
            return this.getActiveWorkPlanDetails();
        }
    }

    getActiveWorkPlanDetails(){
        let item = JsNx.getItem(this.props.data.assignments, 'id', this.props.assignmentId, null);

        if(item === null){
            return null;
        }

        let now = new Date().getTime()/1000;

        if((item.completionState == 0) && (item.startDate > (now))){
            return <CustomBadge variant="bg-success" text="À venir"/>;
        }
        else if((['0', '2'].includes(item.completionState.toString())) && (item.endDate > 0) && (item.endDate < (now))){
            return <CustomBadge variant="bg-warning" text="Échu"/>;
        }
        else{
            return this.getOngoingWorkPlanDetails();
        }
    }

    getOngoingWorkPlanDetails(){
        let result = null;
        let item = JsNx.getItem(this.props.data.assignments, 'id', this.props.assignmentId, null);

        if(item === null){
            return result;
        }

        if(item.completionState == 3){
            return <CustomBadge variant="completed"/>;
        }
        else if(item.completionState == 2){
            result = <CustomBadge variant="late"/>
        }
        else if(item.completionState == 0){
            result = <CustomBadge variant="bg-success" text="En cours"/>;
        }
        
        if (item.nbHoursLate != 0 && this.props.data.template.options.showHoursLate == 1){
            let text = `Retard possible de ${UtilsDateTime.formatHours2Clocktime(item.nbHoursLate)}`;
            let variant = 'bg-info';

            if (item.nbHoursLate < 0){
                text = `En avance de ${UtilsDateTime.formatHours2Clocktime(parseFloat(item.nbHoursLate) * -1) }`
                variant = 'bg-success';
            }

            text = 
            <OverlayTrigger overlay={<Tooltip>On compare le nombre d'heures restantes à réaliser au plan de travail aux nombres d'heures restantes selon la date d'échéance du plan de travail.</Tooltip>}>
              <span className="d-inline-block">
                {text} <FontAwesomeIcon icon={faInfoCircle}/>
              </span>
            </OverlayTrigger>;

            result = <CustomBadge variant={variant} text={text}/>;
        }

        return result;
    }
}
