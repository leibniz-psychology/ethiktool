import { Controller } from "@hotwired/stimulus";
import { checkTextareaInput, getSelected, setElementVisibility, setHint } from "../multiFunction";

export default class extends Controller {

    static targets = ['measuresSurvey','measuresBurdensRisks','measuresDescriptionDiv','measuresDescription','noIntervention','interventionsSurvey','interventionsBurdensRisks','interventionsDescriptionDiv','interventionsDescription','interventionsPDF','loanYes','loanInputHint','onlineHint','locationInputHint','locationDescription','locationEnd','measureTime','breaks'];

    static values = {
        measuresTypes: Array,
        measuresDescription: Array, // 0: nothing selected, 1: at least one measure selected, 2: at least one measure selected including survey
        interventionsTypes: Array,
        interventionsDescription: String,
        interventionsSurvey: Array, // 0: survey sentence, 1: no intervention in a strict sense
        location: String,
        locationHint: Array, // 0: please choose, 1: hint if answer is chosen
        locationInput: Object, // 0: insurance way, 1: apparatus and insurance way
        duration: Object, // keys: 'total', 'measureTime', 'breaks'. Values: arrays: 0: singular, 1: plural
    }

    connect() {
        this.interventionsTypesWoNo = this.interventionsTypesValue.slice(1);
        this.setMeasuresInterventions();
        this.setInputHints(); // needs to be called on connect() in case the page is reloaded by the user
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
            }
            else if (target===this.interventionsSurveyTarget) {
                isMeasuresSurvey = isInterventionsSurvey;
            }
            this.measuresSurveyTarget.checked = isMeasuresSurvey;
            this.interventionsSurveyTarget.checked = isInterventionsSurvey;
        }
        let [anyMeasure] = getSelected(this.measuresTypesValue);
        let [anyIntervention,numInterventions] = getSelected(this.interventionsTypesWoNo);
        // measures
        setElementVisibility(this.measuresBurdensRisksTarget,anyMeasure); // hint for burdens/risks
        setHint(this.measuresDescriptionDivTarget.firstElementChild.firstElementChild,this.measuresDescriptionValue[!anyMeasure ? 0 : (this.measuresSurveyTarget.checked ? 2 : 1)]); // hint above text field
        this.measuresDescriptionTarget.disabled = !anyMeasure;
        // interventions
        let isOnlySurvey = numInterventions===1 && isInterventionsSurvey;
        let isNotOnlySurvey = anyIntervention && !isOnlySurvey;
        setElementVisibility(this.interventionsBurdensRisksTarget,anyIntervention); // hint for burdens/risks
        setElementVisibility(this.interventionsDescriptionDivTarget,anyIntervention); // div containing text field and hint above text field
        let surveyStart = this.interventionsSurveyValue[0];
        setHint(this.interventionsDescriptionDivTarget.firstElementChild.firstElementChild,isOnlySurvey ? surveyStart+this.interventionsSurveyValue[1] : this.interventionsDescriptionValue); // hint above text field. Only visible if at least one intervention unlike 'no intervention' is selected
        setElementVisibility(this.interventionsDescriptionTarget,isNotOnlySurvey); // text field
        setElementVisibility(this.interventionsPDFTarget,anyIntervention);
        if (isMeasuresSurveyTarget) { // deselect and disable the 'no interventions' checkbox in case it was checked before and then measures survey was selected
            this.noInterventionTarget.checked = false;
            for (let checkbox of this.interventionsTypesWoNo) { // enable all checkboxes in case they were disabled
                document.getElementById(checkbox).disabled = false;
            }
            this.noInterventionTarget.disabled = anyIntervention;
        }
        // text of text field for interventions
        if (isNotOnlySurvey) {
            surveyStart = surveyStart+'.';
            let text = this.interventionsDescriptionTarget.value;
            let startsWithDefault = text.startsWith(surveyStart);
            if (isInterventionsSurvey && !startsWithDefault) {
                text = surveyStart+' '+text;
                this.abortCont = new AbortController(); // new instance for abortCont.signal.aborted set to false
                this.interventionsDescriptionTarget.addEventListener('keydown',(event) => {
                    event.params = {'start': this.interventionsSurveyValue[0]+'.', 'furtherAllowed': ['Space','Enter']};
                    checkTextareaInput(event);
                },{signal: this.abortCont.signal});
                this.interventionsDescriptionTarget.params = {'start': surveyStart};
            }
            else if (!isInterventionsSurvey && startsWithDefault) {
                text = text.replace(surveyStart,'').trim();
                this.abortCont.abort(); // remove listener
            }
            this.interventionsDescriptionTarget.value = text;
        }
    }

    /** Sets the hint for deleting inputs for loan and location. */
    setInputHints() {
        let isOnlineNothing = ['','online'].includes(this.locationValue);
        let isNotLoan = !this.loanYesTarget.checked;
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
        for (let [time,value] of Object.entries({'total': measureTime+breaks, 'measureTime': measureTime, 'breaks': breaks})) {
            let isSingular = value===1;
            document.getElementById(time).textContent = this.durationValue[time][isSingular ? 0 : 1].replace(isSingular ? '1' : '0',value===0 ? 'X' : value); // only 'total' will replace
        }
    }
}