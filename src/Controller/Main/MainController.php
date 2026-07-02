<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\MainType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends ControllerAbstract
{
    private string $changeCommittee = 'changeCommittee'; // session key if the committee was changed, the old review process was shortDocs, and the new one would be shortNoDocs

    #[Route('/', name: 'app_home')] // if the url is entered without the page, i.e., only with locale or without anything
    public function showHome(Request $request): Response
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

        $main = $this->createFormAndHandleRequest(MainType::class,
            [self::committee => $committeeTemp,
             self::requirements => $session->get(self::requirementsTemp) ?? false,
             self::committeeChange => $hasChange],$request,
            [self::dummyParams => ['isFilename' => $session->has(self::fileName), self::committee => $this->getCommitteeType($session)]]);
        if ($main->isSubmitted()) {
            $appNode = $this->getXMLfromSession($session);
            $response = $request->request->all();
            $data = $response['main'];
            $committee = $data[self::committee] ?? '';
            $submitDummy = $data[self::submitDummy];
            if (count($response)===1 && $submitDummy==='' && !str_contains($submitDummy,self::preview) || str_contains($submitDummy,self::language)) { // checkbox for changing committee was clicked, committee in dropdown was selected or language was changed
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
                $coreDataNode = $appNode->{self::appDataNodeName}->{self::coreDataNode};
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
                // remove description node of project start if review after start of data collection is not allowed
                $projectStartNode = $coreDataNode->{self::projectStart};
                if (!in_array($committee,self::begunCommittees) && $this->checkElement(self::descriptionNode,$projectStartNode)) {
                    $projectStartNode->{self::chosen} == '';
                    $this->removeElement(self::descriptionNode,$projectStartNode);
                }
                // remove student if new committee does not allow applicant to be student
                $applicantNode = $coreDataNode->{self::applicant};
                $contributorsNode = $appNode->{self::contributorsNodeName};
                $contributorsApplicantNode = $contributorsNode->{self::contributorNode}[0];
                $contributorsInfosNode = $contributorsApplicantNode->{self::infosNode};
                $removeStudent = ((string) $applicantNode->{self::position})===self::positionsStudent && !in_array($committee,self::committeeStudent);
                if ($removeStudent) { // remove position and all tasks
                    $applicantNode->{self::position} = '';
                    $contributorsInfosNode->{self::position} = '';
                    $contributorsApplicant = $contributorsApplicantNode->{self::taskNode};
                    $this->removeAllChildNodes($contributorsApplicant);
                }
                // remove institution for applicant and change institution for other contributors if value is 'institutionSame'
                $applicantNode->{self::institutionInfo} = '';
                $contributorsInfosNode->{self::institutionInfo} = '';
                $children = $contributorsNode->children();
                if (count($children)>1) { // further contributors exist
                    for ($index=1; $index<count($children); ++$index) {
                        $infosNode = $children[$index]->{self::infosNode};
                        if (((string) $infosNode->{self::institutionInfo})===self::institutionSame) {
                            $infosNode->{self::institutionInfo} = $this->translateString('committee.committeeLocationPure',[self::committee => $oldCommittee]);
                        }
                    }
                }
                $contributorsArray = $this->addZeroIndex($this->xmlToArray($contributorsNode)[self::contributorNode]);
                $session->set(self::contributorsSessionName,[0 => $contributorsArray]);
                $this->addAllContributorsNodes($appNode,$contributorsArray);
                if ($removeStudent) {
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
                // update nodes by review process
                $reviewProcess = $reviewProcess==='' ? $this->getCurrentReviewProcess($appNode) : $reviewProcess;
                $session->set(self::reviewProcess,$reviewProcess);
                foreach ($appNode->{self::projectdetailsNodeName}->{self::studyNode} as $studyNode) {
                    foreach ($studyNode->{self::groupNode} as $groupNode) {
                        foreach ($groupNode->{self::measureTimePointNode} as $measureTimePointNode) {
                            $this->updateNodesByReviewProcess($request,$measureTimePointNode,$reviewProcess);
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
             'numCommitteesBeta' => (new \NumberFormatter($request->getLocale(),\NumberFormatter::SPELLOUT))->format(count(self::committeeTypesBeta)),
             'redirectParams' => array_merge([
                 'params' => ['isMain' => $sessionValue['isMain'] ?? '', 'isMajor' => $this->getStringFromBool($isMajor)],
                 'modalID' => $errorModal,
                 'prefix' => 'multiple.loadMessage.'.$errorModal.'.',
                 'link' => $isMajorOrShort ? 'app_coreData' : 'app_contributors'],
                 $isMajorOrShort ? ['hash' => $isMajor ? '#applicationProcess' : '#shortDocs'] : [])]));
    }
}