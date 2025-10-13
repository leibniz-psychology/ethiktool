<?php

namespace App\Form\AppData;

use App\Abstract\TypeAbstract;
use App\Traits\AppData\AppDataTrait;
use DateTime;
use Exception;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class CoreDataType extends TypeAbstract
{
    use AppDataTrait;

    private string $committeeType;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'coreData.';
        $this->committeeType = $options[self::committeeType];
        $isEUB = $this->committeeType===self::committeeEUB;
        $this->addFormElement($builder, self::projectTitle, 'textarea', $translationPrefix.'projectTitle');
        $this->addRadioGroup($builder,self::projectTitleParticipation,self::projectTitleTypes,$translationPrefix.self::projectTitleParticipation.'.title',self::projectTitleParticipation.self::descriptionCap);
        $appTypePrefix = $translationPrefix.'appType.';
        $this->addRadioGroup($builder,self::applicationType,self::applicationTypes,$appTypePrefix.'title');
        if (in_array($this->committeeType,[self::committeeTUC,'testCommittee'])) {
            $tempPrefix = $appTypePrefix.'new.';
            $this->addRadioGroup($builder,self::applicationNewType,$this->translateArray($tempPrefix,[self::appTypeShort,'main']),$tempPrefix.'label');
        }
        $this->addFormElement($builder,self::descriptionNode,'text',$appTypePrefix.'reference');
        // applicant info
        $tempPrefix = 'multiple.position.';
        $dummyParams = $options[self::dummyParams];
        foreach (array_merge([''],in_array($this->committeeType,self::committeeSupervisor) ? [self::supervisor] : []) as $applicant) {
            foreach (self::applicantContributorsInfosTypes as $info) {
                if ($info!==self::position) {
                    $this->addFormElement($builder, $info.$applicant, 'text');
                }
                else {
                    $this->addFormElement($builder, self::position.$applicant, 'choice',options: ['choices' => array_flip($dummyParams[$applicant===self::supervisor ? self::supervisor : self::applicant])],hint: self::choiceTextHint);
                    $this->addFormElement($builder, $this->appendText(self::position.$applicant), 'text',hint: $tempPrefix.'otherDefault');
                }
            }
        }
        if ($isEUB || $this->committeeType===self::committeeDLR) {
            $startPrefix = $translationPrefix.'project.start.';
            // project start
            $this->addCheckboxTextfield($builder,self::projectStartBegun,$startPrefix.'begun',$startPrefix.self::textHint);
        }
        if ($isEUB) {
            // qualification
            $this->addBinaryRadio($builder,self::qualification,$translationPrefix.self::qualification.'.title',);
            // guidelines
            $this->addCheckboxTextfield($builder,self::guidelinesNode, $translationPrefix.self::guidelinesNode.'.choice');
        }
        // project dates
        $this->addFormElement($builder, self::projectStart, 'date');
        $this->addFormElement($builder, self::projectStartNext, 'checkbox', $translationPrefix.'project.next');
        $this->addFormElement($builder, self::projectEnd, 'date');
        // funding
        $tempPrefix = $translationPrefix.'funding.'.self::textHintPlural.'.';
        $fundingStateChoices = $this->translateArray($translationPrefix.'funding.fundingState.',['granted','requested']);
        foreach (self::fundingTypes as $key => $value) {
            $this->addFormElement($builder,$key,'checkbox',$value);
            if ($key!==self::fundingQuali) {
                $this->addFormElement($builder,$this->appendText($key),'textarea',hint: $tempPrefix.$key);
            }
            if (in_array($key,self::fundingResearchExternal)) {
                $this->addRadioGroup($builder,$key.'FundingState',$fundingStateChoices);
            }
        }
        // conflict
        $this->addBinaryRadio($builder,self::conflictNode,$translationPrefix.'conflict.'.'title',self::conflictNode.self::descriptionCap);
        $this->addFormElement($builder,self::participantDescription,'textarea');
        // support
        foreach (array_diff_key(self::supportTypes,!$isEUB ? [self::supportCenter => ''] : []) as $key => $value) {
            $this->addFormElement($builder,$key,'checkbox',$value);
            if ($key!==self::noSupport) {
                $this->addFormElement($builder,$this->appendText($key),'textarea',hint: $translationPrefix.'support.'.self::textHint);
            }
        }
        // dummy
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        $forms[self::projectTitle]->setData($viewData[self::projectTitle]);
        $tempArray = $viewData[self::projectTitleParticipation];
        $forms[self::projectTitleParticipation]->setData($tempArray[self::chosen]);
        $forms[self::projectTitleParticipation.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        $tempArray = $viewData[self::applicationType];
        $type = $tempArray[self::chosen];
        $forms[self::applicationType]->setData($type);
        if (array_key_exists(self::descriptionNode,$tempArray)) {
            $forms[$type===self::appNew ? self::applicationNewType : self::descriptionNode]->setData($tempArray[self::descriptionNode]);
        }
        if (array_key_exists(self::qualification,$forms)) {
            $forms[self::qualification]->setData($viewData[self::qualification]);
        }
        // applicant infos
        foreach (array_merge([self::applicant],array_key_exists(self::supervisor,$viewData) ? [self::supervisor] : []) as $type) {
            $tempArray = $viewData[$type];
            $suffix = $type===self::supervisor ? self::supervisor : '';
            foreach (self::applicantContributorsInfosTypes as $info) {
                $forms[$info.$suffix]->setData($tempArray[$info]);
            }
            // position
            $tempVal = $tempArray[self::position];
            if ($tempVal!=='') {
                $tempBool = array_key_exists($tempVal,self::positionsTypes);
                $forms[self::position.$suffix]->setData($tempBool ? $tempVal : self::positionOther);
                $forms[$this->appendText(self::position.$suffix)]->setData($tempBool ? '' : $tempVal);
            }
        }
        // project dates
        try {
            $tempArray = $viewData[self::projectStart];
            $tempVal = $tempArray[self::chosen];
            $isBegun = array_key_exists(self::descriptionNode,$tempArray);
            $tempBool = $tempVal==='0' && !$isBegun; // true if projectStartNext is selected
            $forms[self::projectStartNext]->setData($tempBool);
            $forms[self::projectStart]->setData(!($tempBool || $isBegun) && $tempVal!=='' ? new DateTime($tempVal, $this->getTimezone()) : null);
            if (array_key_exists(self::projectStartBegun,$forms)) {
                $forms[self::projectStartBegun]->setData($isBegun);
                $forms[$this->appendText(self::projectStartBegun)]->setData($this->getArrayValue($tempArray,self::descriptionNode));
            }
            $tempVal = $viewData[self::projectEnd];
            $forms[self::projectEnd]->setData($tempVal!=='' ? new DateTime($viewData[self::projectEnd]) : null);
        }
        catch (Exception $e) {
            // do not set dates if exception occurs
        }
        // funding
        foreach ($viewData[self::funding] ?: [] as $key => $source) {
            $forms[$key]->setData(true);
            if ($key!==self::fundingQuali) {
                $forms[$this->appendText($key)]->setData($source[self::descriptionNode]);
            }
            if (array_key_exists($key.'FundingState',$forms)) {
                $forms[$key.'FundingState']->setData($source[self::fundingStateNode]);
            }
        }
        // conflict
        $tempArray = $viewData[self::conflictNode];
        $forms[self::conflictNode]->setData($tempArray[self::chosen]);
        $forms[self::conflictNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        $forms[self::participantDescription]->setData($this->getArrayValue($tempArray,self::participantDescription));
        // support
        foreach ($viewData[self::supportNode] ?: [] as $key => $value) {
            $forms[$key]->setData(true);
            if ($key!==self::noSupport) {
                $forms[$this->appendText($key)]->setData($value);
            }
        }
        // guidelines
        if (array_key_exists(self::guidelinesNode,$forms)) {
            $tempArray = $viewData[self::guidelinesNode];
            $isSelected = $tempArray!=='';
            $forms[self::guidelinesNode]->setData($isSelected);
            $forms[$this->appendText(self::guidelinesNode)]->setData($tempArray[self::descriptionNode] ?? ''); // if nothing is chosen yes, tempArray is a string
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        $newData = [self::projectTitle => $forms[self::projectTitle]->getData()];
        $tempVal = $forms[self::projectTitleParticipation]->getData();
        $tempArray = [self::chosen => $tempVal];
        if ($tempVal===self::projectTitleDifferent) {
            $tempArray[self::descriptionNode] = $forms[self::projectTitleParticipation.self::descriptionCap]->getData();
        }
        $newData[self::projectTitleParticipation] = $tempArray;
        $tempVal = $forms[self::applicationType]->getData();
        $tempArray = [self::chosen => $tempVal];
        $isNewType = $tempVal===self::appNew && array_key_exists(self::applicationNewType,$forms);
        if ($isNewType || $tempVal===self::appExtended || $tempVal===self::appResubmission) {
            $tempArray[self::descriptionNode] = $forms[$isNewType ? self::applicationNewType : self::descriptionNode]->getData();
        }
        $newData[self::applicationType] = $tempArray;
        $isQualification = array_key_exists(self::qualification,$forms); // true if question exists
        $qualification = $isQualification ? $forms[self::qualification]->getData() : '';
        if ($isQualification) {
            $newData[self::qualification] = $qualification;
        }
        $isQualification = $isQualification && $qualification===0; // true if question exists and was answered with yes
        // applicant info
        $position = $forms[self::position]->getData();
        $isEUB = $this->committeeType===self::committeeEUB;
        foreach (array_merge([self::applicant],$isEUB && in_array($position,[self::positionsStudent,self::positionsPhd]) && $isQualification || !$isEUB && $position===self::positionsStudent && in_array($this->committeeType,self::committeeSupervisor) ? [self::supervisor] : []) as $type) {
            $tempArray = [];
            $suffix = $type===self::supervisor ? self::supervisor : '';
            foreach (self::applicantContributorsInfosTypes as $info) {
                $tempArray[$info] = $forms[$info.$suffix]->getData();
            }
            // position
            $position = $forms[self::position.$suffix]->getData();
            if ($type===self::applicant && $isQualification && !in_array($position,[self::positionsStudent,self::positionsPhd])) { // reset position if qualification question has changed to yes
                $position = '';
            }
            elseif ($position===self::positionOther) {
                $otherPosition = $forms[$this->appendText(self::position.$suffix)]->getData();
                $position = $otherPosition ?: self::positionOther;
            }
            $tempArray[self::position] = $position;
            $newData[$type] = $tempArray;
        }
        // project dates
        $isBegun = $this->getFormData($forms,self::projectStartBegun,false);
        $tempArray = [self::chosen => (!$forms[self::projectStartNext]->getData() && !$isBegun) ? $this->getDate($forms[self::projectStart]->getData()) : '0'];
        if ($isBegun) {
            $tempArray[self::descriptionNode] = $forms[$this->appendText(self::projectStartBegun)]->getData();
        }
        $newData[self::projectStart] = $tempArray;
        $newData[self::projectEnd] = $this->getDate($forms[self::projectEnd]->getData());
        // funding
        $fundingArray = [];
        foreach (self::fundingTypes as $key => $value) {
            if ($forms[$key]->getData()) { // source was selected
                $isFundingQuali = $key===self::fundingQuali;
                $fundingArray[$key] = !$isFundingQuali ? [self::descriptionNode => $forms[$this->appendText($key)]->getData()] : ''; // text in the text field
                if ($isFundingQuali) { // if fundingQuali is checked immediately before/after any of the other checkboxes is checked (i.e., the second of these two is checked before the page was reloaded after submission), keep only the fundingQuali key
                    break;
                }
                elseif (array_key_exists($key.'FundingState',$forms)) {
                    $fundingArray[$key][self::fundingStateNode] = $forms[$key.'FundingState']->getData(); // granted or requested
                }
            }
        }
        $newData[self::funding] = $fundingArray ?: '';
        // conflict
        $chosen = $forms[self::conflictNode]->getData();
        $tempVal = $chosen===0;
        $tempArray = [self::chosen => $chosen];
        if ($tempVal || $chosen===1 && array_intersect(array_keys($fundingArray),self::fundingResearchExternal)!==[]) {
            $tempArray[self::descriptionNode] = $forms[self::conflictNode.self::descriptionCap]->getData();
            if ($tempVal) {
                $tempArray[self::participantDescription] = $forms[self::participantDescription]->getData();
            }
        }
        $newData[self::conflictNode] = $tempArray;
        // support
        $tempArray = [];
        foreach (array_keys(self::supportTypes) as $support) {
            if (array_key_exists($support,$forms) && $forms[$support]->getData()) { // support type exists and was selected
                $isNoSupport = $support===self::noSupport;
                $tempArray[$support] = !$isNoSupport ?  $forms[$this->appendText($support)]->getData() : '';
                if ($isNoSupport) { // if noSupport is checked immediately before/after any of the other checkboxes is checked (i.e., the second of these two is checked before the page was reloaded after submission), keep only the noSupport key
                    break;
                }
            }
        }
        $newData[self::supportNode] = $tempArray;
        // guidelines
        if (array_key_exists(self::guidelinesNode,$forms)) { // guidelines question exists
            $newData[self::guidelinesNode] = $forms[self::guidelinesNode]->getData() ? [self::descriptionNode => $forms[$this->appendText(self::guidelinesNode)]->getData()] : '';
        }
        $viewData = $newData;
    }

    /** Returns a date as a string of an empty string if no date was provided.
     * @param DateTime|null $dateTime date that will be converted to a string
     * @return string the date as a string or an empty string if $dateTime is null
     */
    private function getDate(?DateTime $dateTime): string {
        return !is_null($dateTime) ? $dateTime->format('Y-m-d') : '';
    }
}