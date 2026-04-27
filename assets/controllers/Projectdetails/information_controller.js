import { Controller } from "@hotwired/stimulus";
import {setElementVisibility, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['preNo','preText','preComplete','preCompleteType','preCompleteText','preAbort','postYes','postText','documentTranslation'];

    static values = {
        pre: String,
        informationHintName: String,
        preAbortHints: Object
    }

    connect() {
        if (this.hasPreTextTarget) {
            this.setPreTextPDF();
        }
    }

    // methods that are called from the template

    /** Sets the visibility of the questions that eventually follow the pre content question.
     * @param event widget that invoked the method
     */
    setPreContent(event) {
        let value = event.target.value;
        setElementVisibility(this.preCompleteTarget,['partial','deceit'].includes(value));
        if (document.getElementById('preContentHint')!==null) {
            setElementVisibility('preContentHint',value==='complete');
        }
    }

    /** Sets the visibility of the pre complete type and (de)actives the text field.
     * @param event
     */
    setPreComplete(event) {
        let value = event.target.value;
        let isAnswered = value!=='';
        let isPreComplete = value==='0';
        setElementVisibility(this.preCompleteTypeTarget,isPreComplete);
        setElementVisibility(this.preAbortTarget,isPreComplete);
        this.preCompleteTextTarget.disabled = !isAnswered;
        setHint('preCompleteTextHint',event.params.hint[isAnswered ? 1 : 0]); // 0: nothing selected, 1: yes or no selected
        let deleteHint = document.getElementById(this.informationHintNameValue);
        if (deleteHint!==null) {
            setElementVisibility(deleteHint,value==='1')
        }
    }

    /** Sets the visibility of the text field for pre abort as well as the hint. Must pass a parameter 'hints'.
     * @param event widget that invoked the method
     */
    setPreAbort(event) {
        let value = event.target.value;
        let isDescription = ['abortOther','abortNo'].includes(value);
        setElementVisibility('preAbortDescriptionDiv',isDescription);
        if (isDescription) {
            setHint('preAbortDescriptionHint',event.params.hints[value]);
        }
    }

    // methods that called from the template or from within this class

    /** Sets the visibility of the pdf-icon for preText. Additionally, sets the visibility of the document translation question.
     * @param event widget that invoked the method
     * */
    setPreTextPDF(event) {
        if (event!==undefined) {
            let target = event.target;
            if (target.id.includes('pre')) {
                this.preValue = target.value;
            }
        }
        let pdf = this.preTextTarget.nextElementSibling;
        if (pdf!==null) {
            let isPost = this.getPost();
            setElementVisibility(pdf,isPost);
            let hasMarkInput = this.preTextTarget.classList.contains('markInput');
            if (isPost && !hasMarkInput) {
                this.preTextTarget.classList.add('markInput');
            } else if (!isPost && hasMarkInput) {
                this.preTextTarget.classList.remove('markInput');
            }
        }
        this.setDocumentTranslation();
    }

    /** Sets the visibility of the document translation question. */
    setDocumentTranslation() {
        if (this.hasDocumentTranslationTarget) {
            setElementVisibility(this.documentTranslationTarget,this.preValue==='0' || this.getPost());
        }
    }

    // methods that are called from within this class

    /** Returns whether post information is given.
     * @returns {*} true if post information is given, false otherwise.
     */
    getPost() {
        return this.preNoTarget.checked && this.postYesTarget.checked;
    }
}