import { Controller } from "@hotwired/stimulus";
import {getSelected, setElementVisibility, setHint} from "../multiFunction";

export default class extends Controller {

    static targets = ['privacyQuestions','responsibilityHint','transferOutsideHint','privacyQuestionsMarking','listDiv','dataPersonal','dataOnlineProcessingDiv','dataResearchDiv','dataResearchHint','laterEnd','anonymization','anonymizationNo','storage','dataPersonalAccess','purposeResearch','purposeNo','purposeCompensation','purposeTechnical','relatable','relatableDiv','purposeFurther','purposeNoFurther','contactResultFurther','technicalFurther','compensationCode','orderProcessingDescription','processingFurther','noDocumentHint']

    static values = {
        create: String,
        responsibility: String,
        responsibilityHint: Array, // 0: with private, 1: without private
        transferOutside: String,
        dataOnline: String,
        dataOnlineProcessing: String,
        dataPersonal: String,
        marking: String,
        markingSecond: String,
        markingHints: Array, // 0: external, 1: name
        markingFurther: String,
        internal: String, // how the code is created
        internalSecond: String, // how the code is created
        external: String, // whether code contains personal data (same for following variables
        externalSecond: String,
        pattern: String,
        patternSecond: String,
        own: String,
        ownSecond: String,
        contributors: String,
        contributorsSecond: String,
        dataResearchHint: Object, // heading hint
        anonymizationTypes: Array, // without 'no'
        purposeResearchTypes: Object, // without 'no purpose'. Keys: values, values: widget IDs
        purposeFurtherTypes: Object, // without 'no further purpose'. Keys: values, values: widget IDs
        laterEnd: Array, // 0: marking with name, 1: marking without name
        storage: String,
        accessOrderProcessing: Array // access types where order processing is asked
    }

    connect() {
        this.responsibilityValues = ['onlyOther','multiple','private'];
        this.personalValues = ['personal','personalMaybe'];
        this.externalString = 'external';
        this.internalString = 'internal';
        this.externalIntervalValues = [this.externalString,this.internalString];
        this.nameString = 'name';
        this.markingOtherString = 'other';
        this.markingValues = [this.externalString,this.internalString,this.nameString];
        this.patternString = 'pattern';
        this.ownString = 'own';
        this.contributorsString = 'contributors';
        this.secondString = 'Second';
        this.listString = 'list';
        this.codeValues = [this.listString,'generation'];
        this.setMarkingHints();
        this.setList();
        this.setDataOnline();
        this.setResponsibilityTransfer();
        for (let widget of [this.purposeTechnicalTarget,this.technicalFurtherTarget]) {
            widget.addEventListener('click', (event) => {
                event.preventDefault(); // (de)selection is done only by the controller
            })
        }
    }

    // methods that are called from the template

    /** Sets this.createValue.
     * @param event widget that invoked the method
     */
    setPrivacyCreate(event) {
        this.createValue = event.target.value;
    }

    /** Sets this.markingValue or this.markingSecondValue.
     * @param event widget that invoked the method
     */
    setMarking(event) {
        let target = event.target;
        let id = target.id;
        if (!id.includes('Description')) {
            let value = target.value;
            if (id.includes(this.secondString)) {
                this.markingSecondValue = value;
            }
            else {
                this.markingValue = value;
            }
            // set visibility of privacy questions
            this.setPrivacyQuestions();
            // set visibility of text fields
            this.setMarkingHints();
            // set visibility of list question
            this.setList();
        }
    }

    /** Sets this.markingFurtherValue and the visibility of the second marking div. Then calls this.setList().
     * @param event widget that invoked the method
     */
    setMarkingFurther(event) {
        this.markingFurtherValue = event.target.value;
        setElementVisibility('markingDivSecond',this.markingFurtherValue==='0');
        this.setList();
    }

    /** Sets this.internalValue or this.internalSecondValue.
     * @param event widget that invoked the method
     */
    setCreate(event) {
        let value = event.target.value;
        if (event.target.id.includes(this.secondString)) {
            this.internalSecondValue = value;
        }
        else {
            this.internalValue = value;
        }
        this.setList();
    }

    /** Sets this.externalValue or this.externalSecondValue.
     * @param event widget that invoked the method
     */
    setExternal(event) {
        let value = event.target.value;
        if (event.target.id.includes(this.secondString)) {
            this.externalSecondValue = value;
        }
        else {
            this.externalValue = value;
        }
        this.setList();
    }

    /** Sets this.patternValue or this.patternSecondValue.
     * @param event widget that invoked the method
     */
    setpattern(event) {
        let value = event.target.value;
        if (event.target.id.includes(this.secondString)) {
            this.patternSecondValue = value;
        }
        else {
            this.patternValue = value;
        }
        this.setList();
    }

    /** Sets this.ownValue or this.ownSecondValue.
     * @param event widget that invoked the method
     */
    setown(event) {
        let value = event.target.value;
        if (event.target.id.includes(this.secondString)) {
            this.ownSecondValue = value;
        }
        else {
            this.ownValue = value;
        }
        this.setList();
    }

    /** Sets this.patternValue or this.patternSecondValue.
     * @param event widget that invoked the method
     */
    setcontributors(event) {
        let value = event.target.value;
        if (event.target.id.includes(this.secondString)) {
            this.contributorsSecondValue = value;
        }
        else {
            this.contributorsValue = value;
        }
        this.setList();
    }

    /** Sets this.storageValue.
     * @param event widget that invoked the method
     */
    setStorage(event) {
        this.storageValue = event.target.value;
    }

    /** Sets the access widgets for a given purpose. Must pass a parameter 'purpose'.
     * @param event widgets that invoked the method.
     */
    setAccess(event) {
        let target = event.target;
        let id = target.id;
        if (!id.includes('Text')) { // checkbox was (de)selected
            let purpose = event.params.purpose;
            let isChecked = target.checked;
            let contributorsID = purpose+'contributors';
            if ([purpose+'contributorsPart',purpose+'institution'].includes(id)) {
                setElementVisibility(id+'Text',isChecked);
            }
            // enable/disable first two options
            let contributors = document.getElementById(contributorsID);
            let contributorsPart = document.getElementById(contributorsID+'Part');
            contributors.disabled = contributorsPart.checked;
            contributorsPart.disabled = contributors.checked;
            // set hint
            if (id===contributorsID+'Other') {
                setElementVisibility(purpose+'AccessHintDiv',isChecked);
            }
            // set order processing
            if (this.accessOrderProcessingValue.includes(id.replace(purpose,''))) {
                setElementVisibility(id+'orderProcessingDiv',isChecked);
            }
            this.setAccessWidgets();
        }
    }

    // methods that are called from within this class

    /** Sets the visibility of the widgets that depend on the responsibility and transfer outside questions.
     * @param event
     */
    setResponsibilityTransfer(event) {
        let isResponsibility = true;
        if (event!==undefined) {
            let target = event.target;
            let value = target.value;
            isResponsibility = target.id.includes('responsibility');
            if (isResponsibility) {
                this.responsibilityValue = value;
            }
            else {
                this.transferOutsideValue = value;
            }
            setElementVisibility(isResponsibility ? this.responsibilityHintTarget: this.transferOutsideHintTarget,isResponsibility ? this.responsibilityValues.includes(this.responsibilityValue) : this.transferOutsideValue==='yes');
        }
        else { // on connect
            setElementVisibility(this.responsibilityHintTarget,this.responsibilityValues.includes(this.responsibilityValue));
            setElementVisibility(this.transferOutsideHintTarget,this.transferOutsideValue==='yes');
        }
        if (isResponsibility) {
            this.responsibilityHintTarget.textContent = this.responsibilityHintValue[this.responsibilityValue==='private' ? 0 : 1];
        }
        this.setPrivacyQuestions();
    }

    /** Sets the widgets whose visibility depends on the questions that may prevent the tool from creating a document. */
    setPrivacyQuestions() {
        let isPrivacy = !(this.responsibilityValues.concat([''])).includes(this.responsibilityValue) && !['yes',''].includes(this.transferOutsideValue);
        setElementVisibility(this.privacyQuestionsTarget,isPrivacy); // including personal and marking question
        setElementVisibility(this.privacyQuestionsMarkingTarget, isPrivacy && this.markingValue!==this.markingOtherString);
    }

    /** Sets the marking hints. */
    setMarkingHints() {
        for (let [type, markingValue] of (Object.entries({'marking': this.markingValue, 'markingSecond': this.markingSecondValue}))) {
            let isExternal = markingValue===this.externalString;
            let isText = isExternal || markingValue===this.nameString;
            let description = document.getElementById(type+'DescriptionDiv');
            setElementVisibility(description,isText);
            setElementVisibility(description.lastElementChild.lastElementChild,isText && isExternal); // pdf symbol. Text field must have been created with addTextfield()
            setHint(description.firstElementChild,this.markingHintsValue[isExternal ? 0 : 1]);
        }
    }

    /** Sets the visibility of the list question. Then calls this.setDataResearch(). */
    setList() {
        setElementVisibility(this.listDivTarget,this.getMarkingPersonal(false,false));
        this.setDataResearch();
    }

    // methods that are called from the template or from within this class

    /** Sets this.dataOnlineValue and the visibility of the data online processing div. Then calls this.setDataOnlineProcessing().
     * @param event widget that invoked the method
     */
    setDataOnline(event) {
        if (this.hasDataOnlineProcessingDivTarget) { // if target exists, questions are asked
            if (event!==undefined) {
                this.dataOnlineValue = event.target.value;
            }
            setElementVisibility(this.dataOnlineProcessingDivTarget,this.dataOnlineValue==='ipTechnical');
        }
        this.setDataOnlineProcessing();
    }

    /** Sets this.dataOnlineProcessingValue and the visibility of the widgets that depend on this value.
     * @param event widget that invoked the method
     */
    setDataOnlineProcessing(event) {
        let isSeparate = false;
        if (this.hasDataOnlineProcessingDivTarget) { // if target exists, questions are asked
            if (event!==undefined) {
                this.dataOnlineProcessingValue = event.target.value;
            }
            let isTechnical = this.dataOnlineValue==='ipTechnical';
            isSeparate = isTechnical && this.dataOnlineProcessingValue==='separate';
            // set data personal
            let isDataPersonal = this.dataOnlineValue==='ipResearch' || isTechnical && this.dataOnlineProcessingValue==='research';
            this.dataPersonalValue = isDataPersonal ? 'personal' : this.dataPersonalValue;
            let id = this.dataPersonalTarget.id;
            this.dataPersonalTarget.checked = isDataPersonal || this.dataPersonalTarget.checked;
            for (let suffix of ['Maybe','No']) {
                let widget = document.getElementById(id+suffix);
                widget.checked = isDataPersonal ? false : widget.checked;
                widget.disabled = isDataPersonal;
            }
            this.setDataResearch();
        }
        // set the technical purposes
        this.setPurposeTechnical();
        this.technicalFurtherTarget.checked = isSeparate;
        this.technicalFurtherTarget.disabled = !isSeparate;
        this.purposeNoTarget.checked = this.purposeTechnicalTarget.checked ? false : this.purposeNoTarget.checked;
        this.purposeNoFurtherTarget.checked = isSeparate ? false : this.purposeNoFurtherTarget.checked;
        this.setPurpose(); // also enables/disables the purpose widgets
    }


    /** Sets the visibility of the data research question and the text of the hint. Then calls this.setPersonalWidgets().
     * @param event widget that invoked the method
     */
    setDataResearch(event) {
        if (event!==undefined && event.target.id.includes('dataPersonal')) {
            this.dataPersonalValue = event.target.value;
        }
        // values that indicate if for any code either 'list' or 'generation' is selected
        let isListGenerationName = this.getMarkingPersonal();
        let isPersonal = this.personalValues.includes(this.dataPersonalValue);
        let isDataResearch = isPersonal || isListGenerationName;
        setElementVisibility(this.dataResearchDivTarget,isDataResearch || this.dataOnlineValue==='ipTechnical' && this.dataOnlineProcessingValue==='linked');
        if (isDataResearch) {
            this.dataResearchHintTarget.innerHTML = this.dataResearchHintValue[isPersonal ? this.dataPersonalValue+(this.markingValue!=='no' ? 'Code' : '') : 'code']
        }
        this.setPersonalWidgets();
        this.setTransferProcessingFurther();
    }

    /** Sets the visibility of the anonymization, the storage, and the personal keep questions. */
    setPersonalWidgets() {
        let isPersonal = this.personalValues.includes(this.dataPersonalValue);
        let isAnyAnonymization = getSelected(this.anonymizationTypesValue)[0];
        setElementVisibility(this.anonymizationTarget,isPersonal);
        setElementVisibility(this.storageTarget,isPersonal && isAnyAnonymization);
        setElementVisibility(this.dataPersonalAccessTarget,isPersonal || this.getMarkingPersonal());
        setElementVisibility('personalKeep', isPersonal && (this.anonymizationNoTarget.checked || isAnyAnonymization && this.storageValue==='keep'));
    }

    /** Sets the widgets for the two purpose questions as well as the compensation code question. */
    setPurpose() {
        let isNotOther = this.markingValue!==this.markingOtherString;
        let isPurposeVisible = this.getAnyMarking();
        setElementVisibility(this.purposeResearchTarget,isPurposeVisible);
        setElementVisibility(this.purposeFurtherTarget,isNotOther);
        let isNoPurpose = this.purposeNoTarget.checked;
        let isNoPurposeFurther = this.purposeNoFurtherTarget.checked;
        // set technical purpose -> if marking is changed from 'no marking' to marking, purpose question gets visible
        this.setPurposeTechnical();
        // research
        this.purposeNoTarget.disabled = getSelected(Object.values(this.purposeResearchTypesValue))[0];
        // further
        this.purposeNoFurtherTarget.disabled = getSelected(Object.values(this.purposeFurtherTypesValue))[0];
        // enable/disable widgets
        let isAnyMarkingPersonal = this.getMarkingPersonal();
        for (let [value,id] of Object.entries(this.purposeResearchTypesValue)) { // need to loop over purposeTypesValue because of one entry that does not exist in purposeFurtherTypesValue
            let purpose = document.getElementById(id);
            let isTechnical = id.includes('technical'); // checking and enabling is done in this.setDataOnlineProcessing()
            let isCheckedFurther = false;
            if (value in this.purposeFurtherTypesValue) {
                let purposeFurther = document.getElementById(this.purposeFurtherTypesValue[value]);
                let isDisabledFurther = isNoPurposeFurther || isPurposeVisible && purpose.checked; // either 'no purpose' is selected or the respective purpose of the other question is checked
                isCheckedFurther = isDisabledFurther ? false : purposeFurther.checked;
                if (!isTechnical) {
                    purposeFurther.disabled = isDisabledFurther;
                    purposeFurther.checked = isCheckedFurther;
                }
            }
            let isDisabled = isNoPurpose || isCheckedFurther;
            let isChecked = isDisabled ? false: purpose.checked;
            if (!isTechnical) {
                purpose.disabled = isDisabled;
                purpose.checked = isChecked;
            }
            // relatable
            if (id==='relatable') {
                setElementVisibility(this.relatableDivTarget,isPurposeVisible && this.relatableTarget.checked);
            }
            let isTechnicalVisible = !isTechnical || this.dataOnlineValue==='ipTechnical' && this.dataOnlineProcessingValue!=='research'; // if technical and research, no further questions are asked
            setElementVisibility(value+'Div', isTechnicalVisible && (isPurposeVisible && isChecked || isNotOther && isCheckedFurther)); // div surrounding further questions for each purpose
            // removal of marking -> only for purpose research and only if list or code personal
            setElementVisibility(value+'markingRemoveDiv', isTechnicalVisible && isChecked && isAnyMarkingPersonal);
        }
        // set contact result -> only for purpose further
        this.contactResultFurtherTarget.disabled = this.purposeNoFurtherTarget.checked;
        setElementVisibility('contactResultDiv',this.contactResultFurtherTarget.checked);
        // set marking remove later sentence that is placed between the two text fields
        let text = this.laterEndValue[this.getMarkingName() || this.getMarkingSecondName() ? 0 : 1];
        for (let target of this.laterEndTargets) {
            target.textContent = text;
        }
        // set visibility of compensation code
        if (this.hasCompensationCodeTarget) {
            setElementVisibility(this.compensationCodeTarget,!isPurposeVisible || !this.purposeCompensationTarget.checked);
        }
        // set visibility of transfer and processing further
        this.setTransferProcessingFurther();
    }

    /** Sets the purpose technical target. */
    setPurposeTechnical() {
        let isTechnical = this.dataOnlineValue==='ipTechnical' && (this.dataOnlineProcessingValue==='linked' || this.dataOnlineProcessingValue==='research' && this.getAnyMarking());
        this.purposeTechnicalTarget.checked = isTechnical;
        this.purposeTechnicalTarget.disabled = !isTechnical;
    }

    /** Sets the visibility of the processing further widget as well as the hint that no personal data are collected. */
    setTransferProcessingFurther() {
        let isPersonal = this.markingValue!==this.markingOtherString && // 'other' would implicate that the document can not be created by the tool
            (this.personalValues.includes(this.dataPersonalValue) || // research data are/may be personal
            this.getMarkingPersonal() || // marking is personal
                this.getAnyMarking() && getSelected(Object.values(this.purposeResearchTypesValue))[0] || // marking has purposes besides research
                getSelected(Object.values(this.purposeFurtherTypesValue))[0]); // further purposes where personal data are collected
        setElementVisibility(this.processingFurtherTarget,isPersonal);
        this.setAccessWidgets()
        this.setNoDocumentHint();
    }

    /** Sets the access widgets. */
    setAccessWidgets() {
        let isDescription = false;
        let purposesSelected = this.personalValues.includes(this.dataPersonalValue) ? ['dataPersonal'] : [];
        if (this.getAnyMarking() && getSelected(Object.values(this.purposeResearchTypesValue))[0]) {
            for (let [value,id] of Object.entries(this.purposeResearchTypesValue)) {
                if (document.getElementById(id).checked) {
                    purposesSelected.push(value);
                }
            }
        }
        if (getSelected(Object.values(this.purposeFurtherTypesValue))[0]) {
            for (let [value,id] of Object.entries(this.purposeFurtherTypesValue)) {
                if (document.getElementById(id).checked && !purposesSelected.includes(value)) { // only add selected purpose once
                    purposesSelected.push(value);
                }
            }
        }
        for (let purpose of purposesSelected) {
            if (purpose!=='technical' || !(this.dataOnlineValue==='technical' && this.dataOnlineProcessingValue==='research')) {
                for (let accessType of this.accessOrderProcessingValue) {
                    let curType = purpose+accessType;
                    let knownPrefix = curType+'orderProcessingKnownknown';
                    if (document.getElementById(curType).checked && document.getElementById(curType+'orderProcessing0').checked && getSelected([knownPrefix+'Yes',knownPrefix+'Part'])[0]) {
                        isDescription = true;
                        break;
                    }
                }
            }
            if (isDescription) {
                break;
            }
        }
        setElementVisibility(this.orderProcessingDescriptionTarget,isDescription);
    }

    /** Sets the visibility of the hint that no personal data are collected. */
    setNoDocumentHint() {
        let anonymousString = 'anonymous';
        let internalValues = ['anonymous','marking'];
        setElementVisibility(this.noDocumentHintTarget,this.dataPersonalValue==='personalNo' && // research data are not personal
             (this.markingValue==='no' || // no marking
                this.markingValue===this.externalString && this.externalValue===anonymousString || // marking is external, but code and not personal
                this.markingValue===this.internalString && (this.internalValue===this.patternString && this.patternValue===anonymousString ||
                                                            this.internalValue===this.ownString && this.ownValue===anonymousString ||
                                                            this.internalValue===this.contributorsString && internalValues.includes(this.contributorsValue))) && // marking is internal and code is not personal
            (!this.getAnyMarking() || this.purposeNoTarget.checked && // no further purpose for the first marking
                (this.markingFurtherValue==='1' || // no second marking
                 this.markingFurtherValue==='0' &&
                    (this.markingSecondValue===this.externalString && this.externalSecondValue===anonymousString || // second marking is external, but code and not personal
                     this.markingSecondValue===this.internalString && (this.internalSecondValue===this.patternString && this.patternSecondValue===anonymousString ||
                                                                       this.internalSecondValue===this.ownString && this.ownSecondValue===anonymousString ||
                                                                       this.internalSecondValue===this.contributorsString && internalValues.includes(this.contributorsSecondValue))))) && // second marking is internal and code is not personal
            this.purposeNoFurtherTarget.checked); // no further purposes for which personal data are collected
    }

    /** Checks whether any marking is personal, i.e., by code with list.
     * @param checkGeneration if true, it is additionally checked whether marking is external by generation
     * @param checkName if true, it is additionally checked whether marking is by name
     * @returns {boolean} true if any marking is personal, false otherwise
     */
    getMarkingPersonal(checkGeneration = true, checkName = true) {
        return [checkName ? this.getMarkingName() : false,
            this.markingValue===this.externalString && (checkGeneration ? this.codeValues.includes(this.externalValue) : this.externalValue===this.listString),
            this.markingValue===this.internalString && (this.internalValue===this.patternString && (checkGeneration ? this.codeValues.includes(this.patternValue) : this.patternValue===this.listString) ||
                                                        this.internalValue===this.ownString && this.ownValue===this.listString ||
                                                        this.internalValue===this.contributorsString && (checkGeneration ? this.codeValues.includes(this.contributorsValue) : this.contributorsValue===this.listString))].includes(true) ||
            [checkName ? this.getMarkingSecondName() : false,
                this.getAnyMarking() && this.markingFurtherValue==='0' &&
                (this.markingSecondValue===this.externalString && (checkGeneration ? this.codeValues.includes(this.externalSecondValue) : this.externalSecondValue===this.listString) ||
                        this.markingSecondValue===this.internalString && (this.internalSecondValue===this.patternString && (checkGeneration ? this.codeValues.includes(this.patternSecondValue) : this.patternSecondValue===this.listString) ||
                        this.internalSecondValue===this.ownString && this.ownSecondValue===this.listString ||
                        this.internalSecondValue===this.contributorsString && (checkGeneration ? this.codeValues.includes(this.contributorsSecondValue) : this.contributorsSecondValue===this.listString)))].includes(true);
    }

    /** Returns whether marking is by name.
     * @returns {boolean} true if marking is by name, false otherwise
     */
    getMarkingName() {
        return this.markingValue===this.nameString;
    }

    /** Returns whether the second marking is by name.
     * @returns {boolean} true if the second marking is by name, false otherwise
     */
    getMarkingSecondName() {
        return this.getAnyMarking() && this.markingFurtherValue==='0' && this.markingSecondValue===this.nameString;
    }

    /** Checks whether the first marking is either external, internal, or by name.
     * @returns {boolean} true if a first marking is selected, false otherwise
     */
    getAnyMarking() {
        return this.markingValues.includes(this.markingValue);
    }
}