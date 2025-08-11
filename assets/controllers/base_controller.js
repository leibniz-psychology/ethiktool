import { Controller } from "@hotwired/stimulus";
import { Modal } from "bootstrap";
import { checkTextareaInput, setElementVisibility, setHint, getSelected } from "./multiFunction";

export default class extends Controller {

    static targets = ['loadModal','download','load','loadInput','sidebar','content','checkDoc','preview','form','submitDummy','hint','countableText','charCount','disableLoad','undo','language','xmlModal','pdfModal','landingRemove'];

    static values = {
        route: String,
        routeParams: Object,
        preview: Number, // position of preview scrollbar
    }

    connect() {
        this.spinnerVal = ''; // value of a spinner if it gets focused
        this.numDocs = 1;
        // set error and preview scrollbars
        if (this.hasCheckDocTarget) {
            this.checkDocValue = 0; // position of error messages scrollbar -> reset if page is left
            this.checkDocTarget.scrollTop = this.checkDocValue;
        }
        if (this.hasPreviewTarget) {
            this.previewTarget.scrollTop = this.previewValue;
        }
        // set text of hints
        this.setAllHints();
        // disable elements
        this.disableWidgets();
        // add listener to different elements
        if (!(this.routeValue.includes('main') || this.routeValue.includes('newForm') || this.routeValue.includes('contributors'))) {
            this.formTarget.addEventListener('change', async  (event) => {
                let target = event.target;
                let type = target.type;
                if (this.submitDummyTarget.value!=='load' && type!=='number' && type!=='file') { // if a file is loaded, the type is checked first. If a spinner input is changed, the validity is checked first.
                    let id = target.id;
                    if (!(this.routeValue.includes('landing') && (id.includes('Text') || id==='landing_copy') || id.includes('language'))) {
                        this.submitDummyTarget.value = '';
                        await this.submitForm(event);
                    }
                }
                else { // file with wrong extension was tried to be uploaded
                    this.submitDummyTarget.value = ''
                }
            });
        }
        // submit if the language is changed. Because the route contains the language, no asynchronous submit
        for (let target of this.languageTargets) {
            target.addEventListener('click', (event) => {
                event.preventDefault();
                this.removeHash();
                this.submitDummyTarget.value = 'language:'+target.id;
                this.formSubmit();
            })
        }
        this.addSidebarPreviewListener();
        this.addListener();
    }

    // methods that are called from a template

    /** Submits the form if either the 'save', the 'undo', or the 'single documents' button was clicked.
     * @param event widget that invoked the method
     * @return {Promise<void>}
     */
    async saveUndoDocuments(event) {
        let target = this.checkElementTag(event.target); // content of the button may be wrapped in a span
        let name = target.name;
        this.submitDummyTarget.value = name;
        if (name!=='documents' && name!=='finish') {
            await this.submitForm(event).then(() => {
                this.submitDummyTarget.value = this.submitDummyTarget.value.replace(name,'');
                if (name==='undo') {
                    --this.numDocs;
                    this.setUndoButton();
                }
            });
        }
        else {
            this.formSubmit()
            this.submitDummyTarget.value = '';
        }
    }

    /** sets the dummy submit content and submits the form.
     * @param event widget that invoked the method
     */
    async setDummySubmit(event) {
        event.preventDefault(); // prevent redirecting
        this.removeHash();
        let dummyVal = '';
        let target = this.checkElementTag(event.target); // if the target is a button, the content of it may be wrapped in a span
        let targetID = target.id;
        if (targetID.includes('quit')) {
            dummyVal = targetID;
        }
        else if (targetID.includes('nav')) { // one of the buttons on bottom of pages, except 'save'
            dummyVal = target.name;
        }
        else {
            let params = event.params;
            dummyVal = params.url;
            let routeIDs = params.routeIDs;
            if (routeIDs!==undefined) {
                dummyVal += this.combineKeyValues(routeIDs);
            }
            let page = params.page;
            if (page!==undefined) { // redirection goes to landing page
                dummyVal += "\n"+'page:'+page;
            }
        }
        let isLoadModal = this.hasLoadModalTarget && targetID===(this.loadModalTarget.id+'Button');
        if (isLoadModal || !this.hasLoadModalTarget && target===this.loadTarget) {
            if (isLoadModal) {
                target.nextElementSibling.click(); // click 'no' to close the modal
            }
            this.loadInputTarget.click();
        }
        else {
            this.formSubmit(dummyVal);
        }
    }

    /** Calls checkTextareaInput() in multiFunction
     * @param event widget that invoked the method
     */
    checkTextareaInput(event) {
        checkTextareaInput(event);
    }

    /** Checks if a '<' was entered and if so, removes it.
     * @param event widget that invoked the method
     */
    preventTagStart(event) { // currently only used once by coreData
        if (event.inputType.includes('insert') && (event.data ?? '').includes('<')) {
            event.preventDefault();
        }
    }

    /** Sets the visibility of one or more elements. The event must have either a parameter 'values' or 'multi'. If 'values' is passed, it must be an array. Each element is an array itself with two elements. The first element is either a string or an array. The values are either strings or arrays. If the value of the event target equals the first entry, all elements in the value string/array are set visible, otherwise invisible. Example: [['value1', ['id1','id2']]]: if the event value equals 'value1', the elements with IDs 'id1' and 'id2' are set visible. If the first entry is an array, the elements are set visible if the value of the event target is in this array. If 'multi' is passed, it must be an array. Each element is an array itself with two or three entries. Both entries are either strings oder arrays. If all elements in the first entry (which are IDs) are checked, all elements in the second entry (which are IDs) are set visible, otherwise invisible. If a third entry exists, the visibility of all elements in this entry are set opposite to the visibility of the elements in the second entry. Example: [[['id1','id2'],['id3','id4'],['id5','id6']]]: If the elements with IDs 'id1' and 'id2' are checked, the elements with IDs 'id3' and 'id4' are set visible and the elements with IDs 'id5' and 'id6' are set invisible. If a parameter 'setOr' is passed, it must either be the boolean 'true' or an array. Then, the elements are set visible if any of the elements in the first entry are checked. If 'setOr' is passed with an array, it must contain indices. 'setOr' is then only applíed to those arrays in 'multi' with the indices, otherwise to all arrays. In the previous example: If 'id1' or 'id2' are checked. If a parameter 'isGrid' (or 'isFlex') is passed, the 'display' attribute is set to 'grid' ('flex'), otherwise to 'block', if the element is set visible.
     * @param event widget that invoked the method
     */
    setVisibility(event) {
        let params = event.params;
        let hasValues = params.values!==undefined;
        let display = params.isGrid!==undefined ? 1 : (params.isFlex!==undefined ? 2 : 0);
        if (hasValues) {
            let targetValue = event.target.value;
            for (let values of params.values) {
                let curValue = values[0];
                let ids = this.checkArray(values[1]);
                this.setArrayVisibility(ids, (typeof curValue)==='string' ? targetValue===curValue : curValue.includes(targetValue), display);
            }
        } else {
            let setOr = params.setOr ?? true;
            let multi = params.multi;
            if (setOr===true) {
                setOr = [];
                for (let index = 0; index < multi.length; index++) {
                    setOr.push(index);
                }
            }
            for (let [index, array] of multi.entries()) {
                let elements = this.checkArray(array[0]);
                let numElements = elements.length;
                let isChecked = Array(numElements).fill(false);
                for (let element = 0; element < numElements; ++element) {
                    isChecked[element] = document.getElementById(elements[element]).checked;
                }
                let isVisible = setOr.includes(index) ? isChecked.includes(true) : !isChecked.includes(false);
                this.setArrayVisibility(array[1], isVisible, display);
                if (array[2]!==undefined) {
                    this.setArrayVisibility(array[2], !isVisible, display);
                }
            }
        }
    }

    /** Sets the value of the submit dummy to the id that eventually gets removed. Is called when the modal opens.
     * @param event widget that invoked the method
     */
    setLandingRemove(event) { // only called in landing
        this.landingRemoveID = parseInt(event.target.id.replace('landing_remove',''))+1;
        this.submitDummyTarget.value = 'remove:'+(this.landingRemoveID-1);
        this.landingRemoveTarget.textContent = this.landingRemoveTarget.textContent.replace('{id}',this.landingRemoveID); // if called, multiple element must exist -> name id in modal text
    }

    /** Removes the id that eventually gets removed from the submit dummy. Is called if the "no" button in the remove modal is clicked. */
    unsetLandingRemove() { // only called in landing
        this.submitDummyTarget.value = '';
        this.landingRemoveTarget.textContent = this.landingRemoveTarget.textContent.replace(this.landingRemoveID,'{id}');
    }

    /** Enables or disables a text field based on checkboxes. The event must have a parameter 'checkboxes' which must be an array of three or four entries. If any of the widgets in the first entry (an array) is checked, a text field gets enabled. The text field must have been created with 'addTextfield()'. The second entry contains the id of the div surrounding the text field and the hint. The third entry contains the texts for the hint above the text field: 0: nothing selected, 1: at least one checkbox selected, 2: at least one checkbox selected including a specific one. This specific one may be provided by the fourth entry (which can also be an array; in that case the hint is set to this text if at least one of these is selected). If a parameter 'setMulti' is passed, the event is passed to the 'setMultiCheckbox' method. In this case, the parameters for this method ('single' and 'multiCheck') must also be passed.
     * @param event widget that invoked the method
     */
    setCheckboxTextfield(event) {
        let params = event.params;
        let checkboxes = params.checkboxes;
        let [anySelected] = getSelected(checkboxes[0]);
        let textDiv = document.getElementById(checkboxes[1]);
        textDiv.lastElementChild.firstElementChild.disabled = !anySelected;
        let specific = checkboxes[3] ?? [];
        specific = ((typeof specific)==='string') ? [specific] : specific;
        setHint(textDiv.firstElementChild,checkboxes[2][!anySelected ? 0 : (specific!==[] && getSelected(specific)[0] ? 2 : 1)]);
        if (params.setMulti!==undefined) {
            this.setMultiCheckbox(event);
        }
    }

    /** Sets a widget. A parameter 'widgetValue' must be passed which must be an array of two or three elements. If the value of the event equals the first element of the array, the second element (which must be the id of an element) gets disabled. If the element is a radiobutton or checkbox, it gets deselected. If the array has a third element, it must be an array whose keys are IDs and values are strings. The text of the previous sibling of the parent element of the enabled/disabled element is set to the value of widget that is checked. If a parameter 'unequal' is passed, the widget gets disabled if the values are unequal.
     * @param event widget that invoked the method
     */
    setWidgetByValue(event) {
        let params = event.params;
        let value = event.target.value.trim();
        let paramsValue = params.widgetValue;
        let checkedValue = paramsValue[0];
        let widget = document.getElementById(paramsValue[1]);
        let hint = paramsValue[2] ?? [];
        widget.disabled = params.unequal!==undefined ? value!=checkedValue : value==checkedValue; // trim() makes value a string, but checkedValue might be a number
        if (widget.checked) {
            widget.checked = false;
        }
        if (hint!==[]) {
            setHint(widget.parentElement.previousElementSibling,hint[value]);
        }
    }

    // methods that are called from a template and from within this class

    /** Sets all elements in array either (in)visible.
     * @param ids IDs of elements. Can either be a string or an array
     * @param visible true if the elements should be set visible, false otherwise
     * @param display if visible is true, the 'display' attribute of the element is set to 'grid' (if 1) or 'flex' (if 2), otherwise to 'block'
     */
    setArrayVisibility(ids,visible,display = 0) {
        for (let element of this.checkArray(ids)) {
            setElementVisibility(element,visible,display);
        }
    }

    /** Checks if element is a string and if so, creates an array with the string as the value.
     * @param element element to be checked
     * @returns {string[]} element as an array value if it is a string, element otherwise
     */
    checkArray(element) {
        if ((typeof element)==='string') {
            element = [element];
        }
        return element;
    }

    /** Enables or disables a group of checkboxes. The event must have two parameters 'single' (string) and 'multiCheck' (array with strings). If the widget with the id of the 'single' parameter is checked, all widgets in 'multiCheck' are deselected and disabled. Otherwise, if any of the widgets in 'multiCheck' is selected, the widget with the id of the 'single' parameter is deselected and disabled.
     * @param event widget that invoked the method
     */
    setMultiCheckbox(event) {
        let params = event.params;
        let single = document.getElementById(params.single);
        let isSingle = single.checked;
        let anyChecked = false; // gets true if any checkbox except 'single' is checked
        for (let id of params.multiCheck) {
            let widget = document.getElementById(id);
            let isChecked = widget.checked;
            anyChecked |= isChecked;
            widget.checked = isSingle ? false : isChecked;
            widget.disabled = isSingle;
        }
        single.checked = anyChecked ? false : isSingle;
        single.disabled = anyChecked;
    }

    /** Submits the form and refreshes the middle (content) and right (preview) columns.
     * @param event widget that invoked the method
     * @return {Promise<Response<any, Record<string, any>, number>>}
     */
    async submitForm(event) {
        event.preventDefault();
        // if submission was invoked inside a modal, reset the body attributes set by bootstrap
        let body = document.body;
        body.classList.remove('modal-open');
        body.removeAttribute('data-bs-overflow');
        body.style.overflow = 'unset';
        body.style.paddingRight = '0';
        let target = event.target;
        // set dummy target if on a landing page of projectdetails
        let params = event.params ?? [];
        let isLanding = this.routeValue.includes('landing');
        if (isLanding && params.routeIDs!==undefined) {
            let dummyVal = this.combineKeyValues(params.routeIDs); // route IDs for landing, empty if on overview of studies
            let id = target.id;
            let removeVal = this.submitDummyTarget.value;
            let editRemove = id.includes('edit') ? 'edit:'+id.replace('landing_edit','') : (removeVal.includes('remove') ? removeVal : '');
            this.submitDummyTarget.value = "app_landing\n"+(editRemove!=='' ? editRemove : id==='submitName' ? 'newClicked:true' : 'copyClicked:true')+"\n"+(dummyVal!=='' ? dummyVal.trim()+"\n" : '')+'page:Projectdetails';
        }

        this.submitDummyTarget.value = !isLanding ? (this.submitDummyTarget.value+"\n"+this.routeValue).trim() : this.submitDummyTarget.value; // may contain 'download' or 'undo'
        // add route IDs to dummy target if existent
        if (this.routeParamsValue['studyID']!==undefined) { // submission on a projectdetails page
            let dummyVal = this.combineKeyValues({'studyID': this.routeParamsValue['studyID'],'groupID': this.routeParamsValue['groupID'],'measureID': this.routeParamsValue['measureID']});
            let page = this.routeParamsValue['page'] ?? '';
            if (page!=='') { // redirection goes to landing page
                dummyVal += "\n"+'page:'+page;
            }
            this.submitDummyTarget.value += dummyVal;
        }

        // submit the form and update the page
        await fetch(this.formTarget.action, {method: this.formTarget.method, body: new FormData(this.formTarget)}).then(async (response) => {
            let dummyVal = this.submitDummyTarget.value;
            this.numDocs += !(dummyVal.includes('undo') || dummyVal.includes('documents')) ? 1 : 0;
            this.submitDummyTarget.value = '';
            let html = document.createElement('div');
            html.innerHTML = await response.text();

            this.sidebarTarget.parentNode.replaceChild(html.querySelector('#sidebar'),this.sidebarTarget);

            if (this.hasCheckDocTarget) { // update checkDoc scrollbar only if scrollbar exists
                this.checkDocValue = this.checkDocTarget.scrollTop;
                this.checkDocTarget.parentNode.replaceChild(html.querySelector('#checkDoc'),this.checkDocTarget);
                this.checkDocTarget.scrollTop = this.checkDocValue;
            }
            if (this.hasPreviewTarget) { // update preview scrollbar only if preview exists
                this.previewValue = this.previewTarget.scrollTop;
                this.previewTarget.parentNode.replaceChild(html.querySelector('#preview'),this.previewTarget);
                this.previewTarget.scrollTop = this.previewValue;
            }
            for (let backdrop of document.getElementsByClassName('modal-backdrop')) { // if submission was made inside a modal, remove the backdrop
                backdrop.remove();
            }
            let scrollPositions = [];
            for (let element of document.getElementsByTagName('textarea')) { // get scroll positions of text areas
                scrollPositions[element.id] = element.scrollTop;
            }
            let isUndo = this.hasUndoTarget && target===this.undoTarget;
            if (isUndo || ['app_contributors','app_landing'].includes(this.routeValue)) {
                this.contentTarget.parentNode.replaceChild(html.querySelector('#content'),this.contentTarget);
                if (isUndo) {
                    for (let [elementID, scrollPos] of Object.entries(scrollPositions)) { // set scroll position of text areas
                        let element = document.getElementById(elementID);
                        if (element!==null) { // in data privacy, some elements may not exist anymore after updating the page
                            element.scrollTop = scrollPos;
                        }
                    }
                    this.setAllHints();
                    this.disableWidgets();
                    this.addListener();
                }
            }
            this.addSidebarPreviewListener();
            // }
            if (this.hasUndoTarget) { // few pages do not have an 'undo' button
                this.setUndoButton();
            }
            setTimeout(function () {},5000);
            });
    }

    /** Gets the text of a textarea and displays its length on another element. The immediately preceding sibling must be the element that is placed behind the textarea. If the length of the textarea is greater than a given value, the remaining text as well as the number indicating the length gets highlighted respectively colored in red. The element showing the length of the text must have a data attribute 'maxChars'.
     * @param element widget that invoked the method
     */
    setTextareaCharCount(element) {
        let text = element.value;
        // get the index of the textarea in the targets
        let id = -1;
        while (id<this.countableTextTargets.length && element!==this.countableTextTargets[id]) {
            ++id;
        }
        let count = this.charCountTargets[id]; // div showing the number of characters entered
        text = text.replaceAll(/<\/?span>/g,''); // remove all opening/closing span tags without matching closing/opening tag entered by the user to avoid bugs in case a span for highlighting is added
        element.value = text; // enter text in textarea in case tags were removed
        let numChars = text.trim().length; // length of text after removing html tags entered by user, but before adding the span tag
        let maxChars = parseInt(count.dataset.maxChars);
        let spanTag = 'span style="background-color: red; margin: 0; padding: 0">';
        let isGreater = numChars>maxChars;
        if (isGreater) { // highlight remaining text in red
            text = text.substring(0,maxChars)+'<'+spanTag+text.substring(maxChars)+'</span>';
        }
        // escape all '<' except the one of the span tag that may have been added -> avoid user generated html
        text = text.replaceAll('<','&lt;').replace('&lt;'+spanTag,'<'+spanTag).replace('&lt;/span>','</span>');
        if (text.endsWith("\n") || text.endsWith("\n</span>")) { // if entered text ends with a line break, add another line break to keep same height for div
            text += "\n";
        }
        let divElement = element.previousElementSibling;
        divElement.innerHTML = text;
        divElement.scrollTop = element.scrollTop; // adjust scrollbar of div
        // set count display
        count.textContent = numChars.toString();
        count.style.color = isGreater ? 'red' : 'black';
        // set visibility of icon
        setElementVisibility(count.nextElementSibling,isGreater);
    }

    /** Opens a modal. If the passed argument is an event, it must have a parameter 'target' indicating the id of the modal.
     * @param element Either the target modal or an event
     */
    showModal(element) {
        if (element.target!==undefined) { // element is an event
            element = document.getElementById(element.params.target);
        }
        (new Modal(element)).show();
    }

    // methods that are called from within this class

    /** (De)actives the 'undo' button. */
    setUndoButton() {
        this.undoTarget.disabled = this.numDocs<2;
    }

    /** Adds the listeners for the sidebar and the preview. */
    addSidebarPreviewListener() {
        // add event listener to clickable dropdown menus or hints that extend
        for (let dropdown of document.getElementsByClassName('clickable')) { // no targets because the class is also needed for styling
            if (dropdown.classList.contains('dropdownNav') && !dropdown.hasAttribute('nosubpage')) {
                dropdown.addEventListener('click', () => {
                    dropdown.classList.toggle('active');
                    let isHint = dropdown.parentElement.classList.contains('hint');
                    let dropdownContent = isHint ? dropdown.nextElementSibling : dropdown.parentElement.nextElementSibling;
                    setElementVisibility(dropdownContent,dropdownContent.style.display==='none',1)
                    let classStart = isHint ? 'hint' : 'dropdown';
                    dropdown.classList.toggle(classStart+'Collapsed');
                    dropdown.classList.toggle(classStart+'Expanded');
                });
            }
        }
        // load form
        // this.loadTarget.addEventListener('click', (event) => { // element in the sidebar
        //     event.preventDefault();
        //     if (this.hasLoadModalTarget) {
        //         this.showModal(this.loadModalTarget);
        //     }
        //     else {
        //         this.loadInputTarget.click();
        //     }
        // });
        // save button
        if (this.hasDownloadTarget) {
            for (let download of this.downloadTargets) { // one is in the sidebar and eventually a second one on the page
                if (!download.hasAttribute('saveListener')) { // add the listener to the element on the page only once
                    download.addEventListener('click', () => {
                        this.formSubmit('download');
                        this.submitDummyTarget.value = '';
                    });
                    download.setAttribute('saveListener', '');
                }
            }
        }
        // hint above headings of the application pdf preview
        for (let heading of document.getElementsByClassName('inputPage')) {
            let clickableHeading = heading.getElementsByClassName('inputPageHeading')[0]; // the classes exist only once inside the respective element
            clickableHeading.addEventListener('click', (event) => {
                // if (event.currentTarget===heading) { // make hint visible only if clicked on heading
                    heading.getElementsByTagName('span')[0].style.opacity = '1';
                // }
            });
            clickableHeading.addEventListener('mouseleave', () => {
                heading.getElementsByTagName('span')[0].style.opacity = '0';
            });
        }
    }

    /** Adds all listeners to the different elements. */
    addListener() {
        this.loadInputTarget.addEventListener('change', () => { // dummy element to for upload
            this.checkFileUpload(this.loadInputTarget,'xml')
        });
        // set textareas with character counts
        for (let textfield of this.countableTextTargets) {
            this.setTextareaCharCount(textfield); // set if page is loaded or content is refreshed
            textfield.addEventListener('input', () => {
                this.setTextareaCharCount(textfield);
            });
            textfield.addEventListener('scroll', () => {
                textfield.previousElementSibling.scrollTop = textfield.scrollTop;
            })
        }
        // prevent submitting the form by pressing enter
        for (let inputField of document.getElementsByTagName('input')) {
            inputField.addEventListener('keydown', event => {
                if (event.key==='Enter') {
                    event.preventDefault();
                }
            });
            let type = inputField.type;
            let isFile = type==='file' && inputField!==this.loadInputTarget;
            if (this.hasPreviewTarget || isFile) {
                inputField.addEventListener('change',() => {
                    if (isFile && this.checkFileUpload(inputField,'pdf')) {
                        document.getElementById(inputField.id+'filename').textContent = inputField.value.split(/(\\|\/)/g).pop();
                    }
                });
            }
            if (type==='number') { // spinner
                inputField.addEventListener('focusin', event => {
                    this.spinnerVal = event.target.value;
                });
                inputField.addEventListener('focusout',async (event) => {
                    let value = inputField.value;
                    let isInt = inputField.step%2===0;
                    value = isInt ? parseInt(value) : parseFloat(value);
                    let min = inputField.min;
                    min = isInt ? parseInt(min) : parseFloat(min);
                    let max = inputField.max;
                    max = isInt ? parseInt(max) : parseFloat(max);
                    let isGreaterThanMax = value>max;
                    if ((value<min || isGreaterThanMax)) { // if only a minus character is entered, min is NaN
                        value = isGreaterThanMax ? max : min;
                    }
                    value = !isNaN(value) ? value : ''
                    inputField.value = value;
                    if (value!==this.spinnerVal) {
                        this.spinnerVal = '';
                        await this.submitForm(event);
                    }
                });
            }
        }
        // prevent aria-hidden warning if modal is closed
        window.addEventListener('hide.bs.modal', () => {
            if (document.activeElement instanceof HTMLElement) {
                document.activeElement.blur();
            }
        })
        // if a browser-based e-mail client is used, open a new tab -> will also open a new tab in some browsers if a desktop client is used
        for (let widget of document.getElementsByTagName('a')) {
            let href = widget.getAttribute('href') ?? '';
            if (href.includes('mailto')) {
                widget.addEventListener('click', event => {
                    event.preventDefault();
                    window.open(href,'mail');
                })
            }
        }
    }

    /** Checks if an uploaded file has a valid extension.
     * @param file form element that stores the file
     * @param type extension
     */
    checkFileUpload(file,type) {
        let isXML = type==='xml';
        let value = file.value;
        if (value.endsWith('.'+type)) {
            if (isXML) {
                this.formSubmit('load');
            }
            else {
                return true;
            }
        }
        else if (value!=='') {
            file.value = '';
            this.showModal(isXML ? this.xmlModalTarget : this.pdfModalTarget);
        }
        return false;
    }

    /** Submits the form with the formTarget.
     * @param dummyVal if not an empty string, value the submitDummyTarget gets set to
     */
    formSubmit(dummyVal = '') {
        this.submitDummyTarget.value = 'preview:'+(this.hasPreviewTarget ? this.previewTarget.scrollTop : '')+"\n"+this.submitDummyTarget.value;
        if (dummyVal!=='') {
            let split = this.submitDummyTarget.value.split("\n");
            let isPage = false;
            for (let [key,value] of Object.entries(split)) {
                if (value.includes('app')) { // if immediately after entering text in a text field a button/link is clicked, the form is submitted twice
                    split[key] = dummyVal;
                    isPage = true;
                }
            }
            this.submitDummyTarget.value = split.join("\n")+(!isPage ? dummyVal : '');
        }
        this.formTarget.submit();
    }

    /** Sets all hints, i.e., if the texts exceeds a maximum length, the remaining text is hidden behind a '+' sign. */
    setAllHints() {
        for (let hint of this.hintTargets) {
            setHint(hint);
        }
    }

    /** Disables all widgets that should be disabled when the page loads. */
    disableWidgets() {
        for (let element of this.disableLoadTargets) {
            element.disabled = true;
        }
    }

    /** Creates a string where each line is an entry of element. The key and the value are separated by a colon.
     * @param element object with keys and values
     * @return {string} combined keys and values
     */
    combineKeyValues(element) {
        let returnVal = '';
        for (let [key, val] of Object.entries(element)) {
            if (key.includes('ID') || key.includes('page')) { // key contains either 'ID' or is 'page'
                returnVal += "\n"+key+':'+val;
            }
        }
        return returnVal;
    }

    /** Checks if the target is either a span-, svg-, or path-element. If so, the parent element is received until it is neither of these tags.
     * @param target element to be checked
     * @returns {*} first (parent) element that is neither a span-, svg-, nor path-element.
     */
    checkElementTag(target) {
        let tags = ['span','svg','path'];
        while (tags.includes(target.tagName.toLowerCase())) {
            target = target.parentElement;
        }
        return target;
    }

    /** Removes the hash from the URL, including, the hash-symbol, to avoid jumps to the top of the page if the page changes and to prevent jumping to the label if the language has changed. */
    removeHash() {
        history.pushState('',document.title,window.location.href.split('#')[0]);
    }
}