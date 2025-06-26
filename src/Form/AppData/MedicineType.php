<?php

namespace App\Form\AppData;

use App\Abstract\TypeAbstract;
use App\Traits\AppData\AppDataTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class MedicineType extends TypeAbstract
{
    use AppDataTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $this->addBinaryRadio($builder,self::medicine, 'medicine.medicine.heading',self::medicine.self::descriptionCap);
        $translationPrefix = 'medicine.physician.';
        $this->addBinaryRadio($builder,self::physicianNode,$translationPrefix.'title',self::descriptionNode,$translationPrefix.self::textHint);
        $translationPrefix .= self::descriptionNode.'.';
        $typesPrefix = $translationPrefix.'types.';
        $this->addRadioGroup($builder,self::physicianNode.self::descriptionCap,[$typesPrefix.'exception' => 0, $typesPrefix.'other' => 1],$translationPrefix.'title',self::descriptionNode,options: [self::labelParams => $options[self::committeeArray]]);
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        $this->setChosenArray($forms,$viewData,self::medicine,[self::descriptionNode => self::medicine.self::descriptionCap],true);
        $tempArray = $viewData[self::physicianNode];
        $forms[self::physicianNode]->setData($tempArray[self::chosen]);
        if (array_key_exists(self::descriptionNode,$tempArray)) {
            $tempArray = $tempArray[self::descriptionNode];
            $forms[self::physicianNode.self::descriptionCap]->setData($tempArray[self::chosen]);
            $forms[self::descriptionNode]->setData($tempArray[self::descriptionNode]);
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        $viewData[self::medicine] = $this->getChosenArray($forms,self::medicine,0,[self::descriptionNode => self::medicine.self::descriptionCap],true);
        $chosen = $forms[self::physicianNode]->getData();
        $tempArray = [self::chosen => $chosen];
        if ($chosen===0) {
            $tempArray[self::descriptionNode] = [self::chosen => $forms[self::physicianNode.self::descriptionCap]->getData(), self::descriptionNode => $forms[self::descriptionNode]->getData()];
        }
        $viewData[self::physicianNode] = $tempArray;
    }
}