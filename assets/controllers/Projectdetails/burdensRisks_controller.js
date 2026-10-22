import { Controller } from "@hotwired/stimulus";
import {getSelected, setElementVisibility, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['burdensEverydayYes','textInput','descriptionDiv','alwaysTemplateText','consentTemplateText','informingDescription','informingTemplate','informingTemplateDiv','informingPDF','informingHint','findingHint','risksIncidental','risksSuspicion','risksOccurrence'];

    static values = {
        informing: String,
        informingHints: Object
    }

    connect() {
        this.setInforming();
    }

    // methods that are called from the template

    /** Sets this.informingValue.
     * @param event widget that invoked the method
     */
    setInformingValue(event) {
        this.informingValue = event.target.value;
        this.setInforming();
    }

    /** Sets the visibility of risks description and risks occurrence as well as the hint that inputs get removed.
     * @param event widget that invoked the method
     * */
    setRisksTextInputHint(event) {
        let params = event.params;
        let type = params.type;
        let isRisks = type==='risks';
        if (type==='burdens' || isRisks) {
            let risksSelected = getSelected(params['risks']);
            let isAnyRisk = risksSelected[0];
            // risks
            if (isRisks) {
                let isNotOnlyFinding = isAnyRisk && !(risksSelected[1]===(2-(this.risksIncidentalTarget.checked ? 0 : 1)-(this.risksSuspicionTarget.checked ? 0 : 1)));
                setElementVisibility('risksDescriptionDiv',isNotOnlyFinding);
                setElementVisibility(this.risksOccurrenceTarget,isNotOnlyFinding);
            }
            // remove hint
            if (this.hasTextInputTarget) {
                let visibility = (!getSelected(params['burdens'])[0] || !this.burdensEverydayYesTarget.checked) && !isAnyRisk;
                for (let target of this.textInputTargets) {
                    setElementVisibility(target,visibility);
                }
            }
        }



    }

    // methods that are called from the template and from within this class

    /** Sets the informing widgets as well as the hint of the text field for informing. */
    setInforming() {
        let isSelected = this.informingValue!=='';
        setElementVisibility(this.descriptionDivTarget,isSelected,1);
        let isAlways = this.informingValue==='always';
        let isNo = this.informingValue==='informingNo';
        let isTemplate = this.informingTemplateTarget.checked;
        setElementVisibility(this.alwaysTemplateTextTarget,isAlways && isTemplate); // template for always
        setElementVisibility(this.consentTemplateTextTarget,this.informingValue==='consent' && isTemplate); // template for consent
        if (isSelected) {
            let hintValue = isNo ? 'informingNo' : (isTemplate ? 'cloze' : 'noTemplate');
            this.informingHintTarget.textContent = this.informingHintsValue[hintValue]; // hint above text field
            if (isNo) {
                if (this.hasInformingPDFTarget) {
                    this.informingDescriptionTarget.classList.remove('markInput');
                }
                this.descriptionDivTarget.classList.remove('pe-0');
            } else {
                this.descriptionDivTarget.classList.add('pe-0');
                if (this.hasInformingPDFTarget) {
                    this.informingDescriptionTarget.classList.add('markInput');
                }
            }
            setElementVisibility(this.informingTemplateDivTarget,!isNo); // checkbox
            if (this.hasInformingPDFTarget) { // pdf symbol
                setElementVisibility(this.informingPDFTarget,!isNo);
            }
            if (this.hasFindingHintTarget) { // hint for inclusion criterion
                setElementVisibility(this.findingHintTarget,isAlways);
            }
        }
    }
}
