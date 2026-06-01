<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;

class DataSourceType extends TypeAbstract
{
    use ProjectdetailsTrait;

    private bool $isNotBegun; // true if for current committee type review process begun is possible

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dummyParams = $options[self::dummyParams];
        $this->isNotBegun = $dummyParams['isNotBegun'];
        $translationPrefix = 'projectdetails.pages.dataSource.';
        // origin
        $this->addRadioGroup($builder,self::originNode,self::originTypes,$translationPrefix.self::originNode.'.title');
        // origin sources
        $tempPrefix = $translationPrefix.self::originSourcesNode.'.';
        $this->addCheckboxGroup($builder,self::originSourcesTypes,$tempPrefix.'types.',$this->createPrefixArray(self::originSourcesTypes),array_combine(self::originSourcesTypes,array_fill(0,count(self::originSourcesTypes),$tempPrefix.'placeholder')));
        // votes
        $votesPrefix = $translationPrefix.self::dataSourceVotesNode.'.';
        $committeePrefix = $votesPrefix.self::dataSourceCommitteeNode.'.';
        $this->addBinaryRadio($builder,self::dataSourceVotesNode,$votesPrefix.'title');
        // committee
        $this->addRadioGroup($builder,self::dataSourceCommitteeNode,self::dataSourceCommitteeTypes,$committeePrefix.'title',textName: $this->appendText(self::dataSourceCommitteeNode));
        // result
        $this->addRadioGroup($builder,self::dataSourceResultNode,self::dataSourceResultTypes,$committeePrefix.self::dataSourceResultNode.'.title',self::dataSourceResultNode.self::descriptionCap);
        // further question for yes
        $tempPrefix = $committeePrefix.self::committeeResultPositiveNode.'.';
        $this->addCheckboxGroup($builder,self::committeeResultPositiveTypes,$tempPrefix.'types.',[$this->appendText(self::committeeResultPositiveOther)],[$tempPrefix.'placeholder']);
        // checkbox if negative
        $this->addFormElement($builder,self::committeeResultNegativeNode,'checkbox',$committeePrefix.self::committeeResultNegativeNode.'.confirm');
        // vote contributors
        $tempPrefix = $votesPrefix.self::voteContributorsNode.'.';
        $this->addBinaryRadio($builder,self::voteContributorsNode,$tempPrefix.'title',$this->isNotBegun ? self::voteContributorsNode.self::descriptionCap : '',$this->isNotBegun ? $tempPrefix.'hints.'.self::textHint : '');
        if ($this->isNotBegun) {
            $this->addFormElement($builder,self::voteContributorsConfirm,'checkbox',$tempPrefix.'confirm');
        }
        // data set
        $this->addFormElement($builder,self::dataSetNode,'textarea');
        if ($dummyParams['hasDocs']) {
            $this->addFormElement($builder,self::dataSetPDF,'checkbox',$translationPrefix.self::dataSetNode.'.pdf');
        }
        // data source procedure
        $this->addFormElement($builder,self::dataSourceProcedureNode,'textarea');
        // restriction
        $tempPrefix = $translationPrefix.self::restrictionNode.'.';
        $this->addRadioGroup($builder,self::restrictionNode,self::restrictionTypes,$tempPrefix.'title',self::restrictionNode.self::descriptionCap,$tempPrefix.'hints.'.self::textHint);
        // data source access
        $this->addCheckboxGroup($builder,self::dataSourceAccessTypes,$translationPrefix.self::dataSourceAccessNode.'.types.');
        // legitimization
        $tempPrefix = $translationPrefix.self::legitimizationNode.'.';
        $this->addCheckboxGroup($builder,self::legitimizationTypes,$tempPrefix.'types.');
        foreach (self::legitimizationOtherTypes as $other) {
            $this->addFormElement($builder,$this->appendText($other),'textarea',hint: $tempPrefix.self::textHintPlural.'.'.$other); // use suffix 'Text' so that get/setSelectedCheckboxes can be used
        }
        // data source identification
        $this->addRadioGroup($builder,self::dataSourceIdentificationNode,self::dataSourceIdentificationTypes,$translationPrefix.self::dataSourceIdentificationNode.'.title');
        // publication
        $this->addRadioGroup($builder,self::publicationNode,self::publicationTypes,$translationPrefix.self::publicationNode.'.title');
        // data source burdens risks and burdens risks contributors
        foreach (self::dataSourceBurdensRisksNodes as $type) {
            $tempPrefix = $translationPrefix.$type.'.';
            $this->addBinaryRadio($builder,$type,$tempPrefix.'title',$type.self::descriptionCap,$tempPrefix.self::textHint);
        }
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        $tempArray = $viewData[self::originNode];
        $forms[self::originNode]->setData($tempArray[self::chosen]);
        $this->setSelectedCheckboxes($forms,$tempArray[self::originSourcesNode] ?? '',$this->combinePrefixArray(self::originSourcesTypes));
        if (array_key_exists(self::dataSourceVotesNode,$viewData)) {
            // votes
            $votesArray = $viewData[self::dataSourceVotesNode];
            $forms[self::dataSourceVotesNode]->setData($votesArray[self::chosen]);
            if (array_key_exists(self::dataSourceCommitteeNode,$votesArray)) {
                // committee
                $tempArray = $votesArray[self::dataSourceCommitteeNode];
                $forms[self::dataSourceCommitteeNode]->setData($tempArray[self::chosen]);
                $forms[$this->appendText(self::dataSourceCommitteeNode)]->setData($this->getArrayValue($tempArray,self::descriptionNode));
                // result
                $tempArray = $votesArray[self::dataSourceResultNode];
                $forms[self::dataSourceResultNode]->setData($tempArray[self::chosen]);
                $this->setSelectedCheckboxes($forms,$tempArray[self::committeeResultPositiveNode] ?? '',$this->createPrefixArray(self::committeeResultPositiveOther)); // further question for positive vote
                $forms[self::dataSourceResultNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode)); // description for negative or no vote
                // checkbox if negative vote
                $forms[self::committeeResultNegativeNode]->setData(($tempArray[self::committeeResultNegativeNode] ?? '')==='1');
            } elseif (array_key_exists(self::voteContributorsNode,$votesArray)) {
                // vote contributors
                $tempArray = $votesArray[self::voteContributorsNode];
                $forms[self::voteContributorsNode]->setData($tempArray[self::chosen]);
                if (array_key_exists(self::voteContributorsConfirm,$forms)) {
                    $forms[self::voteContributorsNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
                    $forms[self::voteContributorsConfirm]->setData($this->getArrayValue($tempArray,self::voteContributorsConfirm)==='1');
                }
            }
            // data set
            if (array_key_exists(self::dataSetNode,$viewData)) {
                $forms[self::dataSetNode]->setData($viewData[self::dataSetNode]);
                $pdf = self::dataSetPDF;
                if (array_key_exists($pdf,$forms)) {
                    $forms[$pdf]->setData(array_key_exists($pdf,$viewData));
                }
            }
        }
        if (array_key_exists(self::dataSourceProcedureNode,$viewData)) { // if dataSourceProcedure exists, all further keys exist, too
            // data source procedure
            $forms[self::dataSourceProcedureNode]->setData($viewData[self::dataSourceProcedureNode]);
            // restriction
            $tempArray = $viewData[self::restrictionNode];
            $forms[self::restrictionNode]->setData($tempArray[self::chosen]);
            $forms[self::restrictionNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
            // data source access
            $this->setSelectedCheckboxes($forms,$viewData[self::dataSourceAccessNode]);
            // legitimization
            $this->setSelectedCheckboxes($forms,$viewData[self::legitimizationNode],$this->createPrefixArray(self::legitimizationOtherTypes));
            // data source identification
            $forms[self::dataSourceIdentificationNode]->setData($viewData[self::dataSourceIdentificationNode]);
            // publication
            $forms[self::publicationNode]->setData($viewData[self::publicationNode]);
            // data source burdens risks and burdens risks contributors
            foreach (self::dataSourceBurdensRisksNodes as $type) {
                $tempArray = $viewData[$type];
                $forms[$type]->setData($tempArray[self::chosen]);
                $forms[$type.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
            }
        }
    }

    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        $origin = $forms[self::originNode]->getData();
        $tempArray = [self::chosen => $origin];
        $originSources = [];
        $hasFurtherQuestions = false; // gets true if further questions after the votes questions are asked
        if ($origin===self::originExisting) {
            $originSources = $this->getSelectedCheckboxes($forms,self::originSourcesTypes,$this->combinePrefixArray(self::originSourcesTypes));
            $tempArray[self::originSourcesNode] = $originSources;
            $hasFurtherQuestions = true;
        }
        $newData = [self::originNode => $tempArray];
        $hasDataSet = false; // gets true if data set question is asked

        if (array_key_exists('research',$originSources)) {
            // votes
            $chosen = $forms[self::dataSourceVotesNode]->getData();
            $votesArray = [self::chosen => $chosen];
            if ($chosen===0) {
                // committee
                $tempVal = $forms[self::dataSourceCommitteeNode]->getData();
                $tempArray = [self::chosen => $tempVal];
                if ($tempVal!=='') {
                    $tempArray[self::descriptionNode] = $forms[$this->appendText(self::dataSourceCommitteeNode)]->getData();
                }
                $votesArray[self::dataSourceCommitteeNode] = $tempArray;
                // result
                $tempVal = $forms[self::dataSourceResultNode]->getData();
                $tempArray = [self::chosen => $tempVal];
                if ($tempVal===self::dataSourceResultPositive) { // further question for positive vote
                    $tempArray[self::committeeResultPositiveNode] = $this->getSelectedCheckboxes($forms,self::committeeResultPositiveTypes,$this->createPrefixArray(self::committeeResultPositiveOther));
                } elseif (in_array($tempVal,[self::dataSourceResultNegative,self::dataSourceResultNoVote])) { // description for negative or no vote
                    $tempArray[self::descriptionNode] = $forms[self::dataSourceResultNode.self::descriptionCap]->getData();
                }
                // checkbox if negative vote
                if ($tempVal===self::dataSourceResultNegative) {
                    $hasDataSet = true;
                    $tempVal = $forms[self::committeeResultNegativeNode]->getData();
                    $tempArray[self::committeeResultNegativeNode] = $tempVal;
                    $hasFurtherQuestions = $tempVal;
                }
                $votesArray[self::dataSourceResultNode] = $tempArray;
            } elseif ($chosen===1) {
                // vote contributors
                $chosen = $forms[self::voteContributorsNode]->getData();
                $tempArray = [self::chosen => $chosen];
                $isContributors = $chosen===0;
                $hasDataSet = $isContributors && $this->isNotBegun || $chosen===1;
                $hasFurtherQuestions = !($isContributors && !$this->isNotBegun);
                if ($isContributors && array_key_exists(self::voteContributorsConfirm,$forms)) { // description and confirm
                    $tempArray[self::descriptionNode] = $forms[self::voteContributorsNode.self::descriptionCap]->getData();
                    $tempArray[self::voteContributorsConfirm] = $forms[self::voteContributorsConfirm]->getData();
                }
                $votesArray[self::voteContributorsNode] = $tempArray;
            }
            $newData[self::dataSourceVotesNode] = $votesArray;
        }
        if ($hasFurtherQuestions) {
            // data set
            if ($hasDataSet) {
                $newData[self::dataSetNode] = $forms[self::dataSetNode]->getData();
                $pdf = self::dataSetPDF;
                if (array_key_exists($pdf,$forms) && $forms[$pdf]->getData()) {
                    $newData[$pdf] = '';
                }
            }
            // data source procedure
            $newData[self::dataSourceProcedureNode] = $forms[self::dataSourceProcedureNode]->getData();
            // restriction
            $tempVal = $forms[self::restrictionNode]->getData();
            $tempArray = [self::chosen => $tempVal];
            if ($tempVal===self::restrictionRestricted) {
                $tempArray[self::descriptionNode] = $forms[self::restrictionNode.self::descriptionCap]->getData();
            }
            $newData[self::restrictionNode] = $tempArray;
            // data source access
            $newData[self::dataSourceAccessNode] = $this->getSelectedCheckboxes($forms,self::dataSourceAccessTypes);
            // legitimization
            $newData[self::legitimizationNode] = $this->getSelectedCheckboxes($forms,self::legitimizationTypes,$this->createPrefixArray(self::legitimizationOtherTypes));
            // data source identification
            $newData[self::dataSourceIdentificationNode] = $forms[self::dataSourceIdentificationNode]->getData();
            // publication
            $newData[self::publicationNode] = $forms[self::publicationNode]->getData();
            // data source burdens risks and burdens risks contributors
            foreach (self::dataSourceBurdensRisksNodes as $type) {
                $tempVal = $forms[$type]->getData();
                $tempArray = [self::chosen => $tempVal];
                if ($tempVal===0) {
                    $tempArray[self::descriptionNode] = $forms[$type.self::descriptionCap]->getData();
                }
                $newData[$type] = $tempArray;
            }
        }
        $viewData = $newData;
    }
}