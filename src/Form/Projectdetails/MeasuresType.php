<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class MeasuresType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'projectdetails.pages.measures.';
        $measuresInterventionsPrefix = $translationPrefix.'measuresInterventions.';
        // measures and interventions
        foreach ([self::measuresNode,self::interventionsNode] as $type) {
            $tempPrefix = $measuresInterventionsPrefix.$type.'.';
            $this->addCheckboxGroup($builder, $type===self::measuresNode ? self::measuresTypes : self::interventionsTypes,$tempPrefix.'types.',textareaName: $type.self::descriptionCap);
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
        $this->addRadioGroup($builder,self::locationNode,$this->translateArray($translationPrefix.'location.types.',['intern','extern',self::locationOnline,'other']),textareaName: self::locationNode.self::descriptionCap);
        // presence
        $this->addBinaryRadio($builder,self::presenceNode,$translationPrefix.self::presenceNode.'.title');
        // durations
        foreach (self::durationTypes as $index => $duration) {
            $this->addFormElement($builder,$duration,'spinner',options: $this->setMinMax(!($index%2),999));
        }
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        // measures and interventions
        foreach ([self::measuresNode,self::interventionsNode] as $type) {
            $tempArray = $viewData[$type];
            $this->setSelectedCheckboxes($forms,$tempArray[$type.'Type']);
            $forms[$type.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode)); // survey start is added in stimulus controller
            $tempVal = $type.'PDF';
            $forms[$tempVal]->setData(array_key_exists($tempVal,$tempArray));
        }
        // other sources
        $this->setChosenArray($forms,$viewData,self::otherSourcesNode,[self::otherSourcesNode.self::descriptionCap]);
        $forms[self::otherSourcesPDF]->setData(array_key_exists(self::otherSourcesPDF,$viewData[self::otherSourcesNode]));
        // loan
        $tempArray = $viewData[self::loanNode];
        $forms[self::loanNode]->setData($tempArray[self::chosen]);
        if (array_key_exists(self::loanReceipt,$tempArray)) {
            $tempArray = $tempArray[self::loanReceipt];
            $forms[self::loanReceipt]->setData($tempArray[self::chosen]);
            $forms[$this->appendText(self::loanReceipt)]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        }
        // location
        $tempArray = $viewData[self::locationNode];
        $forms[self::locationNode]->setData($tempArray[self::chosen]);
        $forms[self::locationNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        // presence
        $forms[self::presenceNode]->setData($viewData[self::presenceNode]);
        // durations
        $this->setSpinner($forms,$viewData[self::durationNode],self::durationTypes);
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        // measures
        $tempArray = [];
        $measures = $this->getSelectedCheckboxes($forms,self::measuresTypes);
        $tempArray[self::measuresTypesNode] = $measures;
        $tempArray[self::descriptionNode] = $measures!==[] ? $forms[self::measuresNode.self::descriptionCap]->getData() : '';
        if ($forms[self::measuresPDF]->getData()) {
            $tempArray[self::measuresPDF] = '';
        }
        $viewData[self::measuresNode] = $tempArray;
        // interventions
        $tempArray = [];
        $interventions = $this->getSelectedCheckboxes($forms,self::interventionsTypes,exclusive: self::noIntervention);
        $tempArray[self::interventionsTypesNode] = $interventions;
        $numSelected = count($interventions);
        $isInterventions = $numSelected>0 && !array_key_exists(self::noIntervention,$interventions);
        if ($isInterventions && !($numSelected===1 && array_key_exists(self::interventionsSurvey,$interventions))) {
            $tempArray[self::descriptionNode] = str_replace($this->translateString('projectdetails.pages.measures.measuresInterventions.interventions.textHints.defaultStart').'.','',$forms[self::interventionsNode.self::descriptionCap]->getData()); // may be invisible, but never disabled, i.e., will always return a string. Survey sentence differs between text field and pdf, therefore, save only user input
        }
        if ($isInterventions && $forms[self::interventionsPDF]->getData()) {
            $tempArray[self::interventionsPDF] = '';
        }
        $viewData[self::interventionsNode] = $tempArray;
        // other sources
        $tempArray = $this->getChosenArray($forms,self::otherSourcesNode,0,[self::otherSourcesNode.self::descriptionCap]);
        if ($forms[self::otherSourcesPDF]->getData()) {
            $tempArray[self::otherSourcesPDF] = '';
        }
        $viewData[self::otherSourcesNode] = $tempArray;
        // loan
        $tempVal = $forms[self::loanNode]->getData();
        $tempArray = [self::chosen => $tempVal];
        if ($tempVal===0) {
            $tempVal = $forms[self::loanReceipt]->getData();
            $tempArray[self::loanReceipt] = array_merge([self::chosen => $tempVal],$tempVal===self::templateText ? [self::descriptionNode => $forms[$this->appendText(self::loanReceipt)]->getData()] : []);
        }
        $viewData[self::loanNode] = $tempArray;
        // location
        $tempVal = $forms[self::locationNode]->getData();
        $viewData[self::locationNode] = array_merge([self::chosen => $tempVal],$tempVal!==null ? [self::descriptionNode => $forms[self::locationNode.self::descriptionCap]->getData()] : []);
        // presence
        $viewData[self::presenceNode] = $forms[self::presenceNode]->getData();
        // durations
        $tempArray = [];
        foreach (self::durationTypes as $duration) {
            $curDur = $forms[$duration]->getData();
            $tempArray[$duration] = $curDur!==null ? floor($curDur) : null; // avoid decimals
        }
        $viewData[self::durationNode] = $tempArray;
    }
}