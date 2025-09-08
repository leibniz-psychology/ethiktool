// contains functions that are used in .js classes

import DOMPurify from "isomorphic-dompurify";

/** Sets the visibility of an element
 * @param id id of the element or the element itself
 * @param visible true if the element should be visible, false otherwise
 * @param display if visible is true, the 'display' attribute of the element is set to 'grid' (if 1) or 'flex' (if 2), otherwise to 'block'
 */
export function setElementVisibility(id,visible,display = 0) {
    if ((typeof id)==='string') {
        id = document.getElementById(id);
    }
    id.style.display = visible ? (display===1 ? 'grid' : (display===2 ? 'flex' : 'block')) : 'none';
}

/** Counts the number of checked buttons.
 * @param elements IDs of buttons to be checked
 * @returns {Array} 0: true if any button is checked, false otherwise; 1: number of checked buttons
 */
export function getSelected(elements) {
    let numSelected = 0;
    for (let element of elements) {
        numSelected += document.getElementById(element).checked ? 1 : 0;
    }
    return [numSelected>0,numSelected];
}

/** Checks if a key was pressed while the cursor was inside a specific string (parameter 'start') at the start of the widget. If so and if the key was not an always allowed key, the event is blocked. Additionally, if the cursor is at the end of the string and backspace is pressed, the event is also blocked. A param 'furtherAllowed' may be passed. It limits the keys that are allowed to be pressed if the cursor is at the end of the string.
 * @param event widget that invoked the method
 */
export function checkTextareaInput(event) {
    let eventStart = event.target.selectionStart;
    let params = event.params;
    let startLength = params.start.length;
    let keyPressed = event.code;
    let allowedKeys = ['ArrowLeft','ArrowUp','ArrowRight','ArrowDown','Tab','Home','End','PageUp','PageDown']; // navigating with arrow keys and tabbing to another field is always allowed
    let isNotAllowed = !allowedKeys.includes(keyPressed);
    if (eventStart<startLength && isNotAllowed || (eventStart===startLength && (keyPressed==='Backspace' || isNotAllowed && !(params.furtherAllowed ?? []).includes(keyPressed)))) {
        event.preventDefault();
    }
}

/** Merges the input of a text field with the new entered text before the text is displayed.
 * @param event widget that invoked the method
 * @returns {string} merged input
 */
export function mergeInput(event) {
    let target = event.target;
    let value = target.value; // text that is already in the text field
    return value.substring(0,target.selectionStart) + (event.data ?? '') + value.substring(target.selectionEnd); // complete string with added text
}

/** Checks if text has more than 300 characters. If so and between 300 and 400 characters, the last 100 characters are hidden and the remaining are visible. If more than 400 characters, the first 300 are visible and the remaining are hidden. the last third of the text is hidden and can be toggled by clicking on a symbol. Must pass either an element, an event, or a string. If a string is passed, it must be the ID of an element. The entire text must be either in the first child or in the optional second parameter 'text'. Depending on the length of the text, two further children are added (if not already) and the text is split. If the text is shorter than or equal to 300 characters, the additional two children are removed (if still there). If an event is passed, the parent element must have three children. The second child contains the symbol and the last child the remaining text. The visibility and look of the symbol are then toggled.
 * @param element either an element, an event, or a string
 * @param text text that may be split
 */
export function setHint(element, text = null) {
    let isElement = element.target===undefined;
    if (!isElement) { // element is an event
        element = element.target.parentElement;
    }
    else if ((typeof element)==='string') {
        element = document.getElementById(element);
    }
    let children = element.children;
    let numChildren = children.length;
    let firstChild = children[0]; // visible text
    let textParsed = text;
    if (text===null) {
        text = firstChild.innerHTML;
        textParsed = firstChild.textContent;
    }
    text = text.replaceAll('<br>','<br></br>'); // checkHTMLvalidity checks for equal number of opening and closing tags, therefore temporarily add closing br-tags
    let innerHTML = ''; // visible text
    let parsedLength = textParsed.length;
    let visibleLength = parsedLength<=400 ? parsedLength-100 : 300; // number of visible characters. Only relevant if parsedLength is greater thann 300
    if (isElement) { // on connect or if text has changed
        if (parsedLength>300) { // text without html-tags to avoid cases where the length exceeds the limit only with the tags
            let isOneChild = numChildren===1; // true if on connect
            let clickableElement = isOneChild ? document.createElement('span') : children[1];
            let remainingElement = isOneChild ? document.createElement('span') : children[2];
            clickableElement.setAttribute('class','hintCollapsed dropdown clickable');
            clickableElement.setAttribute('style', 'color: black'); // text may be red
            if (isOneChild) {
                clickableElement.addEventListener('click',setHint.bind(this)); // use 'global' this
                remainingElement.setAttribute('style','display: none');
                element.append(clickableElement,remainingElement);
            }
            let lastSpace = checkHTMLvalidity(text,text.substring(0,visibleLength).lastIndexOf(' '));
            // let lastSpace = checkHTMLvalidity(text,text.substring(0,Math.ceil(parsedLength*0.66)).lastIndexOf(' '));
            innerHTML = text.substring(0,lastSpace);
            remainingElement.innerHTML = ' '+text.substring(lastSpace).replaceAll('<br></br>','<br>').trim(); // will throw an error in the console if text contains invalid html
        }
        else {
            innerHTML = text;
            if (numChildren===3) {
                firstChild.nextElementSibling.remove();
                firstChild.nextElementSibling.remove();
            }
        }
    }
    else { // event
        let secondChild = children[1]; // symbol to extend/collapse
        secondChild.classList.toggle('hintCollapsed');
        secondChild.classList.toggle('hintExpanded');
        if (secondChild.classList.contains('hintCollapsed')) { // text was collapsed
            innerHTML = text.substring(0,checkHTMLvalidity(text, text.substring(0, visibleLength).lastIndexOf(' ')));
        }
        else { // text was extended
            innerHTML = text + children[2].innerHTML;
        }
    }
    firstChild.innerHTML = innerHTML.replaceAll('<br></br>','<br>').trim();
}

/** Checks if a substring of a given string contains valid html. If not, the index where the string should be cut is increased to the first space after the next closing tag.
 * @param text string whose substring should be checked
 * @param index end index of the substring
 * @return {*} index that may be increased for the substring to be valid html or length of text if text contains invalid html
 */
export function checkHTMLvalidity(text, index) {
    let firstPart = text.substring(0,index);
    let textLength = text.length;
    let isEntireText = false;
    if (firstPart.includes('<')) {
        let numOpen = (firstPart.match(/</g) || []).length;
        let numRuns = 0;
        while ((numOpen>(firstPart.match(/>/g) || []).length || // text was cut inside a tag
            numOpen>(firstPart.match(/<\//g) || []).length*2) && // at least one closing tag is missing
        !isEntireText) { // if a tag is not closed at all (i.e., invalid html), stop if the first part contains the entire text
            let closingIndex = text.substring(index).indexOf('</')+index; // first closing tag after the visible part
            ++numRuns;
            index = text.substring(closingIndex).indexOf(' ')+closingIndex; // first space after the closing tag
            firstPart = text.substring(0,index); // cut the string after the closing tag
            numOpen = (firstPart.match(/</g) || []).length;
            isEntireText = (firstPart.length+numRuns)===textLength;
        }
    }
    return isEntireText ? textLength : index;
}

/** Sanitizes the string.
 * @param input string to be sanitized
 * @returns {string} sanitized string
 */
export function sanitizeString(input) {
    return DOMPurify.sanitize(input,{ALLOWED_TAGS: [], ALLOWED_ATTR: []});
}