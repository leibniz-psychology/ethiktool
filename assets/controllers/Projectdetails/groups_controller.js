import { Controller } from "@hotwired/stimulus";
import { setElementVisibility, getSelected, setHint } from "../multiFunction";

export default class extends Controller {

    static targets = ['minAge','maxAge','unlimited','healthy','wards','examinedDescription','wardsHint','removeHint','physical','mental','dependent','closedNo','voluntaryHint','criteriaHint','includeStart','include'];

    static values = {
        wardsHint: String, // inputs are about to be deleted
        examined: Array,
        examinedDescription: Array, // 0: nothing selected, 1: description optional, 2: description obligatory
        criteriaHint: Array, // 0: participants, 1: children, 2: wards
        includeStart: Array, // 0: participants, 1: children, 2: wards
        firstInclude: Array // 0: min age equals 1, 1: min age unequal to 1. In each array: 0: max age equals 1, 1: max age unequal 1. In each array: 0: participants, 1: children, 2: wards. In each array: 0: no upper limit, 1: same limit, 2: different limits
    }

    connect() {
        this.setExamined();
        if (this.hasVoluntaryHintTarget) {
            this.setVoluntaryHint();
            this.dependentTarget.addEventListener('change', () => {
                this.setVoluntaryHint();
            });
        }
        this.setCriteria();
    }

    // methods that are called from the template and from within this class

    /** Sets the age and examined widgets including the text field. */
    setExamined() {
        let minAge = this.minAgeTarget.value;
        let isMinAge = minAge!=='';
        let maxAge = this.maxAgeTarget.value;
        let isMaxAge = maxAge!=='';
        let isMax18 = isMaxAge && maxAge>17;
        let isUnlimited = this.unlimitedTarget.checked;
        let isHealthy = this.healthyTarget.checked;
        let isWards = this.wardsTarget.checked;
        let isUnder16 = isMinAge && minAge<16;
        // set age
        if (isHealthy && isWards) { // healthy and wards can both be checked only if both ages are below 18
            if (isMinAge && minAge>17) {
                minAge = 17;
            }
            if (isMax18) {
                maxAge = 17;
            }
        }
        if (isUnder16) {
            this.unlimitedTarget.checked = false;
            isUnlimited = false;
            maxAge = isMax18 ? 17 : maxAge;
        }
        if (isUnlimited) {
            maxAge = '';
            isMaxAge = false;
        }
        if (isMinAge && isMaxAge && parseInt(minAge)>parseInt(maxAge)) {
            maxAge = minAge;
        }
        this.minAgeTarget.value = minAge;
        this.maxAgeTarget.value = maxAge;
        isWards = isWards || isUnder16;
        this.wardsTarget.checked = isWards;
        // enable/disable widgets
        this.maxAgeTarget.disabled = isUnlimited;
        this.unlimitedTarget.disabled = isUnder16 || isHealthy && isWards;
        let healthyWards = !isUnder16 && (isMaxAge && maxAge>17 || isUnlimited);
        this.healthyTarget.disabled = isWards && healthyWards;
        this.wardsTarget.disabled = isUnder16 || isHealthy && healthyWards;
        // description text field
        let [anySelected, numSelected] = getSelected(this.examinedValue);
        this.setTextfield(this.examinedDescriptionTarget,!anySelected,this.examinedDescriptionValue[!anySelected ? 0 : (isHealthy && numSelected===1 ? 1 : 2)])
        setElementVisibility(this.wardsHintTarget,isWards); // hint between age and examined
        // wards icon
        if (this.hasRemoveHintTarget) {
            setElementVisibility(this.removeHintTarget,!isWards);
        }
        // criteria
        let addressee = 0; // participants
        if (isWards) {
            if (isUnder16 || isMaxAge && maxAge<18 && !isUnlimited) {
                addressee = 1; // children
            }
            else {
                addressee = 2; // wards
            }
        }
        setHint(this.criteriaHintTarget.firstElementChild.nextElementSibling,this.criteriaHintValue[addressee]); // hint below heading
        this.includeStartTarget.textContent = this.includeStartValue[addressee]; // start of inclusion
        // first inclusion criterion
        let include = this.includeTarget.value.split("\n");
        let minAgeSingular = minAge==='1';
        let maxAgeSingular = maxAge==='1';
        include[0] = this.firstIncludeValue[minAgeSingular ? 0 : 1][maxAgeSingular ? 0 : 1][addressee][isUnlimited ? 0 : (minAge===maxAge || !isMinAge || !isMaxAge ? 1 : 2)].replace(maxAgeSingular ? '1' : '101',isMaxAge ? maxAge : 'X').replace(minAgeSingular ? '1' : '0' ,isMinAge ? minAge : (isMaxAge && !isUnlimited ? maxAge : 'X'));
        this.includeTarget.value = include.join("\n");
    }

    /** Sets the hints that voluntary inputs on the consent page are about to be deleted. */
    setVoluntaryHint() {
        let isVisible = !this.dependentTarget.checked && this.closedNoTarget.checked;
        for (let hint of this.voluntaryHintTargets) {
            setElementVisibility(hint,isVisible);
        }
    }

    /** Enables/Disables the criteria widgets. */
    setCriteria() {
        for (let criterion of ['include','exclude']) {
            let text = document.getElementById(criterion+'Text');
            let input = text.value.trim();
            let checkbox = document.getElementById(criterion);
            text.disabled = checkbox.checked;
            checkbox.disabled = criterion==='include' ? input.split("\n").length>1 : input!=='';
        }
    }

    // methods that are called from within this class

    /** Enables/disables a text field and sets the hint above the text field. The hint must be the previous sibling of the parent element of the text field.
     * @param textfield text field to be set
     * @param disabled true if the text field should be disabled, false otherwise
     * @param text text of the hint
     */
    setTextfield(textfield,disabled,text) {
        textfield.disabled = disabled;
        setHint(textfield.parentElement.previousElementSibling,text);
    }
}