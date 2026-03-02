import { Controller } from "@hotwired/stimulus";
import {getSelected, setElementVisibility} from "../multiFunction";

export default class extends Controller {

    static targets = ['burdensEverydayYes','textInput'];

    // methods that are called from the template

    /** Sets the visibility of the hint that inputs get removed.
     * @param event widget that invoked the method
     * */
    setTextInputHint(event) {
        let params = event.params.textHintParams;
        if (this.hasTextInputTarget) {
            let visibility = (!getSelected(params['burdens'])[0] || !this.burdensEverydayYesTarget.checked) && !getSelected(params['risks'])[0];
            for (let target of this.textInputTargets) {
                setElementVisibility(target,visibility);
            }
        }
    }
}