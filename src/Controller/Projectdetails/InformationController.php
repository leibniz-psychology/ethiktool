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

    #[Route(self::routePrefix.self::informationNode,self::informationNode)]
    #[Route(self::routePrefix.self::informationIINode,self::informationIINode)]
    public function showInformation(Request $request): Response
    {
        $session = $request->getSession();
        $routeParams = $request->get('_route_params');
        $route = substr($request->get('_route'),4); // 'information' or 'informationII'
        $isInformationII = $route===self::informationIINode;
        $appNode = $this->getXMLfromSession($session,setRecent: !$isInformationII);
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measureNode===null || $measureNode->{$route}->{self::pre}->getName()==='') { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened ($measure===null) or informationII was opened, but is not active (second check)
            return $this->redirectToRoute('app_main');
        }
        $informationNode = $measureNode->{$route}[0];
        $measureArray = $this->xmlToArray($measureNode);
        $isFinding = $measureArray[self::burdensRisksNode][self::findingNode][self::chosen]==='0';
        $addressee = $this->getAddressee($measureArray[self::groupsNode]);
        $addresseeString = $this->getAddresseeString($addressee,!$isInformationII);
        $translationPrefix = 'pages.projectdetails.';
        $inputPrefix = 'multiple.inputs.';
        [$informationIIIinputText,$textInputPre,$textInputPost] = ['','',''];
        $informationIIIinput = false; // gets true if inputs were made
        $hasDocsNotInformationII = !$isInformationII && $this->getReviewDocs($session);
        if ($hasDocsNotInformationII) { // check if any page whose input may be deleted has input
            $appNodeLoad = $this->getXMLfromSession($session,true);
            $informationLoad = $this->getInformation($appNodeLoad,$routeParams);
            // pre
            $inputArray = $this->setInputArray();
            // informationIII
            $informationIIIinput = $this->getInformationIII($this->xmlToArray($this->getMeasureTimePointNode($appNodeLoad,$routeParams))[self::informationNode]);
            if ($this->checkInput($measureArray[self::informationIIINode],array_fill_keys(self::informationIIIInputsTypes,''))) {
                $this->addInputPage($translationPrefix,self::informationIIINode,$inputArray);
                $informationIIIinputText = $this->translateString($inputPrefix.'hint',['pages' => 1, 'page' => '„'.$this->translateString($translationPrefix.self::informationIIINode).'“', 'inputs' => $this->translateString($inputPrefix.self::informationIIINode)]);
            }
            // consent
            $consentArray = $measureArray[self::consentNode];
            if (array_key_exists(self::terminateConsParticipationNode,$consentArray) && $this->checkInput($consentArray,[self::terminateConsParticipationNode => ''])) {
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
            $isConflict = $textsArray!=='' && array_key_exists(self::conflictTextNode,$textsArray);
            if ($textsArray!=='' &&
                ($this->checkInput($textsArray[self::introNode],[self::introTemplate => '', self::descriptionNode => '']) ||
                 $this->checkInput($textsArray,[self::goalsNode => '']) ||
                 $this->checkInput($textsArray[self::proNode],[self::proTemplate => '', self::descriptionNode => '']) ||
                 $this->checkInput($conArray,array_fill_keys(array_keys($conArray),'')) ||
                 $findingConsentArray!==[] && $this->checkInput($findingConsentArray,array_fill_keys(array_keys($findingConsentArray),'')) ||
                 $isConflict && $this->checkInput($textsArray, [self::conflictTextNode => ''])
                )
               ) {
                $this->addInputPage($translationPrefix,self::textsNode,$inputArray,['isFinding' => $this->getStringFromBool($isFinding), 'isConflict' => $this->getStringFromBool($isConflict)]);
            }
            $textInputPost = $this->setInputHint($inputArray);
        }

        $information = $this->createFormAndHandleRequest(InformationType::class,$this->xmlToArray($informationNode),$request,
            [self::addresseeType => $addressee, self::dummyParams => ['isAttendance' => !$isInformationII && $addressee!==self::addresseeParticipants, 'isInformation' => !$isInformationII, self::reviewProcess => $this->getCurrentReviewProcess($appNode)],
             self::addresseeString => $addresseeString,
             self::participantsString => $this->getAddresseeString($addressee,!$isInformationII,true, $isInformationII || $addressee===self::addresseeParticipants)]);
        if ($information->isSubmitted()) {
            $data = $this->getDataAndConvert($information,$informationNode);
            if ($hasDocsNotInformationII) {
                [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);

                // informationIII
                $isInformationIII = $this->getInformationIII($data); // true if inputs are necessary
                $informationIIInode = $measureNodeNew->{self::informationIIINode};
                if ($informationIIIinput && !$isInformationIII) { // remove nodes from informationIII
                    $this->removeAllChildNodes($informationIIInode);
                } elseif (!$informationIIIinput && $isInformationIII) { // add nodes to informationIII
                    $this->addChildNodes($informationIIInode,self::informationIIIInputsTypes);
                }
                $isPre = $data[self::pre]===0; // true if pre information
                $isPreOld = $informationLoad===self::pre;
                // consent and texts
                $isPostOld = $informationLoad===self::post;
                $isInformation = $isPre || ($data[self::post][self::chosen] ?? 2)===0; // true if either pre or post information
                $consentNode = $measureNodeNew->{self::consentNode};
                $textsNode = $measureNodeNew->{self::textsNode};
                $legalNode = $measureNodeNew->{self::legalNode};
                if ($isPreOld && !$isPre) { // pre information and now no pre information -> remove terminateConsParticipation and legal nodes
                    $this->removeElement(self::terminateConsParticipationNode,$consentNode);
                    $this->removeAllChildNodes($legalNode);
                }
                if (($isPreOld || $isPostOld) && !$isInformation) { // any information and now no information at all -> remove texts nodes
                    $this->removeAllChildNodes($textsNode);
                } elseif (!$isPreOld) {
                    if (!$isPostOld && $isInformation) { // no information at all and now any information -> add intro, goals, pro, con, and eventually finding consent
                        $this->addChildNodes($textsNode,[self::introNode,self::goalsNode,self::proNode,self::conNode]);
                        $this->addChildNodes($textsNode->{self::introNode},[self::introTemplate,self::descriptionNode]);
                        $this->addChildNodes($textsNode->{self::proNode},[self::proTemplate,self::descriptionNode]);
                        $this->addChildNodes($textsNode->{self::conNode},[self::conTemplate,self::descriptionNode]);
                        if ($isFinding) {
                            $this->addChildNodes($textsNode->addChild(self::findingTextNode),[self::findingTemplate,self::descriptionNode]);
                        }
                        if ($isConflict) {
                            $textsNode->addChild(self::conflictTextNode);
                        }
                    }
                    if ($isPre) { // no pre information and now pre information
                        if (($this->getAnyConsent($measureArray[self::consentNode]) || $this->getTemplateChoice($this->getLoanReceipt($measureArray[self::measuresNode][self::loanNode])))) { // consent or loan receipt -> add legal nodes
                            $this->addLegalNodes($legalNode,$this->xmlToArray($measureNode));
                        }
                        if ($consentArray[self::terminateConsNode][self::chosen]==='1') { // terminateCons is answered with 'no' -> add terminateConsParticipation node
                            $this->insertElementBefore(self::terminateConsParticipationNode,$consentNode->{self::terminateParticipantsNode});
                        }
                    }
                }
            }
            $isNotLeave = !$this->getLeavePage($information,$session,self::informationNode);
            return $this->saveDocumentAndRedirect($request,!$hasDocsNotInformationII || $isNotLeave ? $appNode : $appNodeNew, $hasDocsNotInformationII && $isNotLeave ? $appNodeNew : null); // $appNodeNew is only defined if $isInformationII is false and participation documents are created
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