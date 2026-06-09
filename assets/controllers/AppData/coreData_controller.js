import { Controller } from "@hotwired/stimulus";
import {closeModal, saveUndoModal, setElementVisibility, setHint, showModal} from "../multiFunction";

export default class extends Controller {

    static targets = ['projectTitleParticipation','applicationFull','shortDocs','shortDocsYes','qualificationYes','applicantPosition','supervisorPosition','supervisorDiv','projectStart','projectStartNext','projectStartBegun','projectStartBegunText','fundingQuali','fundingBudget','fundingResearch','fundingResearchRequested','fundingExternal','fundingExternalRequested','fundingOther','requestedInput','requestedConfirm','requestedConfirmHint','conflictNo','conflictInput'];

    static values = {
        committeeType: String,
        positions: Array, // 0: positions without qualification, 1: positions with qualification, 2: positions for supervisor (not used), 3: all positions translated
        noChoice: String,
        conflictHint: Array, // 0: description for yes, 1: description for no
        conflictHintName: String, // id of the hint div
        applicationProcess: String,
        reviewProcess: String, // current review process
        reviewProcessLoad: String, // review process on page load
        requestedConfirmHint: Array, // 0: review process full, 1: review process short
    }

    connect() {
        this.studentValue = 'student';
        this.phdValue = 'phd';
        this.positionOtherValue = 'positionOther';
        this.conflictYesTarget = document.getElementById(this.conflictNoTarget.id.replace('1','0')); // renderButtons allows only one target; therefore, get the other by using the id
        this.applicationProcessLoadValue = this.reviewProcessLoadValue.includes('full') ? 'full' : 'short';
        this.setApplicantSupervisor();
        this.setProjectStart(false);
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
        setElementVisibility(id+'Hint',[this.studentValue,this.phdValue].includes(value));
        setElementVisibility(id+'Other',value===this.positionOtherValue);
        if (!id.includes('supervisor')) {
            this.setApplicantSupervisor();
        }
    }

    // methods that are called from the template or from within this class

    /** Sets the visibility of the project start widgets.
     * @param checkModal if true, a modal may be displayed if the review process has changed
     * */
    setProjectStart(checkModal = true) {
        let isNext = this.projectStartNextTarget.checked;
        let isBegun = this.hasProjectStartBegunTarget && this.projectStartBegunTarget.checked;
        this.projectStartTarget.disabled = isNext;
        this.projectStartNextTarget.disabled = isBegun;
        if (this.hasProjectStartBegunTarget) {
            this.projectStartBegunTarget.disabled = isNext;
            setElementVisibility(this.projectStartBegunTextTarget,isBegun);
        }
        this.setReviewProcessWidgets(null,checkModal);
    }

    /** Sets the visibility of the supervisor div as well as the positions for the applicant and supervisor and the phone label for the applicant. */
    setApplicantSupervisor() {
        if (this.hasSupervisorDivTarget) {
            let isQualification = this.hasQualificationYesTarget && this.qualificationYesTarget.checked;
            let positionApplicant = this.applicantPositionTarget.value;
            let isSupervisor = positionApplicant===this.studentValue ||  this.committeeTypeValue==='EUB' && positionApplicant===this.phdValue;
            setElementVisibility(this.supervisorDivTarget,isSupervisor);
            this.setPositions(this.applicantPositionTarget,this.positionsValue[isQualification ? 1 : 0],positionApplicant);
            if (isSupervisor) {
                let positions = this.positionsValue[0];
                delete positions[this.studentValue];
                if (positionApplicant===this.phdValue) {
                    delete positions[this.phdValue];
                }
                this.setPositions(this.supervisorPositionTarget,positions,this.supervisorPositionTarget.value);
            }
            let isStudent = positionApplicant===this.studentValue; // position may have changed
            setElementVisibility('phoneLabel',!isStudent);
            setElementVisibility('phoneLabelOptional',isStudent);
        }
    }

    /** Sets the conflict widgets. */
    setConflict() {
        let isConflict = this.conflictYesTarget.checked;
        this.setConflictDescription();
        setHint(this.conflictHintNameValue,this.conflictHintValue[isConflict ? 0 : 1]);
        if (this.hasConflictInputTarget) {
            setElementVisibility(this.conflictInputTarget,!isConflict);
        }
    }

    /** Sets the visibility of widgets that depend on the review process. */
    setReviewProcessWidgets(event = null, checkModal = true) {
        let isShortDocs = false; // gets true if selection on shortDocs question invoked the method
        if (event!==null) {
            let target = event.target;
            let id = target.id;
            if (id.includes('applicationProcess')) {
                this.applicationProcessValue = target.value;
            } else if (id.includes('shortDocs')) { // selection on shortDocs question invoked the method
                isShortDocs = true;
            }
        }
        let isFull = this.applicationProcessValue==='full';
        let isBegun = this.hasProjectStartBegunTarget && this.projectStartBegunTarget.checked;
        let isRequested = this.fundingResearchTarget.checked && this.fundingResearchRequestedTarget.checked || this.fundingExternalTarget.checked && this.fundingExternalRequestedTarget.checked;
        let isBegunRequested = isBegun || isRequested;
        setElementVisibility(this.projectTitleParticipationTarget, !isBegunRequested && (isFull || !this.hasShortDocsYesTarget || this.shortDocsYesTarget.checked));
        this.setConflictDescription();
        let oldProcess = this.reviewProcessValue; // review process before a change has been made
        if (this.applicationProcessValue!=='') { // get review process after a change has been made
            this.reviewProcessValue = isRequested
                ? this.applicationProcessValue+'Requested'
                : (isBegun ? this.applicationProcessValue+'Begun' :
                    isFull
                        ? 'fullDocs'
                        : (this.hasShortDocsYesTarget
                            ? (this.shortDocsYesTarget.checked ? 'shortService' : 'shortNoDocs')
                            : 'shortDocs'));
        }
        // set visibility of short docs question
        if (this.hasShortDocsTarget) {
            setElementVisibility(this.shortDocsTarget,this.applicationProcessValue==='short' && !isBegunRequested);
        }
        // check if modal needs to be opened
        if (checkModal && this.applicationProcessValue!=='') {
            let modalID = ''; // id of modal to be opened
            let docsProcesses = ['fullDocs','shortDocs','shortService'];
            if (this.applicationProcessLoadValue==='full' && oldProcess.includes('full') && !isFull) { // any full to any short
                modalID = this.reviewProcessLoadValue==='fullDocs' && oldProcess==='fullDocs' ? 'fullShort' // fullDocs to shortDocs, fullDocs to shortNoDocs
                    : 'begunRequestedShort'; // fullBegun or fullRequested to any short
            } else if (docsProcesses.includes(this.reviewProcessLoadValue)) { // docs are created on page load
                if (docsProcesses.includes(oldProcess) && isBegunRequested) {
                    modalID = 'docs'+(isBegun ? 'Begun' : 'Requested'); // fullDocs to fullBegun, fullDocs to fullRequested, shortDocs to shortBegun, shortDocs to shortRequested, shortService to shortBegun, shortService to shortRequested
                } else if (oldProcess==='shortService' && this.reviewProcessValue==='shortNoDocs') {
                    modalID = 'shortShort'; // shortService to shortNoDocs
                }
            }
            if (modalID!=='' && (!isShortDocs || modalID==='shortShort')) {
                let modal = document.getElementById(modalID);
                if (modal!==null) { // if no information is given, modal may not exist
                    saveUndoModal(modal);
                }
            }
        }
        // set visibility of remove hint
        if (this.hasRequestedInputTarget) {
            setElementVisibility(this.requestedInputTarget,isRequested);
        }
        // set visibility of requested confirm and text of hint
        setElementVisibility(this.requestedConfirmTarget,isRequested);
        this.requestedConfirmHintTarget.textContent = this.requestedConfirmHintValue[isFull ? 0 : 1];
    }

    /** Sets the visibility of the conflict description div. */
    setConflictDescription() {
        setElementVisibility('conflictDescriptionDiv',this.conflictYesTarget.checked || this.conflictNoTarget.checked && (this.fundingResearchTarget.checked || this.fundingExternalTarget.checked) && this.applicationFullTarget.checked)
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