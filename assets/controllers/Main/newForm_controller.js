import { Controller } from '@hotwired/stimulus';
import {addExpandableListener, mergeInput, setElementVisibility} from "../multiFunction";

export default class extends Controller {

    static targets = ['fileName','committee','password','passwordDiv','confirmDiv','requirements','technicalHint','save']

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
        this.committeeTarget.addEventListener('change', async () => {
            let form = document.getElementsByTagName('form')[0];
            await fetch(form.action,{method: form.method, body: new FormData(form)}).then(async (response) => {
                if (this.committeeTarget.value!=='') { // update div with requirements and technical hints
                    let html = document.createElement('div');
                    html.innerHTML = await response.text();
                    let expandables = document.getElementsByClassName('clickable');
                    let numExpandables = expandables.length;
                    let classLists = new Array(expandables.length);
                    for (let element=0;element<numExpandables; ++element) {
                        classLists[element] = expandables[element].firstElementChild.classList; // class lists of span elements
                    }
                    this.confirmDivTarget.parentNode.replaceChild(html.querySelector('#confirmDiv'), this.confirmDivTarget);
                    for (let element=0;element<numExpandables; ++element) { // keep texts expanded/collapsed
                        let curElement = expandables[element];
                        let spanElement = curElement.firstElementChild;
                        spanElement.setAttribute('class','');
                        for (let curClass of classLists[element]) {
                            spanElement.classList.add(curClass);
                        }
                        setElementVisibility(curElement.nextElementSibling,spanElement.classList.contains('dropdownExpanded')); // text that gets expanded
                    }
                    addExpandableListener(document.getElementsByClassName('clickable'));
                }
            });
        });
        this.setButton();
    }

    // methods that are called from the template and from within this class

    /** Checks if a filename was entered, a committee was selected, and eventually a password was entered. */
    setButton() {
        let committee = this.committeeTarget.value;
        let isCommitteeSelected = committee!=='';
        let isBeta = this.committeeBetaValue.includes(committee)
        if (this.hasPasswordTarget) {
            let parent = this.passwordTarget.parentElement;
            setElementVisibility(parent.previousElementSibling,isBeta,1);
            setElementVisibility(this.passwordTarget,isBeta,1);
            setElementVisibility(parent.nextElementSibling,isBeta,1);
        }
        setElementVisibility(this.confirmDivTarget,isCommitteeSelected);
        this.saveTarget.disabled = this.fileNameTarget.value.trim()==='' || !isCommitteeSelected || isBeta && this.passwordTarget.value.trim()==='' || !this.requirementsTarget.checked || !this.technicalHintTarget.checked;
    }
}