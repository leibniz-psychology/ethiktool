import { Controller } from "@hotwired/stimulus";
import {getSelected, setElementVisibility, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['measuresSurvey','measuresBurdensRisks','measuresDescriptionDiv','measuresDescription','noIntervention','interventionsSurvey','interventionsBurdensRisks','interventionsDescriptionDiv','interventionsPDF','loanYes','loanInputHint','onlineHint','locationInputHint','locationDescription','locationEnd','measureTime','breaks','compensationHint'];

    static values = {
        measuresTypes: Array,
        measuresDescription: Array, // 0: nothing selected, 1: at least one measure selected, 2: at least one measure selected including survey
        interventionsTypes: Array,
        location: String,
        locationHint: Array, // 0: please choose, 1: hint if answer is chosen
        locationInput: Object, // 0: insurance way, 1: apparatus and insurance way
        duration: Object, // keys: 'total', 'measureTime', 'breaks'. Values: arrays: 0: singular, 1: plural
    }

    connect() {
        this.interventionsTypesWoNo = this.interventionsTypesValue.slice(1);
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
        setHint(descriptionDiv.firstElementChild,this.locationHintValue[isLocation ? 1 : 0]); // hint above text field
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
        let [anyIntervention,numInterventions] = getSelected(this.interventionsTypesWoNo);
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
            for (let checkbox of this.interventionsTypesWoNo) { // enable all checkboxes in case they were disabled
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
            setElementVisibility(this.onlineHintTarget,!isOnlineNothing); // this.locationValue can not be empty at this point
        }
    }

    /** Sets the duration widgets. */
    setDuration() {
        let measureTime = parseInt(this.measureTimeTarget.value);
        let breaks = parseInt(this.breaksTarget.value);
        // input field checks if value is in after focus is lost, i.e., after calling this method, therefore check values here
        measureTime = isNaN(measureTime) ? 0 : (measureTime<1 ? 1 : (measureTime>999 ? 999 : measureTime));
        breaks = isNaN(breaks) ? 0 : (breaks<0 ? 0 : (breaks>999 ? 999 : breaks));
        let totalTime = measureTime+breaks;
        for (let [time,value] of Object.entries({'total': totalTime, 'measureTime': measureTime, 'breaks': breaks})) {
            let isSingular = value===1;
            document.getElementById(time).textContent = this.durationValue[time][isSingular ? 0 : 1].replace(isSingular ? '1' : '0',value===0 ? 'X' : value); // only 'total' will replace
        }
        if (this.hasCompensationHintTarget) {
            setElementVisibility(this.compensationHintTarget,totalTime<=30);
        }
    }
}