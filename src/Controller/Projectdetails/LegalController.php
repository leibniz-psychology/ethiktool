<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\LegalType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LegalController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'legal', name: 'app_legal')]
    public function showLegal(Request $request): Response {
        $measureNode = $this->getMeasureTimePointNode($this->getXMLfromSession($request->getSession()),$request->get('_route_params'));
        if ($measureNode===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $measureArray = $this->xmlToArray($measureNode);

        $measuresArray = $measureArray[self::measuresNode];
        $location = $measuresArray[self::locationNode][self::chosen];
        $loanArray = $measuresArray[self::loanNode];
        $isLoan = $loanArray[self::chosen]==='0';
        $isReceipt = $this->getStringFromBool($isLoan && $this->getTemplateChoice($loanArray[self::loanReceipt][self::chosen]));
        $legalArray = $measureArray[self::legalNode];
        if ($legalArray==='') { // page was opened, but is not active
            return $this->redirectToRoute('app_main');
        }

        return $this->createFormAndHandleSubmit(LegalType::class,$request,[self::legalNode],
            ['legalNodes' => self::legalTypes,
             'isLoan' => $isLoan,
             'isReceipt' => $isReceipt,],
            [self::dummyParams =>
                 ['isNotLoan' => !$isLoan,
                  'isReceipt' => $isReceipt,
                  'isOnlineEmpty' => $location==='' || $location===self::locationOnline,
                  'legalKeys' => array_intersect(self::legalTypes,array_keys($legalArray))]]);
    }
}