<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\CompensationType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompensationController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.self::compensationNode,self::compensationNode)]
    public function showCompensation(Request $request): Response
    {
        $routeParams = $request->get('_route_params');
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session);
        $measure = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measure===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $compensationNode = $measure->{self::compensationNode}[0];
        $hasDocs = $this->getReviewDocs($session);
        $isCodeCompensationLoad = $this->checkCompensationAwarding($this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams)->{self::compensationNode}[0]));
        $textInput = '';
        if ($hasDocs) {
            // check if inputs were made for the code compensation question on data privacy
            $inputArray = $this->setInputArray();
            if ($this->checkInput($this->xmlToArray($measure)[self::privacyNode][self::codeCompensationNode] ?? '',[self::chosen => ''])) {
                $this->addInputPage('pages.projectdetails.',self::privacyNode,$inputArray);
            }
            $textInput = $this->setInputHint($inputArray);
        }
        $translationPrefix = 'projectdetails.pages.'.self::compensationNode.'.';
        // get date for later text hint
        try {
            $date = (new \DateTime())->add(new \DateInterval('P6M'))->format($this->translateString($translationPrefix.self::awardingNode.'.dateFormat'));
        } catch (\Throwable) {
            $date = '';
        }
        $isDurationParam = ['isDuration' => $this->getDuration($this->xmlToArray($measure->{self::measuresNode}->{self::durationNode}))>30];

        $compensation = $this->createFormAndHandleRequest(CompensationType::class, $this->xmlToArray($compensationNode),$request,[self::dummyParams => array_merge($isDurationParam,['hasDetails' => !$this->getShortBegunRequested($request)])]);
        if ($compensation->isSubmitted()) {
            $data = $this->getDataAndConvert($compensation,$compensationNode);
            if ($hasDocs) {
                [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
                $isCodeCompensation = $this->checkCompensationAwarding($data);
                $privacyNode = $measureNodeNew->{self::privacyNode};
                if (!$isCodeCompensationLoad && $isCodeCompensation) { // eventually add nodes
                    $privacyArray = $this->xmlToArray($privacyNode);
                    $tempArray = $privacyArray[self::purposeResearchNode] ?? '';
                    if (array_key_exists(self::dataPersonalNode,$privacyArray) && ($tempArray==='' || !array_key_exists(self::purposeCompensation,$tempArray))) { // add nodes
                        $this->addChosenNode($privacyNode,self::codeCompensationNode);
                    }
                } elseif ($isCodeCompensationLoad && !$isCodeCompensation) { // remove nodes
                    $this->removeElement(self::codeCompensationNode,$privacyNode);
                }
            }
            $isNotLeave = !$this->getLeavePage($compensation,$session,self::compensationNode);
            return $this->saveDocumentAndRedirect($request,!$hasDocs || $isNotLeave ? $appNode : $appNodeNew, $hasDocs && $isNotLeave ? $appNodeNew : null); // $appNodeNew is only defined is $hasDocs is true
        }
        $tempPrefix = $translationPrefix.self::compensationTextNode.'.textHint';
        return $this->render('Projectdetails/compensation.html.twig',
            $this->setRenderParameters($request,$compensation,array_merge($isDurationParam,
                ['types' => self::compensationTypes,
                 'textInput' => $textInput,
                 'laterDate' => ['date' => $date],
                 'otherHints' => [$this->translateString($tempPrefix),$this->translateString($tempPrefix.'Optional')]]),'projectdetails.compensation',true));
    }
}