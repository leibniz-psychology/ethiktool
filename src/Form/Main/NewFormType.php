<?php

namespace App\Form\Main;

use App\Abstract\ControllerAbstract;
use App\Abstract\TypeAbstract;
use App\Traits\Main\NewFormTrait;
use Symfony\Component\Form\FormBuilderInterface;

class NewFormType extends TypeAbstract
{
    use NewFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $committeTypes = array_flip(self::committeeTypes);
        foreach ($committeTypes as $type => $translation) {
            $committeTypes[$type] = $this->translateString($translation).(in_array($type,self::committeeTypesBeta) ? ' (Beta)' : '');
        }
        $this->addFormElement($builder,ControllerAbstract::fileName,'text','multiple.filename');
        $this->addFormElement($builder,self::committee,'choice','newForm.committee.title',options: ['choices' => array_flip($committeTypes)],hint: self::choiceTextHint);
        if (self::committeeTypesBeta!==[]) {
            $this->addFormElement($builder,self::passwordInput,'text','newForm.password.title');
        }
        foreach ([self::requirements,self::technicalHint] as $confirm) {
            $this->addFormElement($builder,$confirm,'checkbox','newForm.'.$confirm.'.confirm');
        }
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms){}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData){}
}