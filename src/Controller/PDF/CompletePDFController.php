<?php

namespace App\Controller\PDF;

use App\Abstract\PDFAbstract;
use App\Traits\Main\CompleteFormTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CompletePDFController extends PDFAbstract
{
    use CompleteFormTrait;

    public function createPDF(Request $request, array $additional): Response
    {
        $session = $request->getSession();
        $completeArray = $this->xmlToArray($this->getXMLfromSession($session))[self::completeFormNodeName];
        $committeeParams = $session->get(self::committeeParams);
        $completePDF = $this->renderView('PDF/_completePDF.html.twig',array_merge($committeeParams,[
            self::committeeType => $this->getCommitteeType($session),
            self::isCommitteeBeta => $committeeParams[self::isCommitteeBeta],
            self::committeeParams => $committeeParams,
            'isFull' => $this->getStringFromBool(str_contains($session->get(self::reviewProcess),self::reviewProcessFull)),
            'briefReports' => $this->getBriefReport($session,false),
            'savePDF' => self::$savePDF,
            'hints' => [$this->translateString('completeForm.finish.text.end.title',['isTool' => 'false']).':', $this->getFinishEndText($session,false)],
            self::content => $additional,
            'messages' => $completeArray[self::descriptionNode],
            self::bias => $completeArray[self::bias],
            'toolVersion' => self::toolVersion]));

        if (self::$savePDF) {
            $this->forward('App\Controller\PDF\ApplicationController::createPDF');
            $this->generatePDF($session,$completePDF,'complete');
            self::$pdf->removeTemporaryFiles();
            return new Response();
        }
        return new Response($completePDF);
    }
}