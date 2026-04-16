import React, { Component } from 'react';
import { ButtonToolbar, ToggleButtonGroup, ToggleButton as BsToggleButton  } from 'react-bootstrap';

export class ToggleButtons extends Component {
    static defaultProps = {
        name: "",
        defaultValue: [],
        value: [],
        onChange: null,
        type: "checkbox", // checkbox | radio
        options: [], // {value: "", text:"", glyph: ""}
        bsSize: "", // "" | small
        style: null,
        disabled: false
    };
      
    constructor(props){
        super(props);

        this.onChange = this.onChange.bind(this);
    }

    render() {       
        console.log(this.props.value)  
        let main =  
            <ButtonToolbar style={this.props.style} data-read-only={(this.props.disabled ? 1 : 0)}>                        
                <ToggleButtonGroup size={this.props.bsSize} type={this.props.type} name={this.props.name} defaultValue={this.props.defaultValue} value={this.props.value} onChange={this.onChange}>                                
                    {this.props.options.map((item, index) => {   
                        let onClick = (this.props.disabled ? null : (e) => this.onClick(item.value, e));

                        let element = 
                            <BsToggleButton  id={`togglebtn-${this.props.name || 'tb'}-${index}`} className='rounded m-1' key={index} variant={(this.props.value.includes(item.value) ? "primary" : "light")} onClick={onClick} value={item.value} disabled={this.props.disabled}>
                                {item.text}
                            </BsToggleButton>;
                        return (element);
                    })}                                    
                </ToggleButtonGroup>
            </ButtonToolbar>;
        return (main);
    }   
    
    onChange(eventKey){
        if (this.props.onChange){
            this.props.onChange({target: {value: eventKey, name: this.props.name}});
        }
    }

    onClick(eventKey, e){
        if (this.props.onClick){
            e.preventDefault();
            this.props.onClick({target: {value: eventKey, name: this.props.name}});
        }
    }
}
