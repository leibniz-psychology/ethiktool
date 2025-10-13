<?php

namespace App\Form\Contributors;

use App\Abstract\TypeAbstract;
use App\Traits\Contributors\ContributorsTrait;
use Symfony\Component\Form\FormBuilderInterface;

class ContributorsType extends TypeAbstract
{
    use ContributorsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        foreach (self::applicantContributorsInfosTypes as $info) {
            if ($info===self::position) {
                $this->addFormElement($builder, self::position, 'choice',options: ['choices' => array_flip(self::positionsTypes)],hint: self::choiceTextHint);
                $this->addFormElement($builder, $this->appendText(self::positionOther), 'text',hint: 'multiple.position.otherDefault');
            }
            else {
                $this->addFormElement($builder,$info,'text');
            }
        }
        $translationPrefix = 'contributors.tasks.';
        $this->addCheckboxGroup($builder,array_keys(self::tasksTypes),$translationPrefix,self::otherDescription,$translationPrefix.'otherDescription');
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms): void {}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void {}
}