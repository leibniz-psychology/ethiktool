<?php

namespace App\Controller\PDF;

use App\Abstract\PDFAbstract;
use App\Traits\Main\CompleteFormTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CompletePDFController extends PDFAbstract
{
    use CompleteFormTrait;

    public function createPDF(Request $request, array $additional): Response {
        $session = $request->getSession();
        $completeArray = $this->xmlToArray($this->getXMLfromSession($session))[self::completeFormNodeName];
        $committeeParams = $session->get(self::committeeSession);
        $completePDF = $this->renderView('PDF/_completePDF.html.twig',[
            self::committeeType => $this->getCommitteeType($session),
            'committeeParams' => $committeeParams,
            self::isCommitteeBeta => $committeeParams[self::isCommitteeBeta],
            'savePDF' => self::$savePDF,
            self::content => $additional,
            'messages' => $completeArray[self::descriptionNode],
            self::bias => $completeArray[self::bias],
            'toolVersion' => self::toolVersion]);
        $this->forward('App\Controller\PDF\ApplicationController::createPDF');

        if (self::$savePDF) {
            $this->generatePDF($session,$completePDF,'complete');
            self::$pdf->removeTemporaryFiles();
            return new Response();
        }
        return new Response($completePDF.$session->get(self::pdfApplication).$session->get(self::pdfParticipation));
    }
}