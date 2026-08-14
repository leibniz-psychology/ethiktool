<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\BurdensRisksType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BurdensRisksController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.self::burdensRisksNode,self::burdensRisksNode)]
    public function showBurdensRisks(Request $request): Response
    {
        $session = $request->getSession();
        $routeParams = $this->getRouteParams($request);
        $appNode = $this->getXMLfromSession($session); // no setRecent because first it needs to be checked if docNameRecent needs to be set
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($this->checkInactivePage($measureNode,self::burdensRisksNode)) { // page was opened before a proposal was created/loaded, a non-existent study / group / measure time point was opened, or the current measure time point is reanalysis
            return $this->redirectToRoute('app_main');
        }
        $textsArray = $this->xmlToArray($measureNode->{self::textsNode});
        $isTexts = $textsArray!==[];
        if ($isTexts && !$session->has(self::docNameRecent)) {
            $session->set(self::docNameRecent,$session->get(self::docName));
        }
        $burdensRisksNode = $measureNode->{self::burdensRisksNode};
        $textInputCon = '';
        if ($isTexts) { // check if texts page has input that may be deleted
            $conArray = $textsArray[self::conNode];
            $inputArray = $this->setInputArray();
            $burdensRisksArrayLoad = $this->xmlToArray($this->getMeasureTimePointNode($request,getFirst: true)->{self::burdensRisksNode});
            // con
            if (($this->getBurdensOrRisks($burdensRisksArrayLoad,self::burdensNode)[0] || $this->getBurdensOrRisks($burdensRisksArrayLoad,self::risksNode)[0]) && $conArray[self::conTemplate]==='1' && $this->checkInput($conArray,[self::descriptionNode => ''])) {
                $this->addInputPage('multiple.inputs.pages.','textsCon',$inputArray);
            }
            $textInputCon = $this->setInputHint($inputArray);
        }

        // text hints for informing text field
        $addresseeStringParam = [self::addressee => $this->getAddresseeString($this->getAddresseeFromRequest($request))];
        $informingHints = [];
        foreach (['noTemplate','cloze',self::informingNo] as $type) {
            $informingHints[$type] = $this->translateString('projectdetails.pages.'.self::burdensRisksNode.'.'.self::findingNode.'.'.self::informingNode.'.textHints.'.$type,$addresseeStringParam);
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
                } elseif (!$isBurdensRisks && $conArray[self::conTemplate]==='1' && $isDescription) {
                    $this->removeElement(self::descriptionNode,$conNode);
                }
            }
            $isNotLeave = !$this->getLeavePage($burdensRisks,$session,self::burdensRisksNode);
            return $this->saveDocumentAndRedirect($request,!$isTexts || $isNotLeave ? $appNode : $appNodeNew,$isTexts && $isNotLeave ? $appNodeNew : null); // appNodeNew is defined if isTexts is true
        }
        return $this->render('Projectdetails/burdensRisks.html.twig',
            $this->setRenderParameters($request,$burdensRisks,
                ['burdensRisksTypes' => self::burdensRisksTypes,
                 'burdensRisksTypesAll' => self::burdensRisksTypesAll,
                 'burdensRisksOther' => self::burdensRisksOther,
                 'burdensRisksIcons' => self::burdensRisksIcons,
                 'risksNoMeasureTypes' => self::risksNoMeasureTypes,
                 'isDebriefing' => $this->getStringFromBool($this->xmlToArray($measureNode->{self::informationIIINode})!==[]),
                 'informingTemplates' => array_diff(array_values(self::informingTypes),[self::informingNo]),
                 'informingHints' => $informingHints,
                 'maxCharsFinding' => 500,
                 'textInputCon' => $textInputCon],'projectdetails.burdensRisks',true));
    }
}
