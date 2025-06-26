<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\GroupsType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GroupsController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'groups', name: 'app_groups')]
    public function showGroups(Request $request): Response {
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session,setRecent: true);
        $routeParams = $request->get('_route_params');
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measureNode===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $measureArray = $this->xmlToArray($measureNode);
        $groupsNode = $measureNode->{self::groupsNode};
        $addresseeLoad = $this->getAddressee($this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams)->{self::groupsNode})); // addressee on page load
        $isWards = $addresseeLoad!==self::addresseeParticipants;
        $consentArray = $this->xmlToArray($measureNode->{self::consentNode});
        $voluntaryArray = $consentArray[self::voluntaryNode];
        $isClosedDependentLoad = array_key_exists(self::voluntaryYesDescription,$voluntaryArray);
        $textInput = '';
        $addresseePrefix = 'projectdetails.addressee.';
        $thirdPartiesPrefix = $addresseePrefix.'thirdParties.';
        $translationPrefix = 'pages.projectdetails.';
        if ($isWards) { // check if any page whose input may be deleted has input
            $inputArray = $this->setInputArray();
            // information
            if ($this->getInformation($appNode,$routeParams)===self::pre && $this->checkInput($measureArray[self::informationNode],[self::attendanceNode => '2'])) {
                $this->addInputPage($translationPrefix,self::informationNode,$inputArray,[self::addressee => $this->translateString($thirdPartiesPrefix.$addresseeLoad)]);
            }
            $participants = [self::addressee => $this->translateString($addresseePrefix.'participants.'.$addresseeLoad)];
            // informationII
            if ($this->checkInput($measureArray[self::informationIINode],[self::chosen => '2'])) {
                $this->addInputPage($translationPrefix,self::informationIINode,$inputArray,$participants);
            }
            // consent
            if ($this->checkInput($voluntaryArray,[self::chosen2Node => '']) || $this->checkInput($consentArray[self::consentNode],[self::chosen2Node => ''])) {
                $this->addInputPage($translationPrefix,'consentGroups',$inputArray,$participants);
            }
            $textInput = $this->setInputHint($inputArray);
        }
        $inputArray = $this->setInputArray();
        if ($this->checkInput($voluntaryArray,[self::voluntaryYesDescription => ''])) {
            $this->addInputPage($translationPrefix,self::voluntaryNode,$inputArray);
        }
        $textInputVoluntary = $this->setInputHint($inputArray);
        // get all possible first inclusion sentences. Resulting array looks like follows: 0: min age equals 1, 1: min age unequal to 1. In each array: 0: max age equals 1, 1: max age unequal 1. In each array: 0: participants, 1: children, 2: wards. In each array: 0: no upper limit, 1: same limit, 2: different limits. The non-singular min age value is 0 and the non-singular max age value is 101.
        $locale = $request->getLocale();
        [$criteriaHint,$includeStart,$firstInclude] = [[],[],[]];
        foreach ([1,0] as $minAge) {
            $minArray = [];
            foreach ([1,101] as $maxAge) {
                $maxArray = [];
                foreach ([self::addresseeParticipants,self::addresseeChildren,self::addresseeWards] as $addressee) {
                    $criteriaHint[] = $this->translateString('multiple.wording',[self::addressee => $this->translateString($thirdPartiesPrefix.$addressee)]);
                    $includeStart[] = $this->translateString('projectdetails.pages.'.self::groupsNode.'.'.self::criteriaNode.'.include.start',[self::addressee => $addressee]);
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

        $groups = $this->createFormAndHandleRequest(GroupsType::class,$this->xmlToArray($groupsNode),$request);
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
                foreach ($nodesArray as $curNode) {
                    $elementArray = $this->xmlToArray($curNode);
                    if (array_key_exists(self::chosen2Node,$elementArray)) {
                        $this->removeElement(self::chosen2Node,$curNode);
                        if ($elementArray[self::chosen]!==self::voluntaryConsentNo && $this->checkElement(self::descriptionNode,$curNode)) {
                            $this->removeElement(self::descriptionNode,$curNode);
                        }
                    }
                }
                $this->removeElement(self::consentOtherDescription.'Participants',$consentNode);
            }
            elseif (!$isWards && !$isParticipant) { // add necessary nodes
                // information
                $informationNode->addChild(self::attendanceNode);
                // informationII
                $this->addInformationSubNodes($informationIINode);
                // consent
                foreach ($nodesArray as $curNode) { // insert 'chosen2' node
                    if (array_key_exists(self::descriptionNode,$this->xmlToArray($curNode))) { // description node exists
                        $this->insertElementBefore(self::chosen2Node,$curNode->{self::descriptionNode});
                    }
                    else {
                        $curNode->addChild(self::chosen2Node);
                    }
                }
            }
            // voluntary yes description in consent
            $examined = $data[self::examinedPeopleNode];
            $isClosedDependent = $examined!=='' && array_key_exists(self::dependentExaminedNode,$examined) || $data[self::closedNode][self::chosen]===0;
            $isVoluntary = in_array('yes',[$voluntaryArray[self::chosen],$voluntaryArray[self::chosen2Node] ?? '']);
            if ($isClosedDependentLoad && !$isClosedDependent && $isVoluntary) { // remove node
                $this->removeElement(self::voluntaryYesDescription,$voluntaryNode);
            }
            elseif (!$isClosedDependentLoad && $isClosedDependent && $isVoluntary) { // add node
                $voluntaryNode->addChild(self::voluntaryYesDescription);
            }
            $isNotLeave = !$this->getLeavePage($groups,$session,self::groupsNode);
            return $this->saveDocumentAndRedirect($request,$isNotLeave ? $appNode : $appNodeNew, $isNotLeave ? $appNodeNew : null);
        }
        return $this->render('Projectdetails/groups.html.twig',
            $this->setParameters($request,$appNode,
            [self::content => $groups,
             'examined' => self::examinedTypes,
             self::closedTypesNode => self::closedTypes,
             'criteriaHint' => $criteriaHint,
             'includeStart' => $includeStart,
             'firstInclude' => $firstInclude,
             'textInput' => $textInput,
             'textInputVoluntary' => $textInputVoluntary,
             'recruitmentTypes' => self::recruitmentTypes,
             self::pageTitle => 'projectdetails.groups']));
    }
}