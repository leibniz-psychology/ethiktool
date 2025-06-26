<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class LegalType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $dummyParams = $options[self::dummyParams];
        $isReceipt = $dummyParams['isReceipt'];
        foreach ($dummyParams['legalKeys'] as $type) {
            $this->addRadioGroup($builder,$type,self::templateTypes,'projectdetails.pages.legal.'.$type.'.title',$this->appendText($type),options: [self::labelParams => ['isReceipt' => $isReceipt]]);
        }
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        foreach (self::legalTypes as $type) {
            if (array_key_exists($type,$viewData)) {
                $this->setChosenArray($forms,$viewData,$type,[self::descriptionNode => $this->appendText($type)],true);
            }
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        foreach (self::legalTypes as $type) {
            if (array_key_exists($type,$viewData)) {
                $viewData[$type] = $this->getChosenArray($forms,$type,self::templateText,[self::descriptionNode => $this->appendText($type)],true);
            }
        }
    }
}