<?php

namespace App\Form\Main;

use App\Abstract\TypeAbstract;
use Symfony\Component\Form\FormBuilderInterface;

class MainType extends TypeAbstract
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dummy = $options[self::dummyParams];
        if ($dummy['isFilename']) {
            $this->addCommitteeForms($builder,false,$dummy[self::committee]);
        }
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms){}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData){}
}