import { Controller } from "@hotwired/stimulus";
import {getSelected, setElementVisibility, showModal} from "../multiFunction";

export default class extends Controller {

    static targets = ['originExisting','originSources','research','votesDiv','votesYes','votesNo','resultNegative','resultNegativeConfirm','voteContributorsConfirm','further','dataSet','originModal']

    static values = {
        originSources: Array,
        votes: String,
        voteContributors: String
    }

    connect() {
        if (this.hasOriginModalTarget) {
            this.originExistingTarget.addEventListener('click', () => {
                showModal(this.originModalTarget);
                let button = document.getElementById('originModalButton');
                button.addEventListener('click', () => {
                    button.nextElementSibling.click(); // close modal
                })
            });
        }
        this.setDataSet();
    }

    // methods that are called from the template

    /** Sets the visibility of the votes div. */
    setVotesDiv() {
        setElementVisibility(this.votesDivTarget,this.researchTarget.checked);
        this.setDataSet();
    }

    /** Sets this.votesValue and the visibility of the votes sub-questions.
     * @param event widget that invoked the method
     * */
    setVotes(event) {
        this.votesValue = event.target.value;
        setElementVisibility(this.votesYesTarget,this.votesValue==='0');
        setElementVisibility(this.votesNoTarget,this.votesValue==='1');
        this.setDataSet();
    }

    /** Sets this.voteContributorsValue.
     * @param event widget that invoked the method
     */
    setVoteContributors(event) {
        let target = event.target;
        if (!target.id.includes('Description')) {
            this.voteContributorsValue = event.target.value;
            this.setDataSet();
        }
    }

    // methods that are called from the template or from within this class

    /** Sets the visibility of the data set question. */
    setDataSet() {
        setElementVisibility(this.dataSetTarget,
            this.researchTarget.checked && // research is selected
            (this.votesValue==='0' && this.resultNegativeTarget.checked && this.resultNegativeConfirmTarget.checked || // negative vote
             this.voteContributorsValue!=='' && this.getVoteContributors())); // no vote and vote contributors is confirmed or no
        this.setFurther();
    }

    /** Sets the visibility of the origin sources div and further div. */
    setFurther() {
        let isExisting = this.originExistingTarget.checked;
        setElementVisibility(this.originSourcesTarget,isExisting);
        setElementVisibility(this.furtherTarget,
            isExisting && ( // origin is existing
            !getSelected(this.originSourcesValue)[0] || // no origin source is selected
                !this.researchTarget.checked || this.votesValue==='' || // research not selected or votes not answered yet
                  this.votesValue==='0' && (!this.resultNegativeTarget.checked || this.resultNegativeConfirmTarget.checked) || // no negative vote or confirmed
                  this.getVoteContributors() // no vote and vote contributors is confirmed or no
                  ));
    }

    // methods that are called from within this class

    /** Checks whether vote contributors is answered such that further question are asked.
     * @returns {boolean} true if vote question is answered with no and vote contributors is either yes and confirm is checked or is no, false otherwise (i.e., also if votes is not yes)
     */
    getVoteContributors() {
        return this.votesValue==='1' && !(this.voteContributorsValue==='0' && (!this.hasVoteContributorsConfirmTarget || !this.voteContributorsConfirmTarget.checked));
    }
}