<?php

namespace App\Form\Main;

use App\Abstract\ControllerAbstract;
use App\Abstract\TypeAbstract;
use Symfony\Component\Form\FormBuilderInterface;

class QuitType extends TypeAbstract
{
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        self::setPage('quit');
        if ($options['dummy']['quit']) {
            foreach (['quit', 'backToMain'] as $button) {
                $this->addFormElement($builder, $button, 'submit', 'quit.buttons.'.$button, ['attr' => ['class' => 'Button_primary_go']]);
            }
        }
        $this->addFormElement($builder,ControllerAbstract::submitDummy,'textarea');
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms){}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData){}
}