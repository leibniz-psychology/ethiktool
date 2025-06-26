import { Controller } from "@hotwired/stimulus";
import {setElementVisibility, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['qualificationYes','applicantPosition','supervisorPosition','supervisorDiv','projectStart','projectStartNext','projectStartBegun','projectStartBegunText','fundingResearch','fundingExternal','conflictNo'];

    static values = {
        positions: Array, // 0: positions without qualification, 1: positions with qualification, 2: positions for supervisor (not used), 3: all positions translated
        noChoice: String,
        conflictHint: Array, // 0: description for yes, 1: description for no
        conflictHintName: String // id of the hint div
    }

    connect() {
        this.studentValue = 'student';
        this.phdValue = 'phd';
        this.studentPhd = [this.studentValue,this.phdValue];
        this.positionOtherValue = 'positionOther';
        this.conflictYesTarget = document.getElementById(this.conflictNoTarget.id.replace('1','0')); // renderButtons allows only one target; therefore, get the other by using the id
        this.setApplicantSupervisor();
        this.setProjectStart();
        this.setConflict();
    }

    // methods that are called from the template

    /** Sets the hint for the professorship as well as the visibility of the text field for the 'other' position.
     * @param event widget that invoked the method
     */
    setProfessorshipHint(event) {
        let target = event.target;
        let id = target.id;
        let value = target.value;
        setElementVisibility(id+'Hint',this.studentPhd.includes(value));
        setElementVisibility(id+'Other',value===this.positionOtherValue);
        if (!id.includes('supervisor')) {
            this.setApplicantSupervisor();
        }
    }

    // methods that are called from the template or from within this class

    /** Sets the visibility of the project start widgets. */
    setProjectStart() {
        let isNext = this.projectStartNextTarget.checked;
        let isBegun = this.hasProjectStartBegunTarget && this.projectStartBegunTarget.checked;
        setElementVisibility(this.projectStartTarget.parentElement,!(isNext || isBegun));
        setElementVisibility(this.projectStartNextTarget.parentElement,!isBegun);
        if (this.hasProjectStartBegunTarget) {
            setElementVisibility(this.projectStartBegunTarget.parentElement,!isNext);
            setElementVisibility(this.projectStartBegunTextTarget,isBegun);
        }
    }

    /** Sets the visibility of the supervisor div as well as the positions for the applicant and supervisor and the phone label for the applicant. */
    setApplicantSupervisor() {
        if (this.hasQualificationYesTarget) { // only EUB
            let isQualification = this.qualificationYesTarget.checked;
            let positionApplicant = this.applicantPositionTarget.value;
            let isSupervisor = isQualification && this.studentPhd.includes(positionApplicant);
            setElementVisibility(this.supervisorDivTarget,isSupervisor);
            // set allowed positions for applicant and supervisor: remove all positions and recreate allowed ones
            this.setPositions(this.applicantPositionTarget,this.positionsValue[isQualification ? 1 : 0],positionApplicant);
            if (isSupervisor) {
                let positions = this.positionsValue[0];
                delete positions[this.studentValue];
                if (positionApplicant===this.phdValue) {
                    delete positions[this.phdValue];
                }
                this.setPositions(this.supervisorPositionTarget,positions,this.supervisorPositionTarget.value);
            }
            let isStudent = positionApplicant===this.studentValue;
            setElementVisibility('phoneLabel',!isStudent);
            setElementVisibility('phoneLabelOptional',isStudent);
        }
    }

    /** Sets the conflict widgets. */
    setConflict() {
        let isConflict = this.conflictYesTarget.checked;
        setElementVisibility('conflictDescriptionDiv',isConflict || this.conflictNoTarget.checked && (this.fundingResearchTarget.checked || this.fundingExternalTarget.checked));
        setHint(this.conflictHintNameValue,this.conflictHintValue[isConflict ? 0 : 1]);
        setElementVisibility('participantDescriptionDiv',isConflict);
    }

    // methods that are called from within this class

    /** Removes all positions from the target and recreates them.
     * @param target element whose positions are recreated
     * @param positions positions that are newly added
     * @param oldPosition position that currently selected
     */
    setPositions(target,positions,oldPosition) {
        while (target.hasChildNodes()) {
            target.firstChild.remove();
        }
        positions = Object.keys(positions);
        let positionsTranslated = this.positionsValue[3];
        for (let choice of [''].concat(positions)) {
            let newChoice = document.createElement('option');
            target.append(newChoice);
            newChoice.value = choice;
            newChoice.textContent = choice!=='' ? positionsTranslated[choice] : this.noChoiceValue;
        }
        if (positions.includes(oldPosition)) { // keep selection if still allowed
            target.value = oldPosition;
        }
        if (!positions.includes(this.positionOtherValue)) { // if position of applicant was 'other' and then qualification was answered with yes, hide the text field
            setElementVisibility(this.positionOtherValue,false);
        }
    }
}