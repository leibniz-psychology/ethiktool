<?php

namespace App\Traits\Main;

use App\Traits\PageTrait;

trait CompleteFormTrait
{
    use PageTrait;

    protected const consent = 'consent';
    protected const bias = 'bias';
    protected const noBias = 'noBias';
    protected const biasParticipate = 'participate';
    protected const biasAccess = 'access';
    protected const biasTypes = ['noBias','participate','access']; // values must equal the values of the preceding variables
    protected const consentFurther = 'consentFurther';
}