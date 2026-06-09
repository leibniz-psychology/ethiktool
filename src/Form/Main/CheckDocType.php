<?php

namespace App\Form\Main;

use App\Abstract\TypeAbstract;
use Symfony\Component\Form\FormBuilderInterface;

class CheckDocType extends TypeAbstract
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms){}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData){}
}