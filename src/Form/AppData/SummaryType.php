<?php

namespace App\Form\AppData;

use App\Abstract\TypeAbstract;
use App\Traits\AppData\AppDataTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class SummaryType extends TypeAbstract
{
    use AppDataTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $this->addFormElement($builder,self::summary,'textarea','summary.heading');
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        $forms[self::summary]->setData($viewData[self::descriptionNode]);
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        $viewData[self::descriptionNode] = $forms[self::summary]->getData();
    }
}