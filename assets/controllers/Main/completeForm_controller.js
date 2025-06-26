import { Controller } from '@hotwired/stimulus';
import { setElementVisibility } from "../multiFunction";

export default class extends Controller {

    static targets = ['consent','noBias','participate','participateHint','access','consentFurther','finish','finishText','pdf'];

    connect() {
        this.setBias();
        if (this.hasPdfTarget) {
            for (let widget of this.pdfTargets) {
                widget.addEventListener('change',() => {
                    this.setSubmitButton();
                });
            }
        }
    }

    // methods that are called from the template and from within this class

    /** Sets the bias widgets. */
    setBias() {
        let isNoBias = this.noBiasTarget.checked;
        let anyBias = false;
        let biasDescription = true;
        for (let bias of [this.participateTarget,this.accessTarget]) {
            let isChecked = bias.checked;
            anyBias = anyBias || isChecked;
            let description = document.getElementById(bias.id+'DescriptionDiv');
            setElementVisibility(description,isChecked);
            if (isChecked) {
                biasDescription = biasDescription && description.value!=='';
            }
            bias.disabled = isNoBias;
        }
        this.participateHintTarget.style.color = isNoBias ? 'darkgray' : 'black';
        this.noBiasTarget.disabled = anyBias;
        this.biasInput = isNoBias || anyBias && biasDescription;
        this.setSubmitButton();
    }

    /** Checks if all PDFs are added and sets the button for creating the complete proposal. */
    setSubmitButton() {
        let allAdded = true;
        for (let target of this.pdfTargets) {
            let isValue = target.value!=='';
            allAdded = allAdded && isValue;
            if (!isValue) {
                target.parentElement.nextElementSibling.textContent = ''; // div showing the filename
            }
        }
        let isFinish = this.consentTarget.checked && this.biasInput && allAdded && this.consentFurtherTarget.checked;
        this.finishTarget.disabled = !isFinish;
        setElementVisibility(this.finishTextTarget,isFinish);
    }
}