import { Controller } from '@hotwired/stimulus';
import {mergeInput} from "../multiFunction";

export default class extends Controller {

    static targets = ['fileName','committee','save']

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

    /** Checks if a committee was selected and a filename was entered. */
    setButton() {
        this.saveTarget.disabled = this.fileNameTarget.value.trim()==='' || this.committeeTarget.value==='';
    }
}