<?php

namespace App\Traits\Main;

use App\Traits\PageTrait;
use Symfony\Component\HttpFoundation\Session\Session;

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

    // functions

    /** Creates the string for instructions on how to proceed.
     * @param Session $session current session
     * @param bool $isTool true if the string should be created for the tool, false otherwise
     * @return string string for instructions on how to proceed
     */
    protected function getFinishEndText(Session $session, bool $isTool): string
    {
        return $this->translateString('completeForm.finish.text.end.text',array_merge($session->get(self::committeeParams),[self::fileName => $session->get(self::fileName), 'curDate' => $this->getCurrentTime()->format('Ymd'), 'isTool' => $this->getStringFromBool($isTool)]));
    }
}