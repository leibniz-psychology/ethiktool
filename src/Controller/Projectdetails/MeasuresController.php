<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\MeasuresType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MeasuresController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'measures', name: 'app_measures')]
    public function showMeasures(Request $request): Response {
        $session = $request->getSession();
        $routeParams = $request->get('_route_params');
        $isPre = $this->getInformation($request)===self::pre;
        $appNode = $this->getXMLfromSession($session,setRecent: $isPre);
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measureNode===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $measureArrayOld = $this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams));
        $measuresNode = $measureNode->{self::measuresNode};
        $measuresArray = $this->xmlToArray($measuresNode);
        $measuresArrayOld = $measureArrayOld[self::measuresNode];
        $locationOld = $measuresArrayOld[self::locationNode][self::chosen];
        $isLocationOnlineOld = $locationOld===self::locationOnline;
        $isLocationNotOnlineOld = $locationOld!=='' && !$isLocationOnlineOld;
        $loanArrayOld = $measuresArrayOld[self::loanNode];
        $isLoanOld = $loanArrayOld[self::chosen]==='0';
        $textInputOnline = '';
        $inputPrefix = 'multiple.inputs.pages.';
        // check if data privacy page has input that may be deleted
        $privacyArrayOld = $measureArrayOld[self::privacyNode];
        $dataOnline = $privacyArrayOld[self::dataOnlineNode] ?? '';
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
        $textInputs = [self::apparatusNode => '', self::insuranceWayNode => '', 'both' => ''];
        $legalArray = $this->xmlToArray($measureNode)[self::legalNode];
        $isConsent = $this->getAnyConsent((string) $measureNode->{self::consentNode}->{self::consentNode}->{self::chosen});
        if ($legalArray!=='') {
            // check if legal page has input that may be deleted
            $apparatusInput = ($isLoanOld || $isLocationNotOnlineOld) && ($legalArray[self::apparatusNode][self::chosen] ?? '')!=='';
            $insuranceWayInput = $isLocationNotOnlineOld && $isConsent && ($legalArray[self::insuranceWayNode][self::chosen] ?? '')!=='';
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

        $measures = $this->createFormAndHandleRequest(MeasuresType::class,$measuresArray,$request);
        if ($measures->isSubmitted()) {
            $data = $this->getDataAndConvert($measures,$measuresNode);
            $location = $data[self::locationNode][self::chosen];
            $isLocationOnline = $location===self::locationOnline;
            $isLocationNotOnline = $location!==null && !$isLocationOnline;
            [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
            if ($isPre) { // only update if information is pre
                // update legal
                $legalNode = $measureNodeNew->{self::legalNode};
                $loanArray = $data[self::loanNode];
                $isLoan = $loanArray[self::chosen]===0;
                $isApparatusOld = ($isLoanOld && $this->getTemplateChoice($loanArrayOld[self::loanReceipt][self::chosen]) || $isConsent && ($isLoanOld || $isLocationNotOnlineOld));
                $isApparatus = ($isLoan && $this->getTemplateChoice($this->getLoanReceipt($loanArray)) || $isConsent && ($isLoan || $isLocationNotOnline));
                // remove nodes if necessary
                if ($isApparatusOld && !$isApparatus) { // remove node
                    $this->removeElement(self::apparatusNode,$legalNode);
                }
                elseif (!$isApparatusOld && $isApparatus) { // add node
                    $this->addChosenNode($legalNode,self::apparatusNode);
                }
                if ($isConsent) {
                    if ($isLocationNotOnlineOld && !$isLocationNotOnline) { // remove node
                        $this->removeElement(self::insuranceWayNode,$legalNode);
                    }
                    elseif (!$isLocationNotOnlineOld && $isLocationNotOnline) { // add node
                        $this->addChosenNode($legalNode,self::insuranceWayNode);
                    }
                }
            }
            // update data privacy
            $dataPrivacyNode = $measureNodeNew->{self::privacyNode};
            if ($isLocationOnlineOld && $isLocationNotOnline) { // remove node -> if $isLocationOnlineOld is true, $location can not be null
                $this->removeElement(self::dataOnlineNode,$dataPrivacyNode);
                $this->removeElement(self::dataOnlineProcessingNode,$dataPrivacyNode);
                if ($this->checkElement(self::purposeResearchNode,$dataPrivacyNode)) {
                    $this->removeElement('technical',$dataPrivacyNode->{self::purposeResearchNode});
                }
                if ($this->checkElement(self::purposeFurtherNode,$dataPrivacyNode)) {
                    $this->removeElement('technicalFurther',$dataPrivacyNode->{self::purposeFurtherNode});
                }
            }
            elseif (!$isLocationOnlineOld && $isLocationOnline && $this->checkElement(self::dataPersonalNode,$dataPrivacyNode)) { // add node -> only if privacy document should (and can) be created with tool
                $this->insertElementBefore(self::dataOnlineNode,$dataPrivacyNode->{self::dataPersonalNode});
            }
            $isNotLeave = !$this->getLeavePage($measures,$session,self::measuresNode);
            return $this->saveDocumentAndRedirect($request,!$isPre || $isNotLeave ? $appNode : $appNodeNew, $isPre && $isNotLeave ? $appNodeNew : null); // $appNodeNew is only defined if $isPre is true
        }
        return $this->render('Projectdetails/measures.html.twig',
            $this->setParameters($request,$appNode,
                [self::content => $measures,
                 self::pageTitle => 'projectdetails.measures',
                 'measuresTypes' => self::measuresTypes,
                 'interventionsTypes' => self::interventionsTypes,
                 'durationTypes' => self::durationTypes,
                 'textInputs' => $textInputs,
                 'textInputOnline' => $textInputOnline,
                 'isConsent' => $isConsent]));
    }
}