<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class BurdensRisksType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'projectdetails.pages.burdensRisks.';
        // burdens/risks
        foreach ([self::burdensNode,self::risksNode,self::burdensRisksContributorsNode] as $type) {
            $description = $type.self::descriptionCap;
            if ($type!==self::burdensRisksContributorsNode) {
                $this->addCheckboxGroup($builder, $type===self::burdensNode ? self::burdensTypes : self::risksTypes, $translationPrefix.$type.'.types.', textareaName: $description);
            }
            else {
                $this->addBinaryRadio($builder,$type,textareaName: $description);
            }
            $this->addBinaryRadio($builder,$type.'Compensation', $translationPrefix.'compensation.title',$type.'CompensationDescription',options: [self::labelParams => ['{type}' => $this->translateString($translationPrefix.'title.'.$type,['number' => 2])]]);
        }
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

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        // burdens and risks
        foreach ([self::burdensNode,self::risksNode] as $type) {
            $tempArray = $viewData[$type];
            $this->setSelectedCheckboxes($forms,$tempArray[$type===self::burdensNode ? self::burdensTypesNode: self::risksTypesNode]);
            $forms[$type.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
            $this->setCompensation($forms,$tempArray,$type);
        }
        // burdensRisksContributors
        $tempArray = $viewData[self::burdensRisksContributorsNode];
        $forms[self::burdensRisksContributorsNode]->setData($tempArray[self::chosen]);
        $forms[self::burdensRisksContributorsNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        $this->setCompensation($forms,$tempArray,self::burdensRisksContributorsNode);
        $this->setChosenArray($forms,$viewData,self::findingNode,[self::descriptionNode,self::informingNode]); // finding
        $this->setChosenArray($forms,$viewData,self::feedbackNode,[self::descriptionNode => self::feedbackNode.self::descriptionCap],true);
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        // burdens and risks
        foreach ([self::burdensNode,self::risksNode] as $type) {
            $isBurdens = $type===self::burdensNode;
            $selected = $this->getSelectedCheckboxes($forms,$isBurdens ? self::burdensTypes : self::risksTypes);
            $isNotNo = !array_key_exists($isBurdens ? self::noBurdens : self::noRisks,$selected);
            $tempArray = [$type.'Type' => $selected];
            if ($selected!==[]) {
                if ($isBurdens || $isNotNo) {
                    $tempArray[self::descriptionNode] = $forms[$type.self::descriptionCap]->getData();
                }
                if ($isNotNo) {
                    $tempArray[self::burdensRisksCompensationNode] = $this->getCompensation($forms,$type);
                }
            }
            $viewData[$type] = $tempArray;
        }
        // burdensRisksContributors
        $tempVal = $forms[self::burdensRisksContributorsNode]->getData();
        $tempArray = [self::chosen => $tempVal];
        if ($tempVal===0) {
            $tempArray[self::descriptionNode] = $forms[self::burdensRisksContributorsNode.self::descriptionCap]->getData();
            $tempArray[self::burdensRisksCompensationNode] = $this->getCompensation($forms,self::burdensRisksContributorsNode);
        }
        $viewData[self::burdensRisksContributorsNode] = $tempArray;
        $viewData[self::findingNode] = $this->getChosenArray($forms,self::findingNode,0,[self::descriptionNode,self::informingNode]); // finding
        $viewData[self::feedbackNode] = $this->getChosenArray($forms,self::feedbackNode,0,[self::descriptionNode => self::feedbackNode.self::descriptionCap],true);
    }

    /** Sets the compensation.
     * @param array $forms form array where the data is set
     * @param array $viewData array containing the data
     * @param string $type must equal 'burdens', 'risks', or 'burdensRisksContributors'
     * @return void
     */
    private function setCompensation(array $forms, array $viewData, string $type): void {
        $compensation = $viewData[self::burdensRisksCompensationNode] ?? [];
        $compensationNode = $type.'Compensation';
        $forms[$compensationNode]->setData($this->getArrayValue($compensation,self::chosen));
        $forms[$compensationNode.self::descriptionCap]->setData($this->getArrayValue($compensation,self::descriptionNode));
    }

    /** Gets the compensation data.
     * @param array $forms form array containing the data
     * @param string $type must equal 'burdens', 'risks', or 'burdensRisksContributors'
     * @return array array with the compensation data
     */
    private function getCompensation(array $forms, string $type): array {
        $compensationNode = $type.'Compensation';
        return [self::chosen => $forms[$compensationNode]->getData(), self::descriptionNode => $forms[$compensationNode.self::descriptionCap]->getData()];
    }
}