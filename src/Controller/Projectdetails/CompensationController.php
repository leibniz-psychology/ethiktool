<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\CompensationType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        if ($this->checkInactivePage($measure,self::compensationNode)) { // page was opened before a proposal was created/loaded, a non-existent study / group / measure time point was opened, or the current measure time point is reanalysis
            return $this->redirectToRoute('app_main');
        }
        $compensationNode = $measure->{self::compensationNode}[0];
        $hasDocs = $this->getReviewDocs($session);
        $isCodeCompensationLoad = $this->checkCompensationAwarding($this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams)->{self::compensationNode}[0]));
        $textInput = '';
        $measureArray = $this->xmlToArray($measure);
        if ($hasDocs) {
            // check if inputs were made for the code compensation question on data privacy
            $inputArray = $this->setInputArray();
            if ($this->checkInput($measureArray[self::privacyNode][self::codeCompensationNode] ?? '',[self::chosen => ''])) {
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

        $compensation = $this->createFormAndHandleRequest(CompensationType::class, $this->xmlToArray($compensationNode),$request,[self::informationNode => $this->getInformationString($measureArray[self::informationNode]), self::dummyParams => array_merge($isDurationParam,['hasDetails' => !(in_array($this->getCommitteeType($session),self::reviewShortChoose) && in_array($session->get(self::reviewProcess),[self::reviewShortBegun,self::reviewShortRequested]))])]);
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
                 'otherHints' => [$this->translateString($tempPrefix),$this->translateString($tempPrefix.'Optional')],
                 'voluntaryTypes' => self::compensationVoluntaryTypes]),'projectdetails.compensation',true));
    }
}