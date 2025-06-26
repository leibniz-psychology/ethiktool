<?php

namespace App\Form;

use App\Abstract\TypeAbstract;
use App\Traits\LandingTrait;
use Symfony\Component\Form\FormBuilderInterface;

class LandingType extends TypeAbstract
{
    use LandingTrait;
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $attributes = $options['attr'];
        if ($attributes[self::isProjectdetails] && !$attributes[self::isMeasure]) { // is $attributes[self::isMeasure] is true, then $isGroup is also true
            $this->addFormElement($builder,self::copy,'choice',options: ['choices' => $options[self::dummyParams] [self::dropdownChoices]],hint: self::choiceTextHint);
            if (!$attributes[self::isGroup]) {
                $isStudy = $attributes[self::isStudy];
                $this->addFormElement($builder,self::newStudyGroupName,'text',options: ['attr' => ['placeholder' => 'landing.manipulate.placeholder'], self::attrParams => [self::isStudy => $this->getStringFromBool($isStudy)]]);
                for($curEdit=0;$curEdit<$attributes[$isStudy ? self::numGroups : self::numStudies];++$curEdit) {
                    $this->addFormElement($builder,self::editName.$curEdit,'text');
                }
            }
        }
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms){}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData){}
}