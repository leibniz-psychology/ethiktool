<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\InformationIIIType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InformationIIIController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'informationIII', name: 'app_informationIII')]
    public function showInformationIII(Request $request): Response {
        return $this->createFormAndHandleSubmit(InformationIIIType::class,$request,[self::informationIIINode],
            [self::pageTitle => 'projectdetails.informationIII',
             'widgetIDs' => array_keys(self::informationIIIInputsTypes)]);
    }
}