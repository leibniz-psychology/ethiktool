<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\MeasuresType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MeasuresController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.self::measuresNode,self::measuresNode)]
    public function showMeasures(Request $request): Response
    {
        $session = $request->getSession();
        $routeParams = $request->get('_route_params');
        $appNode = $this->getXMLfromSession($session,setRecent: true); // if no pre information is given, docNameRecent and docName are equal
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($this->checkInactivePage($measureNode,self::measuresNode)) { // page was opened before a proposal was created/loaded, a non-existent study / group / measure time point was opened, or the current measure time point is reanalysis
            return $this->redirectToRoute('app_main');
        }
        $hasDocs = $this->getReviewDocs($session);
        $measureArrayOld = $this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams));
        $measuresNode = $measureNode->{self::measuresNode};
        $measuresArrayOld = $measureArrayOld[self::measuresNode];
        $textInputOnline = '';
        $textInputs = [self::apparatusNode => '', self::insuranceWayNode => '', 'both' => ''];
        if ($hasDocs) { // check inputs if participation documents may be created
            $inputPrefix = 'multiple.inputs.pages.';
            // check if legal page has input that may be deleted
            $locationOld = $measuresArrayOld[self::locationNode][self::chosen];
            $isLocationOnlineOld = $locationOld===self::locationOnline;
            $isLocationNotOnlineOld = $locationOld!=='' && !$isLocationOnlineOld;
            $isLoanOld = $measuresArrayOld[self::loanNode][self::chosen]==='0';
            $legalArray = $this->xmlToArray($measureNode)[self::legalNode];
            if ($legalArray!=='') {
                $apparatusInput = ($isLoanOld || $isLocationNotOnlineOld) && ($legalArray[self::apparatusNode][self::chosen] ?? '')!=='';
                $insuranceWayInput = $isLocationNotOnlineOld && ($legalArray[self::insuranceWayNode][self::chosen] ?? '')!=='';
                if ($apparatusInput || $insuranceWayInput) {
                    if ($isLoanOld && $apparatusInput) { // loan was answered with yes
                        $inputArray = $this->setInputArray();
                        $this->addInputPage($inputPrefix,'consentApparatusInsuranceWay',$inputArray,['input' => self::apparatusNode]);
                        $textInputs[self::apparatusNode] = $this->setInputHint($inputArray); // will only be shown if location is still not chosen or online and loan is changed to no
                    }
                    if ($insuranceWayInput) { // location was chosen and not online
                        $inputArray = $this->setInputArray();
                        $this->addInputPage($inputPrefix,'consentApparatusInsuranceWay',$inputArray,['input' => self::insuranceWayNode]);
                        $textInputs[self::insuranceWayNode] = $this->setInputHint($inputArray); // will only be shown if location is changed to online and loan is answered with / changed to yes
                    }
                    if ($isLocationNotOnlineOld) { // location was chosen and not online
                        $inputArray = $this->setInputArray();
                        $this->addInputPage($inputPrefix,'consentApparatusInsuranceWay',$inputArray,['input' => 'both']);
                        $textInputs['both'] = $this->setInputHint($inputArray); // will only be shown if location is changed to online and loan is answered with / changed to no
                    }
                }
            }
            // check if data privacy page has input that may be deleted
            $privacyArrayOld = $measureArrayOld[self::privacyNode];
            $dataOnline = $privacyArrayOld[self::dataOnlineNode][self::chosen] ?? '';
            if ($dataOnline!=='') { // data online question was answered
                $hasPurpose = false;
                foreach ([self::purposeResearchNode,self::purposeFurtherNode] as $type) {
                    $technicalKey = ($type===self::purposeFurtherNode ? self::purposeFurtherNode : '').self::purposeTechnical;
                    $tempArray = $privacyArrayOld[$type] ?? '';
                    $hasPurpose = $hasPurpose || $tempArray!=='' && array_key_exists($technicalKey,$tempArray) && $tempArray[$technicalKey]!=='';
                }
                $inputArray = $this->setInputArray();
                $this->addInputPage($inputPrefix,'dataPrivacyOnline', $inputArray,['isProcessing' => $this->getStringFromBool($dataOnline===self::dataOnlineTechnical), 'isPurposeQuestions' => $this->getStringFromBool($hasPurpose), 'isPurposeData' => $this->getStringFromBool($hasPurpose && !in_array($privacyArrayOld[self::dataPersonalNode],self::dataPersonal) && array_key_exists(self::dataResearchNode,$privacyArrayOld))]);
                $textInputOnline = $this->setInputHint($inputArray);
            }
        }
        // check if compensation page has input that may be deleted
        $textInputCompensation = '';
        $terminateArray = $measureArrayOld[self::compensationNode][self::terminateNode] ?? [];
        $isTerminateNothing = ($terminateArray[self::chosen] ?? '')===self::terminateNothing;
        $isDurationOld = $this->getDuration($measuresArrayOld[self::durationNode])>30;
        if ($isDurationOld && $isTerminateNothing && ($terminateArray[self::descriptionNode] ?? '')!=='') {
            $inputArray = $this->setInputArray();
            $this->addInputPage('pages.projectdetails.',self::compensationNode,$inputArray);
            $textInputCompensation = $this->setInputHint($inputArray);
        }

        $measures = $this->createFormAndHandleRequest(MeasuresType::class, $this->xmlToArray($measuresNode),$request,[self::dummyParams => ['hasLocation' => array_key_exists(self::locationNode,$measuresArrayOld)]]);
        if ($measures->isSubmitted()) {
            $data = $this->getDataAndConvert($measures,$measuresNode);
            [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
            if ($hasDocs) { // update only if participation documents may be created
                $location = $data[self::locationNode][self::chosen];
                $isLocationOnline = $location===self::locationOnline;
                $isLocationNotOnline = $location!==null && !$isLocationOnline;
                if ($this->getInformationString($measureArrayOld[self::informationNode])===self::pre) { // only update if information is pre
                    // update legal
                    $legalNode = $measureNodeNew->{self::legalNode};
                    $isApparatusOld = $isLoanOld || $isLocationNotOnlineOld;
                    $isApparatus = ($data[self::loanNode][self::chosen]===0 || $isLocationNotOnline);
                    // remove nodes if necessary
                    if ($isApparatusOld && !$isApparatus) { // remove node
                        $this->removeElement(self::apparatusNode,$legalNode);
                    } elseif (!$isApparatusOld && $isApparatus) { // add node
                        $this->addChosenNode($legalNode,self::apparatusNode);
                    }
                    if ($isLocationNotOnlineOld && !$isLocationNotOnline) { // remove node
                        $this->removeElement(self::insuranceWayNode,$legalNode);
                    } elseif (!$isLocationNotOnlineOld && $isLocationNotOnline) { // add node
                        $this->addChosenNode($legalNode,self::insuranceWayNode);
                    }
                }
                // update data privacy
                $dataPrivacyNode = $measureNodeNew->{self::privacyNode};
                if ($isLocationOnlineOld && $isLocationNotOnline) { // remove node -> if $isLocationOnlineOld is true, $location can not be null
                    $this->removeElement(self::dataOnlineNode,$dataPrivacyNode);
                    if ($this->checkElement(self::purposeResearchNode,$dataPrivacyNode)) {
                        $this->removeElement(self::purposeTechnical,$dataPrivacyNode->{self::purposeResearchNode});
                    }
                    if ($this->checkElement(self::purposeFurtherNode,$dataPrivacyNode)) {
                        $this->removeElement(self::purposeFurtherNode.self::purposeTechnical,$dataPrivacyNode->{self::purposeFurtherNode});
                    }
                } elseif (!$isLocationOnlineOld && $isLocationOnline && $this->checkElement(self::dataPersonalNode,$dataPrivacyNode)) { // add node -> only if privacy document should (and can) be created with tool
                    $this->insertElementBefore(self::dataOnlineNode,$dataPrivacyNode->{self::dataPersonalNode},[self::chosen]);
                }
            }
            // update compensation
            if ($isTerminateNothing) {
                $isDurationNew = $this->getDuration($data[self::durationNode])>30;
                $terminateNode = $measureNodeNew->{self::compensationNode}->{self::terminateNode};
                if ($isDurationOld && !$isDurationNew) { // duration was longer than 30 minutes, but now is not -> remove node
                    $this->removeElement(self::descriptionNode,$terminateNode);
                } elseif (!$isDurationOld && $isDurationNew) { // duration was at most 30 minutes, but is now longer -> add node
                    $terminateNode->addChild(self::descriptionNode);
                }
            }

            $isNotLeave = !$this->getLeavePage($measures,$session,self::measuresNode);
            return $this->saveDocumentAndRedirect($request,$isNotLeave ? $appNode : $appNodeNew, $isNotLeave ? $appNodeNew : null); // $appNodeNew is only defined if $hasDocs is true
        }
        return $this->render('Projectdetails/measures.html.twig',
            $this->setRenderParameters($request,$measures,
                ['maxCharsProcedure' => 1000,
                 'measuresTypes' => self::measuresTypes,
                 'interventionsTypes' => self::interventionsTypes,
                 'measuresInterventionsOther' => self::measuresInterventionsOther,
                 'measureTimeTypes' => self::durationMeasureTimeTypes,
                 'textInputs' => $textInputs,
                 'textInputOnline' => $textInputOnline,
                 'textInputCompensation' => $textInputCompensation],'projectdetails.measures',true));
    }
}