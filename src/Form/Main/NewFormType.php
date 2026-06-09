<?php

namespace App\Form\Main;

use App\Abstract\ControllerAbstract;
use App\Abstract\TypeAbstract;
use Symfony\Component\Form\FormBuilderInterface;

class NewFormType extends TypeAbstract
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFormElement($builder,ControllerAbstract::fileName,'text','multiple.filename');
        $this->addCommitteeForms($builder);
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms):void{}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData):void{}
}