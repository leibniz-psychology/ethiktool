<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\InformationType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InformationController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'information', name: 'app_information')]
    #[Route(self::routePrefix.'informationII', name: 'app_informationII')]
    public function showInformation(Request $request): Response {
        $session = $request->getSession();
        $routeParams = $request->get('_route_params');
        $route = substr($request->get('_route'),4); // 'information' or 'informationII'
        $isInformationII = $route===self::informationIINode;
        $appNode = $this->getXMLfromSession($session,setRecent: !$isInformationII);
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measureNode===null || $measureNode->{$route}->{self::chosen}->getName()==='') { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened ($measure===null) or informationII was opened, but is not active (second check
            return $this->redirectToRoute('app_main');
        }
        $informationNode = $measureNode->{$route};
        $measureArray = $this->xmlToArray($measureNode);
        $isFinding = $measureArray[self::burdensRisksNode][self::findingNode][self::chosen]==='0';
        $addressee = $this->getAddressee($measureArray[self::groupsNode]);
        $addresseeString = $this->getAddresseeString($addressee,!$isInformationII);
        $translationPrefix = 'pages.projectdetails.';
        $inputPrefix = 'multiple.inputs.';
        [$informationIIIinputText,$textInputPre,$textInputPost] = ['','',''];
        $informationIIIinput = false; // gets true if inputs were made
        if (!$isInformationII) { // check if any page whose input may be deleted has input
            $appNodeLoad = $this->getXMLfromSession($session,true);
            $informationLoad = $this->getInformation($appNodeLoad,$routeParams);
            // pre
            $inputArray = $this->setInputArray();
            // informationIII
            $informationIIIinput = $this->getInformationIII($this->xmlToArray($this->getMeasureTimePointNode($appNodeLoad,$routeParams))[self::informationNode]);
            if ($this->checkInput($measureArray[self::informationIIINode],array_combine(array_keys(self::informationIIIInputsTypes),array_fill(0,count(self::informationIIIInputsTypes),'')))) {
                $this->addInputPage($translationPrefix,self::informationIIINode,$inputArray);
                $informationIIIinputText = $this->translateString($inputPrefix.'hint',['pages' => 1, 'page' => '„'.$this->translateString($translationPrefix.self::informationIIINode).'“', 'inputs' => $this->translateString($inputPrefix.self::informationIIINode)]);
            }
            // consent
            $terminateConsArray = $measureArray[self::consentNode][self::terminateConsNode];
            if (array_key_exists(self::terminateConsParticipationNode,$terminateConsArray) && $this->checkInput($terminateConsArray,[self::terminateConsParticipationNode => ''])) {
                $this->addInputPage($translationPrefix,self::consentNode,$inputArray,[self::addressee => $addresseeString]);
            }
            // legal
            $textInputPre = $this->getLegalInput($inputArray,$measureArray);
            // post
            $inputArray = $this->setInputArray();
            // texts
            $textsArray = $measureArray[self::textsNode];
            $conArray = $textsArray[self::conNode] ?? null; // can only be null if tempArray is an empty string
            $findingConsentArray = $textsArray[self::findingTextNode] ?? [];
            if ($textsArray!=='' &&
                ($this->checkInput($textsArray[self::introNode],[self::introTemplate => '', self::descriptionNode => '']) ||
                 $this->checkInput($textsArray,[self::goalsNode => '',self::procedureNode => '']) ||
                 $this->checkInput($textsArray[self::proNode],[self::proTemplate => '', self::descriptionNode => '']) ||
                 $this->checkInput($conArray,array_combine(array_keys($conArray),array_fill(0,count($conArray),''))) ||
                 $findingConsentArray!==[] && $this->checkInput($findingConsentArray,array_combine(array_keys($findingConsentArray),array_fill(0,count($findingConsentArray),'')))
                )
               ) {
                $this->addInputPage($translationPrefix,self::textsNode,$inputArray,['isFinding' => $this->getStringFromBool($isFinding)]);
            }
            $textInputPost = $this->setInputHint($inputArray);
        }

        $information = $this->createFormAndHandleRequest(InformationType::class,$this->xmlToArray($informationNode),$request,
            [self::dummyParams => ['isAttendance' => !$isInformationII && $addressee!==self::addresseeParticipants, 'isInformation' => !$isInformationII],
             self::addresseeString => $addresseeString,
             self::participantsString => $this->getAddresseeString($addressee,!$isInformationII,true, $isInformationII || $addressee===self::addresseeParticipants)]);
        if ($information->isSubmitted()) {
            $data = $this->getDataAndConvert($information,$informationNode);
            if (!$isInformationII) {
                [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);

                // informationIII
                $isInformationIII = $this->getInformationIII($data); // true if inputs are necessary
                $informationIIInode = $measureNodeNew->{self::informationIIINode};
                if ($informationIIIinput && !$isInformationIII) { // remove nodes from informationIII
                    $this->removeAllChildNodes($informationIIInode);
                }
                elseif (!$informationIIIinput && $isInformationIII) { // add nodes to informationIII
                    $this->addChildNodes($informationIIInode,array_keys(self::informationIIIInputsTypes));
                }
                $isPre = $data[self::chosen]===0; // true if pre information
                $isPreOld = $informationLoad===self::pre;
                // consent and texts
                $isPostOld = $informationLoad===self::post;
                $isInformation = $isPre || ($data[self::informationAddNode][self::chosen] ?? 2)===0; // true if either pre or post information
                $terminateConsNode = $measureNodeNew->{self::consentNode}->{self::terminateConsNode};
                $textsNode = $measureNodeNew->{self::textsNode};
                $legalNode = $measureNodeNew->{self::legalNode};
                if ($isPreOld && !$isPre) { // pre information and now no pre information -> remove terminateConsParticipation and legal nodes
                    $this->removeElement(self::terminateConsParticipationNode,$terminateConsNode);
                    $this->removeAllChildNodes($legalNode);
                }
                if (($isPreOld || $isPostOld) && !$isInformation) { // any information and now no information at all -> remove texts nodes
                    $this->removeAllChildNodes($textsNode);
                }
                elseif (!$isPreOld) {
                    if (!$isPostOld && $isInformation) { // no information at all and now any information -> add intro, goals, procedure, pro, con, and eventually finding consent
                        $this->addChildNodes($textsNode,[self::introNode,self::goalsNode,self::procedureNode,self::proNode,self::conNode]);
                        $this->addChildNodes($textsNode->{self::introNode},[self::introTemplate,self::descriptionNode]);
                        $this->addChildNodes($textsNode->{self::proNode},[self::proTemplate,self::descriptionNode]);
                        $this->addChildNodes($textsNode->{self::conNode},[self::conTemplate,self::descriptionNode]);
                        if ($isFinding) {
                            $this->addChildNodes($textsNode->addChild(self::findingTextNode),[self::findingTemplate,self::descriptionNode]);
                        }
                    }
                    if ($isPre) { // no pre information and now pre information
                        if (($this->getAnyConsent($measureArray[self::consentNode]) || $this->getTemplateChoice($this->getLoanReceipt($measureArray[self::measuresNode][self::loanNode])))) { // consent or loan receipt -> add legal nodes
                            $this->addLegalNodes($legalNode,$this->xmlToArray($measureNode));
                        }
                        if (array_key_exists(self::descriptionNode,$terminateConsArray)) { // terminateCons is answered with 'no' -> add terminateConsParticipation node
                            $this->addChildNodes($terminateConsNode,[self::terminateConsParticipationNode]);
                        }
                    }
                }
            }
            $isNotLeave = !$this->getLeavePage($information,$session,self::informationNode);
            return $this->saveDocumentAndRedirect($request,$isInformationII || $isNotLeave ? $appNode : $appNodeNew, !$isInformationII && $isNotLeave ? $appNodeNew : null); // $appNodeNew is only defined if $isInformationII is false
        }
        return $this->render('Projectdetails/information.html.twig',
            $this->setRenderParameters($request,$information,
                array_merge(
                ['isInformation' => !$isInformationII,
                 'informationIIIinput' => $informationIIIinputText,
                 'textInputPre' => $textInputPre,
                 'textInputPost' => $textInputPost],
                  $isInformationII ? [self::participantsString => $this->getAddresseeString($addressee,false),
                                      self::participantsString.self::post => $this->getAddresseeString($addressee,false,true,true)] : []),'projectdetails.information'.($isInformationII ? 'II' : ''),true));
    }
}