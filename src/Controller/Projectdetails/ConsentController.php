<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\ConsentType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConsentController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.self::consentNode,self::consentNode)]
    public function showConsent(Request $request): Response
    {
        $session = $request->getSession();
        $routeParams = $request->get('_route_params');
        $appNode = $this->getXMLfromSession($session,setRecent: true); // if no information is given/chosen, docNameRecent equals docName
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($this->checkInactivePage($measureNode,self::consentNode)) { // page was opened before a proposal was created/loaded, a non-existent study / group / measure time point was opened, or the current measure time point is reanalysis
            return $this->redirectToRoute('app_main');
        }
        $information = $this->getInformation($appNode,$routeParams);
        $measureArray = $this->xmlToArray($measureNode);
        $consentNode = $measureNode->{self::consentNode};
        $groupsArray = $measureArray[self::groupsNode];
        $examined = $groupsArray[self::examinedPeopleNode];
        $addressee = $this->getAddresseeFromRequest($request);
        $hasInformationII = $addressee!==self::addresseeParticipants;
        $emptyPre = ['',self::pre];
        $isPreEmpty = in_array($information,$emptyPre);
        $isPreEmptyParticipants = in_array($hasInformationII ? $this->getInformationString($measureArray[self::informationIINode]) : '',$emptyPre);
        // check if inputs on texts are made that may be deleted
        $measureArrayLoad = $this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams));
        $introArrayLoad = $measureArrayLoad[self::textsNode][self::introNode] ?? [];
        $hasIntroDescription = array_key_exists(self::descriptionNode,$introArrayLoad);
        $isNoConsentLoad = $measureArrayLoad[self::consentNode][self::consent][self::chosen]===self::voluntaryConsentNo;
        $textInput = '';
        if ($hasIntroDescription && $introArrayLoad[self::descriptionNode]!=='' && $isNoConsentLoad) {
            $inputArray = $this->setInputArray();
            if ($this->checkInput($measureArrayLoad[self::textsNode][self::introNode],[self::descriptionNode => ''])) {
                $this->addInputPage('multiple.inputs.pages.',self::textsNode.'Intro',$inputArray);
            }
            $textInput = $this->setInputHint($inputArray);
        }

        $consent = $this->createFormAndHandleRequest(ConsentType::class,$this->xmlToArray($consentNode),$request,[self::informationNode => $information, self::addresseeType => $this->getAddresseeFromRequest($request), self::dummyParams => ['isAttendance' => ($measureArray[self::informationNode][self::attendanceNode] ?? '')==='0', 'isClosedDependent' => $examined!=='' && array_key_exists(self::dependentExaminedNode,$examined) || $groupsArray[self::closedNode][self::chosen]==='0', 'hasTerminateParticipants' => array_key_exists(self::terminateParticipantsNode,$measureArray[self::consentNode])]]);
        if ($consent->isSubmitted()) {
            $consentNew = $this->getDataAndConvert($consent,$consentNode)[self::consentNode][self::chosen];
            $isPrePost = $information===self::pre && $this->getReviewDocs($session) || $information===self::post;
            if ($isPrePost) {
                [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
                $introNode = $measureNodeNew->{self::textsNode}->{self::introNode};
                if ($hasIntroDescription && $isNoConsentLoad && in_array($consentNew,self::consentTypesAll)) { // no consent and now any consent -> remove description node for intro
                    $this->removeElement(self::descriptionNode,$introNode);
                } elseif (!$hasIntroDescription && !$isNoConsentLoad && $consentNew===self::voluntaryConsentNo) { // any consent and now no consent -> add description node for intro
                    $introNode->addChild(self::descriptionNode);
                }
            }
            $isNotLeave = !$this->getLeavePage($consent,$session,self::consentNode);
            return $this->saveDocumentAndRedirect($request,!$isPrePost || $isNotLeave ? $appNode : $appNodeNew, $isPrePost && $isNotLeave ? $appNodeNew : null); // appNodeNew is only defined if $isPrePost is true
        }
        return $this->render('Projectdetails/consent.html.twig',
            $this->setRenderParameters($request,$consent,
                array_merge(
                ['textInput' => $textInput,
                        'ensureTypes' => self::voluntaryEnsureTypes,
                        'ensureTypesOther' => self::voluntaryEnsureTypesOther,
                        'headingParams' => [self::addressee => $addressee, 'type' => $isPreEmpty && (!$hasInformationII || $isPreEmptyParticipants) ? 'onlyPre' : (!$isPreEmpty && (!$hasInformationII || !$isPreEmptyParticipants) ? 'onlyNotPre' : 'both')]],
                $addressee!==self::addresseeParticipants ? ['labelParamsParticipants' => [self::addressee => $this->getAddresseeString($addressee,false), self::participant => $this->getAddresseeString($addressee,false,true,true)]] : []),'projectdetails.consent',true));
    }
}