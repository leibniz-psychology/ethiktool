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
    #[Route('/appData/coreData', name: 'app_coreData')]
    public function showCoreData(Request $request): Response {
        $session = $request->getSession();
        $committeeType = $this->getCommitteeType($session);
        if ($committeeType==='') { // page was opened before a proposal was created/loaded
            return $this->redirectToRoute('app_main');
        }
        $isEUB = $committeeType===self::committeeEUB;
        $appNode = $this->getXMLfromSession($session,setRecent: true);
        $coreDataNode = $appNode->{self::appDataNodeName}->{self::coreDataNode};
        $coreDataArray = $this->xmlToArray($coreDataNode);
        $positions = $this->setPositions($session);

        $coreData = $this->createFormAndHandleRequest(CoreDataType::class,$coreDataArray,$request,[self::dummyParams => [self::applicant => $positions[$this->getQualification($coreDataArray) ? 1 : 0], self::supervisor => $positions[2]]]);
        if ($coreData->isSubmitted()) { // a button was clicked or the language was changed
            $data = $this->getDataAndConvert($coreData,$coreDataNode);
            $appNodeNew = $this->cloneNode($appNode);
            $studentPhd = [self::positionsStudent,self::positionsPhd];
            $coreDataArrayLoad = $this->xmlToArray($this->getXMLfromSession($session,true)->{self::appDataNodeName}->{self::coreDataNode}); // core data on page loading
            $isStudentPhd = in_array($coreDataArrayLoad[self::applicant][self::position],$studentPhd) && $this->getQualification($coreDataArrayLoad); // position on page loading
            // set number or reference for extended or resubmission type
            $appType = $data[self::applicationType];
            $type = $appType[self::chosen];
            if ($type===self::appExtended || $type===self::appResubmission) {
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
            $isStudentPhdNew = $isEUB && in_array($data[self::applicant][self::position],$studentPhd) && $data[self::qualification]===0; // position on submission
            if ($isStudentPhdNew) {
                if ($isStudentPhd) {
                    $supervisorTasks = $contributorsArray[1][self::taskNode];
                    if ($supervisorTasks!=='' && !array_key_exists(self::supervisorNode,$supervisorTasks)) { // supervisor existed on page load, but was task was removed and needs to be added again
                        $contributorsArray[1][self::taskNode] = array_merge([self::supervisorNode => ''],$supervisorTasks);
                    }
                }
                $this->updateContributor($contributorsArray,$data,self::supervisor);
            }
            else {
                if (count($contributorsArray)>count($this->getContributors($session,true))) { // supervisor was added after entering the page, but position is no longer student/phd or no qualification -> remove supervisor
                    unset($contributorsArray[1]);
                    $contributorsArray = array_values($contributorsArray); // re-indexing
                }
                elseif ($isStudentPhd) { // position was student or phd or qualification on page load, but was changed -> remove supervision as task
                    unset($contributorsArray[1][self::taskNode][self::supervisorNode]);
                }
            }
            $this->addAllContributorsNodes($appNodeNew,$contributorsArray);
            $session->set(self::contributorsSessionName,array_merge($session->get(self::contributorsSessionName),[$contributorsArray])); // needs to be set before calling updateProjectdetailsContributor
                if (!$isStudentPhd && $isStudentPhdNew) { // supervisor was added or removed
                    $this->updateProjectdetailsContributor($request,$appNodeNew,'',[],false,true);
                }
            $isNotLeave = !$this->getLeavePage($coreData,$session,self::coreDataNode);
            return $this->saveDocumentAndRedirect($request,$isNotLeave ? $appNode : $appNodeNew,$isNotLeave ? $appNodeNew : null);
        } // if ($coreData->isSubmitted())
        return $this->render('AppData/coreData.html.twig', $this->setRenderParameters($request,$coreData,
            ['positions' => $positions,
                'funding' => self::fundingTypes,
                'support' => array_diff_key(self::supportTypes,!$isEUB ? [self::supportCenter => ''] : []),
                'applicantInfo' => self::applicantContributorsInfosTypes,],'appData.coreData'));
    }

    #[Route('/appData/votes', name: 'app_votes')]
    public function showVotes(Request $request): Response {
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
            if ($appType===self::appExtended || $appType===self::appResubmission) { // set reference because text field is disabled
                $votesNode->{self::instVote}->{self::instReference} = $appTypeNode->{self::descriptionNode};
            }
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
        $translationPrefix = 'votes.otherVote.description.';
        $tempVal = $this->translateString($translationPrefix.'positiveNo');
        return $this->render('AppData/votes.html.twig', $this->setRenderParameters($request,$votes,
                ['appType' => $appType,
                 'otherVoteResultHeadingText' => ['positive' => $tempVal, self::otherVoteResultNegative => $this->translateString($translationPrefix.self::otherVoteResultNegative), 'noVote' => $tempVal]],'appData.votes')
            );
    }

    #[Route('/appData/medicine', name: 'app_medicine')]
    public function showMedicine(Request $request): Response {
        return $this->createFormAndHandleSubmit(MedicineType::class,$request,[self::appDataNodeName,self::medicine],
            ['hintArray' => $this->createStringArray(['0','1'],'medicine.physician.description.textHint')]);
    }

    #[Route('/appData/summary', name: 'app_summary')]
    public function showSummary(Request $request): Response {
        return $this->createFormAndHandleSubmit(SummaryType::class,$request,[self::appDataNodeName,self::summary],
            ['maxChars' => 3000,
             self::pageTitle => 'appData.summaryPage']);
    }

    // functions for core data
    /** Checks if the qualification question was answered with yes.
     * @param array $coreDataArray array containing the core data
     * @return bool true if qualification questions exists and was answered with yes, false otherwise
     */
    private function getQualification(array $coreDataArray): bool {
        return ($coreDataArray[self::qualification] ?? '')==='0';
    }

    /** Updates the applicant and the supervisor.
     * @param array $contributors array containing all contributors
     * @param array $data array containing the submitted data
     * @param string $type must equal 'applicant' or 'supervisor'
     */
    private function updateContributor(array &$contributors, array $data, string $type): void {
        $dataType = $data[$type];
        $tempArray = [];
        foreach (self::applicantContributorsInfosTypes as $info) {
            $tempArray[$info] = $dataType[$info] ?? '';
        }
        if ($type===self::applicant) {
            $contributors[0][self::infosNode] = $tempArray;
        }
        else { // supervisor
            $tasks = $contributors[1][self::taskNode] ?? '';
            if ($tasks!=='' && array_key_exists(self::supervisorNode,$tasks)) { // supervisor already exists
                $contributors[1][self::infosNode] = $tempArray;
            }
            else { // supervisor does not exist -> add as second contributor
                $this->addSupervisor($contributors,$tempArray);
            }
        }
    }
}