<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\ConsentType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConsentController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'consent', name: 'app_consent')]
    public function showConsent(Request $request): Response {
        $session = $request->getSession();
        $routeParams = $request->get('_route_params');
        $information = $this->getInformation($request);
        $isInformation = $information==='pre';
        $appNode = $this->getXMLfromSession($session,setRecent: $isInformation);
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measureNode===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $measureArray = $this->xmlToArray($measureNode);
        $consentNode = $measureNode->{self::consentNode};
        $isLoanReceipt = $this->getTemplateChoice($this->getLoanReceipt($measureArray[self::measuresNode][self::loanNode]));
        $groupsArray = $measureArray[self::groupsNode];
        $examined = $groupsArray[self::examinedPeopleNode];

        $consent = $this->createFormAndHandleRequest(ConsentType::class,$this->xmlToArray($consentNode),$request,[self::informationNode => $information, self::addresseeType => $this->getAddresseeFromRequest($request), self::dummyParams => ['isAttendance' => ($measureArray[self::informationNode][self::attendanceNode] ?? '')==='0', 'isClosedDependent' => $examined!=='' && array_key_exists(self::dependentExaminedNode,$examined) || $groupsArray[self::closedNode][self::chosen]==='0']]);
        if ($consent->isSubmitted()) {
            $isConsentOld = $this->getAnyConsent($this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams)->{self::consentNode}));
            $isConsent = in_array($this->getDataAndConvert($consent,$consentNode)[self::consentNode][self::chosen],self::consentTypesAny);
            if ($isInformation) {
                [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
                $legalNode = $measureNodeNew->{self::legalNode};
                if ($isConsentOld && !$isConsent) { // remove nodes
                    foreach (array_diff(self::legalTypes,$isLoanReceipt ? [self::apparatusNode] : []) as $type) {
                        $this->removeElement($type,$legalNode);
                    }
                }
                elseif (!$isConsentOld && $isConsent) { // add nodes
                    $this->addLegalNodes($legalNode,$measureArray);
                }
            }
            $isNotLeave = !$this->getLeavePage($consent,$session,self::consentNode);
            return $this->saveDocumentAndRedirect($request,!$isInformation || $isNotLeave ? $appNode : $appNodeNew, $isInformation && $isNotLeave ? $appNodeNew : null); // appNodeNew is only defined if $isInformation is true
        }
        return $this->render('Projectdetails/consent.html.twig',
            $this->setRenderParameters($request,$consent,
                ['textInput' => $this->getLegalInput($this->setInputArray(),$measureArray,!$isLoanReceipt)],'projectdetails.consent',true));
    }
}