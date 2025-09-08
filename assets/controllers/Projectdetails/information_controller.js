import { Controller } from "@hotwired/stimulus";
import { setElementVisibility, setHint } from "../multiFunction";

export default class extends Controller {

    static targets = ['preNo','preText','preComplete','preCompleteType','preCompleteText','postYes','postText','documentTranslation'];

    static values = {
        pre: String,
        preCompleteTextHint: Array, // 0: nothing selected, 1: yes or no selected
        informationHintName: String,
        isPreComplete: Boolean
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
        this.isPreCompleteValue = value==='0';
        setElementVisibility(this.preCompleteTypeTarget,this.isPreCompleteValue);
        this.preCompleteTextTarget.disabled = !isAnswered;
        setHint('preCompleteTextHint',this.preCompleteTextHintValue[isAnswered ? 1 : 0]);
        let deleteHint = document.getElementById(this.informationHintNameValue);
        if (deleteHint!==null) {
            setElementVisibility(deleteHint,value==='1')
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
        let isPost = this.preNoTarget.checked && this.postYesTarget.checked;
        setElementVisibility(this.preTextTarget.nextElementSibling,isPost);
        setElementVisibility(this.documentTranslationTarget,this.preValue==='0' || isPost);
    }
}