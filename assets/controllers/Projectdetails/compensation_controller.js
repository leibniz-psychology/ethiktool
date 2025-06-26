import { Controller } from "@hotwired/stimulus";
import {setElementVisibility, getSelected, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['moneyMiddle','moneyEndSpecific','moneyValue','moneyAmountReal','hoursAmountFlat','hoursValueDiv','hoursValue','hoursEndDefault','hoursEndSpecific','moneyFurther','terminate','awarding','further','textInput'];

    static values = {
        compensationTypes: Array, // without 'no compensation'
        terminateHint: Array // 0: no compensation, 1: other
    }

    connect() {
        this.setCompensation();
        for (let type of this.compensationTypesValue) {
            this.setAwarding(type);
        }
    }

    /** Sets the compensation widgets. */
    setCompensation() {
        let anySelected = getSelected(this.compensationTypesValue)[0];
        setElementVisibility(this.terminateTarget,anySelected);
        setElementVisibility(this.awardingTarget,anySelected); // entire question
        setElementVisibility(this.furtherTarget,anySelected);
        for (let type of this.compensationTypesValue) {
            let isMoney = type==='money';
            let isChecked = document.getElementById(type).checked;
            if (!isMoney) {
                setElementVisibility(type+'DescriptionDiv',isChecked);
            }
            if (isMoney || type==='hours') {
                setElementVisibility(type+'Amount',isChecked);
                if (isMoney) {
                    setElementVisibility(this.moneyFurtherTarget,isChecked);
                    setElementVisibility(this.moneyMiddleTarget,isChecked); // text between label and spinner
                    setElementVisibility(this.moneyValueTarget,isChecked);
                    setElementVisibility(this.moneyEndSpecificTarget,this.moneyAmountRealTarget.checked);  // text between currency symbol and full stop
                }
                else {
                    setElementVisibility(this.hoursValueDivTarget,this.hoursAmountFlatTarget.checked,2);
                    let isOneHour = this.hoursValueTarget.value==='1';
                    setElementVisibility(this.hoursEndDefaultTarget,!isOneHour);
                    setElementVisibility(this.hoursEndSpecificTarget,isOneHour);
                }
            }
            setElementVisibility('awarding'+type+'Div',isChecked); // question for (de)selected type
        }
        this.setInputHint();
    }

    /** Sets the terminate widget.
     * @param event widget that invoked the method
     */
    setTerminate(event) {
        let value = event.target.value;
        let isNothing = value==='nothing';
        let descriptionDiv = document.getElementById('terminateDescriptionDiv');
        setElementVisibility(descriptionDiv,isNothing || value==='terminateOther');
        setHint(descriptionDiv,this.terminateHintValue[isNothing ? 0 : 1]);
    }

    /** Sets the awarding widgets.
     * @param type either an event or a compensation type
     */
    setAwarding(type) {
        if (type.currentTarget!==undefined) { // event
            type = type.currentTarget.id.replace('Awarding',''); // currentTarget must be the div surrounding the choice and further inputs (if any) with the id type~'Awarding'
        }
        for (let choice of ['later','deliver','external','other']) {
            let curChoice = type+choice;
            let div = document.getElementById(curChoice+'Div');
            if (div!==null) {
                setElementVisibility(div,document.getElementById(curChoice).checked); // div belonging to the choice
            }

        }
        this.setInputHint();
    }

    /** Sets the hint saying that input are removed. */
    setInputHint() {
        if (this.hasTextInputTarget) {
            let isHint = true;
            for (let type of this.compensationTypesValue) {
                let later = type+'later';
                if (document.getElementById(type).checked && document.getElementById(later).checked && document.getElementById('compensation_'+later+'Information_0').checked) {
                    isHint = false;
                }
            }
            for (let target of this.textInputTargets) {
                setElementVisibility(target,isHint);
            }
        }
    }
}