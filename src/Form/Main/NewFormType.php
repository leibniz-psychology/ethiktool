<?php

namespace App\Form\Main;

use App\Abstract\ControllerAbstract;
use App\Abstract\TypeAbstract;
use App\Traits\PageTrait;
use Symfony\Component\Form\FormBuilderInterface;

class NewFormType extends TypeAbstract
{
    use PageTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $committeTypes = array_flip(self::committeeTypes);
        foreach ($committeTypes as $type => $translation) {
            $committeTypes[$type] = $this->translateString($translation).(in_array($type,self::committeeTypesBeta) ? ' (Beta)' : '');
        }
        $this->addFormElement($builder,ControllerAbstract::fileName,'text','multiple.filename');
        $this->addFormElement($builder,self::committee,'choice','newForm.committee.title',options: ['choices' => array_flip($committeTypes)],hint: self::choiceTextHint);
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms){}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData){}
}