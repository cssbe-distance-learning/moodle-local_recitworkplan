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
import { ButtonGroup,  Button, Form, Col, Row, Table, Badge, Tabs, Tab, Modal} from 'react-bootstrap';
import { faTrashAlt, faArrowRight} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {ComboBoxPlus, DataGrid, FeedbackCtrl, InputNumber} from '../libs/components/Components';
import {$glVars, Options} from '../common/common';
import { JsNx, UtilsDateTime } from '../libs/utils/Utils';
import {CustomFormControl} from './Components'
import { DateTime } from '../libs/components/DateTime';

export class ModalAssignmentPicker extends Component{
    static defaultProps = {        
        data: null,
        onClose: null
    };

    constructor(props){
        super(props);

        this.getData = this.getData.bind(this);
        this.getDataResult = this.getDataResult.bind(this);
        this.onSave = this.onSave.bind(this);
        this.onClose = this.onClose.bind(this);
        this.onDataChange = this.onDataChange.bind(this);
        this.onDelete = this.onDelete.bind(this);
        this.onAdd = this.onAdd.bind(this);

        this.state = {data: props.data, dropdownLists: {studentList: [], groupList: [], group: null, name: ''}, flags: {dataChanged: false},};
    }

    componentDidMount(){
        this.getData();
    }

    getData(){
        $glVars.webApi.getStudentList(this.state.data.template.id, this.getDataResult);
    }

    getDataResult(result){
        if(!result.success){
            FeedbackCtrl.instance.showError($glVars.i18n.tags.appName, result.msg);
            return;
        }

        let lists = this.state.dropdownLists;
        lists.studentList = result.data;

        lists.groupList = [];
        for (let user of result.data){
            for (let g of user.groupList){
                if (!lists.groupList.includes(g)){
                    lists.groupList.push(g);
                }
            }
        }
        for (let k in lists.groupList){
            lists.groupList[k] = {label: lists.groupList[k], value: lists.groupList[k]};
        }
        lists.groupList.unshift({label: '', value: null});

        this.setState({
            dropdownLists: lists
        });
    }

    getFilteredStudentList(){
        return this.state.dropdownLists.studentList.filter((item) => {
            let found = false;
            for(let assignment of this.state.data.assignments){
                if(parseInt(assignment.user.id, 10) === parseInt(item.userId, 10)){
                    found = true;
                    break;
                }
            }
            let show = true;
            if (found) show = false;
            if (this.state.dropdownLists.group && !item.groupList.includes(this.state.dropdownLists.group)) show = false;
            if (this.state.dropdownLists.name.length > 1 && !item.fullname.toLowerCase().includes(this.state.dropdownLists.name.toLowerCase())) show = false;
            return show;
        });
    }

    render(){
        if(this.state.data === null){ return null; }

        let studentList = this.getFilteredStudentList();

        let body = 
            <div>
                <div>
                    <Form.Group as={Col}>
                        <Form.Label>{"Filtrer par groupe"}</Form.Label>
                        <ComboBoxPlus placeholder={"Sélectionnez votre option"} name="group" value={this.state.dropdownLists.group} options={this.state.dropdownLists.groupList} onChange={(e) => this.onFilterChange(e.target.name, e.target.value)} />
                    </Form.Group>
                </div>
                <div className='mt-4 row'>
                    <div className='col-lg-6'>
                        <div style={{display: 'flex', alignItems: "center", justifyContent: "space-between"}}>
                            <strong>Liste d'élèves</strong>
                            <CustomFormControl style={{width:'200px'}} onChange={(e) => this.onFilterChange(e.target.name, e.target.value)}  type="search" value={this.state.dropdownLists.name} name='name' placeholder="Rechercher..."/>
                        </div>
                        
                        <div className='mt-2 mb-2' style={{maxHeight: 500, overflowY: 'auto', scrollbarWidth: 'thin'}}>
                            <Table striped bordered hover>                                
                                <tbody>
                                    {studentList.map((item, index) => {
                                        let row =
                                            <tr key={index}>
                                                <td style={{display: 'flex', alignItems: 'center', justifyContent: 'space-between'}}>
                                                    <a href={item.userUrl} target="_blank"><span dangerouslySetInnerHTML={{__html: item.avatar}}></span>{`${item.fullname}`}</a>
                                                    <Button onClick={() => this.onAdd(item)} variant="link" title="Ajouter"><FontAwesomeIcon icon={faArrowRight}/></Button>
                                                </td>
                                            </tr>

                                            return row;
                                        }
                                    )}
                                    {studentList.length == 0 && <tr><td className='text-muted'>Pas de données</td></tr>}
                                </tbody>
                            </Table>
                        </div>
                    </div>
                    <div className='col-lg-6'>
                        <div>
                            <h6>Élèves assignés <Badge variant="warning" className="p-2 rounded">{this.state.data.assignments.length}/{Options.MAX_AFFECTATIONS}</Badge></h6>
                            <div style={{maxHeight: 500, overflowY: 'scroll', scrollbarWidth: 'thin'}}>
                                <Table striped bordered hover>
                                    <tbody>
                                        {this.state.data.assignments.map((item, index) => {
                                                let row =
                                                    <tr key={index}>
                                                        <td>
                                                            <div className='d-flex'>
                                                                <div className='col-md-10'>
                                                                    <div>
                                                                        <span dangerouslySetInnerHTML={{__html: item.user.avatar}}></span>
                                                                        <strong>{`${item.user.fullname}`}</strong> 
                                                                    </div>
                                                                    
                                                                </div>
                                                                <div className='col-md-1'>
                                                                    <Button variant="link" title="Supprimer" onClick={() => this.onDelete(item.id)}><FontAwesomeIcon icon={faTrashAlt}/></Button>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>;

                                                return row;
                                            }
                                        )}
                                    </tbody>
                                </Table>
                            </div>
                        </div>
                    </div>
                    <div className='col-6 p-3'>
                        <Button variant="primary" className='w-100' onClick={() => this.onAddSelected()}>{"Ajouter tous les utilisateurs "}</Button>
                    </div>
                </div>
            </div>;

        let main = 
            <Modal show={true} onHide={this.onClose} size="lg" backdrop='static' className='recit_workplan'>
                <Modal.Header closeButton>
                    <Modal.Title>{'Attribuer un plan de travail'}</Modal.Title>
                </Modal.Header>
                <Modal.Body>{body}</Modal.Body>
            </Modal>;

        return (main);
    }

    onFilterChange(k, v){
        let list = this.state.dropdownLists;
        list[k] = v;
        this.setState({dropdownLists:list});
    }

    createNewAssignment(item){
        let result = {
            id: 0,
            templateId: this.state.data.template.id,
            user: {id: item.userId, firstName: item.firstName, lastName: item.lastName, fullname: item.fullname, avatar: item.avatar}, 
            nbHoursPerWeek: this.state.rhythme == '' ? 0 : this.state.rhythme,
            comment: '',
            startDate: new Date().getTime()/1000,
            endDate: 0,
            completionState: 0
        };
        if (isNaN(result.nbHoursPerWeek)) result.nbHoursPerWeek = 0;

        return result;
    }

    onAdd(item){
        if (this.state.data.assignments.length >= Options.MAX_AFFECTATIONS){
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, 'Vous avez atteint la limite d\'affectations.', 3);
            return;
        }
        let newItems = [this.createNewAssignment(item)]
        this.setState({flags: {dataChanged: true}}, () => this.onSave(newItems))
    }

    onAddSelected(){
        if (!confirm('Confirmez-vous cette opération: ajouter tous les utilisateurs?')) return;
        let newItems = []
        let studentList = this.getFilteredStudentList();
        for (let item of studentList){
            newItems.push(this.createNewAssignment(item));
        }
        if ((newItems.length + this.state.data.assignments.length) >= Options.MAX_AFFECTATIONS){
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, 'Vous avez atteint la limite d\'affectations.', 3);
            return;
        }
        this.setState({flags: {dataChanged: true}}, () => this.onSave(newItems))
    }

    onDelete(assignmentId){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            let data = that.state.data;
            JsNx.removeItem(data.assignments, 'id', assignmentId);
            that.setState({data: data, flags: {dataChanged: true}});
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
        }

        if(window.confirm($glVars.i18n.tags.msgConfirmDeletion)){
            $glVars.webApi.deleteAssignment(assignmentId, callback);
        }
    }

    onDataChange(event, index){
        let tmp = this.state.data;
        let flags = this.state.flags;
        flags.dataChanged = (tmp.assignments[index][event.target.name] !== event.target.value);
        tmp.assignments[index][event.target.name] = event.target.value;
        this.setState({data: tmp, flags: flags});
    }

    onSave(data){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            let index = 0;
            for (let item of data){
                if(parseInt(item.id,10) === 0){
                    item.id = result.data[index];
                    let tmp = that.state.data;
                    tmp.assignments.push(item);
                    that.setState({data: tmp});
                }
                index++;
            }
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
        }

        if(this.state.flags.dataChanged){
            $glVars.webApi.saveAssignment(data, '', callback);
        }
    }

    onClose(){
        if(this.state.flags.dataChanged){
            $glVars.webApi.processWorkPlan(this.state.data.template.id);
        }
        this.props.onClose(this.state.flags.dataChanged);
    }
}

export class ModalAssignmentMassActions extends Component{
    static defaultProps = {        
        data: null,
        onClose: null,
        onRefresh: null
    };

    constructor(props){
        super(props);

        this.onClose = this.onClose.bind(this);
        this.onAssign = this.onAssign.bind(this);

        this.state = {
            dataChanged: false,
            activeTab: '0'
        };
    }

    render(){
        let data = this.props.data;

        if(data === null){ return null; }

        let body = 
            <div className='row'>
                <div className='col-md-6 mb-3'>
                    <div>
                        <h5>Élèves assignés <Badge variant="warning" className="p-2 rounded">{`${data.assignments.length}`}</Badge></h5>
                        <div style={{maxHeight: 500, overflowY: 'auto', scrollbarWidth: 'thin'}}>
                            <div style={{display:'flex',flexFlow:'wrap'}}>
                                {data.assignments.map((item, index) => {
                                    let row =
                                        <div key={index} className='m-1 p-2 d-flex align-items-center'>
                                            <div>
                                                <span dangerouslySetInnerHTML={{__html: item.user.avatar}}></span>
                                            </div>
                                            <div>
                                                <strong>{`${item.user.fullname}`}</strong><br/>
                                                <span className='text-muted'>Rythme: {item.nbHoursPerWeek}h/semaine</span><br/>
                                                <span className='text-muted'>{item.nbAdditionalHours}h supplémentaires</span>
                                            </div>
                                        </div>;

                                    return row;
                                    }
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div className='col-md-6'>
                    <Tabs  activeKey={this.state.activeTab} onSelect={(tab) => this.setState({activeTab: tab})}>
                        <Tab eventKey="0" title="Date de début" className='p-2'>
                            <StartEndDatesFormBatchAssignment onAssign={this.onAssign} dataProvider={data}/>
                        </Tab>
                        <Tab eventKey="1" title="Rythme par semaine" className='p-2' disabled={data.template.type == 's'}>
                            <RythmeFormBatchAssignment onAssign={this.onAssign} dataProvider={data}/>
                        </Tab>  
                        <Tab eventKey="2" title="Heures supplémentaires" className='p-2' disabled={data.template.type == 's'}>
                            <AdditionalHoursFormBatchAssignment onAssign={this.onAssign} dataProvider={data}/>
                        </Tab>
                    </Tabs>
                </div>
                
            </div>;

        let main = 
            <Modal show={true} onHide={this.onClose} size="xl" backdrop='static' className='recit_workplan'>
                <Modal.Header closeButton>
                    <Modal.Title>{'Actions en lot pour tous les élèves assignés'}</Modal.Title>
                </Modal.Header>
                <Modal.Body>{body}</Modal.Body>
            </Modal>;

        return (main);
    }

    onAssign(){
        this.setState({dataChanged: true}, this.props.onRefresh);
    }

    onClose(){
        if(this.state.dataChanged){
            $glVars.webApi.processWorkPlan(this.props.data.template.id, () => this.props.onClose(true));
        }
        else{
            this.props.onClose(false);
        }
    }
}

export class StartEndDatesFormBatchAssignment extends Component{
    static defaultProps = {        
        onAssign: null,
        dataProvider: null
    };

    constructor(props){
        super(props);

        this.onSubmit = this.onSubmit.bind(this);
        this.onAssign = this.onAssign.bind(this);
        this.onDataChange = this.onDataChange.bind(this);

        this.state = {
            data: {
                startDate: 0,
                endDate: 0
            },
            formValidated: false
        };
    }

    render(){
        if(this.props.dataProvider === null){ return null;}

        let main = 
            <Form noValidate validated={this.state.formValidated} onSubmit={this.onSubmit}>
                <Form.Group className='mb-3' as={Row}>
                    <Form.Label column sm="5">{"Date de début"}</Form.Label>
                    <Col sm="7">
                        <DateTime required onChange={this.onDataChange} name="startDate" value={this.state.data.startDate}/>
                    </Col>
                </Form.Group>
                <Form.Group className='mb-3' as={Row}>
                    <Form.Label column sm="5">{"Échéance"}</Form.Label>
                    <Col sm="7">
                        <DateTime disabled={(this.props.dataProvider.template.type == 'd')} onChange={this.onDataChange} value={this.state.data.endDate} name="endDate"/>
                    </Col>
                </Form.Group>
                <Form.Group  as={Row}>
                    <Col sm="5"></Col>
                    <Col sm="7">
                        <Button variant="primary" className="rounded"  type="submit">{"Assigner"}</Button>
                    </Col>
                </Form.Group>
            </Form>;

        return main;
    }

    onDataChange(event){
        let data = this.state.data;
        data[event.target.name] = event.target.value;
        this.setState({data: data});
    }

    onSubmit(event){
        const form = event.currentTarget;
        
        event.preventDefault();
        event.stopPropagation();
        
        this.setState({formValidated: true}, (form.checkValidity() ? this.onAssign : null));
    }

    onAssign(){
        if (!confirm($glVars.i18n.tags.msgConfirm)){
            return;
        }

        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }
           
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
            
            that.props.onAssign();
        }

        let assignments = []
        for (let item of this.props.dataProvider.assignments){
            let tmp = JsNx.clone(item);
            tmp.startDate = this.state.data.startDate;
            tmp.endDate = this.state.data.endDate;
            assignments.push(tmp);
        }

        $glVars.webApi.saveAssignment(assignments, null, callback);
    }
}

export class RythmeFormBatchAssignment extends Component{
    static defaultProps = {        
        onAssign: null,
        dataProvider: null
    };

    constructor(props){
        super(props);

        this.onAssign = this.onAssign.bind(this);
        this.onDataChange = this.onDataChange.bind(this);

        this.state = {
            data: {
                rhythme: 0 
            }
        };
    }

    render(){
        if(this.props.dataProvider === null){ return null;}

        let main =
            <div>
                <Form.Group className='mb-3' as={Row}>
                    <Form.Label column sm="5">{"Rythme (h/semaine)"}</Form.Label>
                    <Col sm="7">
                        <InputNumber min={0} nbDecimals={1} style={{display:'inline'}} onChange={this.onDataChange} value={this.state.data.rhythme} name='rhythme' placeholder="Rythme (h/semaine)"/>
                    </Col>
                </Form.Group>
                <Form.Group as={Row}>
                    <Col sm="5"></Col>
                    <Col sm="7">
                        <Button variant="primary" className="rounded" onClick={this.onAssign}>{"Assigner"}</Button>
                    </Col>
                </Form.Group>
            </div>;

        return main;
    }

    onDataChange(event){
        let data = this.state.data;
        data[event.target.name] = event.target.value;
        this.setState({data: data});
    }

    onAssign(){
        if (!confirm($glVars.i18n.tags.msgConfirm)){
            return;
        }

        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }
           
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
            
            that.props.onAssign();
        }

        let assignments = []
        for (let item of this.props.dataProvider.assignments){
            let tmp = JsNx.clone(item);
            tmp.nbHoursPerWeek = this.state.data.rhythme;
            assignments.push(tmp);
        }

        $glVars.webApi.saveAssignment(assignments, null, callback);
    }
}

export class AdditionalHoursFormBatchAssignment extends Component{
    static defaultProps = {        
        onAssign: null,
        dataProvider: null
    };

    constructor(props){
        super(props);

        this.onAssign = this.onAssign.bind(this);
        this.onDataChange = this.onDataChange.bind(this);

        this.state = {
            data: {
                nbAdditionalHours: 0, 
                additionalHoursReason: '',
            }
        };
    }

    render(){
        if(this.props.dataProvider === null){ return null;}

        let main =
            <div>
                <Form.Group className='mb-3' as={Row}>
                    <Form.Label column sm="5">{"Heures supplémentaires"}</Form.Label>
                    <Col sm="7">
                        <InputNumber nbDecimals={1} style={{display:'inline'}} name="nbAdditionalHours" onChange={this.onDataChange} value={this.state.data.nbAdditionalHours} placeholder="Heures"/>
                    </Col>
                </Form.Group>
                <Form.Group className='mb-3' as={Row}>
                    <Form.Label column sm="5">{"Raison"}</Form.Label>
                    <Col sm="7">
                        <CustomFormControl style={{display:'inline'}} max="250" name="additionalHoursReason"  onChange={this.onDataChange} type="text" value={this.state.data.additionalHoursReason}/>
                    </Col>
                </Form.Group>
                <Form.Group as={Row}>
                    <Col sm="5"></Col>
                    <Col sm="7">
                        <Button variant="primary" className="rounded"  disabled={this.state.data.additionalHoursReason.length == 0} onClick={this.onAssign}>{"Ajouter"}</Button>
                    </Col>
                </Form.Group>
            </div>;

        return main;
    }

    onDataChange(event){
        let data = this.state.data;
        data[event.target.name] = event.target.value;
        this.setState({data: data});
    }

    onAssign(){
        if (!confirm($glVars.i18n.tags.msgConfirm)){
            return;
        }

        let newItems = []
        for (let item of this.props.dataProvider.assignments){
            let add = {
                id: item.id,
                nbAdditionalHours: parseFloat(this.state.data.nbAdditionalHours),
                additionalHoursReason: this.state.data.additionalHoursReason,
                templateId: this.props.dataProvider.template.id
            };

            newItems.push(add);
        }

        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
            that.props.onAssign();
        }

        $glVars.webApi.addAssignmentAdditionalHours(newItems, callback);
    }
}

export class ModalAssignmentForm extends Component{
    static defaultProps = {        
        data: null,
        metadata: null,
        onClose: null
    };

    constructor(props){
        super(props);

        this.onSubmit = this.onSubmit.bind(this);
        this.onSave = this.onSave.bind(this);
        this.onDataChange = this.onDataChange.bind(this);
        this.onClose = this.onClose.bind(this);

        let copy = JsNx.clone(props.data);
        copy.endDate = (this.props.metadata.type === 'd' ? 0 : copy.endDate); // if workplan is dynamic then it resets the enddate
        
        this.state = {
            data: copy, 
            flags: {dataChanged: false},
            formValidated: false
        };
    }

    render(){
        if(this.state.data === null){ return null; }
        if(this.props.metadata === null){ return null; }

        let item = this.state.data;
        let dateMin = new Date(item.startDate*1000);
        dateMin.setDate(dateMin.getDate() + 1);

        let body = 
            <Form noValidate validated={this.state.formValidated} onSubmit={this.onSubmit}>
                <Form.Group className='mb-3'>
                    <Form.Label>{"Début"}</Form.Label>
                    <DateTime required={true} value={item.startDate} name="startDate" onChange={this.onDataChange} />
                    <Form.Control.Feedback type="invalid">Veuillez indiquer une date de début</Form.Control.Feedback>
                </Form.Group>
                <Form.Group className='mb-3'>
                    <Form.Label>{"Échéance"}</Form.Label>
                    <DateTime min={UtilsDateTime.formatDateTime(dateMin.getTime() / 1000, 'T')} disabled={this.props.metadata.type === 'd'} value={item.endDate} name="endDate" onChange={this.onDataChange} />
                    <Form.Text className="text-muted">Si le plan est dynamique, alors l'échéance est calculée dynamiquement.</Form.Text>
                </Form.Group>
                <Form.Group className='mb-3'>
                    <Form.Label>{"Commentaire"}</Form.Label>
                    <CustomFormControl as="textarea" rows={4} className='w-100' name="comment" value={item.comment} onChange={this.onDataChange}/>
                </Form.Group>
                <Form.Group className='mb-3'>
                    <Form.Label>{"H/semaine"}</Form.Label>
                    <InputNumber nbDecimals={1} min={0} disabled={this.props.metadata.type === 's'} value={item.nbHoursPerWeek} name="nbHoursPerWeek" onChange={this.onDataChange} />
                    <Form.Text className="text-muted">Si le plan de travail est statique, alors le rythme de travail n'est pas pris en compte.</Form.Text>
                </Form.Group>

                <hr/>
                <ButtonGroup className='d-flex justify-content-end'>
                    <Button style={{flex: 0}} variant='secondary'  onClick={this.onClose}>Annuler</Button>
                    <Button style={{flex: 0}} disabled={!this.state.flags.dataChanged} variant='success' type='submit'>Enregistrer</Button>
                </ButtonGroup>
            </Form>;


        let main = 
            <Modal show={true} onHide={this.onClose} size="lg" backdrop='static' className='recit_workplan'>
                <Modal.Header closeButton>
                    <Modal.Title>{'Modifier élève'}</Modal.Title>
                </Modal.Header>
                <Modal.Body>{body}</Modal.Body>
            </Modal>;

        return (main);
    }

    onDataChange(event, index){
        let data = this.state.data;
        let flags = this.state.flags;
        flags.dataChanged = (data[event.target.name] != event.target.value);
        data[event.target.name] = event.target.value;
        this.setState({data: data, flags: flags});
    }

    onSubmit(event){
        const form = event.currentTarget;
        
        event.preventDefault();
        event.stopPropagation();
        
        this.setState({formValidated: true}, (form.checkValidity() ? this.onSave : null));
    }

    onSave(){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            $glVars.webApi.processWorkPlan(that.state.data.templateId, (result) => {
                if(!result.success){
                    $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                    return;
                }

                that.props.onClose(that.state.flags.dataChanged);
                $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
            });
        }

        if(this.state.flags.dataChanged){           
            $glVars.webApi.saveAssignment([this.state.data], 'update', callback);
        }
    }

    onClose(){
        this.props.onClose();
    }
}

export class ModalAssignmentAdditionalHoursForm extends Component{
    static defaultProps = {        
        data: null,
        templateId: null,
        onClose: null
    };

    constructor(props){
        super(props);

        this.onSave = this.onSave.bind(this);
        this.onDataChange = this.onDataChange.bind(this);
        this.onClose = this.onClose.bind(this);

        this.state = {data: {id: props.data.id, templateId: props.templateId, nbAdditionalHours: 0, additionalHoursReason: ''}, flags: {dataChanged: false}};
    }

    render(){
        if(this.state.data === null){ return null; }

        let item = this.state.data;
        let body = 
            <Form>
                <Form.Group className='mb-3' as={Row}>
                    <Form.Label column sm="5">{"Heures supplémentaires"}</Form.Label>
                    <Col sm="7">
                        <InputNumber nbDecimals={1} style={{width: '80px', display: 'inline'}}  type="number" value={item.nbAdditionalHours} name="nbAdditionalHours" onChange={this.onDataChange} />
                        <Form.Text className="text-muted ms-3">Le nombre peut être négatif.</Form.Text>
                    </Col>
                </Form.Group>
                <Form.Group className='mb-3' as={Row}>
                    <Form.Label column sm="5">{"Raison"}</Form.Label>
                    <Col sm="7">
                        <CustomFormControl style={{display:'inline'}} onChange={this.onDataChange} max="250" name="additionalHoursReason" type="text" value={item.additionalHoursReason}/>
                    </Col>
                </Form.Group>
            </Form>;

        let modalFooter = 
        <ButtonGroup>
                <Button variant='secondary' className='rounded' onClick={this.onClose}>Annuler</Button>
                <Button disabled={!this.state.flags.dataChanged || item.additionalHoursReason.length == 0} variant='success' className='ms-2 rounded' onClick={this.onSave}>Enregistrer</Button>
        </ButtonGroup>;


        let main = 
            <Modal show={true} onHide={this.onClose} size="lg" backdrop='static' className='recit_workplan'>
                <Modal.Header closeButton>
                    <Modal.Title>{'Ajout d\'heures supplémentaires'}</Modal.Title>
                </Modal.Header>
                <Modal.Body>{body}</Modal.Body>
                <Modal.Footer>{modalFooter}</Modal.Footer>
            </Modal>;

        return (main);
    }

    onDataChange(event, index){
        let data = this.state.data;
        let flags = this.state.flags;
        flags.dataChanged = (data[event.target.name] != event.target.value);
        data[event.target.name] = event.target.value;
        this.setState({data: data, flags: flags});
    }

    onSave(){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            that.props.onClose(that.state.flags.dataChanged);
            $glVars.feedback.showInfo($glVars.i18n.tags.appName, $glVars.i18n.tags.msgSuccess, 3);
        }

        if(this.state.flags.dataChanged){
            $glVars.webApi.addAssignmentAdditionalHours([this.state.data], callback);
        }
    }

    onClose(){
        this.props.onClose();
    }
}

export class ModalAssignmentAdditionalHoursHistory extends Component{
    static defaultProps = {        
        data: null,
        onClose: null
    };

    constructor(props){
        super(props);

        this.onClose = this.onClose.bind(this);

        this.state = {data: props.data, history: null};
    }

    componentDidMount(){
        this.getData();
    }

    render(){
        if(this.state.history === null){ return null; }

        let body = 
            <div style={{maxHeight:'600px',overflowY:'auto'}}>
                    <DataGrid orderBy={true} style={{wordBreak:'break-all'}}>
                        <DataGrid.Header>
                            <DataGrid.Header.Row>
                                <DataGrid.Header.Cell style={{width: "170px"}}>Date</DataGrid.Header.Cell>
                                <DataGrid.Header.Cell style={{width: "100px"}}>Heures</DataGrid.Header.Cell>
                                <DataGrid.Header.Cell style={{width: "215px"}}>Responsable</DataGrid.Header.Cell>
                                <DataGrid.Header.Cell >Raison</DataGrid.Header.Cell>
                            </DataGrid.Header.Row>
                        </DataGrid.Header>
                        <DataGrid.Body>
                            {this.state.history.map((item, index) => {   
                                // all items (children) need to be inside a single array otherwise the orderby won't work                                 
                                    let items =  [];
                                    let date = UtilsDateTime.toTimeString(item.lastupdate)

                                    let cell = 
                                        <DataGrid.Body.Cell sortValue={date}  key={items.length} freezing={true}>
                                            {date}
                                        </DataGrid.Body.Cell>;

                                    items.push(cell);

                                    cell = 
                                        <DataGrid.Body.Cell sortValue={item.nb_additional_hours}  key={items.length} freezing={true}>
                                            {item.nb_additional_hours}h
                                        </DataGrid.Body.Cell>;

                                    items.push(cell);


                                    cell = 
                                        <DataGrid.Body.Cell sortValue={item.assignorname}  key={items.length} freezing={true}>
                                            {item.assignorname}
                                        </DataGrid.Body.Cell>;

                                    items.push(cell);

                                    cell = 
                                        <DataGrid.Body.Cell sortValue={item.comment}  key={items.length} freezing={true}>
                                            {item.comment}
                                        </DataGrid.Body.Cell>;

                                    items.push(cell);
                                        
                                    return (<DataGrid.Body.Row key={index}>{items}</DataGrid.Body.Row>);                                    
                                }
                            )}
                        </DataGrid.Body>
                    </DataGrid>
            </div>;


        let main = 
            <Modal show={true} onHide={this.onClose} size="lg" backdrop='static' className='recit_workplan'>
                <Modal.Header closeButton>
                    <Modal.Title>{'Heures supplémentaires de '+this.state.data.user.fullname}</Modal.Title>
                </Modal.Header>
                <Modal.Body>{body}</Modal.Body>
            </Modal>;

        return (main);
    }

    getData(){
        let that = this;
        let callback = function(result){
            if(!result.success){
                $glVars.feedback.showError($glVars.i18n.tags.appName, result.msg);
                return;
            }

            that.setState({history: result.data})
        }

        $glVars.webApi.getAssignmentAdditionalHours(this.state.data.id, callback);
    }

    onClose(){
        this.props.onClose();
    }
}