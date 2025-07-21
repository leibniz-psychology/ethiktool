<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\DummyType;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckDocController extends ControllerAbstract
{
    /** @throws Exception if the document check fails */
    #[Route('/checkDoc', name: 'app_checkDoc')]
    public function showCheckDoc(Request $request): Response {
        $appNode = $this->getXMLfromSession($request->getSession());
        if (!$appNode) { // page was opened before a proposal was created/loaded
            return $this->redirectToRoute('app_main');
        }

        $checkDoc = $this->createFormAndHandleRequest(DummyType::class,[self::language => $request->getSession()->get(self::language)],$request);
        if ($checkDoc->isSubmitted()) { // language was changed or a link was clicked
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
        return $this->render('Main/checkDoc.html.twig', $this->setRenderParameters($request,$checkDoc,['text' => $this->getErrors($request,element: $appNode)],'checkDoc',addErrors: false));
    }
}