<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormTypeExtension extends AbstractTypeExtension
{

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults(['information' => '', '{addressee}' => '', '{participant}' => '', 'committeeArray' => [], 'committeeNom' => '', 'committeeGen' => '', 'committeeDat' => '', 'committeeAcc' => '', 'committeeType' => '', 'committeeLocation' => '', 'committeeLocationGen' => '', 'isCommitteeBeta' => false, 'toolVersion' => '', 'dummy' => []]);
        $resolver->setAllowedTypes('information','string');
        $resolver->setAllowedTypes('{addressee}', 'string');
        $resolver->setAllowedTypes('{participant}','string');
        $resolver->setAllowedTypes('committeeArray','array');
        $resolver->setAllowedTypes('committeeNom','string');
        $resolver->setAllowedTypes('committeeGen','string');
        $resolver->setAllowedTypes('committeeDat','string');
        $resolver->setAllowedTypes('committeeAcc','string');
        $resolver->setAllowedTypes('committeeType','string');
        $resolver->setAllowedTypes('committeeLocation','string');
        $resolver->setAllowedTypes('committeeLocationGen','string');
        $resolver->setAllowedTypes('isCommitteeBeta','bool');
        $resolver->setAllowedTypes('toolVersion','string');
        $resolver->setAllowedTypes('dummy', 'array');
    }

    public static function getExtendedTypes(): iterable {
        return [FormType::class];
    }
}