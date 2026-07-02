import { Controller } from "@hotwired/stimulus";
import {saveUndoModal, setElementVisibility, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['projectTitleParticipation','applicationFull','exDiv','exReDiv','exReHint','shortDocs','shortDocsYes','qualificationYes','institutionHint','professorshipHint','phoneLabelOptional','position','projectStart','projectStartNext','projectStartBegun','projectStartBegunDiv','projectStartBegunConfirm','projectStartBegunText','fundingResearch','fundingResearchRequested','fundingExternal','fundingExternalRequested','requestedInput','requestedConfirm','requestedConfirmHint','conflictNo','conflictInput'];

    static values = {
        appType: String,
        extended: String,
        exReHint: Array, // 0: hint if extended and same proposal, 1: hint if extended and other proposal or resubmission
        positions: Array, // 0: positions without qualification, 1: positions with qualification, 3: all positions translated
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
        this.positionOtherValue = 'positionOther';
        this.conflictYesTarget = document.getElementById(this.conflictNoTarget.id.replace('1','0')); // renderButtons allows only one target; therefore, get the other by using the id
        this.applicationProcessLoadValue = this.reviewProcessLoadValue.includes('full') ? 'full' : 'short';
        this.setApplicationType();
        this.setApplicant();
        this.setProjectStart(false);
        this.setConflict();
    }

    // methods that are called from the template

    /** Sets this.appTypeValue.
     * @param event widget that invoked the method
     */
    setAppType(event) {
        this.appTypeValue = event.target.value;
        this.setApplicationType();
    }

    /** Sets this.extendedValue.
     * @param event widget that invoked the method
     */
    setExtended(event) {
        this.extendedValue = event.target.value;
        this.setApplicationType();
    }

    /** Sets the visibility of the text field for the 'other' institution or position and eventually the hint for the professorship and for the institution.
     * @param event widget that invoked the method
     */
    setInstitutionPosition(event) {
        let target = event.target;
        let id = target.id;
        let value = target.value;
        let other = id+'Other';
        let isOther = value===other;
        setElementVisibility(other,isOther);
        if (id==='position') {
            setElementVisibility(this.professorshipHintTarget,[this.studentValue,'phd'].includes(value));
            this.setApplicant();
        } else if (id==='institution') {
            setElementVisibility(this.institutionHintTarget,isOther);
        }
    }

    // methods that are called from the template or from within this class

    /** Sets the visibility of the application type elements. */
    setApplicationType() {
        let isExtended = this.appTypeValue==='extended';
        setElementVisibility(this.exDivTarget,isExtended);
        setElementVisibility(this.exReDivTarget,['extended','resubmission','resubmissionGranted'].includes(this.appTypeValue));
        setHint(this.exReHintTarget,this.exReHintValue[isExtended && ['','extendedDifferent'].includes(this.extendedValue) ? 1 : 0]);
    }

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
            setElementVisibility(this.projectStartBegunDivTarget,isBegun);
            setElementVisibility(this.projectStartBegunTextTarget,isBegun && (!this.hasProjectStartBegunConfirmTarget || this.projectStartBegunConfirmTarget.checked));
        }
        this.setReviewProcessWidgets(null,checkModal);
    }

    /** Sets the positions and the phone label for the applicant. */
    setApplicant() {
        if (this.hasPhoneLabelOptionalTarget) {
            let positionApplicant = this.positionTarget.value;
            // remove all positions and recreate them
            while (this.positionTarget.hasChildNodes()) {
                this.positionTarget.firstChild.remove();
            }
            let positions = Object.keys(this.positionsValue[this.hasQualificationYesTarget && this.qualificationYesTarget.checked ? 1 : 0]);
            let positionsTranslated = this.positionsValue[2];
            for (let choice of [''].concat(positions)) {
                let newChoice = document.createElement('option');
                this.positionTarget.append(newChoice);
                newChoice.value = choice;
                newChoice.textContent = choice!=='' ? positionsTranslated[choice] : this.noChoiceValue;
            }
            if (positions.includes(positionApplicant)) { // keep selection if still allowed
                this.positionTarget.value = positionApplicant;
            }
            if (!positions.includes(this.positionOtherValue)) { // if position of applicant was 'other' and then qualification was answered with yes, hide the text field
                setElementVisibility(this.positionOtherValue,false);
            }
            let isStudent = positionApplicant===this.studentValue; // position may have changed
            setElementVisibility('phoneLabel',!isStudent);
            setElementVisibility(this.phoneLabelOptionalTarget,isStudent);
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
}