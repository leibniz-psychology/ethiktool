<?php

namespace App\Controller\AppData;

use App\Abstract\ControllerAbstract;
use App\Form\AppData\CoreDataType;
use App\Form\AppData\MedicineType;
use App\Form\AppData\SummaryType;
use App\Form\AppData\VotesType;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Contributors\ContributorsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AppDataController extends ControllerAbstract
{
    use AppDataTrait;
    use ContributorsTrait;

    // routes
    #[Route(self::routePrefixAppData.self::coreDataNode,self::coreDataNode)]
    public function showCoreData(Request $request): Response
    {
        $session = $request->getSession();
        $committeeType = $this->getCommitteeType($session);
        if ($committeeType==='') { // page was opened before a proposal was created/loaded
            return $this->redirectToRoute('app_main');
        }
        $isEUB = $committeeType===self::committeeEUB;
        $appNodeOld = $this->getXMLfromSession($session,true);
        $appNode = $this->getXMLfromSession($session,setRecent: true);
        $coreDataNode = $appNode->{self::appDataNodeName}->{self::coreDataNode};
        $coreDataArray = $this->xmlToArray($coreDataNode);
        $positions = $this->setPositions($session);
        $conflictReviewProcesses = self::reviewQuestions[self::textsNode][self::conflictTextNode]; // review processes for which a description needs to be given
        $reviewProcessOld = !$session->has('updateProcess') ? $this->getCurrentReviewProcess($appNodeOld) : self::reviewFullDocs;
        $coreDataArrayOld = $this->xmlToArray($appNodeOld->{self::appDataNodeName}->{self::coreDataNode});
        $isConflictOld = $coreDataArrayOld[self::conflictNode][self::chosen]==='0' && in_array($reviewProcessOld,$conflictReviewProcesses);
        $textInput = '';
        $textInputRequested = ''; // if begun is selected and then begun is deselected and (before or after) requested is selected
        $tempArray = $coreDataArrayOld[self::projectStart];
        $isBegunOld = $tempArray[self::chosen]==='0' && array_key_exists(self::descriptionNode,$tempArray); // true if begun is selected
        $hasBegunInput = false; // gets true if any input was made that gets deleted when changing from begun to requested
        $hasTexts = false; // gets true if any 'texts' page is active
        $hasConflictDescription = false;
        foreach ($this->addZeroIndex($this->xmlToArray($appNodeOld->{self::projectdetailsNodeName}->{self::studyNode})) as $study) {
            foreach ($this->addZeroIndex($study[self::groupNode]) as $group) {
                foreach ($this->addZeroIndex($group[self::measureTimePointNode]) as $measureTimePoint) {
                    if ($isBegunOld) {
                        $hasInput = false;
                        $tempArray = $measureTimePoint[self::dataReuseNode];
                        if (($measureTimePoint[self::consentNode][self::terminateParticipantsNode][self::chosen] ?? '')!=='' || ($tempArray!=='' && (($tempArray[self::dataReuseHowNode][self::chosen] ?? '')!='' || ($tempArray[self::dataReuseHowNode.'reuse'][self::chosen] ?? '')!==''))) { // terminate participants or data reuse how
                            $hasInput = true;
                        }
                        $compensationArray = $measureTimePoint[self::compensationNode];
                        $tempArray = $compensationArray[self::compensationTypeNode];
                        if ($tempArray!=='' && !array_key_exists(self::compensationNo,$tempArray)) { // compensation awarding
                            foreach (array_keys($tempArray) as $selection) {
                                $awardingArray = $compensationArray[$selection.self::awardingNode] ?? '';
                                if ($awardingArray!=='' && $awardingArray[self::chosen]!=='' || $selection===self::compensationLottery && ($awardingArray[self::lotteryStart.self::descriptionCap]!=='' || $awardingArray[self::lotteryStart]!=='')) {
                                    $hasInput = true;
                                }
                            }
                        }
                        if ($hasInput) {
                            $hasBegunInput = true;
                        }
                    }
                    $textsArray = $measureTimePoint[self::textsNode];
                    $hasTexts = $hasTexts || $textsArray!=='';
                    if (($textsArray[self::conflictTextNode] ?? '')!=='') {
                        $hasConflictDescription = true;
                    }
                    if ($hasConflictDescription && $hasBegunInput) {
                        break(3);
                    }
                }
            }
        }
        if ($hasBegunInput) {
            $textInputRequested = $this->translateString(self::coreDataNode.'.'.self::funding.'.removeHint');
        }
        if ($hasConflictDescription) {
            $inputArray = $this->setInputArray();
            $this->addInputPage('multiple.inputs.pages.','textsConflict',$inputArray);
            $textInput = $this->setInputHint($inputArray);
        }
        // check which modals need to be displayed if the review process changes
        $modals = [];
        $modalPrefix = self::coreDataNode.'.modal.';
        $tempPrefix = $modalPrefix.'buttons.';
        $tempArray = ['prefix' => $modalPrefix, 'modalWidth' => true, 'link' => 'app_coreData', 'leftButton' => $tempPrefix.'save', 'rightButton' => $tempPrefix.'cancel'];
        $hasShortDocs = in_array($committeeType,self::reviewShortChoose);
        if (str_contains($reviewProcessOld,self::reviewProcessFull)) {
            $modals[] = array_merge($tempArray,['modalID' => 'fullShort', 'params' => array_merge($session->get(self::committeeParams),['type' => 'fullToShort', 'hasShortDocs' => $this->getStringFromBool($hasShortDocs && $hasTexts)])]); // fullDocs to shortDocs or fullDocs to shortNoDocs
            $modals[] = array_merge($tempArray,['modalID' => 'begunRequestedShort', 'params' => ['type' => 'fullToShort', 'hasShortDocs' => 'false']]); // fullBegun or fullRequested to any short
        }
        $isOldDocs = in_array($reviewProcessOld,self::reviewDocs);
        if ($isOldDocs && $hasTexts) {
            $modals[] = array_merge($tempArray,['modalID' => 'docsBegun', 'params' => ['type' => self::projectStart]]); // fullDocs to fullBegun, shortDocs to shortBegun, shortService to shortBegun
            $modals[] = array_merge($tempArray,['modalID' => 'docsRequested', 'params' => ['type' => self::funding]]); // fullDocs to fullRequested, shortDocs to shortRequested, shortService to shortRequested
            if ($hasShortDocs) {
                $modals[] = array_merge($tempArray,['modalID' => 'shortShort', 'params' => ['type' => 'shortToShort']]); // shortService to shortNoDocs
            }
        }

        $coreData = $this->createFormAndHandleRequest(CoreDataType::class,$coreDataArray,$request,[self::dummyParams => [self::applicant => $positions[$this->getQualification($coreDataArray) ? 1 : 0], self::supervisor => $positions[2]]]);
        if ($coreData->isSubmitted()) { // a button was clicked or the language was changed
            $data = $this->getDataAndConvert($coreData,$coreDataNode);
            $submitDummy = $request->request->all()['core_data'][self::submitDummy];
            if (str_contains($submitDummy,self::preview) && str_contains($submitDummy,'app_coreData') && !str_contains($submitDummy,'#')) { // download xml-file after review process has been changed
                return $this->getDownloadResponse($session,getSecondLast: true);
            }
            $appNodeNew = $this->cloneNode($appNode);
            $positionLoad = $this->xmlToArray($this->getXMLfromSession($session,true)->{self::appDataNodeName}->{self::coreDataNode})[self::applicant][self::position];
            $isStudentPhd = $this->checkSupervisor($committeeType,$positionLoad); // position on page loading
            // set number or reference for extended or resubmission type
            $appType = $data[self::applicationType];
            $type = $appType[self::chosen];
            if (in_array($type,self::appExtendedResubmission)) {
                $instVoteNode = $appNodeNew->{self::appDataNodeName}->{self::voteNode}->{self::instVote};
                $instVoteArray = $this->xmlToArray($instVoteNode);
                $instVoteArray[self::chosen] = 0;
                $instVoteArray[self::instReference] = $appType[self::descriptionNode];
                $instVoteArray[self::instVoteText] = $instVoteArray[self::instVoteText] ?? '';
                $this->arrayToXml($instVoteArray,$instVoteNode);
            }
            // update applicant and supervisor in contributors
            $contributorsArray = $this->getContributors($session);
            $this->updateContributor($contributorsArray,$data,self::applicant);
            $position = $data[self::applicant][self::position];
            $isStudentPhdNew = $this->checkSupervisor($committeeType,$position); // position on submission
            if ($isStudentPhdNew) {
                if ($isStudentPhd) {
                    $supervisorTasks = $contributorsArray[1][self::taskNode];
                    if ($supervisorTasks!=='' && !array_key_exists(self::supervisorNode,$supervisorTasks)) { // supervisor existed on page load, but was task was removed and needs to be added again
                        $contributorsArray[1][self::taskNode] = array_merge([self::supervisorNode => ''],$supervisorTasks);
                    }
                }
                $this->updateContributor($contributorsArray,$data,self::supervisor);
            } else {
                if (count($contributorsArray)>count($this->getContributors($session,true))) { // supervisor was added after entering the page, but position (and qualification) do no longer lead to supervisor-> remove supervisor
                    unset($contributorsArray[1]);
                    $contributorsArray = array_values($contributorsArray); // re-indexing
                } elseif ($isStudentPhd) { // supervisor was existent on page load, but due to changes no supervisor is needed anymore -> remove supervision as task
                    unset($contributorsArray[1][self::taskNode][self::supervisorNode]);
                }
            }
            $this->addAllContributorsNodes($appNodeNew,$contributorsArray);
            $session->set(self::contributorsSessionName,array_merge($session->get(self::contributorsSessionName),[$contributorsArray])); // needs to be set before calling updateProjectdetailsContributor
            if (!$isStudentPhd && $isStudentPhdNew) { // supervisor was added or removed
                $this->updateProjectdetailsContributor($request,$appNodeNew,'',[],false,true);
            }
            $reviewProcessNew = $this->getCurrentReviewProcess($appNodeNew);
            $session->set(self::reviewProcess,$reviewProcessNew);
            $isConflict = $data[self::conflictNode][self::chosen]===0;
            $isConflictNew = !$isConflictOld && $isConflict && in_array($reviewProcessNew,$conflictReviewProcesses); // conflict was not chosen, but is chosen now
            $updateConflict = $hasTexts && $isConflictNew || $isConflictOld && !$isConflict;
            foreach ($appNodeNew->{self::projectdetailsNodeName}->{self::studyNode} as $studyNode) {
                foreach ($studyNode->{self::groupNode} as $groupNode) {
                    foreach ($groupNode->{self::measureTimePointNode} as $measureTimePointNode) {
                        $this->updateNodesByReviewProcess($request,$measureTimePointNode,$reviewProcessNew); // always update because appNodeNew does not include changes on other pages at this point
                        if ($updateConflict) { // may only be true if old and new review process can contain participation documents
                            $textsNode = $measureTimePointNode->{self::textsNode};
                            if (count($textsNode->children())>0) {
                                if ($isConflictNew) { // add conflictText node
                                    $textsNode->addChild(self::conflictTextNode);
                                } else { // remove conflictText node
                                    $this->removeElement(self::conflictTextNode,$textsNode);
                                }
                            }
                        }
                    }
                }
            }
            if (!$isOldDocs && in_array($reviewProcessNew,self::reviewDocs) && !$this->getMultiStudyGroupMeasure($appNodeNew)) { // old review process was without participant documents and new review process is with participant documents. May only be true if only one time points exists
                $this->setProjectdetailsContributor($request,$appNodeNew); // add indices to contributor tasks
            }
            $isNotLeave = !$this->getLeavePage($coreData,$session,self::coreDataNode);
            if (!$isNotLeave) {
                $session->remove('updateProcess');
            }
            return $this->saveDocumentAndRedirect($request,$isNotLeave ? $appNode : $appNodeNew,$isNotLeave ? $appNodeNew : null);
        } // if ($coreData->isSubmitted())
        return $this->render('AppData/coreData.html.twig', $this->setRenderParameters($request,$coreData,
            ['reviewProcessLoad' => $reviewProcessOld,
             self::reviewProcess => $coreDataArrayOld[self::applicationProcessNode][self::chosen]!=='' ? $session->get(self::reviewProcess) : self::reviewFullDocs,
             'positions' => $positions,
             'funding' => self::fundingTypes,
             'support' => array_diff_key(self::supportTypes,!$isEUB ? [self::supportCenter => ''] : []),
             'applicantInfo' => self::applicantContributorsInfosTypes,
             'textInputConflict' => $textInput,
             'textInputRequested' => $textInputRequested,
             'modals' => $modals],'appData.coreData'));
    }

    #[Route(self::routePrefixAppData.self::voteNode,self::voteNode)]
    public function showVotes(Request $request): Response
    {
        $appNode = $this->getXMLfromSession($request->getSession());
        if (!$appNode) { // page was opened before a proposal was created/loaded
            return $this->redirectToRoute('app_main');
        }
        $appDataNode = $appNode->{self::appDataNodeName};
        $votesNode = $appDataNode->{self::voteNode};
        $appTypeNode = $appDataNode->{self::coreDataNode}->{self::applicationType};
        $appType = (string) $appTypeNode->{self::chosen};

        $votes = $this->createFormAndHandleRequest(VotesType::class,$this->xmlToArray($votesNode),$request);
        if ($votes->isSubmitted()) {
            $this->getDataAndConvert($votes,$votesNode);
            if (in_array($appType,self::appExtendedResubmission)) { // set reference because text field is disabled
                $votesNode->{self::instVote}->{self::instReference} = $appTypeNode->{self::descriptionNode};
            }
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
        $translationPrefix = 'votes.otherVote.description.';
        $tempVal = $this->translateString($translationPrefix.'positiveNo');
        return $this->render('AppData/votes.html.twig', $this->setRenderParameters($request,$votes,
                ['appType' => $appType,
                 'otherVoteResultHeadingText' => ['positive' => $tempVal, self::otherVoteResultNegative => $this->translateString($translationPrefix.self::otherVoteResultNegative), 'noVote' => $tempVal],
                 'exReArray' => self::appExtendedResubmission],'appData.votes')
            );
    }

    #[Route(self::routePrefixAppData.self::medicine,self::medicine)]
    public function showMedicine(Request $request): Response
    {
        $tempPrefix = self::medicine.'.'.self::physicianNode.'.'.self::descriptionNode.'.textHints.';
        return $this->createFormAndHandleSubmit(MedicineType::class,$request,[self::appDataNodeName,self::medicine],
            ['hintArray' => ['' => $this->translateString('multiple.choiceTextHint'),$this->translateString($tempPrefix.'exception'),$this->translateString($tempPrefix.'other')]]);
    }

    #[Route(self::routePrefixAppData.self::summary, name: self::summary)]
    public function showSummary(Request $request): Response
    {
        return $this->createFormAndHandleSubmit(SummaryType::class,$request,[self::appDataNodeName,self::summary],
            ['maxChars' => 3000,
             self::pageTitle => 'appData.summaryPage']);
    }
}