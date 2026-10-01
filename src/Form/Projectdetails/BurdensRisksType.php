<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class BurdensRisksType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
      $translationPrefix = 'projectdetails.pages.burdensRisks.';
      // burdens, risks, risks occurrence, risks trigger, burdens risks contributors and burdens risks uninvolved
      foreach (self::burdensRisksTypes as $type => $selections) {
        $typePrefix = $translationPrefix.$type.'.';
        $otherTypes = self::burdensRisksOther[$type];
        $this->addCheckboxGroup($builder,$selections,$typePrefix.'types.',$this->createPrefixArray($otherTypes),array_fill_keys($otherTypes,$translationPrefix.'placeholder'),$type.self::descriptionCap,$typePrefix.'hints.'.self::textHint);
      }
      $this->addBinaryRadio($builder,self::burdensEveryday,$translationPrefix.self::burdensNode.'.'.self::burdensEveryday); // burdens everyday
      $tempPrefix = $translationPrefix.self::risksNoMeasureNode.'.';
      $this->addCheckboxGroup($builder,self::risksNoMeasureTypes,$tempPrefix.'types.',textareaName: self::risksNoMeasureNode.self::descriptionCap,textareaTextHint: $tempPrefix.self::textHint); // risks no measure
      // burdens risks contributors and uninvolved compensation
      foreach ([self::burdensRisksContributorsNode,self::burdensRisksUninvolvedNode] as $type) {
        $this->addBinaryRadio($builder,$type.'Compensation', $translationPrefix.'compensation.title',$type.'CompensationDescription',options: [self::labelParams => ['type' => $type]]);
      }
      // finding
      $this->addFormElement($builder,self::descriptionNode,'textarea');
      $tempPrefix = $translationPrefix.self::findingNode.'.'.self::informingNode.'.';
      // informing
      $this->addRadioGroup($builder,self::informingNode,self::informingTypes,$tempPrefix.'title');
      $this->addFormElement($builder,self::informingNode.'Template','checkbox',$tempPrefix.'useTemplate');
      $this->addFormElement($builder,self::informingNode.self::descriptionCap,'textarea');
      // risks after
      $this->addBinaryRadio($builder,self::risksAfterNode,$translationPrefix.self::risksAfterNode);
      // feedback
      $tempPrefix = $translationPrefix.self::feedbackNode.'.';
      $this->addBinaryRadio($builder,self::feedbackNode,$tempPrefix.'title',self::feedbackNode.self::descriptionCap,$tempPrefix.self::textHint);
       // dummy forms
       $this->addDummyForms($builder);
       $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        // burdens, risks, burdens risks contributors and burdens risks uninvolved
        foreach (self::burdensRisksTypesAll as $type => $options) {
            $typeArray = in_array($type,[self::risksOccurrenceNode,self::risksTriggerNode]) ? ($viewData[self::risksNode][$type] ?? []) : $viewData[$type] ?? [];
            $this->setSelectedCheckboxesMultiLevel($forms,$typeArray[$type.'Type'] ?? '');
            $tempVal = $type.self::descriptionCap;
            if (array_key_exists($tempVal,$forms)) {
                $forms[$tempVal]->setData($this->getArrayValue($typeArray,self::descriptionNode)); // description
            }
            // compensation
            if (in_array($type,[self::burdensRisksContributorsNode,self::burdensRisksUninvolvedNode])) {
                $compensationNode = $type.'Compensation';
                $tempArray = $typeArray[self::burdensRisksCompensationNode] ?? [];
                $forms[$compensationNode]->setData($this->getArrayValue($tempArray,self::chosen));
                $forms[$compensationNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
            }
        }
        // burdens everyday
        $forms[self::burdensEveryday]->setData($this->getArrayValue($viewData[self::burdensNode],self::burdensEveryday));
        // finding
        $risksArray = $viewData[self::risksNode];
        if (array_key_exists(self::findingNode,$risksArray)) {
            $tempArray = $risksArray[self::findingNode];
            $forms[self::descriptionNode]->setData($tempArray[self::descriptionNode]); // description
            // informing
            $tempArray = $tempArray[self::informingNode];
            $forms[self::informingNode]->setData($tempArray[self::chosen]);
            $forms[self::informingTemplate]->setData($this->getArrayValue($tempArray,self::informingTemplate)==='1');
            $forms[self::informingNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        }
        // risks occurrence further questions
        if (array_key_exists(self::risksOccurrenceNode,$risksArray)) {
            $this->setSelectedCheckboxes($forms,$risksArray[self::risksOccurrenceNode][self::risksNoMeasureNode] ?? [],[self::risksNoMeasureOther => self::risksNoMeasureNode.self::descriptionCap]); // risks no measure
            // risks after
            $forms[self::risksAfterNode]->setData($this->getArrayValue($risksArray,self::risksAfterNode));
        }
        // feedback
        $this->setChosenArray($forms,$viewData,self::feedbackNode,[self::descriptionNode => self::feedbackNode.self::descriptionCap]);
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        $newData = [];
        // burdens, risks, burdens risks contributors and burdens risks uninvolved
        foreach (self::burdensRisksTypesAll as $type => $options) {
            if (!in_array($type,[self::risksOccurrenceNode,self::risksTriggerNode])) {
                $selections = $this->getSelectedCheckboxesMultiLevel($forms,$options,self::burdensRisksOther[$type]);
                $tempArray = [$type.'Type' => $selections];
                if ($selections!==[] && !array_key_exists('no'.ucfirst($type),$selections)) { // at least one option except "no" was selected
                    $tempArray[self::descriptionNode] = $forms[$type.self::descriptionCap]->getData(); // description
                    if ($type===self::burdensNode) {
                        $tempArray[self::burdensEveryday] = $forms[self::burdensEveryday]->getData(); // burdens everyday
                    } elseif ($type===self::risksNode) {
                        // finding
                        if (array_key_exists('risksFinding',$selections)) { // at least one of the finding options was selected
                            $findingArray = [self::descriptionNode => $forms[self::descriptionNode]->getData()]; // finding description
                            $tempVal = $forms[self::informingNode]->getData(); // informing
                            $informingArray = [self::chosen => $tempVal];
                            if ($tempVal!=='') {
                                $informingArray[self::informingTemplate] = $forms[self::informingTemplate]->getData();
                                $informingArray[self::descriptionNode] = $forms[self::informingNode.self::descriptionCap]->getData();
                            }
                            $findingArray[self::informingNode] = $informingArray;
                            $tempArray[self::findingNode] = $findingArray;
                        }
                        // risks occurrence
                        $risksOccurrences = $this->getSelectedCheckboxesMultiLevel($forms,self::burdensRisksTypesAll[self::risksOccurrenceNode],self::burdensRisksOther[self::risksOccurrenceNode]);
                        $risksOccurrenceArray = [self::risksOccurrenceNode.'Type' => $risksOccurrences];
                        // risks no measure
                        $hasRisksOccurrence = $risksOccurrences!==[];
                        $isNoRisksOccurrence = $hasRisksOccurrence && array_key_exists(self::noRisksOccurrence,$risksOccurrences);
                        if ($isNoRisksOccurrence) {
                            $risksOccurrenceArray[self::risksNoMeasureNode] = $this->getSelectedCheckboxes($forms,self::risksNoMeasureTypes,[self::risksNoMeasureOther => self::risksNoMeasureNode.self::descriptionCap]);
                        }
                        $tempArray[self::risksOccurrenceNode] = $risksOccurrenceArray;
                        // risks trigger
                        if (array_key_exists('trigger',$risksOccurrences['occurrenceBefore'] ?? [])) {
                            $tempArray[self::risksTriggerNode][self::risksTriggerNode.'Type'] = $this->getSelectedCheckboxesMultiLevel($forms,self::burdensRisksTypesAll[self::risksTriggerNode],self::burdensRisksOther[self::risksTriggerNode]);
                        }
                        // risks after
                        if ($hasRisksOccurrence && !$isNoRisksOccurrence) {
                            $tempArray[self::risksAfterNode] = $forms[self::risksAfterNode]->getData();
                        }
                    } else { // burdens risks contributors and uninvolved
                        // compensation
                        $compensationNode = $type.'Compensation';
                        $tempArray[self::burdensRisksCompensationNode] = $this->getChosenArray($forms,$compensationNode,null,[self::descriptionNode => $compensationNode.self::descriptionCap]);
                    }
                }
                $newData[$type] = $tempArray;
            }
        }
        if (array_key_exists(self::feedbackNode,$forms)) { // feedback
            $newData[self::feedbackNode] = $this->getChosenArray($forms,self::feedbackNode,0,[self::descriptionNode => self::feedbackNode.self::descriptionCap]);
        }
        $viewData = $newData;
    }
}
