import { Controller } from "@hotwired/stimulus";
import { setElementVisibility, setHint } from "../multiFunction";

export default class extends Controller {

    static targets = ['preComplete','preCompleteType','preCompleteText'];

    static values = {
        preCompleteTextHint: Array, // 0: nothing selected, 1: yes or no selected
        informationHintName: String,
        isPreComplete: Boolean
    }

    // methods that are called from the template

    /** Sets the visibility of the questions that eventually follow the pre content question.
     * @param event widget that invoked the method
     */
    setPreContent(event) {
        let value = event.target.value;
        setElementVisibility(this.preCompleteTarget,['partial','deceit'].includes(value));
        setElementVisibility('preContentHint',value==='complete');
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
}