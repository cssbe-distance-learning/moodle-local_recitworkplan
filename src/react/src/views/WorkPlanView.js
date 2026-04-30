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
import { Tabs, Tab, Button, Form, DropdownButton, Dropdown, ButtonGroup, Modal, OverlayTrigger, Tooltip} from 'react-bootstrap';
import { faPencilAlt,  faPlus, faTrashAlt, faCopy, faArrowLeft, faEllipsisV, faSyncAlt, faBookmark, faChevronUp, faChevronDown, faArchive, faChalkboardTeacher, faUserFriends, faUserAltSlash, faUserAlt, faClock, faCogs, faInfoCircle} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { FeedbackCtrl, ToggleButtons } from '../libs/components/Components';
import {$glVars, WorkPlanUtils} from '../common/common';
import { JsNx, UtilsString, UtilsDateTime, Cookies } from '../libs/utils/Utils';
import { Pagination } from '../libs/components/Pagination';
import {ActivityPicker, WorkPlanTemplateView} from './TemplateView';
import { UserActivityList, CustomCard, CustomHeader, CustomButton, CustomBadge, CustomBadgeCompletion, CustomFormControl, FollowUpCard, AssignmentFollowUp, WorkPlanCollapsible, WorkPlanFollowUp  } from './Components';
import { ModalAssignmentPicker, ModalAssignmentForm, ModalAssignmentAdditionalHoursForm, ModalAssignmentMassActions, ModalAssignmentAdditionalHoursHistory } from './AssignmentView';
import {StudentWorkPlanList} from './StudentView';

export class AdminView extends Component {

    static defaultProps = {
        workPlanId: 0
    }
    render() {       
        let main = <WorkPlanListView workPlanId={this.props.workPlanId} />;

        return (main);
    }
}

export class WorkPlanListView extends Component{
    static defaultProps = {
        workPlanId: 0
    };

    constructor(props){
        super(props);
        
        this.onAdd = this.onAdd.bind(this);
        this.onEdit = this.onEdit.bind(this);
        this.onDelete = this.onDelete.bind(this);
        this.onArchive = this.onArchive.bind(this);
        this.onClose = this.onClose.bind(this);
        this.onCompletionStateChange = this.onCompletionStateChange.bind(this);
        this.getData = this.getData.bind(this);
        this.getDataResult = this.getDataResult.bind(this);
        this.onCopy = this.onCopy.bind(this);
        this.onCopyEnd = this.onCopyEnd.bind(this);
        this.onOrderBy = this.onOrderBy.bind(this);

        this.state = {
            dataProvider: [], 
            templateId: -1, 
            activeTab: 'ongoing', 
            pagination: {current_page: 1, count: 0, item_per_page: 25}, 
            editTab: 'activities',
            onCopy:  {
                templateId: 0,
                title: '',
                state: 0
            },
            orderBy: Cookies.get('orderBy', "templateid,desc")
        };
        
        if (props.workPlanId > 0){
            this.state.templateId = props.workPlanId;
        }
    }

    componentDidMount(){
        this.getData();
    }

    getData(){
        $glVars.webApi.getWorkPlanList(
            this.state.pagination.item_per_page, 
            this.state.pagination.current_page - 1, 
            this.state.activeTab, 
            false, 
            0, 
            this.state.orderBy,
            this.getDataResult);
    }

    getDataResult(result){
        if(!result.success){
            FeedbackCtrl.instance.showError($glVars.i18n.tags.appName, result.msg);
            return;
        }

        let pagination = this.state.pagination;
        pagination.current_page = parseInt(result.data.current_offset) + 1; 
        pagination.count = parseInt(result.data.total_count);
        this.setState({dataProvider: result.data.items, pagination: pagination});
    }

    changePage(page){
        let pagination = this.state.pagination;
        pagination.current_page = page;
        this.setState({pagination: pagination}, this.getData);
    }

    onCompletionStateChange(event){
        this.setState({activeTab: event.target.value}, this.getData); 
    }

    render(){
        $glVars.context.activeWorkPlanStateTab = this.state.activeTab;
        let dataProvider = this.state.dataProvider;

        let main = 
            <div>
                <CustomHeader title="Plans de travail" btnAfter={<CustomButton title='Créer un plan de travail' onClick={this.onAdd} ><FontAwesomeIcon icon={faPlus}/></CustomButton>}>                    
                    <ToggleButtons name="completionState" onClick={this.onCompletionStateChange} type="radio" value={this.state.activeTab} options={[
                            {value: "ongoing", text: <span><FontAwesomeIcon icon={faSyncAlt} />{" En cours"}</span>}, 
                            {value: "archive", text:  <span><FontAwesomeIcon icon={faArchive} />{" Archivés"}</span>}, 
                            {value: "template", text: <span><FontAwesomeIcon icon={faBookmark} />{" Gabarits"}</span>},
                            {value: "manager", text: <span><FontAwesomeIcon icon={faChalkboardTeacher} />{" Gestionnaire"}</span>},
                            ]}/>
                    <div className='d-flex justify-content-end align-items-baseline mt-3'>
                        <label className='me-2'>Trier par</label>
                        <select type="select" value={this.state.orderBy} className='form-control rounded' onChange={this.onOrderBy}>
                            <option value="templateid,asc">Date de création (croissant)</option>
                            <option value="templateid,desc">Date de création (décroissant)</option>
                            <option value="templatename,asc">Nom (croissant)</option>
                            <option value="templatename,desc">Nom (décroissant)</option>
                            <option value="lastupdate,asc">Dernière mise à jour (croissant)</option>
                            <option value="lastupdate,desc">Dernière mise à jour (décroissant)</option>
                        </select> 
                    </div>
                </CustomHeader>

                <div className='tiles'>
                    {dataProvider.map((workPlan, index) => {

                            let card = <WorkPlanCard key={index} data={workPlan} onCopy={this.onCopy} onDelete={this.onDelete} onEdit={this.onEdit} onArchive={this.onArchive}/>;
                                
                            return (card);                                     
                        }
                    )}
                </div>

                {false && <Pagination pagination={this.state.pagination} onChangePage={(p) => this.changePage(p)}/>}      

                {this.state.onCopy.templateId > 0 && <ModalWorkPlanCopy data={this.state.onCopy} onClose={this.onCopyEnd} />}
            </div>;

        let form = <WorkPlanView templateId={this.state.templateId} editTab={this.state.editTab} onClose={this.onClose}/>;

        return (this.state.templateId >= 0 ? form : main);
    }

    onOrderBy(event){
        Cookies.set('orderBy', event.target.value, 60*24*31);
        this.setState({orderBy: event.target.value}, this.getData);
    }

    onAdd(){
        this.setState({templateId: 0});
    }

    onEdit(templateId, tab){
        this.setState({templateId: templateId, editTab: tab});
    }

    onClose(){
        this.getData();
        this.setState({templateId: -1});
    } 

    onDelete(templateId){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

           that.getData();
           $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
        }

        if(window.confirm($glVars.i18n.tags.msgConfirmDeletion)){
            $glVars.webApi.deleteWorkPlan(templateId, callback);
        }
    }

    onArchive(template, archive){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

           that.getData();
           $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
        }

        if(window.confirm(archive ? $glVars.i18n.tags.msgConfirmArchive : $glVars.i18n.tags.msgConfirmUnArchive)){
            let assignments = [];
            for (let a of template.assignments){
                a.completionState = (archive ? 1 : 0);
                assignments.push(a);
            }
            $glVars.webApi.saveAssignment(assignments, (archive ? 'delete' : 'update'), callback);
        }
    }

    onCopy(templateId, title, state){
       this.setState({onCopy: {templateId: templateId, title: title, state: state}});
    }

    onCopyEnd(refresh){
        let callback = null;
        if(refresh){
            callback = this.getData;
        }

        this.setState({onCopy: {templateId: 0, title: '', state: 0}}, callback);
    }
}

class ModalWorkPlanCopy extends Component{
    static defaultProps = {        
        data: null,
        onClose: null
    };

    constructor(props){
        super(props);

        this.onDataChange = this.onDataChange.bind(this);
        this.onSubmit = this.onSubmit.bind(this);

        this.state = {keepCollaborators: false};
    }

    render(){
        let body = 
        <Form onSubmit={this.onSubmit}>
            <Form.Group >
                <Form.Label>{"Conserver les collaborateurs"}</Form.Label>
                <ToggleButtons name="keepCollaborators" type="radio" value={[this.state.keepCollaborators]} onClick={this.onDataChange} 
                            options={[{value: false, text: 'Non'}, {value: true, text: 'Oui'}]}/>
            </Form.Group>
            <hr/>
            <ButtonGroup className='d-flex justify-content-end'>
                <Button style={{flex: 0}} variant='secondary'  onClick={this.props.onClose}>Annuler</Button>
                <Button style={{flex: 0}} variant='success' type='submit'>Enregistrer</Button>
            </ButtonGroup>
        </Form>;

        let main = 
            <Modal show={true} onHide={this.props.onClose} size="sm" backdrop='static' className='recit_workplan'>
                <Modal.Header closeButton>
                    <Modal.Title>{this.props.data.title}</Modal.Title>
                </Modal.Header>
                <Modal.Body>{body}</Modal.Body>
            </Modal>;

        return main;
    }

    onDataChange(event){
        let data = this.state;
        data[event.target.name] = event.target.value;
        this.setState(data);
    }

    onSubmit(event){
        event.preventDefault();
        event.stopPropagation();
        
        let that = this;
        let callback = function(result){
            if(!result.success){
                FeedbackCtrl.instance.showError($glVars.i18n.tags.appName, result.msg);
            }
            else{
                FeedbackCtrl.instance.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
                that.props.onClose(true);
            }
        };

        if(window.confirm($glVars.i18n.tags.msgConfirm)){
            $glVars.webApi.cloneTemplate(this.props.data.templateId, {state: this.props.data.state, keepCollaborators: this.state.keepCollaborators}, callback);
        }
    }
}

class WorkPlanCard extends Component{
    static defaultProps = {        
        data: null,
        onEdit: null,
        onCopy: null,
        onDelete: null,
        onArchive: null,
    };

    constructor(props){
        super(props);

        this.onDetail = this.onDetail.bind(this);

        this.state = {data: props.data};
    }

    componentDidUpdate(prevProps){
        if(JSON.stringify(prevProps.data) !== JSON.stringify(this.props.data)){
            this.setState({data: this.props.data});
        }
    }

    render(){
        let workPlan = this.state.data;
        let hasAccess = workPlan.template.hasAccess == 1;
        let progress = WorkPlanUtils.getWorkPlanProgress(workPlan);

        let buttons = 
            <DropdownButton size='sm' as={ButtonGroup} variant='outline-primary' title={<FontAwesomeIcon icon={faEllipsisV} />} id={`optionsWorkPlan${workPlan.template.id}`}>
                {hasAccess && $glVars.context.activeWorkPlanStateTab != 'archive' && <Dropdown.Item onClick={() => this.props.onCopy(workPlan.template.id, "Copier ce gabarit", null)}><FontAwesomeIcon icon={faCopy}  />{" Copier"}</Dropdown.Item>}
                {hasAccess && workPlan.template.state == 1 && <Dropdown.Item onClick={() => this.props.onCopy(workPlan.template.id, "Utiliser ce gabarit", 0)}><FontAwesomeIcon icon={faBookmark}  />{" Utiliser ce gabarit"}</Dropdown.Item>}
                {hasAccess && workPlan.template.state != 1 && <Dropdown.Item onClick={() => this.props.onCopy(workPlan.template.id, "Enregistrer en tant que gabarit", 1)}><FontAwesomeIcon icon={faBookmark}  />{" Enregistrer en tant que gabarit"}</Dropdown.Item>}
                <Dropdown.Item onClick={() => this.props.onDelete(workPlan.template.id)}><FontAwesomeIcon icon={faTrashAlt}  />{" Supprimer"}</Dropdown.Item>
                {hasAccess && workPlan.assignments.length > 0 && !WorkPlanUtils.isArchived(JsNx.at(workPlan.assignments, 0, null)) &&  <Dropdown.Item onClick={() => this.props.onArchive(workPlan, true)}><FontAwesomeIcon icon={faArchive}  />{" Archiver"}</Dropdown.Item>}
                {hasAccess && $glVars.context.activeWorkPlanStateTab == 'archive' && <Dropdown.Item onClick={() => this.props.onArchive(workPlan, false)}><FontAwesomeIcon icon={faArchive}  />{" Désarchiver"}</Dropdown.Item>}
            </DropdownButton>;

        let collaboratorList = [];
        for(let item of workPlan.template.collaboratorList){
            collaboratorList.push(`${item.firstName} ${item.lastName}`);
        }

        let content =
            <>
                <div title="Créateur" className='d-flex justify-content-center align-items-center col-12 col-md-3' style={{flexDirection: 'column'}} >
                    <div dangerouslySetInnerHTML={{__html: workPlan.template.creator.avatar}}></div>
                    <div className='text-muted' style={{position: 'absolute', bottom: 0, right: -5}} > 
                        {collaboratorList.length > 0 && 
                            <FontAwesomeIcon icon={faUserFriends} title={`Collaborateurs: ${collaboratorList.join(", ")}`}/>} </div>
                </div>

                <div className='d-flex justify-content-start flex-wrap col-12 col-md-9'>
                    {workPlan.stats && workPlan.stats.nbStudents > 0 && 
                        <CustomBadgeCompletion label="Plans complétés:" title="Le nombre d'élèves qui ont complété le plan de travail / le nombre total d'élèves assigné au plan de travail" stats={`${workPlan.stats.workPlanCompletion}/${workPlan.stats.nbStudents}`}/>
                    }
                    <div>
                        <WorkPlanFollowUp data={workPlan}/>
                    </div>
                </div>
            </>       

        let main = <WorkPlanCollapsible progress={progress} data={workPlan} buttons={buttons} onClick={() => this.props.onEdit(workPlan.template.id, 'activities')}
                                contentCollapsible={content} onDetail={this.onDetail}/>;

        return main;
    }

    onDetail(data){
        this.setState({data: data});
    }
}

class WorkPlanView extends Component{
    static defaultProps = {        
        templateId: 0,
        editTab: 'activities',
        onClose: null
    };
 
    constructor(props){
        super(props);

        this.onTabChange = this.onTabChange.bind(this);
        this.getDataResult = this.getDataResult.bind(this);
        this.getData = this.getData.bind(this);
        this.onSaveTemplate = this.onSaveTemplate.bind(this); 
        this.onProcessWorkPlan = this.onProcessWorkPlan.bind(this);

        this.state = {tab: this.props.editTab, data: null};
    }

    componentDidMount(){
        this.getData(this.props.templateId); 
    }

    componentWillUnmount(){
    }

    componentDidUpdate(prevProps) {
        if (prevProps.editTab !== this.props.editTab) {
          this.setState({tab:this.props.editTab});
        }
    }

    getData(templateId){
        $glVars.webApi.getWorkPlanFormKit(templateId, false, this.getDataResult);
    }

    getDataResult(result){
        if(!result.success){
            FeedbackCtrl.instance.showError($glVars.i18n.tags.appName, result.msg);
            return;
        }
        if(!result.data.data.template){
            FeedbackCtrl.instance.showError($glVars.i18n.tags.appName, 'Plan de travail non accessible. Vérifiez vos permissions en validant votre rôle enseignant ou enseignant non éditeur à l\'intérieur des cours');
            return;
        }
       
        let tab = this.state.tab;

        if(result.data.data.assignments.length > 0){
            tab = 'assignments';
        }

        this.setState({data: result.data.data, tab: tab});
    }

    render(){
        if(this.state.data === null){ return null;}

        let main =  
            <div>   
                <CustomHeader title={this.state.data.template.name} btnBefore={<CustomButton title="Revenir" onClick={this.props.onClose} faIcon={faArrowLeft}/>}>
                    {this.state.data.template.state == 1 && <CustomBadge variant='bg-warning' faIcon={faBookmark} text={" Gabarit"}/>}
                </CustomHeader>

                <WorkPlanTemplateView data={this.state.data} onSave={this.onSaveTemplate} onProcessWorkPlan={this.onProcessWorkPlan} />
                    
                <Tabs id="workPlanTabs" className="mt-5" variant="pills" activeKey={this.state.tab} onSelect={this.onTabChange}>
                    <Tab eventKey="activities" title="Activités">
                       <WorkPlanActivitiesView data={this.state.data} onClose={this.props.onClose} onRefresh={() => this.getData(this.state.data.template.id)}/>
                    </Tab>
                    <Tab eventKey="assignments" title="Affectations" disabled={this.state.data.template.state == 1}>
                        <WorkPlanAssignmentsView data={this.state.data} onRefresh={() => this.getData(this.state.data.template.id)}/>
                    </Tab>
                </Tabs>  
            </div>
            
        return (main);
    }
    
    onTabChange(k){
        this.setState({tab: k});
    }

    onSaveTemplate(template){
        let data = this.state.data;
        data.template = template;
        this.setState({data: data});
    }

    onProcessWorkPlan(){
        let that = this;
        $glVars.webApi.processWorkPlan(this.state.data.template.id, (result) => {
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            that.getData(that.state.data.template.id)
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
        })
    }
}

class WorkPlanAssignmentsView extends Component{
    static defaultProps = {        
        data: [],
        onRefresh: null
    };

    constructor(props){
        super(props);

        this.onShowAssignments = this.onShowAssignments.bind(this);
        this.onDeleteAssignment = this.onDeleteAssignment.bind(this);
        this.onSearch = this.onSearch.bind(this);
        this.onFilterChange = this.onFilterChange.bind(this);

        this.state = {queryStr: "", detail: -1, showAssignments: false, filter: ['late','ongoing'], editAssignment: null, editAssignmentAdditionalHours: null, showAssignmentMassActions: null, showAssignmentAdditionalHours: null, sortAssignment: 0, showUser: null};
    }

    render(){
        let data = this.props.data;
        let assignments = data.assignments;

        let regexp = UtilsString.getRegExp(this.state.queryStr);

        assignments = assignments.filter((item) =>{
            if (!this.state.filter.includes('late') && item.completionState == 2) return false;
            if (!this.state.filter.includes('ongoing') && item.completionState == 0) return false;
            if (!this.state.filter.includes('inactive') && item.completionState == 4) return false;
            if(this.state.queryStr.length > 0){
                return ((item.user.fullname.search(regexp) >= 0) || (item.user.groupList.search(regexp) >= 0));
            }
            return true;
        });
        
        assignments = assignments.sort((a,b) =>{
            if (this.state.sortAssignment == 'firstname'){
                return a.user.firstName.localeCompare(b.user.firstName);
            }
            if (this.state.sortAssignment == 'lastname'){
                return a.user.lastName.localeCompare(b.user.lastName);
            }
            if (this.state.sortAssignment == 'enddate'){
                if (!a.endDate || !b.endDate) return 0;
                return a.endDate.localeCompare(b.endDate);
            }
            if (this.state.sortAssignment == 'progress'){
                let progressValueA = 0;
                if(data.stats.workplanprogress[`${a.user.id}`]){
                    progressValueA = data.stats.workplanprogress[`${a.user.id}`];
                }
                let progressValueB = 0;
                if(data.stats.workplanprogress[`${b.user.id}`]){
                    progressValueB = data.stats.workplanprogress[`${b.user.id}`];
                }
                if (progressValueA > progressValueB){
                    return -1;
                }
                else if (progressValueA < progressValueB){
                    return 1;
                }
            }
            return 0;
        });

        let filters = [];
        if (this.countFlag(2) > 0){
            filters.push({value:'late', text: 'Risque de retard ('+this.countFlag(2)+')'});
        }
        if (this.countFlag(0) > 0){
            filters.push({value:'ongoing', text: 'En cours ('+this.countFlag(0)+')'});
        }
        if (this.countFlag(4) > 0){
            filters.push({value:'inactive', text: 'Inactif ('+this.countFlag(4)+')'});
        }

        let main =  
            <>     
                <CustomHeader title="" btnAfter={<ButtonGroup>
                    <Button style={{borderRadius: "0.25rem 0 0 0.25rem"}} disabled={WorkPlanUtils.isArchived(JsNx.at(data.assignments, 0, null))}  onClick={() => this.onShowAssignments(true)}><FontAwesomeIcon icon={faPlus}/> Attribuer un plan de travail</Button>
                    <Button style={{borderRadius: "0 0.25rem 0.25rem 0"}} disabled={WorkPlanUtils.isArchived(JsNx.at(data.assignments, 0, null))}  onClick={() => this.setState({showAssignmentMassActions: true})}><FontAwesomeIcon icon={faCogs}/> Actions en lot</Button>
                    </ButtonGroup>}>
                    <div className='m-1 d-flex align-items-center d-block-mobile w-100-mobile flex-wrap'>
                        Filtrer par <CustomFormControl className='w-100-mobile' style={{display:'inline',width:'200px',marginRight:'10px', marginLeft:'10px'}} onChange={this.onSearch} type="search" value={this.state.queryStr} name='queryStr' placeholder="Nom, groupe..."/>
                        Trier par <select type="select" className='form-control rounded ms-2 me-2' style={{width:'115px'}} onChange={(e) => this.setState({sortAssignment:e.target.value})}>
                            <option value="lastname">Nom</option>
                            <option value="firstname">Prénom</option>
                            <option value="progress">Progrès</option>
                            <option value="enddate">Date d'échéance</option>
                        </select>
                        <ToggleButtons className='ms-2' type="checkbox" value={this.state.filter} onChange={this.onFilterChange} options={filters}/>
                    </div>
                </CustomHeader>            

                <div>
                    {assignments.map((item, iAssignment) => {
                            let progressValue = 0;
                            let progressText  = `0%`;
                            if(data.stats.workplanprogress[`${item.user.id}`]){
                                progressValue = data.stats.workplanprogress[`${item.user.id}`];
                                progressText = `${progressValue}%`;
                            }
                            
                            let nbHoursCompletionTotal = WorkPlanUtils.getTotalNrHours(data.template.activities);
                            nbHoursCompletionTotal = nbHoursCompletionTotal + item.nbAdditionalHours;
                            let txtDuration = (item.nbHoursPerWeek > 0 ? `${Math.ceil(nbHoursCompletionTotal / item.nbHoursPerWeek)} semaines` : '');
                            let nbHoursCompleted = WorkPlanUtils.getNbHoursCompletion(data.template.activities, item);

                            let card = 
                                <CustomCard key={iAssignment} progressText={progressText} progressValue={`${progressValue}%`}>
                                    <div className='grid-assignments'>
                                        <Button variant={'link'} onClick={() => this.onDetail(this.state.detail == item.id ? -1 : item.id)}><FontAwesomeIcon icon={this.state.detail == item.id ? faChevronUp : faChevronDown}/></Button>
                                        <div>
                                            <span dangerouslySetInnerHTML={{__html: item.user.avatar}}></span>
                                        </div>
                                        <div>
                                            <a href='#' onClick={() => this.onOpenStudentView(item.user)}><strong>{`${item.user.fullname} `}</strong></a>
                                            <OverlayTrigger overlay={
                                                <Tooltip>
                                                    <div>Groupe: <span className='font-weight-bold'>{` ${item.user.groupList}`}</span></div>
                                                    <div>Dernière connexion: {UtilsDateTime.toTimeString(item.user.lastAccess)}</div>
                                                    <div>{`Début: ${UtilsDateTime.formatDateTime(item.startDate)}`}</div>
                                                    <div>{`Échéance: ${UtilsDateTime.formatDateTime(item.endDate, " ", "Non définie")} `}</div>
                                                    <div>{`Nb heures effectuées: ${nbHoursCompleted}h`}</div>
                                                    
                                                    {data.template.type === 'd' && 
                                                        <>
                                                            <div>{`Durée: ${txtDuration}`}</div>
                                                            <div>{`Rythme: ${item.nbHoursPerWeek} h/semaine`}</div>
                                                        </>
                                                    }
                                                </Tooltip>}>
                                                <a><FontAwesomeIcon icon={faInfoCircle}/> </a>
                                            </OverlayTrigger>
                                            
                                            {data.template.type === 'd' && 
                                                <div className='text-muted'>
                                                    <a href='#' onClick={() => this.setState({showAssignmentAdditionalHours: item})}>
                                                        {`Heures supplémentaires: ${item.nbAdditionalHours}h`}
                                                    </a>
                                                </div>
                                            }
                                            <div className='text-muted'>
                                                {`Échéance: ${UtilsDateTime.formatDateTime(item.endDate, " ", "Non définie")} `}
                                                {data.template.type === 'd' && 
                                                    <OverlayTrigger overlay={
                                                        <Tooltip>Le calcul de la date d'échéance s'éffectue de la façon suivante :<br/>
                                                        Nb. de semaines = Nb. d'heures du plan de travail / Rythme en h/semaine. Le résultat est arrondi à l'entier supérieur.<br/>
                                                        
                                                        Les heures supplémentaires sont ajoutées aux heures du plan de travail.</Tooltip>}>
                                                        <a><FontAwesomeIcon icon={faInfoCircle}/> </a>
                                                    </OverlayTrigger>
                                                }
                                            </div>
                                        </div>
                                        <div className='w-100-mobile'>
                                            <AssignmentFollowUp data={data} assignmentId={item.id}/>
                                        </div>
                                        <div className="p-2 text-muted d-flex" style={{alignItems: 'center', justifyContent: 'flex-end'}}>
                                            <div className='me-5'>
                                                <CustomBadgeCompletion  stats={progressText}/>
                                                <div className='text-muted mt-2'>{`Temps consacré: `}<strong>{UtilsDateTime.formatHours2Clocktime(nbHoursCompleted)}</strong></div>
                                            </div>
                                            
                                            <DropdownButton as={ButtonGroup}  disabled={WorkPlanUtils.isArchived(JsNx.at(data.assignments, 0, null))} className='me-3' bsPrefix='rounded btn btn-sm btn-outline-primary' variant='' title={<span><FontAwesomeIcon icon={faEllipsisV}  />{" "}</span>} id={`optionsAssignments${item.id}`}>
                                                <Dropdown.Item onClick={() => this.setState({editAssignment: item})}><FontAwesomeIcon icon={faPencilAlt} />{" Modifier"}</Dropdown.Item>
                                                <Dropdown.Item disabled={data.template.type === 's'} onClick={() => this.setState({editAssignmentAdditionalHours: item})}><FontAwesomeIcon icon={faClock} />{" Heures supplémentaires"}</Dropdown.Item>
                                                <Dropdown.Item onClick={() => this.onSetInactiveAssignment(item)}>
                                                    {item.completionState == 4 ? 
                                                        <><FontAwesomeIcon icon={faUserAlt}/>{" Mettre actif"}</>
                                                        : 
                                                        <><FontAwesomeIcon icon={faUserAltSlash}/>{" Mettre inactif"}</>
                                                    }
                                                    </Dropdown.Item>
                                                <Dropdown.Item onClick={() => this.onDeleteAssignment(item.id)}><FontAwesomeIcon icon={faTrashAlt} />{" Supprimer"}</Dropdown.Item>
                                            </DropdownButton>                                           
                                        </div>
                                    </div>
                      
                                    {this.state.detail == item.id && 
                                        <div style={{width:'100%'}}>
                                            {data.template.activities.map((act, index) => {
                                                    return (<UserActivityList isTeacher={true} user={item.user} data={act} key={index}/>);   
                                                }
                                            )}
                                    </div>}
                                </CustomCard>

                            return (card);                                     
                        }
                    )}
                </div>
                {this.state.showAssignments && <ModalAssignmentPicker data={data} onClose={(refresh) => this.onShowAssignments(false, refresh)}/>}
                {this.state.editAssignment !== null && <ModalAssignmentForm metadata={data.template} data={this.state.editAssignment} onClose={(refresh) => this.onShowAssignments(false, refresh)}/>}
                {this.state.showAssignmentMassActions !== null && <ModalAssignmentMassActions data={data} onClose={(refresh) => this.onShowAssignments(false, refresh)} onRefresh={this.props.onRefresh}/>}
                {this.state.editAssignmentAdditionalHours !== null && <ModalAssignmentAdditionalHoursForm templateId={data.template.id} data={this.state.editAssignmentAdditionalHours} onClose={(refresh) => this.onShowAssignments(false, refresh)}/>}
                {this.state.showAssignmentAdditionalHours !== null && <ModalAssignmentAdditionalHoursHistory data={this.state.showAssignmentAdditionalHours} onClose={(refresh) => this.onShowAssignments(false, refresh)}/>}
            </>;

            let studentView = null;

            if(this.state.showUser !== null){
                let title =
                    <div className=' d-flex align-items-center'>
                        <span dangerouslySetInnerHTML={{__html: this.state.showUser.avatar}}></span>
                        <span>{`${this.state.showUser.fullname}`}</span>
                    </div>;

                let body =  <StudentWorkPlanList userId={this.state.showUser.id}/>;

                studentView = 
                <Modal show={true} onHide={() => this.onOpenStudentView(null)} size="lg" backdrop='static' className='recit_workplan'>
                    <Modal.Header closeButton>
                        <Modal.Title>{title}</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>{body}</Modal.Body>
                </Modal>;                   
            }
                
            
        return (studentView ? studentView : main);
    }

    countFlag(flag){
        let count = 0;
        let data = this.props.data;
        let assignments = data.assignments;
        for (let a of assignments){
            if (a.completionState.toString() === flag.toString()){
                count++;
            }
        }
        return count;
    }

    onSetInactiveAssignment(item){
        let assignments = [];
        item.completionState = item.completionState == 4 ? 0 : 4;
        assignments.push(item);
        let callback = (this.props.onRefresh ? this.props.onRefresh : null);
        let calendar = (item.completionState == 4 ? 'delete' : 'update');
        $glVars.webApi.saveAssignment(assignments, calendar, callback);
    }
    
    onDetail(id){
        this.setState({detail:id});
    }

    onFilterChange(e){
        this.setState({filter:e.target.value});
    }

    onShowAssignments(value, refresh){
        refresh = (typeof refresh === 'undefined' ? false : refresh);
        let callback = (refresh ? this.props.onRefresh : null);
        this.setState({showAssignments: value, editAssignment: null, editAssignmentAdditionalHours: null, showAssignmentMassActions: null, showAssignmentAdditionalHours: null}, callback);
    }

    onDeleteAssignment(assignmentId){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            that.props.onRefresh();
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
        }

        if(window.confirm($glVars.i18n.tags.msgConfirmDeletion)){
            $glVars.webApi.deleteAssignment(assignmentId, callback);
        }
    }

    onSearch(event){
        this.setState({queryStr: event.target.value});
    }
    
    onOpenStudentView(user){
        this.setState({showUser: user});
    }
}

class WorkPlanActivitiesView extends Component{
    static defaultProps = {        
        data: [],
        onRefresh: null,
        onClose: null,
    };

    constructor(props){
        super(props);

        this.onSearch = this.onSearch.bind(this);
        this.onShowActivities = this.onShowActivities.bind(this);
        this.onDeleteActivity = this.onDeleteActivity.bind(this);

        this.state = {queryStr: "", showActivities: false};
    }

    render(){
        if(this.props.data === null){return null;}

        let activityList = this.props.data.template.activities;
        let stats = this.props.data.stats;
        let template = this.props.data.template;

        let regexp = UtilsString.getRegExp(this.state.queryStr);

        if(this.state.queryStr.length > 0){
            activityList = activityList.filter((item) => {
                return ((item.cmName.search(regexp) >= 0) || (item.categoryName.search(regexp) >= 0) || (item.courseName.search(regexp) >= 0));
            })
        }
        
        let main =  
            <>      
                <CustomHeader title="" btnAfter={<Button className="rounded" disabled={WorkPlanUtils.isArchived(JsNx.at(this.props.data.assignments, 0, null))} onClick={() => this.onShowActivities(true)}><FontAwesomeIcon icon={faPlus}/> Ajouter des activités</Button>}>
                    <div>
                        Filtrer par <CustomFormControl style={{width: '300px', display: 'inline-block'}} onChange={this.onSearch} type="search" value={this.state.queryStr} name='queryStr' placeholder="Catégories, cours..."/>
                    </div>
                </CustomHeader>          
                <div>
                    {activityList.map((item, index) => {
                            if (!stats) return null;
                            let progressValue = 0;
                            let progressText  = `0/${stats.nbStudents}`;
                            if(stats.activitycompleted[`${item.cmId}`]){
                                progressValue = stats.activitycompleted[`${item.cmId}`]/stats.nbStudents * 100;
                                progressText = `${stats.activitycompleted[`${item.cmId}`]}/${stats.nbStudents}`;
                            }

                            let actStats = WorkPlanUtils.getActivityStats(this.props.data, item);

                            progressValue = (isNaN(progressValue) ? 0 : Math.round(progressValue,1));
                            
                            let card = 
                                <CustomCard key={index} progressText={`${progressValue}% (le nombre d'activités complètes / le nombre d'élèves)`} progressValue={`${progressValue}%`}>
                                    <div className='d-flex d-block-mobile align-items-center' style={{justifyContent: 'space-between'}}>
                                        <div>
                                            <a className='font-weight-bold' href={item.cmUrl} target="_blank">{this.props.data.template.options.showCategory == 1 ? item.categoryName+'/' : ''}{`${item.courseName}/${item.cmName}`}</a>
                                            <span className='font-weight-bold'>{' ('+UtilsDateTime.formatHours2Clocktime(item.nbHoursCompletion)+')'}</span>
                                        </div>
                                        <div className='d-flex align-items-center flex-wrap'>                                           
                                            {actStats.nbAwaitingGrade > 0 && <CustomBadge variant="correction" nbIndicator={actStats.nbAwaitingGrade}/>}
                                            {actStats.nbFails > 0 && <CustomBadge variant="failure" nbIndicator={actStats.nbFails}/>}                                            
                                        </div>
                                        <div className='d-flex align-items-center'>
                                            <CustomBadgeCompletion stats={progressText}/>
                                            <DropdownButton disabled={WorkPlanUtils.isArchived(JsNx.at(this.props.data.assignments, 0, null))} bsPrefix='rounded btn btn-sm btn-outline-primary' variant='' title={<span><FontAwesomeIcon icon={faEllipsisV}  />{" "}</span>} id={`optionsActivity${item.id}`}>
                                                <Dropdown.Item onClick={() => this.onShowActivities(true)}><FontAwesomeIcon icon={faPencilAlt}  />{" Modifier"}</Dropdown.Item>
                                                <Dropdown.Item onClick={() => this.onDeleteActivity(item.id)}><FontAwesomeIcon icon={faTrashAlt}  />{" Supprimer"}</Dropdown.Item>
                                            </DropdownButton>
                                        </div>
                                    </div>
                                </CustomCard>
                               
                            return (card);                                     
                        }
                    )}
                </div>
                {this.state.showActivities && <ActivityPicker templateId={template.id} onClose={(refresh) => this.onShowActivities(false, refresh)}/>}
            </>                                                   
            
        return (main);
    }

    onShowActivities(value, refresh){
        refresh = (typeof refresh === 'undefined' ? false : refresh);
        let callback = (refresh ? this.props.onRefresh : null);
        this.setState({showActivities: value}, callback);
    }

    onDeleteActivity(tplActId){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            that.props.onRefresh();
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
        }

        if (this.props.data.template.activities.length > 1){
            if(window.confirm($glVars.i18n.tags.msgConfirmDeletion)){
                $glVars.webApi.deleteTplAct(this.props.data.template.id, tplActId, callback);
            }
        }else{
            if(window.confirm('Ce plan de travail ne lui restera plus d\'activité donc le plan sera supprimé. Confirmez-vous cette opération?')){
                $glVars.webApi.deleteWorkPlan(this.props.data.template.id, () => this.props.onClose());
            }
        }
    }

    onSearch(event){
        this.setState({queryStr: event.target.value});
    }
}
