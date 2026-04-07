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
        $tempPrefix = $translationPrefix.self::procedureNode.'.';
        $this->addFormElement($builder,self::procedureNode,'textarea',$tempPrefix.'title');
        $measuresInterventionsPrefix = $translationPrefix.'measuresInterventions.';
        // measures and interventions
        foreach ([self::measuresNode,self::interventionsNode] as $type) {
            $tempPrefix = $measuresInterventionsPrefix.$type.'.';
            $otherTypes = self::measuresInterventionsOther[$type];
            $this->addCheckboxGroup($builder, $type===self::measuresNode ? self::measuresTypes : self::interventionsTypes,$tempPrefix.'types.',$this->createPrefixArray($otherTypes),array_fill_keys($otherTypes,$tempPrefix.self::descriptionNode),textareaName: $type.self::descriptionCap);
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
            $otherTypes = self::measuresInterventionsOther[$type];
            $this->setSelectedCheckboxes($forms,$viewData[$type],array_combine($otherTypes,$this->createPrefixArray($otherTypes)));
            $tempVal = $type.self::descriptionCap;
            if (array_key_exists($tempVal,$forms)) {
                $forms[$tempVal]->setData($this->getArrayValue($viewData,$tempVal)); // survey start is added in stimulus controller
                $tempVal = $type.'PDF';
                $forms[$tempVal]->setData(array_key_exists($tempVal,$viewData));
            }
        }
        // other sources
        $this->setChosenArray($forms,$viewData,self::otherSourcesNode,[self::otherSourcesNode.self::descriptionCap],false);
        $forms[self::otherSourcesPDF]->setData(array_key_exists(self::otherSourcesPDF,$viewData[self::otherSourcesNode]));
        // loan
        if (array_key_exists(self::loanNode,$forms)) {
            $tempArray = $viewData[self::loanNode];
            $forms[self::loanNode]->setData($tempArray[self::chosen]);
            $this->setChosenArray($forms,$tempArray,self::loanReceipt,[self::descriptionNode => $this->appendText(self::loanReceipt)]);
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
        $otherTypes = self::measuresInterventionsOther[self::measuresNode];
        $measures = $this->getSelectedCheckboxes($forms,self::measuresTypes,array_combine($otherTypes,$this->createPrefixArray($otherTypes)));
        $newData[self::measuresNode] = $measures;
        if (array_key_exists(self::measuresDescription,$forms)) {
            $newData[self::measuresDescription] = $measures!==[] ? $forms[self::measuresDescription]->getData() : '';
            if ($forms[self::measuresPDF]->getData()) {
                $newData[self::measuresPDF] = '';
            }
        }
        // interventions
        $otherTypes = self::measuresInterventionsOther[self::interventionsNode];
        $interventions = $this->getSelectedCheckboxes($forms,self::interventionsTypes,array_combine($otherTypes,$this->createPrefixArray($otherTypes)),exclusive: self::noIntervention);
        $newData[self::interventionsNode] = $interventions;
        if (array_key_exists(self::interventionsDescription,$forms)) {
            $numSelected = count($interventions);
            $tempVal = $numSelected>0 && !array_key_exists(self::noIntervention,$interventions);
            if ($tempVal && !($numSelected===1 && array_key_exists('interventionsSurvey',$interventions))) {
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
                $tempArray[self::loanReceipt] = $this->getChosenArray($forms,self::loanReceipt,self::templateText,[self::descriptionNode => $this->appendText(self::loanReceipt)]);
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