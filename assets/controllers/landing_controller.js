import { Controller } from "@hotwired/stimulus";
import {addInputListener, mergeInput, setElementVisibility} from "./multiFunction";
import getComputedStyle from "@popperjs/core/lib/dom-utils/getComputedStyle";

export default class extends Controller {

    static targets = ['edit','name','copy','submitName','submitCopy','landingRemove']

    static values = {
        names: Object, // translated names of 'study', 'group' and 'measure time point'
        copyNames: Object, // translated headings if element should be copied
        remove: Object // translated headings and texts for the remove modal
    }

    connect() {
        // add expandable listener
        for (let expandable of document.getElementsByClassName('landingDropdown')) {
            expandable.addEventListener('click', () => {
                expandable.classList.toggle('dropdownCollapsed');
                expandable.classList.toggle('dropdownExpanded');
                this.setExpandable(expandable.nextElementSibling.nextElementSibling.nextElementSibling,expandable.classList.contains('dropdownExpanded'),expandable.id.replace('dropdown','new')+'_Outer');
            });
        }
        for (let input of document.getElementsByTagName('input')) {
            addInputListener(input);
        }
    }

    // methods that are called from the template

    /** Sets the visibility of the text field and buttons for creating a new element if another one ic copied.
     * @param event widget that invoked the method
     */
    setCopy(event) {
        let id = event.currentTarget.id;
        id = id.replace('copy','new');
        let idSplit = id.split('_');
        let idSplitLength = idSplit.length;
        let isStudy = idSplitLength===3;
        let copyID = parseInt(idSplit[idSplitLength-2])+1; // id of element -> set here because length of idSplit changes
        let underscore = isStudy ? '_' : '';
        idSplit.splice(idSplit.length-2,1); // remove last index
        id = idSplit.join('_');
        document.getElementById(id.replace('button',underscore+'name')).textContent = this.copyNamesValue[isStudy ? 'study' : (idSplitLength===4 ? 'group' : 'measureTimePoint')].replace('X',copyID); // hint above text field
        let buttonDiv = document.getElementById(id.replace('button',underscore+'Div')); // div with text field and two buttons
        let outer = document.getElementById(id.replace('button',underscore+'Outer'));
        outer.style.marginTop = '0.8rem !important';
        if (buttonDiv.style.display==='none') { // if copy button is clicked again or after 'new' was clicked, visibility is already as it should be and the grid span should not be increased again
            setElementVisibility(id.replace('button',underscore+'button'),false); // 'new' button
            setElementVisibility(buttonDiv,true,2 );
            this.changeGridColumn(outer); // div surrounding hint and div with text field
        }
        document.getElementById(id.replace('_button',underscore)).focus(); // focus text field
    }

    /** Sets the visibility of the text field and buttons for creating or editing a new element.
     * @param event widget that invoked the method.
     */
    setNewEdit(event) {
        let target = event.currentTarget;
        let id = target.id;
        setElementVisibility(target,false); // button
        setElementVisibility(id.replace('button','Div'),true,2); // div with text field and two buttons
        let textField = document.getElementById(id.replace('_button','')); // text field
        if (id.startsWith('new')) {
            let idSplit = id.split('_');
            let idSplitLength = idSplit.length;
            let isStudy = idSplit[1]==='';
            let idWoLast = idSplit.slice(0,idSplitLength-1).join('_').replace('new','dropdownDiv').replace('button','')+(!isStudy ? '_' : ''); // remove last index
            let index = 0;
            let hasElement = true;
            while (hasElement) {
                hasElement = document.getElementById(idWoLast+index)!==null;
                ++index;
            }
            document.getElementById(id.replace('button','name')).textContent = 'Name '+this.namesValue[isStudy ? 'study' : (idSplitLength===3 ? 'group' : 'measureTimePoint')]+index; // hint above text field
            this.changeGridColumn(document.getElementById(id.replace('button','Outer'))); // div surrounding hint and div with
        } else {
            let title = document.getElementById(id.replace('button','Text')); // name of the element
            setElementVisibility(title,false);
            title = title.textContent;
            textField.value = title.includes('(') ? title.substring(title.indexOf('(')+1,title.lastIndexOf(')')) : '';
        }
        textField.focus(); // focus text field
    }

    /** Sets the visibility of the involved widgets if the 'cancel' button of an element to be created or edited was clicked.
     * @param event widget that invoked the method
     */
    cancelNewEdit(event) {
        let id = event.currentTarget.id;
        let isNew = id.startsWith('new');
        setElementVisibility(id.replace('cancel','Div'),false); // div with text field and two buttons
        setElementVisibility(id.replace('cancel','button'),true,isNew ? 2 : 0); // button
        document.getElementById(id.replace('_cancel','')).value = ''; // remove text that was eventually entered
        if (isNew) {
            this.changeGridColumn(document.getElementById(id.replace('cancel','Outer')),false);
        } else {
            setElementVisibility(id.replace('cancel','Text'),true); // name of the element
        }
    }

    /** Sets the modal heading and body a remove button is clicked.
     * @param event widget that invoked the method
     */
    setLandingRemove(event) {
        let idSplit = event.currentTarget.id.split('_');
        let idSplitLength = idSplit.length;
        let type = idSplitLength===2 ? 'study' : (idSplitLength===3 ? 'group' : 'measureTimePoint');
        this.landingRemoveTarget.textContent = this.removeValue[type]['text'].replace('X',parseInt(idSplit[idSplitLength-1])+1);
        this.landingRemoveTarget.parentElement.firstElementChild.textContent = this.removeValue[type]['title'];
    }

    /** Checks if the name already exists in the current branch. The event must pass two parameters: 'names' containing all names to be checked against and 'current' containing the entered name on page load.
     * @param event widget that invoked the method
     */
    setCreate(event) {
        let target = event.currentTarget.firstElementChild;
        let name = target.value.trim();
        let params = event.params;
        let id = target.id;
        document.getElementById(id.includes('editText') ? id.replace('editText','rename') : id+'_create').disabled = name!=='' && name!==params.current && params.names.includes(name);
    }

    /** Checks if the length of an entered name is at most 20 characters.
     * @param event widget that invoked the method
     */
    checkName(event) {
        if (mergeInput(event).trim().length>20) {
            event.preventDefault();
        }
    }

    // methods that are called from within this class

    /** Sets the visibility of elements belonging to a clicked expandable.
     * @param expandable first element following the clicked caret whose visibility is set
     * @param isExpanded true if the dropdown is expanded, false otherwise
     * @param newDivID first element following the clicked caret whose visibility is not set
     */
    setExpandable(expandable,isExpanded,newDivID) {
        let isNewDiv = false;
        let setVisibility = true; // if isExpanded is true and another caret is encountered, the visibility of these elements is set recursively
        let lastID = ''; // if setVisibility is false, the last element whose visibility is not set
        while (!isNewDiv) {
            let nextID = expandable.id;
            if (setVisibility) {
                setElementVisibility(expandable,isExpanded,nextID!=='' ? 2 : 0);
            } else if (nextID===lastID) {
                setVisibility = true;
                lastID = '';
            }
            expandable = expandable.nextElementSibling;
            if (expandable.classList.contains('clickable') && isExpanded && setVisibility) { // element is a dropdown and visible
                setElementVisibility(expandable,true,2); // caret
                setElementVisibility(expandable.nextElementSibling,true,2); // button for changing name
                setElementVisibility(expandable.nextElementSibling.nextElementSibling,true,2); // buttons for copying etc.
                lastID = expandable.id.replace('dropdown','new')+'_Outer';
                setVisibility = false;
                this.setExpandable(expandable.nextElementSibling.nextElementSibling.nextElementSibling,expandable.classList.contains('dropdownExpanded'),lastID);
            }
            isNewDiv = nextID===newDivID;
        }
    }

    /** Either increases or decreases the grid column span of an element by 1.
     * @param element element whose grid column span gets increased
     * @param increase if true, the span will be increased, otherwise decreased
     * @param changeGrid
     */
    changeGridColumn(element, increase = true, changeGrid = true) {
        if (changeGrid) {
            element.style.gridColumnEnd = 'span '+(parseInt(element.style.gridColumnEnd.replace('span ',''))+(increase ? 1 : -1));
        }
        element.style.marginTop = increase ? '0.25rem' : '1rem';
        element.style.marginBottom = increase ? '0.55rem' : '0';
        // element.classList.remove(increase ? 'mt-3' : 'mt-2');
        // element.classList.add(increase ? 'mt-2' : 'mt-3');
    }
}