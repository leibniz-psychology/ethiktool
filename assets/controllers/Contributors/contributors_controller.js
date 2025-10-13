import { Controller } from "@hotwired/stimulus";
import {sanitizeString, setElementVisibility} from "../multiFunction";

export default class extends Controller {

    static targets = ['nameError','professorshipIcon','eMailError','position','positionOther','institutionLabel','phoneLabel','phoneError','taskOther','taskOtherDescription','taskHint','modal','modalLabel','modalSubmit','modalFooter']

    static values = {
        committeeType: String,
        contributors: Array, // all contributors
        title: Array,
        isStudentApplicant: Boolean, // true if position of applicant is student
        positions: Object,
        committeeStudent: Array, // committees where student is allowed position
        hasSupervisor: Boolean, // true if second contributor exists and has task 'supervision'
        isQualification: Boolean, // true if qualification question was answered with yes
        noChoice: String,
        infosNames: Array,
        institutionLabel: Array, // 0: applicant/supervisor, 1: remaining contributors
        phoneLabel: Array, // 0: mandatory, 1: optional
        tasksNames: Array}

    connect() {
        this.modalType = ''; // add, edit, or remove -> used for setting the value of the modal submit button which is used for identifying the type in the php-controller
        this.studentChoiceValue = 'student'; // value of the option-tag
        this.phdChoiceValue = 'phd'; // value of the option-tag
        this.positionOtherValue = 'positionOther'; // value of the option-tag
        this.isStudentApplicantValue = this.contributorsValue[0]['infos']['position']===this.studentChoiceValue;
        // fill the modal inputs if a contributor gets edited
        this.modalTarget.addEventListener('show.bs.modal', event => {
            let id = event.relatedTarget.getAttribute('data-bs-id');
            this.modalType = id;
            this.setSubmitDummy();
            this.idValue = parseInt(id.substring(4)); // id of the contributor that is edited
            let isApplicant = this.idValue===0;
            let isSupervisor = this.idValue===1 && this.hasSupervisorValue;
            let isAdd = id==='add';
            let noChoice = {'': this.noChoiceValue};
            let allowedPositions = Object.assign({},noChoice,this.positionsValue);
            let isStudent = this.committeeStudentValue.includes(this.committeeTypeValue);
            if (isApplicant && (this.hasSupervisorValue || this.isQualificationValue) && this.committeeTypeValue==='EUB') { // applicant and has supervisor or qualification was answered with yes -> only PhD and eventually student
                allowedPositions = noChoice;
                allowedPositions[this.phdChoiceValue] = this.positionsValue[this.phdChoiceValue];
                if (isStudent) {
                    allowedPositions[this.studentChoiceValue] = this.positionsValue[this.studentChoiceValue];
                }
            }
            else {
                if (isSupervisor || isApplicant && !isStudent) { // applicant and supervisor must not be student
                    delete allowedPositions[this.studentChoiceValue];
                }
                if (isSupervisor && this.contributorsValue[0]['infos']['position']===this.phdChoiceValue) { // if applicant is PhD, supervisor must not be PhD
                    delete allowedPositions[this.phdChoiceValue];
                }
            }
            // remove all positions and recreate them
            while (this.positionTarget.hasChildNodes()) {
                this.positionTarget.firstChild.remove();
            }
            for (let [choice, translation] of Object.entries(allowedPositions)) {
                let newChoice = document.createElement('option');
                this.positionTarget.append(newChoice);
                newChoice.value = choice;
                newChoice.textContent = translation;
            }
            // set content and visibility of widgets
            if (!isAdd) {
                let contributor = this.contributorsValue[this.idValue];
                let infos = contributor['infos'];
                let positionOtherTextName = this.positionOtherTarget.id;
                let positionsKeys = Object.keys(this.positionsValue);
                for (let curInfo of this.infosNamesValue) {
                    let curValue = infos[curInfo];
                    if (curInfo==='position' && curValue!=='' && (curValue===positionOtherTextName || !positionsKeys.includes(curValue))) { // 'other position'
                        this.positionTarget.value = this.positionOtherValue;
                        this.positionOtherTarget.value = curValue;
                    }
                    else if (curValue!==undefined) { // phone may be optional
                        document.getElementById(curInfo).value = curValue;
                    }
                }
                let tasks = contributor['tasks'];
                for (let task of this.tasksNamesValue) {
                    document.getElementById(task).checked = tasks[task]!==undefined;
                }
                this.taskOtherDescriptionTarget.value = this.taskOtherTarget.checked ? tasks['other'] : '';
            }
            else {
                this.idValue = this.contributorsValue.length;
            }
            this.modalLabelTarget.textContent = this.titleValue[isAdd ? 0 : 1];
            this.institutionLabelTarget.firstChild.textContent = this.institutionLabelValue[isApplicant || isSupervisor ? 0 : 1]+':';
            this.setTasks();
            this.setSubmitButton();
        });
        this.modalSubmitTarget.addEventListener('click', event => {
            event.target.value = this.modalType;
        });
        // sanitize inputs in modal
        for (let info of this.infosNamesValue) {
            (info!=='position' ? document.getElementById(info) : this.positionOtherTarget).addEventListener('input', event => {
                let target = event.target;
                target.value = sanitizeString(target.value);
            });
        }
        this.taskOtherDescriptionTarget.addEventListener('input', event => {
            let target = event.target;
            target.value = sanitizeString(target.value);
        })
    }

    // methods that are called from the template

    /** Sets this.modalType to indicate that a contributor should be removed.
     * @param event widget that invoked the method
     */
    removeContributor(event) {
        let target = event.target;
        while (target.id==='') { // the element is a button with a svg inside, so click may go on svg
            target = target.parentElement;
        }
        this.modalType = target.id;
        this.setSubmitDummy();
        this.modalSubmitTarget.disabled = false; // in case an edit modal was open before which was cancelled
        this.modalSubmitTarget.click(); // simulate a click in order to have the submitted form a 'modalSubmitButton' field
    }

    // methods that are called from the template and from within this class

    /** Enables or disables the tasks and sets the visibility of the 'other' text fields. Additionally, sets the label for the phone text field. */
    setTasks() {
        let position = this.positionTarget.value;
        let disabled = position==='';
        let isStudent = position===this.studentChoiceValue;
        this.isStudentApplicantValue = this.idValue===0 && isStudent;
        this.phoneLabelTarget.textContent = this.phoneLabelValue[this.idValue===0 && !this.isStudentApplicantValue || this.idValue===1 && this.hasSupervisorValue ? 0 : 1]+':'; // needs to be set here because method is also invoked if position changed and phone for student is optional
        for (let task of this.tasksNamesValue) {
            let widget = document.getElementById(task);
            let noStudentTask = isStudent && (task==='leader' || task==='data');
            widget.disabled = noStudentTask || disabled;
            widget.checked = noStudentTask || disabled ? false : widget.checked;
        }
        this.taskOtherDescriptionTarget.disabled = disabled;
        setElementVisibility(this.taskOtherDescriptionTarget,this.taskOtherTarget.checked,1)
        setElementVisibility(this.taskHintTarget,disabled,1);
        setElementVisibility(this.professorshipIconTarget,isStudent || position==='phd',1);
        setElementVisibility(this.positionOtherTarget,position===this.positionOtherValue,1);
        this.setSubmitButton();
    }

    /** (de)actives the submit button depending on the inputs that were made. */
    setSubmitButton() {
        let disabled = false;
        let isApplicant = this.idValue===0;
        let isSecondContributor = this.idValue===1
        for (let info of this.infosNamesValue) {
            let tempVal = false;
            let value = document.getElementById(info).value.trim();
            let isPhone = info==='phone';
            disabled |= (isPhone && (this.idValue>1 || isApplicant && this.isStudentApplicantValue || isSecondContributor && !this.hasSupervisorValue)) ? false : value===''; // phone may only be mandatory for first or second contributor
            if (info==='name') {
                tempVal = value.split(' ').length===1;
                setElementVisibility(this.nameErrorTarget,value!=='' && tempVal,1);
            }
            else if (info==='eMail') {
                // local: start with letter, then any number of any character except §ß`"()\€[]. domain: start with letter, then any number of letters and digits, then a dot, than only letters, but at least two
                tempVal = !this.getInputValidityEmpty(value,/^[a-zA-Z]+[a-zA-Z0-9.!#$%&'*+-/=?^_`{|}~]*@[a-zA-Z]+[a-zA-Z0-9-.]*[.][a-zA-Z]{2,}$/);
                setElementVisibility(this.eMailErrorTarget,tempVal,1);
            }
            else if (isPhone) {
                tempVal = !this.getInputValidityEmpty(value,/^\+?([0-9][\s\/-]?)+[0-9]+$/); // optionally starting with a '+', then at least two numbers. After each number (except the last), optionally a separator space, '/', or '-'
                setElementVisibility(this.phoneErrorTarget,tempVal,1);
            }
            else if (info==='position' && value===this.positionOtherValue) {
                tempVal = this.positionOtherTarget.value.trim()==='';
            }
            disabled |= tempVal;
        }
        let curTask = 0;
        let hasTask = false;
        while (!hasTask && curTask<this.tasksNamesValue.length) {
            hasTask = document.getElementById(this.tasksNamesValue[curTask]).checked;
            ++curTask;
        }
        disabled = disabled || !(hasTask && (!this.taskOtherTarget.checked || this.taskOtherDescriptionTarget.value.trim()!==''));
        this.modalSubmitTarget.disabled = disabled;
        setElementVisibility(this.modalFooterTarget,disabled);
    }

    // methods that are called from within this class

    /** Returns if a string is either empty or matches a regular expression.
     * @param input string to be tested
     * @param regEx regular expression
     * @return true if the string is empty or matches the regular expression, false otherwise
     */
    getInputValidityEmpty(input,regEx) {
        return input==='' | regEx.test(input);
    }

    setSubmitDummy() {
        document.getElementById('submitDummy').value = 'modalSubmitButton:'+this.modalType;
    }
}