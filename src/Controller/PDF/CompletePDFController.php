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
        $appArray = $this->xmlToArray($this->getXMLfromSession($session));
        $completeArray = $appArray[self::completeFormNodeName];
        $committeeParams = $session->get(self::committeeParams);
        $committeeType = $committeeParams[self::committeeType];
        $completePDF = $this->renderView('PDF/_completePDF.html.twig',array_merge($committeeParams,[
            self::committeeType => $committeeType,
            self::isCommitteeBeta => $committeeParams[self::isCommitteeBeta],
            self::committeeParams => $committeeParams,
            'isFull' => $this->getStringFromBool(str_contains((string) $session->get(self::reviewProcess),self::reviewProcessFull)),
            'briefReports' => $this->getBriefReport($session,false),
            'savePDF' => self::$savePDF,
            'hints' => [$this->translateString('completeForm.finish.text.end.title',['isTool' => 'false']).':', $this->getFinishEndText($session,false, $this->checkSupervisor($committeeType,$appArray[self::appDataNodeName][self::coreDataNode][self::applicant][self::position]))],
            self::content => $additional,
            'messages' => $completeArray[self::descriptionNode],
            self::bias => $completeArray[self::bias],
            'toolVersion' => self::toolVersion,
            'isNotParticipation' => true]));

        if (self::$savePDF) {
            $this->forward(ApplicationController::class.'::createPDF');
            $this->generatePDF($session,$completePDF,'complete');
            self::$pdf->removeTemporaryFiles();
            return new Response();
        }
        return new Response($completePDF);
    }
}
