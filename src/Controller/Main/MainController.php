<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\MainType;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends ControllerAbstract
{
    private string $changeCommittee = 'changeCommittee'; // session key if the committee was changed, the old review process was shortDocs, and the new one would be shortNoDocs

    #[Route('/', name: 'app_home')] // if the url is entered without the page, i.e., only with locale or without anything
    public function showHome(): Response
    {
        return $this->redirectToRoute('app_main');
    }

    #[Route('main', name: 'main')]
    public function showMain(Request $request): Response
    {
        $session = $request->getSession();
        if ($session->has(self::quit)) { // if xml should be downloaded before quitting and then header is used to return to the main page, remove session variable
            $session->remove(self::quit);
        }
        $isXmlLoadFailure = $session->has(self::xmlLoad);
        $isError = $session->has(self::errorModal);
        $isLoadSuccess = $session->has(self::loadSuccess);
        $isNewForm = $session->has(self::newForm);
        [$errorModal,$sessionValue] = ['',[]];
        if ($isXmlLoadFailure || $isError || $isLoadSuccess || $isNewForm || $session->has($this->changeCommittee)) {
            $errorModal = $isError ? self::errorModal : ($isXmlLoadFailure ? self::xmlLoad : ($isLoadSuccess ? self::loadSuccess : ($isNewForm ? self::newForm : $this->changeCommittee)));
            $sessionValue = $session->get($errorModal); // 'loadSuccess' (whether cur route is main) or 'committeeChange'
            $session->remove($errorModal);
        }
        $wrongPassword = $session->has(self::wrongPassword);
        if ($wrongPassword) {
            $session->remove(self::wrongPassword);
        }
        $committeeTemp = $session->get(self::committeeTemp) ?? '';
        $hasChange = $session->get(self::committeeChangeTemp) ?? false;
        $currentCommittee = $this->getCommitteeType($session);

        $main = $this->createFormAndHandleRequest(MainType::class,
            [self::committee => $committeeTemp,
             self::requirements => $session->get(self::requirementsTemp) ?? false,
             self::committeeChange => $hasChange],$request,
            [self::dummyParams => ['isFilename' => $session->has(self::fileName), self::committee => $currentCommittee]]);
        if ($main->isSubmitted()) {
            $appNode = $this->getXMLfromSession($session);
            $response = $request->request->all();
            $data = $response['main'];
            $committee = $data[self::committee] ?? '';
            $submitDummy = $data[self::submitDummy];
            if (count($response)===1 && $submitDummy==='' || str_contains((string) $submitDummy,self::language)) { // checkbox for changing committee was clicked, committee in dropdown was selected or language was changed
                $this->setTemp($session,$data,true);
            } elseif (array_key_exists(self::committeeChange,$response)) {
                if (!$this->checkPassword($session,$data)) {
                    return $this->redirectToRoute('app_main');
                }
                $this->removeTemp($session,false);
                $oldCommittee = $this->getCommitteeType($session);
                $isEUBold = $oldCommittee===self::committeeEUB;
                $appNode->{self::committee} = $committee;
                $this->setCommittee($session,$committee,$request->getLocale());
                $appDataNode = $appNode->{self::appDataNodeName};
                $coreDataNode = $appDataNode->{self::coreDataNode};
                // add/remove shortDocs node
                $applicationProcessNode = $coreDataNode->{self::applicationProcessNode};
                $isShortChoose = in_array($committee,self::reviewShortChoose);
                $hasShortDocs = $this->checkElement(self::shortDocsNode,$applicationProcessNode);
                $reviewProcess = '';
                $shortChange = false;
                if (((string) $applicationProcessNode->{self::chosen})===self::reviewProcessShort) { // review process is short
                    if ($isShortChoose && !$hasShortDocs) { // old committee has no shortDocs, but new one has
                        $applicationProcessNode->addChild(self::shortDocsNode);
                        $reviewProcess = self::reviewShortService; // keep input for participation documents
                        $shortChange = true;
                    } elseif (!$isShortChoose && $hasShortDocs) { // old committee has shortDocs, but new one has not
                        $this->removeElement(self::shortDocsNode,$applicationProcessNode);
                    }
                }
                // remove some nodes of project start if review after start of data collection is not allowed
                $projectStartNode = $coreDataNode->{self::projectStart};
                $hasConfirm = $this->checkElement(self::projectStartBegunConfirm,$projectStartNode);
                $hasRetrospective = $this->checkElement(self::projectStartRetrospective,$projectStartNode);
                $isConfirmCommittee = in_array($committee,self::begunConfirmCommittees); // true if new committee has confirm
                $isRetrospectiveCommittee = in_array($committee,self::retrospectiveCommittees); // true if new committee has retrospective
                $hasBegunNew = $isConfirmCommittee || $isRetrospectiveCommittee; // true if review after start of data collection is allowed for new committee -> each committee where review after start of data collection is allowed either has the confirm checkbox or the retrospective text field
                if ($hasConfirm && !$isConfirmCommittee) {
                    $this->removeElement(self::projectStartBegunConfirm,$projectStartNode);
                    if ($isRetrospectiveCommittee) {
                        if (!$this->checkElement(self::descriptionNode,$projectStartNode)) { // confirm checkbox was not checked
                            $projectStartNode->addChild(self::descriptionNode);
                        }
                        $projectStartNode->addChild(self::projectStartRetrospective);
                    }
                }
                if ($hasRetrospective && !$isRetrospectiveCommittee) {
                    $this->removeElement(self::projectStartRetrospective,$projectStartNode);
                    if ($hasBegunNew) {
                        $descriptionNode = $projectStartNode->{self::descriptionNode};
                        $this->insertElementBefore(self::projectStartBegunConfirm,$descriptionNode);
                        $projectStartNode->{self::projectStartBegunConfirm} = ((string) $descriptionNode)!=='' ? '1' : ''; // automatically check the checkbox to keep input of description node if any input was entered
                    }
                }
                if (($hasConfirm || $hasRetrospective) && !$hasBegunNew) {
                    $projectStartNode->{self::chosen} = '';
                    $this->removeElement(self::descriptionNode,$projectStartNode);
                }
                // remove student if new committee does not allow applicant to be student
                $applicantNode = $coreDataNode->{self::applicant};
                $contributorsNode = $appNode->{self::contributorsNodeName};
                $contributorsApplicantNode = $contributorsNode->{self::contributorNode}[0];
                $contributorsInfosNode = $contributorsApplicantNode->{self::infosNode};
                $position = (string) $applicantNode->{self::position};
                $isStudent = $position===self::positionsStudent;
                $updateApplicant = $isStudent && !in_array($committee,self::committeeStudent);
                $contributorsApplicantTasks = $contributorsApplicantNode->{self::taskNode};
                if ($updateApplicant) { // remove position and all tasks
                    $applicantNode->{self::position} = '';
                    $contributorsInfosNode->{self::position} = '';
                    $this->removeAllChildNodes($contributorsApplicantTasks);
                } else {
                    $isEUBoldStudent = $isEUBold && $isStudent;
                    if ($isEUBoldStudent || $position===self::positionsPhd && in_array($committee,self::committeePhDnoLeaderData)) { // remove task data and eventually task leader if they were selected
                        $updateApplicant = true;
                        foreach ($isEUBoldStudent ? [self::taskData] : [self::taskLeader,self::taskData] as $task) {
                            $this->removeElement($task,$contributorsApplicantTasks);
                        }
                    }
                }
                // remove institution for applicant and change institution for other contributors if value is 'institutionSame'
                $applicantNode->{self::institutionInfo} = '';
                $contributorsInfosNode->{self::institutionInfo} = '';
                $children = $contributorsNode->children();
                if (count($children)>1) { // further contributors exist
                    $numChildren = count($children);
                    for ($index=1; $index<$numChildren; ++$index) {
                        $infosNode = $children[$index]->{self::infosNode};
                        if (((string) $infosNode->{self::institutionInfo})===self::institutionSame) {
                            $infosNode->{self::institutionInfo} = $this->translateString('committee.committeeLocationPure',[self::committee => $oldCommittee]);
                        }
                    }
                }
                $contributorsArray = $this->addZeroIndex($this->xmlToArray($contributorsNode)[self::contributorNode]);
                $session->set(self::contributorsSessionName,[0 => $contributorsArray]);
                $this->addAllContributorsNodes($appNode,$contributorsArray);
                if ($updateApplicant) {
                    $this->updateProjectdetailsContributor($request,$appNode,0,[],false); // needs to be called after addAllContributorsNodes()
                }
                // add/remove qualification and guidelines node
                $isEUB = $committee===self::committeeEUB;
                if (!$isEUBold && $isEUB) {
                    $this->insertElementBefore(self::qualification,$applicantNode);
                    $coreDataNode->addChild(self::guidelinesNode);
                } elseif ($isEUBold && !$isEUB) {
                    $this->removeElement(self::qualification,$coreDataNode);
                    $this->removeElement(self::guidelinesNode,$coreDataNode);
                }
                // add/remove medicine nodes
                $hasMedicineOld = !in_array($oldCommittee,self::committeeNoMedicine,true);
                $hasMedicine = !in_array($committee,self::committeeNoMedicine);
                $medicineNode = $appDataNode->{self::medicine};
                if (!$hasMedicineOld && $hasMedicine) {
                    foreach ([self::medicine,self::physicianNode] as $type) {
                        $this->addChosenNode($medicineNode,$type);
                    }
                } elseif ($hasMedicineOld && !$hasMedicine) {
                    $this->removeAllChildNodes($medicineNode);
                }
                // update nodes by review process
                $reviewProcess = $reviewProcess==='' ? $this->getCurrentReviewProcess($appNode) : $reviewProcess;
                $session->set(self::reviewProcess,$reviewProcess);
                $isBICC = $committee===self::committeeBICC;
                $isBICCold = $oldCommittee===self::committeeBICC;
                foreach ($appNode->{self::projectdetailsNodeName}->{self::studyNode} as $studyNode) {
                    foreach ($studyNode->{self::groupNode} as $groupNode) {
                        foreach ($groupNode->{self::measureTimePointNode} as $measureTimePointNode) {
                            $this->updateNodesByReviewProcess($request,$measureTimePointNode,$reviewProcess);
                            // update compensation if change from/to BICC
                            $compensationNode = $measureTimePointNode->{self::compensationNode};
                            if (!$isBICCold && $isBICC && $this->checkElement(self::compensationTypeNode,$compensationNode)) { // select 'no compensation' if page is active
                                $this->removeAllChildNodes($compensationNode);
                                $compensationNode->addChild(self::compensationTypeNode)->addChild(self::compensationNo);
                            } elseif ($isBICCold && !$isBICC && !$this->checkElement(self::terminateNode,$compensationNode)) { // deselect 'no compensation' in case it was selected
                                $this->removeAllChildNodes($compensationNode->{self::compensationTypeNode});
                            }
                            // update data privacy access
                            $dataPrivacyNode = $measureTimePointNode->{self::privacyNode};
                            if ($this->checkElement(self::accessNode,$dataPrivacyNode)) {
                                $this->updateAccess($dataPrivacyNode->{self::accessNode});
                            }
                            foreach ([self::purposeResearchNode,self::purposeFurtherNode] as $type) {
                                if ($this->checkElement($type,$dataPrivacyNode)) {
                                    foreach ($dataPrivacyNode->{$type}->children() as $child) {
                                        if ($this->checkElement(self::accessNode,$child)) {
                                            $this->updateAccess($child->{self::accessNode});
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $session->set($this->changeCommittee,['isShort' => $shortChange]);
            } else {
                $this->removeTemp($session,false);
            }
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
        $isMajor = $sessionValue['isMajor'] ?? false;
        $isMajorOrShort = $isMajor || ($sessionValue['isShort'] ?? false);
        return $this->render('Main/main.html.twig',$this->setRenderParameters($request,$main,
            ['error' => $errorModal,
             'isRedirectModal' => $isMajorOrShort || ($sessionValue['isInstUpdate'] ?? false),
             'committeeParamsChange' => $this->setCommittee($session,$session->get(self::committeeTemp) ?? 'testCommittee',$request->getLocale(),false),
             'showCommittee' => $hasChange,
             'wrongPassword' => $wrongPassword,
             'committeeTypes' => $this->getCommitteeArray($currentCommittee),
             'selected' => $committeeTemp,
             'numCommitteesBeta' => (new \NumberFormatter($request->getLocale(),\NumberFormatter::SPELLOUT))->format(count(self::committeeTypes['newForm.committee.headings.beta'])),
             'redirectParams' => array_merge([
                 'params' => ['isMain' => $sessionValue['isMain'] ?? '', 'isMajor' => $this->getStringFromBool($isMajor)],
                 'modalID' => $errorModal,
                 'prefix' => 'multiple.loadMessage.'.$errorModal.'.',
                 'link' => $isMajorOrShort ? 'app_coreData' : 'app_contributors'],
                 $isMajorOrShort ? ['hash' => $isMajor ? '#applicationProcess' : '#shortDocs'] : [])]));
    }

    /** Updates the access node when the committee has changed.
     * @param SimpleXMLElement $accessNode node with selected access options as children
     * @return void
     */
    private function updateAccess(SimpleXMLElement $accessNode): void
    {
        $children = $accessNode->children();
        $numChildren = count($children);
        $hasMultipleChildren = $numChildren>1;
        if ($numChildren>0) {
            $firstName = $children[0]->getName();
            if (str_ends_with($firstName,'contributors')) { // 'all' contributors have access -> replace by 'some' contributors
                $this->removeElement($firstName,$accessNode); // remove 'all' contributors
                $firstName .= 'Part';
                if ($hasMultipleChildren) {
                    $this->insertElementBefore($firstName,$children[0]);
                } else {
                    $accessNode->addChild($firstName);
                }
            }
            if ($hasMultipleChildren) {
                foreach ($accessNode->children() as $child) { // remove description of contributors options
                    $name = $child->getName();
                    if (str_ends_with($name,'Part') || str_ends_with($name,'institution')) { // some contributors or non-contributors
                        $accessNode->{$name} = '';
                    } elseif (str_ends_with($name,'contributorsOther')) { // external contributors
                        $orderProcessing = $accessNode->{$name}->{self::orderProcessingNode};
                        if ($this->checkElement(self::descriptionNode,$orderProcessing)) {
                            $orderProcessing->{self::descriptionNode} = '';
                        }
                    }
                }
            }
        }
    }
}
