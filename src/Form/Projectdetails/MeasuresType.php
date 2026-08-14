<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class MeasuresType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $translationPrefix = 'projectdetails.pages.measures.';
        // procedure
        $this->addFormElement($builder,self::procedureNode,'textarea',$translationPrefix.self::procedureNode.'.title');
        $measuresInterventionsPrefix = $translationPrefix.'measuresInterventions.';
        $placeholderPrefix = $measuresInterventionsPrefix.'placeholder.';
        $documentationPrefix = $measuresInterventionsPrefix.self::measuresNode.'.'.self::measuresDocumentation.'.';
        // measures and interventions
        foreach ([self::measuresNode,self::interventionsNode] as $type) {
            $tempPrefix = $measuresInterventionsPrefix.$type.'.';
            $otherTypes = self::measuresInterventionsOther[$type];
            $otherPlaceholder = array_fill_keys($otherTypes,$placeholderPrefix.'noContent');
            if ($type===self::measuresNode) {
                $otherPlaceholder = array_replace($otherPlaceholder,array_fill_keys(self::interviewQuestionnaire,$placeholderPrefix.'content'));
                foreach (self::measuresDocumentationTypes as $measure => $selectables) { // documentation
                    $this->addCheckboxGroup($builder,$selectables,$documentationPrefix.'types.',$this->appendText($measure.'documentationOther'),$documentationPrefix.'placeholder',labelNames: str_replace($measure,'',$selectables));
                }
                $this->addRadioGroup($builder,self::surveyConductNode,self::surveyConductTypes,$tempPrefix.self::surveyConductNode.'.title'); // conduct of survey
                $this->addBinaryRadio($builder,self::screeningNode,$tempPrefix.self::screeningNode); // screening
                $this->addBinaryRadio($builder,self::geneNode,$tempPrefix.self::geneNode); // gene
            }
            $this->addCheckboxGroup($builder,self::measuresInterventionsTypes[$type],$tempPrefix.'types.',$this->createPrefixArray($otherTypes),$otherPlaceholder,textareaName: $type.self::descriptionCap); // all selectable options
            $this->addFormElement($builder,$type.'PDF','checkbox',$measuresInterventionsPrefix.'pdf',[self::labelParams => ['type' => $type]]);
        }
        // other sources
        $tempPrefix = $translationPrefix.self::otherSourcesNode.'.';
        $this->addBinaryRadio($builder,self::otherSourcesNode,$tempPrefix.'title',self::otherSourcesNode.self::descriptionCap,$tempPrefix.self::textHint);
        $this->addFormElement($builder,self::otherSourcesPDF,'checkbox',$tempPrefix.'pdf.text');
        // loan
        $tempPrefix = $translationPrefix.self::loanNode.'.';
        $this->addBinaryRadio($builder,self::loanNode,$tempPrefix.'title');
        $this->addRadioGroup($builder,self::loanReceipt,self::templateTypes,$tempPrefix.'receipt',$this->appendText(self::loanReceipt));
        // location
        if ($options[self::dummyParams]['hasLocation']) { // location may not be asked even if the review process says so
            $this->addRadioGroup($builder,self::locationNode,$this->translateArray($translationPrefix.'location.types.',self::locationTypes),textareaName: self::locationNode.self::descriptionCap);
        }
        // presence
        $tempPrefix = $translationPrefix.self::presenceNode.'.';
        $this->addRadioGroup($builder,self::presenceNode,self::presenceTypes,$tempPrefix.'title',self::presenceNode.self::descriptionCap,$tempPrefix.self::textHint);
        // durations
        foreach (self::durationTypes as $duration) {
            $this->addFormElement($builder,$duration,'spinner',options: $this->setMinMax(0,self::durationMax[$duration]));
        }
        $this->addFormElement($builder,$this->appendText(self::durationMeasureTimeDays),'textarea',hint: $translationPrefix.self::durationNode.'.measureTime.hintDays');
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        // procedure
        if (array_key_exists(self::procedureNode,$forms)) {
            $forms[self::procedureNode]->setData($viewData[self::procedureNode]);
        }
        // measures and interventions
        foreach ([self::measuresNode,self::interventionsNode] as $type) {
            $this->setSelectedCheckboxesMultiLevel($forms,$viewData[$type]);
            $tempVal = $type.self::descriptionCap;
            if (array_key_exists($tempVal,$forms)) {
                $forms[$tempVal]->setData($this->getArrayValue($viewData,$tempVal));
                $tempVal = $type.'PDF';
                $forms[$tempVal]->setData(array_key_exists($tempVal,$viewData));
            }
        }
        if (array_key_exists(self::measuresFurtherNode,$viewData)) {
            $measuresFurther = $viewData[self::measuresFurtherNode];
            foreach (array_keys(self::measuresDocumentationTypes) as $documentation) { // documentation
                if (array_key_exists($documentation,$measuresFurther)) {
                    $other = $documentation.self::documentationOther;
                    $this->setSelectedCheckboxes($forms,$measuresFurther[$documentation],[$other => $this->appendText($other)]);
                }
            }
            foreach ([self::surveyConductNode,self::screeningNode,self::geneNode] as $type) { // survey conduct, screening, and gene
                $forms[$type]->setData($this->getArrayValue($measuresFurther,$type));
            }
        }
        // other sources
        $this->setChosenArray($forms,$viewData,self::otherSourcesNode,[self::otherSourcesNode.self::descriptionCap],false);
        $forms[self::otherSourcesPDF]->setData(array_key_exists(self::otherSourcesPDF,$viewData[self::otherSourcesNode]));
        // loan
        if (array_key_exists(self::loanNode,$forms)) {
            $tempArray = $viewData[self::loanNode];
            $forms[self::loanNode]->setData($tempArray[self::chosen]);
            $this->setChosenArray($forms,$tempArray,self::loanReceipt,$this->createPrefixArray(self::loanReceipt));
        }
        // location
        $this->setChosenArray($forms,$viewData,self::locationNode,[self::descriptionNode => self::locationNode.self::descriptionCap]);
        // presence
        if (array_key_exists(self::presenceNode,$forms)) {
            $this->setChosenArray($forms,$viewData,self::presenceNode,[self::descriptionNode => self::presenceNode.self::descriptionCap]);
        }
        // durations
        $tempArray = $viewData[self::durationNode];
        $this->setSpinner($forms,$tempArray,self::durationTypes);
        $forms[$this->appendText(self::durationMeasureTimeDays)]->setData($this->getArrayValue($tempArray,self::descriptionNode));
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        $newData = [];
        // procedure
        if (array_key_exists(self::procedureNode,$forms)) {
            $newData[self::procedureNode] = $forms[self::procedureNode]->getData();
        }
        // measures
        $measures = $this->getSelectedCheckboxesMultiLevel($forms,self::measuresInterventionsTypesAll[self::measuresNode],self::measuresInterventionsOther[self::measuresNode]);
        $newData[self::measuresNode] = $measures;
        $tempArray = [];
        foreach (self::measuresDocumentationTypes as $documentation => $options) { // documentation
            if (array_key_exists($documentation,$measures)) {
                $other = $documentation.self::documentationOther;
                $tempArray[$documentation] = $this->getSelectedCheckboxes($forms,$options,[$other => $this->appendText($other)]);
            }
        }
        if (array_key_exists(self::measuresQuestionnaire,$measures)) { // survey conduct and screening
            $tempArray[self::surveyConductNode] = $forms[self::surveyConductNode]->getData();
            $tempArray[self::screeningNode] = $forms[self::screeningNode]->getData();
        }
        if (count(array_diff_key(self::measuresBodyTypes,$measures['measuresBody'] ?? []))<count(self::measuresBodyTypes)) { // gene
            $tempArray[self::geneNode] = $forms[self::geneNode]->getData();
        }
        if ($tempArray!==[]) {
            $newData[self::measuresFurtherNode] = $tempArray;
        }
        if (array_key_exists(self::measuresDescription,$forms)) {
            $newData[self::measuresDescription] = $measures!==[] ? $forms[self::measuresDescription]->getData() : ''; // description
            if ($forms[self::measuresPDF]->getData()) {
                $newData[self::measuresPDF] = '';
            }
        }
        // interventions
        $interventions = $this->getSelectedCheckboxesMultiLevel($forms,self::measuresInterventionsTypesAll[self::interventionsNode],self::measuresInterventionsOther[self::interventionsNode]);
        $newData[self::interventionsNode] = $interventions;
        if (array_key_exists(self::interventionsDescription,$forms)) {
            $numSelected = count($interventions); // not necessarily the real number of selected elements because sub-categories may be selected
            $tempVal = $numSelected>0 && !array_key_exists(self::noIntervention,$interventions);
            if ($tempVal && ($numSelected-count(array_intersect_key(['interventionsQuestionnaire' => '', 'invasiveExtract' => ''],$interventions)))>0) {
                $newData[self::interventionsDescription] = $forms[self::interventionsDescription]->getData();
            }
            if ($tempVal && $forms[self::interventionsPDF]->getData()) {
                $newData[self::interventionsPDF] = '';
            }
        }
        // other sources
        $tempArray = $this->getChosenArray($forms,self::otherSourcesNode,0,[self::otherSourcesNode.self::descriptionCap],false);
        if ($tempArray[self::chosen]===0 && $forms[self::otherSourcesPDF]->getData()) {
            $tempArray[self::otherSourcesPDF] = '';
        }
        $newData[self::otherSourcesNode] = $tempArray;
        // loan
        if (array_key_exists(self::loanNode,$forms)) {
            $tempVal = $forms[self::loanNode]->getData();
            $tempArray = [self::chosen => $tempVal];
            if ($tempVal===0) {
                $tempArray[self::loanReceipt] = $this->getChosenArray($forms,self::loanReceipt,self::templateText,$this->createAppendArray(self::loanReceipt));
            }
            $newData[self::loanNode] = $tempArray;
        }
        // location
        if (array_key_exists(self::locationNode,$forms)) {
            $newData[self::locationNode] = $this->getChosenArray($forms,self::locationNode,self::locationTypes,[self::descriptionNode => self::locationNode.self::descriptionCap]);
        }
        // presence
        if (array_key_exists(self::presenceNode,$forms)) {
            $newData[self::presenceNode] = $this->getChosenArray($forms,self::presenceNode,self::presencePartly,[self::descriptionNode => self::presenceNode.self::descriptionCap]);
        }
        // durations
        $tempArray = [];
        $days = $forms[self::durationMeasureTimeDays]->getData();
        if ($days>0) {
            $tempArray = [self::durationMeasureTimeDays => floor($days), self::descriptionNode => $forms[$this->appendText(self::durationMeasureTimeDays)]->getData()];
        } else {
            foreach (self::durationTypes as $duration) {
                $curDur = $forms[$duration]->getData();
                $tempArray[$duration] = $curDur!==null ? floor($curDur) : null; // avoid decimals
            }
        }
        $newData[self::durationNode] = $tempArray;
        $viewData = $newData;
    }
}
