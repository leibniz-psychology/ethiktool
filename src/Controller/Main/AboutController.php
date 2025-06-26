<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\DummyType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AboutController extends ControllerAbstract
{
    #[Route('/about', name: 'app_about')]
    public function showAbout(Request $request): Response {

        $about = $this->createFormAndHandleRequest(DummyType::class,null,$request);
        if ($about->isSubmitted()) {
            return $this->saveDocumentAndRedirect($request,$this->getXMLfromSession($request->getSession()));
        }


        return $this->render('Main/about.html.twig',[self::content => $about]);
    }

}