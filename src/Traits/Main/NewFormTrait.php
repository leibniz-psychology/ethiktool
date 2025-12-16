<?php

namespace App\Traits\Main;

use App\Traits\PageTrait;

trait NewFormTrait
{
    use PageTrait;

    protected const passwordInput = 'passwordInput';
    protected const requirements = 'requirements';
    protected const technicalHint = 'technicalHint';
    protected const wrongPassword = 'wrongPassword'; // session variable indicating that a wrong password was entered
    protected const fileNameTemp = 'fileNameTemp';
    protected const committeeTemp = 'committeeTemp';
    protected const requirementsTemp = 'requirementsTemp';
    protected const technicalHintTemp = 'technicalHintTemp';
}