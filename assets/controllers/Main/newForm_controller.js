import { Controller } from '@hotwired/stimulus';
import {mergeInput, setElementVisibility} from "../multiFunction";

export default class extends Controller {

    static targets = ['fileName','committee','password','passwordDiv','save']

    static values = {
        committeeBeta: Array // beta committees
    }

    connect() {
        this.fileNameTarget.addEventListener('beforeinput', function (event) {
            let regEx = /^[a-zA-Z0-9][a-zA-Z0-9\s.\-_]*$/; // starting with a letter or a number, then any number of letters, digits, '.-_' or whitespace
            let mergedInput = mergeInput(event);
            if (event.inputType.includes('insert') && (!regEx.test(mergedInput) || mergedInput.length>50)) {
                event.preventDefault();
            }
        });
        this.setButton();
    }

    // methods that are called from the template and from within this class

    /** Checks if a filename was entered, a committee was selected, and eventually a password was entered. */
    setButton() {
        let committee = this.committeeTarget.value;
        let isBeta = this.committeeBetaValue.includes(committee)
        if (this.hasPasswordTarget) {
            let parent = this.passwordTarget.parentElement;
            setElementVisibility(parent.previousElementSibling,isBeta,1);
            setElementVisibility(this.passwordTarget,isBeta,1);
            setElementVisibility(parent.nextElementSibling,isBeta,1);
        }
        this.saveTarget.disabled = this.fileNameTarget.value.trim()==='' || this.committeeTarget.value==='' || isBeta && this.passwordTarget.value.trim()==='';
    }
}