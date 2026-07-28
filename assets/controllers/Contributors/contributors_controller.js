import { Controller } from "@hotwired/stimulus";
import {sanitizeString, setElementVisibility} from "../multiFunction";

export default class extends Controller {

    static targets = ['nameError','institutionIcon','professorshipIcon','eMailError','institution','institutionOther','position','positionOther','phoneError','supervisionTask','supervisionIcon','otherTask','taskOtherDescription','taskHint','modal','modalLabel','modalSubmit','modalFooter']

    static values = {
        committeeType: String,
        contributors: Array, // all contributors
        title: Array,
        institutions: Array,
        positions: Object,
        committeeStudent: Array, // committees where student is allowed position
        committeePhdSupervisor: Array, // committees where a supervisor is needed if position is PhD
        committeePhdTasks: Array, // committees where leader and data can not be selected as tasks if position is PhD
        isQualification: Boolean, // true if qualification question was answered with yes
        noChoice: String,
        infosNames: Array,
        tasksNames: Array,
        tasksHints: Array // 0: no position selected, 1: position selected
    }

    connect() {
        this.modalType = ''; // add, edit, or remove -> used for setting the value of the modal submit button which is used for identifying the type in the php-controller
        this.studentChoiceValue = 'student'; // value of the option-tag
        this.phdChoiceValue = 'phd'; // value of the option-tag
        this.institutionPosition = ['institution','position'];
        this.positionOtherValue = 'positionOther'; // value of the option-tag
        this.applicantPosition = this.contributorsValue[0]['infos']['position'];
        // fill the modal inputs if a contributor gets edited
        this.modalTarget.addEventListener('show.bs.modal', event => {
            let id = event.relatedTarget.getAttribute('data-bs-id');
            this.modalType = id;
            this.setSubmitDummy();
            this.idValue = parseInt(id.substring(4)); // id of the contributor that is edited
            let isApplicant = this.idValue===0;
            let isAdd = id==='add';
            let noChoice = {'': this.noChoiceValue};
            let allowedPositions = Object.assign({},noChoice,this.positionsValue);
            if (isApplicant && this.isQualificationValue && this.committeeTypeValue==='EUB') { // applicant and has supervisor or qualification was answered with yes -> only PhD and student
                allowedPositions = noChoice;
                allowedPositions[this.phdChoiceValue] = this.positionsValue[this.phdChoiceValue];
                allowedPositions[this.studentChoiceValue] = this.positionsValue[this.studentChoiceValue];
            } else {
                if (isApplicant && !this.committeeStudentValue.includes(this.committeeTypeValue)) { // applicant must not be student
                    delete allowedPositions[this.studentChoiceValue];
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
                let positionsKeys = Object.keys(this.positionsValue);
                for (let curInfo of this.infosNamesValue) {
                    let curValue = infos[curInfo];
                    let isInstitution = curInfo==='institution';
                    if (this.institutionPosition.includes(curInfo) && curValue!=='' && !((isInstitution ? this.institutionsValue : positionsKeys).includes(curValue))) { // 'other' institution or position
                        if (isInstitution) {
                            this.institutionTarget.value = 'institutionOther';
                            this.institutionOtherTarget.value = curValue;
                        } else {
                            this.positionTarget.value = this.positionOtherValue;
                            this.positionOtherTarget.value = curValue;
                        }
                    } else if (curValue!==undefined) { // phone may be optional
                        document.getElementById(curInfo).value = curValue;
                    }
                }
                this.setInstitution();
                let tasks = contributor['tasks'];
                for (let task of this.tasksNamesValue) {
                    document.getElementById(task).checked = tasks[task]!==undefined;
                }
                this.taskOtherDescriptionTarget.value = this.otherTaskTarget.checked ? tasks['other'] : '';
            } else {
                this.idValue = this.contributorsValue.length;
            }
            if (this.hasSupervisionIconTarget) {
                setElementVisibility(this.supervisionIconTarget,this.idValue>0);
            }
            this.modalLabelTarget.textContent = this.titleValue[isAdd ? 0 : 1];
            this.setTasks();
            this.setSubmitButton();
        });
        this.modalSubmitTarget.addEventListener('click', event => {
            event.target.value = this.modalType;
        });
        // sanitize inputs in modal
        for (let info of this.infosNamesValue) {
            (!this.institutionPosition.includes(info) ? document.getElementById(info) : (info==='institution' ? this.institutionOtherTarget : this.positionOtherTarget)).addEventListener('input', event => {
                let target = event.target;
                target.value = sanitizeString(target.value);
            });
        }
        this.taskOtherDescriptionTarget.addEventListener('input', event => {
            let target = event.target;
            target.value = sanitizeString(target.value);
        });
        this.setInstitution();
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

    /** Sets the visibility of the institution other text field and the icon. */
    setInstitution() {
        setElementVisibility(this.institutionOtherTarget,this.institutionTarget.value==='institutionOther');
        setElementVisibility(this.institutionIconTarget,this.idValue===0 && this.institutionTarget.value==='institutionOther');
        this.setSubmitButton();
    }

    /** Enables or disables the tasks and sets the visibility of the 'other' text fields. */
    setTasks() {
        let position = this.positionTarget.value;
        let disabled = position==='';
        let isStudent = position===this.studentChoiceValue;
        let isPhd = position===this.phdChoiceValue;
        let isApplicantPhdSupervisor = this.applicantPosition===this.phdChoiceValue && this.committeePhdSupervisorValue.includes(this.committeeTypeValue); // supervisor is needed for PhD
        let isApplicantPhdTasks = this.committeePhdTasksValue.includes(this.committeeTypeValue); // leader and data not allowed for PhD
        let isSupervisorNeeded = this.applicantPosition===this.studentChoiceValue || isApplicantPhdSupervisor;
        let isApplicant = this.idValue===0;
        for (let task of this.tasksNamesValue) {
            let widget = document.getElementById(task);
            let isSupervision = task==='supervision';
            // let noAvailableTask = isStudent && ['leader','data','supervision'].includes(task) && !(this.isQualificationValue && task==='data' && isApplicant) || isSupervision && (isApplicant || !isSupervisorNeeded || isApplicantPhdSupervisor && isPhd) || ['leader','data'].includes(task) && isApplicant && isPhd && isApplicantPhdTasks;
            let noAvailableTask = ['leader','data'].includes(task) && (isStudent && !(isApplicant && this.isQualificationValue && task==='data') || isApplicant && isPhd && isApplicantPhdTasks) || isSupervision && (isApplicant || !isSupervisorNeeded || isStudent || isPhd && isApplicantPhdSupervisor);
            widget.disabled = noAvailableTask || disabled;
            widget.checked = noAvailableTask || disabled ? false : widget.checked;
        }
        setElementVisibility(this.taskOtherDescriptionTarget,this.otherTaskTarget.checked,1)
        this.taskHintTarget.innerHTML = this.tasksHintsValue[disabled ? 0 : 1];
        this.taskHintTarget.style.fontStyle = disabled ? 'italic' : 'normal';
        this.taskHintTarget.style.fontWeight = disabled ? 'normal' : 'bold';
        setElementVisibility(this.professorshipIconTarget,isStudent || isPhd,1);
        setElementVisibility(this.positionOtherTarget,position===this.positionOtherValue,1);
        this.setSubmitButton();
    }

    /** (de)actives the submit button depending on the inputs that were made. */
    setSubmitButton() {
        let disabled = false;
        for (let info of this.infosNamesValue) {
            let tempVal = false;
            let value = document.getElementById(info).value.trim();
            let hasValue = value!=='';
            let isPhone = info==='phone';
            disabled |= !isPhone && !hasValue;
            if (info==='name') {
                tempVal = value.split(' ').length===1;
                setElementVisibility(this.nameErrorTarget,hasValue && tempVal,1);
            } else if (info==='eMail') {
                // local: start with letter, then any number of any character except §ß`"()\€[]. domain: start with letter, then any number of letters and digits, then a dot, than only letters, but at least two
                tempVal = !this.getInputValidityEmpty(value,/^[a-zA-Z]+[a-zA-Z0-9.!#$%&'*+-/=?^_`{|}~]*@[a-zA-Z]+[a-zA-Z0-9-.]*[.][a-zA-Z]{2,}$/) || value.includes('.@') || value.includes(',');
                setElementVisibility(this.eMailErrorTarget,tempVal,1);
            } else if (isPhone) {
                tempVal = !this.getInputValidityEmpty(value,/^\+?([0-9][\s\/-]?)+[0-9]+$/); // optionally starting with a '+', then at least two numbers. After each number (except the last), optionally a separator space, '/', or '-'
                setElementVisibility(this.phoneErrorTarget,tempVal,1);
            } else if (this.institutionPosition.includes(info) && value===(info+'Other')) {
                tempVal = (info==='institution' ? this.institutionOtherTarget : this.positionOtherTarget).value.trim()==='';
            }
            disabled |= tempVal;
        }
        let numTasks = 0;
        for (let curTask of this.tasksNamesValue) {
            numTasks += document.getElementById(curTask).checked ? 1 : 0;
        }
        disabled = disabled || numTasks===0 || // no task selected
            this.otherTaskTarget.checked && this.taskOtherDescriptionTarget.value.trim()==='' || // 'other' task description is missing
            numTasks===1 && !this.isQualificationValue && this.hasSupervisionTaskTarget && this.supervisionTaskTarget.checked; // only 'supervision' is selected
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
