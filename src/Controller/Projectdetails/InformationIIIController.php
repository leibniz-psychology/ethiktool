<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\InformationIIIType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InformationIIIController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.self::informationIIINode,self::informationIIINode)]
    public function showInformationIII(Request $request): Response
    {
        return $this->createFormAndHandleSubmit(InformationIIIType::class,$request,[self::informationIIINode],
            [self::pageTitle => 'projectdetails.informationIII',
             'widgetIDs' => self::informationIIIInputsTypes]);
    }
}