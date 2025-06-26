<?php

namespace App\Form\Main;

use App\Abstract\ControllerAbstract;
use App\Abstract\TypeAbstract;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

class DummyType extends TypeAbstract
{
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $this->addFormElement($builder,ControllerAbstract::submitDummy,'textarea');
        $builder->add(ControllerAbstract::loadInput,FileType::class,['empty_data' => '', 'required' => false, 'attr' => ['type' => 'file', 'accept' => '.xml']]);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms){}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData){}
}