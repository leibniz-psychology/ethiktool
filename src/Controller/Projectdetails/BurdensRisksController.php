<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\BurdensRisksType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BurdensRisksController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'burdensRisks', name: 'app_burdensRisks')]
    public function showBurdensRisks(Request $request): Response {
        $session = $request->getSession();
        $routeParams = $request->get('_route_params');
        $appNode = $this->getXMLfromSession($session); // no setRecent because first it needs to be checked if docNameRecent needs to be set
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measureNode===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $textsArray = $this->xmlToArray($measureNode->{self::textsNode});
        $isTexts = $textsArray!==[];
        if ($isTexts && !$session->has(self::docNameRecent)) {
            $session->set(self::docNameRecent,$session->get(self::docNameRecent));
        }
        $burdensRisksNode = $measureNode->{self::burdensRisksNode};
        [$textInputCon,$textInputFinding] = ['',''];
        if ($isTexts) { // check if texts page has input that may be deleted
            $conArray = $textsArray[self::conNode];
            $translationPrefix = 'multiple.inputs.pages.';
            $inputArray = $this->setInputArray();
            $burdensRisksArrayLoad = $this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams)->{self::burdensRisksNode});
            // con
            if (($this->getBurdensOrRisks($burdensRisksArrayLoad,self::burdensNode)[0] || $this->getBurdensOrRisks($burdensRisksArrayLoad,self::risksNode)[0]) && $conArray[self::conTemplate]==='1' && $this->checkInput($conArray,[self::descriptionNode => ''])) {
                $this->addInputPage($translationPrefix,'textsCon',$inputArray);
            }
            $textInputCon = $this->setInputHint($inputArray);
            // finding
            if (array_key_exists(self::findingTextNode,$textsArray)) {
                $inputArray = $this->setInputArray();
                $findingArray = $textsArray[self::findingTextNode];
                if ($burdensRisksArrayLoad[self::findingNode][self::chosen]==='0' && ($findingArray[self::findingTemplate]!=='' || $this->checkInput($findingArray,[self::descriptionNode => '']))) {
                    $this->addInputPage($translationPrefix,'textsFinding',$inputArray);
                }
                $textInputFinding = $this->setInputHint($inputArray);
            }
        }
        $iconArray = [];
        $translationPrefix = 'projectdetails.pages.burdensRisks.';
        foreach ([self::burdensNode,self::risksNode] as $type) {
            $typeUC = ucfirst($type);
            $types = array_diff($type===self::burdensNode ? self::burdensTypes : self::risksTypes,['no'.$typeUC,'other'.$typeUC]);
            $iconArray[$type] = array_combine($types,$this->prefixArray($types,$translationPrefix.$type.'.hintsTypes.'));
        }

        $burdensRisks = $this->createFormAndHandleRequest(BurdensRisksType::class,$this->xmlToArray($burdensRisksNode),$request);
        if ($burdensRisks->isSubmitted()) {
            $data = $this->getDataAndConvert($burdensRisks,$burdensRisksNode);
            $isBurdensRisks =  $this->getBurdensOrRisks($data,self::burdensNode)[0] || $this->getBurdensOrRisks($data,self::risksNode)[0];
            if ($isTexts) {
                [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);

                // update con description
                $textsNode = $measureNodeNew->{self::textsNode};
                $conNode = $textsNode->{self::conNode};
                $isDescription = array_key_exists(self::descriptionNode,$conArray);
                if ($isBurdensRisks && !$isDescription) {
                    $conNode->addChild(self::descriptionNode);
                }
                elseif (!$isBurdensRisks && $conArray[self::conTemplate]==='1' && $isDescription) {
                    $this->removeElement(self::descriptionNode,$conNode);
                }
                // update finding consent
                $isFinding = $data[self::findingNode][self::chosen]===0;
                $isFindingConsent = array_key_exists(self::findingTextNode,$textsArray);
                if ($isFinding && !$isFindingConsent) {
                    $this->addChildNodes($textsNode->addChild(self::findingTextNode),[self::findingTemplate,self::descriptionNode]);
                }
                elseif (!$isFinding && $isFindingConsent) {
                    $this->removeElement(self::findingTextNode,$textsNode);
                }
            }
            $isNotLeave = !$this->getLeavePage($burdensRisks,$session,self::burdensRisksNode);
            return $this->saveDocumentAndRedirect($request,!$isTexts || $isNotLeave ? $appNode : $appNodeNew,$isTexts && $isNotLeave ? $appNodeNew : null); // appNodeNew is defined if isTexts is true
        }
        return $this->render('Projectdetails/burdensRisks.html.twig',
            $this->setRenderParameters($request,$burdensRisks,
                ['checkboxTypes' => [self::burdensNode => self::burdensTypes,self::risksNode => self::risksTypes],
                 'iconArrays' => $iconArray,
                 'textInputCon' => $textInputCon,
                 'textInputFinding' => $textInputFinding],'projectdetails.burdensRisks',true));
    }
}