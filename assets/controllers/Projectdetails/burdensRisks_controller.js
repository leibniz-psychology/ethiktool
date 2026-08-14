import { Controller } from "@hotwired/stimulus";
import {getSelected, setElementVisibility, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['burdensEverydayYes','textInput','descriptionDiv','alwaysTemplateText','consentTemplateText','informingDescription','informingTemplate','informingTemplateDiv','informingPDF','informingHint','findingHint'];

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

    /** Sets the visibility of the hint that inputs get removed.
     * @param event widget that invoked the method
     * */
    setTextInputHint(event) {
        let params = event.params;
        if (this.hasTextInputTarget) {
            let visibility = (!getSelected(params['burdens'])[0] || !this.burdensEverydayYesTarget.checked) && !getSelected(params['risks'])[0];
            for (let target of this.textInputTargets) {
                setElementVisibility(target,visibility);
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
                this.informingDescriptionTarget.classList.remove('markInput');
                this.descriptionDivTarget.classList.remove('pe-0');
            } else {
                this.descriptionDivTarget.classList.add('pe-0');
                this.informingDescriptionTarget.classList.add('markInput');
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
