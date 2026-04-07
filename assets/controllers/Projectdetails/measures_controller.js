import { Controller } from "@hotwired/stimulus";
import {getSelected, setElementVisibility, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['measuresSurvey','measuresBurdensRisks','measuresDescriptionDiv','measuresDescription','noIntervention','interventionsSurvey','interventionsBurdensRisks','interventionsDescriptionDiv','interventionsPDF','loanYes','loanInputHint','onlineHint','locationInputHint','locationDescription','locationEnd','duration','measureTimeDays','measureTimeHours','measureTimeMinutes','breaksMinutes','compensationHint'];

    static values = {
        measuresTypes: Array,
        measuresDescription: Array, // 0: nothing selected, 1: at least one measure selected, 2: at least one measure selected including survey
        interventionsTypes: Array, // without 'no intervention
        location: String,
        locationInput: Object, // 0: insurance way, 1: apparatus and insurance way
    }

    connect() {
        this.setMeasuresInterventions();
        if (this.hasLocationDescriptionTarget || this.hasLoanInputTarget) {
            this.setInputHints(); // needs to be called on connect() in case the page is reloaded by the user
        }
        this.setDuration();
    }

    // methods that are called from the template

    /** Sets the location widgets.
     * @param event widget that invoked the method
     */
    setLocation(event) {
        this.locationValue = event.target.value;
        let isLocation = this.locationValue!=='';
        let descriptionDiv = document.getElementById('locationDescriptionDiv');
        setElementVisibility(descriptionDiv,this.locationValue!=='');
        setHint(descriptionDiv.firstElementChild,event.params.hints[isLocation ? 1 : 0]); // hint above text field
        this.locationDescriptionTarget.disabled = !isLocation;
        setElementVisibility(this.locationEndTarget,this.locationValue==='online'); // end of sentence below text field if online is chosen
        this.setInputHints();
    }

    // methods that are called from the template or from within this class

    /** Sets the measures and interventions widgets.
     * @params event widget that invoked the method
     */
    setMeasuresInterventions(event) {
        let isMeasuresSurvey = this.measuresSurveyTarget.checked;
        let isInterventionsSurvey = this.interventionsSurveyTarget.checked;
        let isMeasuresSurveyTarget = false; // gets true if the selection of measures survey was changed
        if (event!==undefined) { // a checkbox was clicked
            let target = event.target;
            isMeasuresSurveyTarget = target===this.measuresSurveyTarget;
            // either both or none of the survey options must be checked
            if (isMeasuresSurveyTarget) {
                isInterventionsSurvey = isMeasuresSurvey;
                if (isInterventionsSurvey) {
                    this.noInterventionTarget.checked = false; // uncheck in case it was selected before
                }
            } else if (target===this.interventionsSurveyTarget) {
                isMeasuresSurvey = isInterventionsSurvey;
            }
            this.measuresSurveyTarget.checked = isMeasuresSurvey;
            this.interventionsSurveyTarget.checked = isInterventionsSurvey;
        }
        let [anyMeasure] = getSelected(this.measuresTypesValue);
        let [anyIntervention,numInterventions] = getSelected(this.interventionsTypesValue);
        // measures
        setElementVisibility(this.measuresBurdensRisksTarget,anyMeasure); // hint for burdens/risks
        setElementVisibility('measuresSurveyText',isMeasuresSurvey); // text field for description of survey
        if (this.hasMeasuresDescriptionTarget) {
            setHint(this.measuresDescriptionDivTarget.firstElementChild.firstElementChild,this.measuresDescriptionValue[!anyMeasure ? 0 : (this.measuresSurveyTarget.checked ? 2 : 1)]); // hint above text field
            this.measuresDescriptionTarget.disabled = !anyMeasure;
        }
        // interventions
        setElementVisibility(this.interventionsBurdensRisksTarget,anyIntervention); // hint for burdens/risks
        if (this.hasInterventionsDescriptionDivTarget) {
            setElementVisibility(this.interventionsDescriptionDivTarget,anyIntervention && (numInterventions>1 || !isInterventionsSurvey)); // div containing text field and hint above text field
            setElementVisibility(this.interventionsPDFTarget,anyIntervention);
        }
        if (isMeasuresSurveyTarget) { // deselect and disable the 'no interventions' checkbox in case it was checked before and then measures survey was selected
            this.noInterventionTarget.checked = false;
            for (let checkbox of this.interventionsTypesValue) { // enable all checkboxes in case they were disabled
                document.getElementById(checkbox).disabled = false;
            }
            this.noInterventionTarget.disabled = anyIntervention;
        }
    }

    /** Sets the hint for deleting inputs for loan and location. */
    setInputHints() {
        let isOnlineNothing = ['','online'].includes(this.locationValue);
        let isNotLoan = this.hasLoanYesTarget && !this.loanYesTarget.checked;
        if (this.hasLoanInputHintTarget) {
            setElementVisibility(this.loanInputHintTarget,isOnlineNothing && isNotLoan);
        }
        if (this.hasLocationInputHintTarget) {
            setHint(this.locationInputHintTarget,this.locationInputValue[isNotLoan ? 'both' : 'insuranceWay']);
            setElementVisibility(this.locationInputHintTarget,isOnlineNothing);
        }
        if (this.hasOnlineHintTarget) { // hint that ip-question in data privacy will be deleted
            setElementVisibility(this.onlineHintTarget,!isOnlineNothing);
        }
    }

    /** Sets the visibility of the hint for deleting inputs for durations. */
    setDuration() {
        let days = this.measureTimeDaysTarget.value;
        days = days!=='' ? parseInt(days) : 0;
        days = days<0 ? 0 : days;
        let isNotDays = days===0;
        for (let target of this.durationTargets) {
            setElementVisibility(target,isNotDays,1);
        }
        setElementVisibility('measureTimeDaysTextDiv',!isNotDays,1);
        let minutes = [0,0];
        if (isNotDays) {
            for (let [index,targetValue] of [this.measureTimeMinutesTarget.value,this.breaksMinutesTarget.value].entries()) {
                if (targetValue!=='') {
                    targetValue = parseInt(targetValue);
                    minutes[index] = targetValue<0 ? 0 : targetValue; // if a value smaller than 0 is entered, it is updated after this check
                }
            }
        }
        if (this.hasCompensationHintTarget) {
            setElementVisibility(this.compensationHintTarget,isNotDays && this.measureTimeDaysTarget.value<=0 && this.measureTimeHoursTarget.value<=0 && (minutes[0]+minutes[1])<=30);
        }
    }
}