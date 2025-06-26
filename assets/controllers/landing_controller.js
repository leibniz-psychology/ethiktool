import { Controller } from "@hotwired/stimulus";
import {mergeInput, setElementVisibility} from "./multiFunction";

export default class extends Controller {

    static targets = ['edit','name','dropdownNew','newDiv','copy','submitName','submitCopy']
    static values = {
        names: Array
    }

    connect() {
        if (this.hasDropdownNewTarget) {
            this.dropdownNewTarget.addEventListener('click', () => {
                let dropdown = this.dropdownNewTarget.firstElementChild;
                dropdown.classList.toggle('dropdownCollapsed');
                dropdown.classList.toggle('dropdownExpanded');
                this.newDivTarget.style.display = this.newDivTarget.style.display==='none' ? 'block' : 'none';
            })
        }
    }

    // methods that are called from the template

    /** Toggles the display attribute of a div containing a text field and a button.
     * @param event widget that invoked the method
     */
    editStudyGroup(event) {
        let element = this.editTargets[this.getID(event)];
        setElementVisibility(element,element.style.display==='none',2);
    }

    /** Sets the buttons for creating a new or copying a study, group, or measure point in time.
     * @param event widget that invoked the method
     */
    setButtons(event) {
        let id = this.getID(event);
        let numTargets  = this.nameTargets.length;
        let targetID = id<0 ? numTargets-1 : id;
        let name = this.hasNameTarget ? this.nameTargets[targetID].value.trim() : '';
        let nameEmpty = name==='';
        let nameExisting = this.namesValue.includes(name);
        // let disabled = nameEmpty || nameExisting;
        let disabled = this.hasNameTarget && !nameEmpty && nameExisting && name!==(this.namesValue[targetID] ?? '');
        if (id<0) { // copy dropdown was selected or text field for new study/group was edited
            this.submitCopyTarget.disabled = this.copyTarget.value==='' || disabled; // (de)activate button for copying
        }
        if (id>-2) { // text field was edited
            this.submitNameTargets[targetID].disabled = disabled;
        }
    }

    /** Checks if the length of an entered name is at most 50 characters.
     * @param event widget that invoked the method
     */
    checkName(event) {
        if (mergeInput(event).length>50) {
            event.preventDefault();
        }
    }

    // methods that are called from within this class

    /** Gets the ID from an element by returning the first number in the ID.
     * @param event widget that invoked the method
     * @return {*} id of the element, -1 if the element is the text field for the name of a new study/group, -2 if the element is the dropdown
     */
    getID(event) {
        let id = event.target.id;
        let match = id.match(/(\d+)/);
        return match!==null ? match[0] : (id===this.copyTarget.id ? -2 : -1);
    }
}