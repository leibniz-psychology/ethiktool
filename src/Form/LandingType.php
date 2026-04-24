<?php

namespace App\Form;

use App\Abstract\TypeAbstract;
use App\Traits\LandingTrait;
use Symfony\Component\Form\FormBuilderInterface;

class LandingType extends TypeAbstract
{
    use LandingTrait;
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dummyParams = $options[self::dummyParams];
        if ($dummyParams[self::isProjectdetails] && !$dummyParams[self::isMeasure]) {
            $placeholderArray = $this->getPlaceholder($this->translateString('multiple.optional')); // placeholder
            foreach ($dummyParams['allStudies'] as $studyIndex => $study) {
                $this->addFormElement($builder,self::editName.'_'.$studyIndex,'text',options: $placeholderArray); // edit name
                foreach ($study[self::groupNode] as $groupIndex => $group) {
                    $this->addFormElement($builder,self::editName.'_'.$studyIndex.'_'.$groupIndex,'text',options: $placeholderArray); // edit name
                    foreach ($group[self::measureTimePointNode] as $measureIndex => $measure) {
                        $this->addFormElement($builder,self::editName.'_'.$studyIndex.'_'.$groupIndex.'_'.$measureIndex,'text',options: $placeholderArray); // edit name
                    }
                    $this->addFormElement($builder,self::newElement.'_'.$studyIndex.'_'.$groupIndex,'text',options: $placeholderArray);
                }
                $this->addFormElement($builder,self::newElement.'_'.$studyIndex,'text',options: $placeholderArray); // new group in current study
            }
            $this->addFormElement($builder,self::newElement.'_','text',options: $placeholderArray); // new study
        }

        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms):void{}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData):void{}
}