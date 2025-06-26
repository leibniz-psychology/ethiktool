<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class InformationIIIType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'projectdetails.pages.informationIII.';
        foreach (self::informationIIIInputsTypes as $input => $value) {
            $this->addFormElement($builder,$input,'textarea',$translationPrefix.$input.'.title',hint: $translationPrefix.$input.'.'.self::textHint);
        }
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        foreach (self::informationIIIInputsTypes as $input => $value) {
            $forms[$input]->setData($viewData[$input]);
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        foreach (self::informationIIIInputsTypes as $input => $value) {
            $viewData[$input] = $forms[$input]->getData();
        }
    }
}