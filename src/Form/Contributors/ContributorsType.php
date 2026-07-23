<?php

namespace App\Form\Contributors;

use App\Abstract\TypeAbstract;
use App\Traits\Contributors\ContributorsTrait;
use Symfony\Component\Form\FormBuilderInterface;

class ContributorsType extends TypeAbstract
{
    use ContributorsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::applicantContributorsInfosTypes as $info) {
            if (in_array($info,self::institutionPosition,true)) {
                $isInstitution = $info===self::institutionInfo;
                $this->addFormElement($builder, $info, 'choice',options: array_merge(['choices' => array_flip($isInstitution ? self::institutionTypes : self::positionsTypes)],$isInstitution ? [self::choiceParams => [self::institutionSameOption => $options[self::committeeParams]]] : []),hint: self::choiceTextHint);
                $this->addFormElement($builder, $this->appendText($info.'Other'), 'text',hint: 'multiple.placeholder.'.$info);
            } else {
                $this->addFormElement($builder,$info,'text');
            }
        }
        $translationPrefix = 'contributors.tasks.';
        $this->addCheckboxGroup($builder,$options[self::dummyParams][self::taskNode],$translationPrefix,self::otherDescription,$translationPrefix.'otherDescription');
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms): void {}

    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void {}
}