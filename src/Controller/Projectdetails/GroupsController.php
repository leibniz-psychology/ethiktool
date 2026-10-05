<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\GroupsType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupsController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.self::groupsNode,self::groupsNode)]
    public function showGroups(Request $request): Response
    {
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session,setRecent: true);
        $routeParams = $this->getRouteParams($request);
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($this->checkInactivePage($measureNode,self::groupsNode)) { // page was opened before a proposal was created/loaded, a non-existent study / group / measure time point was opened, or the current measure time point is reanalysis
            return $this->redirectToRoute('app_main');
        }
        $measureArray = $this->xmlToArray($measureNode);
        $groupsNode = $measureNode->{self::groupsNode};
        $hasCriteria = $this->checkElement(self::criteriaIncludeNode,$groupsNode);
        $groupsNodeLoad = $this->getMeasureTimePointNode($request,getFirst: true)->{self::groupsNode};
        $addresseeLoad = $this->getAddressee($this->xmlToArray($groupsNodeLoad)); // addressee on page load
        $isWards = $addresseeLoad!==self::addresseeParticipants;
        $consentArray = $measureArray[self::consentNode];
        $voluntaryArray = $consentArray[self::voluntaryNode];
        $isVoluntaryEnsureLoad = array_key_exists(self::voluntaryEnsureNode,$voluntaryArray);
        $textInput = [];
        $addresseePrefix = 'projectdetails.addressee.';
        $thirdPartiesPrefix = $addresseePrefix.'thirdParties.';
        $translationPrefix = 'pages.projectdetails.';
        $informationArray = $measureArray[self::informationNode];
        if ($isWards || array_key_exists(self::informationMeasuresNode,$informationArray)) { // check if any page whose input may be deleted has input
            // information
            $isAttendanceInput = $this->checkInput($informationArray,[self::attendanceNode => '2']);
            $isInformationMeasuresInput = $this->checkInput($informationArray,[self::informationMeasuresNode => '']);
            $participants = [self::addressee => $this->translateString($addresseePrefix.'participants.'.$addresseeLoad)];
            $hasInformationIIinput = $this->checkInput($measureArray[self::informationIINode],[self::pre => '']);
            $hasConsentInput = $this->checkInput($voluntaryArray,[self::chosen2Node => '']) || $this->checkInput($consentArray[self::consentNode],[self::chosen2Node => '']);
            $addresseeParam = [self::addressee => $this->translateString($thirdPartiesPrefix.$addresseeLoad)];
            foreach (array_merge($isAttendanceInput ? [self::attendanceNode => ['isAttendance' => 'true', 'isInformationMeasures' => 'false']] : [],$isInformationMeasuresInput ? [self::informationMeasuresNode => ['isAttendance' => 'false', 'isInformationMeasures' => 'true']] : [], $isAttendanceInput && $isInformationMeasuresInput ? ['attendanceMeasures' => ['isAttendance' => 'true', 'isInformationMeasures' => 'true']] : []) as $removeHint => $params) {
                $inputArray = $this->setInputArray();
                $this->addInputPage($translationPrefix,self::informationNode,$inputArray,array_merge($addresseeParam,$params));
                if ($isWards && $removeHint!==self::informationMeasuresNode) {
                    // informationII
                    if ($hasInformationIIinput) {
                        $this->addInputPage($translationPrefix,self::informationIINode,$inputArray,$participants);
                    }
                    // consent
                    if ($hasConsentInput) {
                        $this->addInputPage($translationPrefix,'consentGroups',$inputArray,$participants);
                    }
                }
                $textInput[$removeHint] = $this->setInputHint($inputArray);
            }
        }
        $inputArray = $this->setInputArray();
        if (($voluntaryArray[self::voluntaryEnsureNode] ?? '')!=='') {
            $this->addInputPage($translationPrefix,self::voluntaryNode,$inputArray);
        }
        $textInputVoluntary = $this->setInputHint($inputArray);
        // get all possible first inclusion sentences. Resulting array looks like follows: 0: min age equals 1, 1: min age unequal to 1. In each array: 0: max age equals 1, 1: max age unequal 1. In each array: 0: participants, 1: children, 2: wards. In each array: 0: no upper limit, 1: same limit, 2: different limits. The non-singular min age value is 0 and the non-singular max age value is 101.
        $locale = $request->getLocale();
        [$criteriaHint,$includeStart,$firstInclude] = [[],[],[]];
        if ($hasCriteria) {
            $informationParam = [self::informationNode => $this->getInformation($appNode,$routeParams)];
            foreach ([1,0] as $minAge) {
                $minArray = [];
                foreach ([1,101] as $maxAge) {
                    $maxArray = [];
                    foreach ([self::addresseeParticipants,self::addresseeChildren,self::addresseeWards] as $addressee) {
                        $criteriaHint[] = $this->translateString('multiple.wording',[self::addressee => $this->translateString($thirdPartiesPrefix.$addressee), 'isBold' => 'true']);
                        $includeStart[] = $this->translateString('projectdetails.pages.'.self::groupsNode.'.'.self::criteriaNode.'.include.start',array_merge($informationParam,[self::addressee => $addressee]));
                        $tempArray = [];
                        foreach (['noUpperLimit','sameLimit','limits'] as $limit) {
                            $tempArray[] = $this->getFirstInclusion($addressee,$limit,$minAge,$maxAge,$locale);
                        }
                        $maxArray[] = $tempArray;
                    } // addressee
                    $minArray[] = $maxArray;
                } // max age
                $firstInclude[] = $minArray;
            } // min age
        }

        $groups = $this->createFormAndHandleRequest(GroupsType::class,$this->xmlToArray($groupsNode),$request,[self::dummyParams => ['hasCriteria' => $hasCriteria]]);
        if ($groups->isSubmitted()) {
            $data = $this->getDataAndConvert($groups,$groupsNode);
            $this->setFirstInclusion($groupsNode,$locale);
            $isParticipant = $this->getAddressee($this->xmlToArray($groupsNode))===self::addresseeParticipants; // true if addressee is participants
            // update information(II) and consent nodes
            [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
            $informationNode = $measureNodeNew->{self::informationNode};
            $informationIINode = $measureNodeNew->{self::informationIINode};
            $consentPageNode = $measureNodeNew->{self::consentNode};
            $voluntaryNode = $consentPageNode->{self::voluntaryNode};
            $consentNode = $consentPageNode->{self::consentNode};
            $nodesArray = [$voluntaryNode,$consentNode];
            if ($isWards && $isParticipant) { // remove unnecessary nodes and node contents
                // information
                $this->removeElement(self::attendanceNode,$informationNode);
                // informationII
                $this->removeAllChildNodes($informationIINode);
                // consent
                $voluntaryArray[self::chosen2Node] = ''; // remove any selection for children/wards (for voluntary yes check)
                foreach ($nodesArray as $curNode) {
                    $elementArray = $this->xmlToArray($curNode);
                    if (array_key_exists(self::chosen2Node,$elementArray)) {
                        $this->removeElement(self::chosen2Node,$curNode);
                        if ($elementArray[self::chosen]!==self::voluntaryConsentNo) {
                            $this->removeElement(self::descriptionNode,$curNode);
                        }
                    }
                }
                $this->removeElement(self::consentOtherDescription.'Participants',$consentNode);
            } elseif (!$isWards && !$isParticipant) { // add necessary nodes
                // information
                $informationNode->addChild(self::attendanceNode);
                // informationII
                $informationIINode->addChild(self::pre);
                // consent
                foreach ($nodesArray as $curNode) { // insert 'chosen2' node
                    if (array_key_exists(self::descriptionNode,$this->xmlToArray($curNode))) { // description node exists
                        $this->insertElementBefore(self::chosen2Node,$curNode->{self::descriptionNode});
                    } else {
                        $curNode->addChild(self::chosen2Node);
                    }
                }
            }
            // information measures
            if (in_array($this->getInformation($appNode,$routeParams),self::prePostArray)) {
                $isExaminedConsentOld = $this->checkElement(self::examinedConsentNode,$groupsNodeLoad->{self::examinedPeopleNode});
                $isExaminedConsent = array_key_exists(self::examinedConsentNode,$data[self::examinedPeopleNode] ?: []);
                if (!$isExaminedConsentOld && $isExaminedConsent) {
                    $informationNode->addChild(self::informationMeasuresNode);
                } elseif ($isExaminedConsentOld && !$isExaminedConsent) {
                    $this->removeElement(self::informationMeasuresNode,$informationNode);
                }
            }
            // voluntary yes description in consent
            $isVoluntaryEnsure = $this->getVoluntaryEnsure($data);
            if (!in_array('yes',[$voluntaryArray[self::chosen],$voluntaryArray[self::chosen2Node] ?? '']) || $isVoluntaryEnsureLoad && !$isVoluntaryEnsure) { // remove node -> if 'wards' group was deselected, $voluntaryArray may not contain 'yes' anymore. May also be true if voluntary is not yet answered or answered with no/not applicable
                $this->removeElement(self::voluntaryEnsureNode,$voluntaryNode);
            } elseif (!$isVoluntaryEnsureLoad && $isVoluntaryEnsure) { // add node
                $voluntaryNode->addChild(self::voluntaryEnsureNode);
            }
            $isNotLeave = !$this->getLeavePage($groups,$session,self::groupsNode);
            return $this->saveDocumentAndRedirect($request,$isNotLeave ? $appNode : $appNodeNew, $isNotLeave ? $appNodeNew : null);
        }
        return $this->render('Projectdetails/groups.html.twig',
            $this->setRenderParameters($request,$groups,
            ['examined' => self::examinedTypes,
             'examinedAll' => self::examinedTypesAll,
             'examinedIcons' => self::examinedIcons,
             'dependentTypes' => array_combine(self::dependentTypes,array_fill(0,count(self::dependentTypes),self::dependentExaminedNode)),
             'examinedOther' => self::examinedTypesOther,
             'criteriaHint' => $criteriaHint,
             'includeStart' => $includeStart,
             'firstInclude' => $firstInclude,
             'textInput' => $textInput,
             'textInputVoluntary' => $textInputVoluntary,
             'recruitmentTypes' => self::recruitmentTypes,
             'recruitmentTypesAll' => self::recruitmentTypesAll,
             'recruitmentTypesOther' => self::recruitmentTypesOther,
             'recruitmentIcons' => self::recruitmentIcons],'projectdetails.groups',true));
    }
}