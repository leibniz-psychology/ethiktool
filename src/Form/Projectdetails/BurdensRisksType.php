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
        // burdens/risks
        foreach ([self::burdensNode,self::risksNode,self::burdensRisksContributorsNode] as $type) {
            $description = $type.self::descriptionCap;
            if ($type!==self::burdensRisksContributorsNode) {
                $this->addCheckboxGroup($builder, $type===self::burdensNode ? self::burdensTypes : self::risksTypes, $translationPrefix.$type.'.types.', textareaName: $description);
            } else {
                $this->addBinaryRadio($builder,$type,textareaName: $description);
            }
            $this->addBinaryRadio($builder,$type.'Compensation', $translationPrefix.'compensation.title',$type.'CompensationDescription',options: [self::labelParams => ['type' => $type]]);
        }
        $tempPrefix = $translationPrefix.self::burdensNode.'.';
        $this->addFormElement($builder,self::burdensNoDescription,'textarea',hint: $tempPrefix.'hints.'.self::noBurdens);
        $this->addBinaryRadio($builder,self::burdensEveryday,$tempPrefix.self::burdensEveryday);
        // finding
        $tempPrefix = $translationPrefix.self::findingNode.'.';
        $this->addBinaryRadio($builder,self::findingNode,$tempPrefix.'title',self::descriptionNode,$tempPrefix.self::textHint);
        $tempPrefix .= self::informingNode.'.';
        $this->addRadioGroup($builder,self::informingNode,$this->translateArray($tempPrefix.'types.',[self::informingAlways,self::informingConsent]),$tempPrefix.'title');
        // finding
        $tempPrefix = $translationPrefix.self::feedbackNode.'.';
        $this->addBinaryRadio($builder,self::feedbackNode,$tempPrefix.'title',self::feedbackNode.self::descriptionCap,$tempPrefix.self::textHint);
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        // burdens and risks
        foreach ([self::burdensNode,self::risksNode] as $type) {
            $tempArray = $viewData[$type];
            $this->setSelectedCheckboxes($forms,$tempArray[$type===self::burdensNode ? self::burdensTypesNode: self::risksTypesNode]);
            $forms[$type.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
            $this->setCompensation($forms,$tempArray,$type);
        }
        if (array_key_exists(self::burdensNoDescription,$forms)) {
            $forms[self::burdensNoDescription]->setData($this->getArrayValue($viewData,self::burdensNoDescription));
        }
        $forms[self::burdensEveryday]->setData($this->getArrayValue($viewData[self::burdensNode],self::burdensEveryday));
        // burdensRisksContributors
        $this->setChosenArray($forms,$viewData,self::burdensRisksContributorsNode,[self::descriptionNode => self::burdensRisksContributorsNode.self::descriptionCap]);
        $this->setCompensation($forms,$viewData[self::burdensRisksContributorsNode],self::burdensRisksContributorsNode);
        // finding
        $this->setChosenArray($forms,$viewData,self::findingNode,[self::descriptionNode,self::informingNode],false);
        // feedback
        $this->setChosenArray($forms,$viewData,self::feedbackNode,[self::descriptionNode => self::feedbackNode.self::descriptionCap]);
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        $newData = [];
        // burdens and risks
        foreach ([self::burdensNode,self::risksNode] as $type) {
            $isBurdens = $type===self::burdensNode;
            $selected = $this->getSelectedCheckboxes($forms,$isBurdens ? self::burdensTypes : self::risksTypes);
            $isNotNo = !array_key_exists($isBurdens ? self::noBurdens : self::noRisks,$selected);
            $tempArray = [$type.'Type' => $selected];
            $isAnySelected = $selected!==[];
            if ($isAnySelected && $isNotNo) { // any type except 'no' is selected
                $tempArray[self::descriptionNode] = $forms[$type.self::descriptionCap]->getData();
                $hasCompensation = true;
                if ($isBurdens) {
                    $tempVal = $forms[self::burdensEveryday]->getData();
                    $tempArray[self::burdensEveryday] = $tempVal;
                    $hasCompensation = $tempVal===0;
                }
                if ($hasCompensation) {
                    $tempArray[self::burdensRisksCompensationNode] = $this->getCompensation($forms,$type);
                }
            }
            $newData[$type] = $tempArray;
            if ($isBurdens && array_key_exists(self::burdensNoDescription,$forms) && $isAnySelected && !$isNotNo) { // 'no burdens' is selected
                $newData[self::burdensNoDescription] = $forms[self::burdensNoDescription]->getData();
            }
        }
        // burdensRisksContributors
        $tempArray = $this->getChosenArray($forms,self::burdensRisksContributorsNode,0,[self::descriptionNode => self::burdensRisksContributorsNode.self::descriptionCap]);
        if ($tempArray[self::chosen]===0) {
            $tempArray[self::burdensRisksCompensationNode] = $this->getCompensation($forms,self::burdensRisksContributorsNode);
        }
        $newData[self::burdensRisksContributorsNode] = $tempArray;
        $newData[self::findingNode] = $this->getChosenArray($forms,self::findingNode,0,[self::descriptionNode,self::informingNode],false); // finding
        if (array_key_exists(self::feedbackNode,$forms)) { // feedback
            $newData[self::feedbackNode] = $this->getChosenArray($forms,self::feedbackNode,0,[self::descriptionNode => self::feedbackNode.self::descriptionCap]);
        }
        $viewData = $newData;
    }

    /** Sets the compensation.
     * @param array $forms form array where the data is set
     * @param array $viewData array containing the data
     * @param string $type must equal 'burdens', 'risks', or 'burdensRisksContributors'
     * @return void
     */
    private function setCompensation(array $forms, array $viewData, string $type): void
    {
        $compensationNode = $type.'Compensation';
        $tempArray = $viewData[self::burdensRisksCompensationNode] ?? [];
        $forms[$compensationNode]->setData($this->getArrayValue($tempArray,self::chosen));
        $forms[$compensationNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
    }

    /** Gets the compensation data.
     * @param array $forms form array containing the data
     * @param string $type must equal 'burdens', 'risks', or 'burdensRisksContributors'
     * @return array array with the compensation data
     */
    private function getCompensation(array $forms, string $type): array
    {
        $compensationNode = $type.'Compensation';
        return $this->getChosenArray($forms,$compensationNode,null,[self::descriptionNode => $compensationNode.self::descriptionCap]);
    }
}