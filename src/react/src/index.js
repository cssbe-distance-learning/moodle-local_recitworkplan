import React, { Component } from 'react';
import { createRoot } from 'react-dom/client';
/**************************************************************************************
 *  il ne faut pas charger le bootstrap de base car il est déjà chargé dans le thème
 * //import 'bootstrap/dist/css/bootstrap.min.css';  
 **************************************************************************************/ 
import {faSpinner} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {VisualFeedback, Loading} from "./libs/components/Components";
import Utils from "./libs/utils/Utils";
import './common/i18n';
import {AdminView} from "./views/WorkPlanView";
import {$glVars, Options} from "./common/common";
import "./css/style.scss"; 
import { StudentView } from './views/StudentView';
import { AdminBlockView, StudentBlockView } from './views/BlockView';

export * from "./common/i18n";
 
class App extends Component {
    static defaultProps = {
        signedUser: null,
        mode: 'a',
        workPlanId: 0
    };

    static loaded

    constructor(props) {
        super(props);

        this.onFeedback = this.onFeedback.bind(this);

        $glVars.signedUser = this.props.signedUser;
        $glVars.urlParams = Utils.getUrlVars();

        //(UtilsMoodle.checkRoles($glVars.signedUser.roles, UtilsMoodle.rolesL2) ? 't' : 's');

        this.state = {mode: this.props.mode};
    }

    componentDidMount(){
        $glVars.feedback.addObserver("App", this.onFeedback);
        if (this.state.mode  === 'a' || this.state.mode  === 's'){
            window.document.title = window.document.title + ' - v' + Options.appVersion();
        }
    }

    componentWillUnmount(){
        $glVars.feedback.removeObserver("App");        
    }

    render() {
        let view = null;
        let minHeight = '450px';
        if (this.state.mode  === 'a'){
            view = <AdminView workPlanId={this.props.workPlanId}/>;
        }else if (this.state.mode  === 's'){
            view =<StudentView workPlanId={this.props.workPlanId}/>;
        }else if (this.state.mode  === 'sb'){
            view =<StudentBlockView/>;
            minHeight = 'auto';
        }else if (this.state.mode  === 'ab'){
            view =<AdminBlockView/>;
            minHeight = 'auto';
        }
        let main =
            <div style={{minHeight: minHeight}}>
                {view}
                {$glVars.feedback.msg.map((item, index) => {  
                    return (<VisualFeedback key={index} id={index} msg={item.msg} type={item.type} title={item.title} timeout={item.timeout}/>);                                    
                })}
                <Loading webApi={$glVars.webApi}><FontAwesomeIcon icon={faSpinner} spin/></Loading>
            </div>

        return (main);
    }

    onFeedback(){
        this.forceUpdate();
    }
}

document.addEventListener('DOMContentLoaded', function(e){     
    let domContainer = document.getElementById('recit_workplan');
    if (domContainer && domContainer.childNodes.length === 0){
        const root = createRoot(domContainer);
        let signedUser = {userId: domContainer.getAttribute('data-user-id')};
        root.render(<App signedUser={signedUser} mode={domContainer.getAttribute('data-mode')} workPlanId={parseInt(domContainer.getAttribute('data-workplanid'))}/>);
    }
}, false);