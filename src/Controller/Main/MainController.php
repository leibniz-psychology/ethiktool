<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\DummyType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends ControllerAbstract
{
    #[Route('/', name: 'app_home')] // if the url is entered without the page, i.e., only with locale or without anything
    public function showHome(Request $request): Response {
        return $this->redirectToRoute('app_main');
    }

    #[Route('/main', name: 'app_main')]
    public function showMain(Request $request): Response {
        $session = $request->getSession();
        $isXmlLoadFailure = $session->has(self::xmlLoad);
        $isError = $session->has(self::errorModal);
        $isLoadSuccess = $session->has(self::loadSuccess);
        [$errorModal,$sessionValue] = ['',[]];
        if ($isXmlLoadFailure || $isError || $isLoadSuccess || $session->has(self::newForm)) {
            $errorModal = $isError ? self::errorModal : ($isXmlLoadFailure ? self::xmlLoad : ($isLoadSuccess ? self::loadSuccess : self::newForm));
            $sessionValue = $session->get($errorModal); // only needed if errorModal equals 'updated' (old version) or 'loadSuccess' (whether cur route is main)
            $session->remove($errorModal);
        }

        $main = $this->createFormAndHandleRequest(DummyType::class,null,$request);
        if ($main->isSubmitted()) { // language has changed, a link was clicked, the xml-file should be downloaded, or the program should be quit
            return $this->saveDocumentAndRedirect($request,$this->getXMLfromSession($session));
        }
        return $this->render('Main/main.html.twig',
            [self::content => $main,
             'committeeParams' => $this->getCommitteeSession($session),
             'error' => $errorModal,
             'params' => ['oldVersion' => $sessionValue['oldVersion'] ?? '', 'newVersion' => self::toolVersion, 'isMain' => $sessionValue['isMain'] ?? '']]);
    }
}