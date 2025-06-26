<?php

namespace App\Classes;

use App\Abstract\ControllerAbstract;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Contributors\ContributorsTrait;
use App\Traits\PageTrait;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Session\Session;

class CheckDocClass extends ControllerAbstract
{
    use AppDataTrait; // application data
    use ContributorsTrait; // contributors
    use ProjectdetailsTrait; // projectdetails
    use PageTrait;

    private string $checkLabel; // contains all error messages or the message that no error was found
    private bool $anyMissing = false; // temporary variable indicating if any error was found on the current page
    private string $curWindow = ''; // title of the current window
    private string $curSubPageHeading = ''; // current heading of a type of page
    private string $curPage = ''; // title of the current page
    private array $isOne = [self::studyNode => true, self::groupNode => true, self::measureTimePointNode => true]; // true if one element of the type is created
    private array $IDs = [self::studyNode => 0, self::groupNode => 0, self::measureTimePointNode => 0];
    private array $studyGroupName = [self::studyNode => '', self::groupNode => '']; // name of the current study and group
    private bool $anyWindowMissing = false; // indicates if there is any error on any page of the current window
    private bool $isAppTypeShort = false; // true if application type is new and short, i.e., can only be true if committeeType is TUC
    private bool $isTwoAddressees = false; // gets true if $addressee is not 'participants'
    private array $paramsAddressee = []; // translation parameters for addressee
    private array $paramsParticipants = []; // translation parameters for participants, if applicable
    private SimpleXMLElement $appNode; // root node of the xml-document
    private array $appArray = []; // root node of the xml-document as an array
    private string $appType = ''; // application type
    private array $appDataArray = []; // contains all data of the application data
    private array $coreDataArray = []; // contains the date of the core data page
    private array $committeeParam = []; // parameter for translations
    private array $contributorTasks = []; // one sub-array for each task containing all contributor that have this task. Each sub-array: key: index of the contributor. value: either empty of description of 'other'
    private array $isMandatory = []; // for each mandatory task, indicates if at least one contributor has this task
    private array $measure = []; // contains all data of the current time point that is checked
    private array $routeIDs = []; // route IDs of the current time point that is checked
    private string $addressee = ''; // addressee of the current time point that is checked
    // variables that are needed on at least on other page than the one where the question is asked, for the current time point
    private bool $isPre = false; // gets true if information is 'pre'
    private bool $noPre = false; // gets true if pre-information question is answered with 'no'
    private bool $noPreParticipants = false; // same as $noPre, but for participants, if third parties
    private bool $noPost = false; // gets  true if post-information is answered with 'no'
    private bool $noPostParticipants = false; // same as $noPost, but for participants, if third parties
    private array $information = [2,2]; // 0: pre information, 1: post information
    private array $informationII = [2,2]; // same as $information, but for participants, if third parties
    private bool $isInformationII = false; // gets true if informationII is active
    private bool $isFeedback = false; // gets true if 'feedback' is chosen in interventions
    private bool $isNoPresence = false; // gets true if the presence question is answered with 'no'
    private bool $isFinding = false; // gets true if the findings questions is answered with 'yes'
    // prefixes
    private const appDataPrefix = 'checkDoc.appData.';
    private const contributorsPrefix = 'checkDoc.contributors.';
    private const projectdetailsPrefix = 'checkDoc.projectdetails.pages.';
    private const projectdetailsPrefixTool = 'projectdetails.pages.';
    private const missingPrefix = 'checkDoc.missing.';
    private const missingSingle = 'checkDoc.missing.single';
    private const missingTypes = 'checkDoc.projectdetails.missingTypes';

    /** Creates an object of CheckDocClass and checks the document for errors. If an xml document is passed, the entire document is checked.
     * @param Request $request
     * @param string $page if not an empty string, only the errors on a single page are checked
     * @param bool $returnCheck if true and $page is an empty string, a boolean is returned whether no errors were found
     * @param SimpleXMLElement|bool|null $element if not null, the xml document to be checked
     * @return string|bool if \$page is an empty string and \$returnCheck is true: true is no errors were found, false otherwise; otherwise: string with errors or message that no errors were found
     * @throws Exception if an error occurs during the check
     */
    public static function getDocumentCheck(Request $request, string $page = '', bool $returnCheck = false, SimpleXMLElement|bool $element = null): string|bool {
        $checkDoc = new CheckDocClass(self::$translator);
        $session = $request->getSession();
        // set variables
        $checkDoc->appNode = $checkDoc->getXMLfromSession($session);
        $checkDoc->appArray = $checkDoc->xmlToArray($checkDoc->appNode);
        $checkDoc->committeeParam = $checkDoc->setCommittee($session,$checkDoc->appArray[self::committee],$session->get(self::language),false);
        $checkDoc->appDataArray = $checkDoc->appArray[self::appDataNodeName];
        $checkDoc->coreDataArray = $checkDoc->appDataArray[self::coreDataNode];
        $tempArray = $checkDoc->coreDataArray[self::applicationType];
        $checkDoc->appType = $tempArray[self::chosen];
        $checkDoc->isAppTypeShort = $checkDoc->committeeParam[self::committeeType]==='TUC' && $checkDoc->appType===self::appNew && $tempArray[self::descriptionNode]===self::appTypeShort;
        $checkDoc->contributorTasks = array_combine(self::tasksNodes,array_fill(0,count(self::tasksTypes),array()));
        $checkDoc->isMandatory = array_combine(self::tasksMandatory,array_fill(0,count(self::tasksMandatory),false));
        foreach ($checkDoc->addZeroIndex($checkDoc->appArray[self::contributorsNodeName][self::contributorNode]) as $index => $contributor) {
            $tasks = $contributor[self::taskNode] ?: [];
            if ($tasks!==[]) {
                foreach ($contributor[self::taskNode] as $key => $value) { // key: node name, value: empty or description of 'other'
                    if (in_array($key,self::tasksNodes)) { // exclude applicant and supervisor
                        if (in_array($key, self::tasksMandatory)) {
                            $checkDoc->isMandatory[$key] = true; // may already be true
                        }
                        $checkDoc->contributorTasks[$key][$index] = $value;
                    }
                }
            }
        }
        $checkDoc->checkLabel = '';
        // check document
        if ($element!==null) {
            return $checkDoc->checkDocument($request,$element);
        }
        else {
            if ($session->has(self::docName)) {
                if ($page==='') {
                    $returnVal = $checkDoc->checkDocument($request);
                    if ($returnCheck) {
                        $returnVal = $returnVal===$checkDoc->getNoError();
                    }
                    return $returnVal;
                }
                else {
                    try {
                        $routeParams = $request->get('_route_params');
                        if (array_key_exists(self::measureID,$routeParams)) { // check of a projectdetails page
                            $studyID = $routeParams[self::studyID];
                            $groupID = $routeParams[self::groupID];
                            $measureID = $routeParams[self::measureID];
                            $checkDoc->IDs = [self::studyNode => $studyID, self::groupNode => $groupID,self::measureTimePointNode => $measureID];
                            $checkDoc->measure = $checkDoc->xmlToArray($checkDoc->getMeasureTimePointNode($checkDoc->appNode,[self::studyID => $studyID, self::groupID => $groupID, self::measureID => $measureID]));
                            $checkDoc->setProjectdetailsVariables();
                        }
                        $type = '';
                        switch ($page) {
                            case self::appDataNodeName: // landing page for application data
                                $checkDoc->checkCoreData();
                                $checkDoc->checkVotes();
                                $checkDoc->checkMedicine();
                                $checkDoc->checkSummary();
                                break;
                            case self::coreDataNode:
                                $checkDoc->checkCoreData(false);
                                break;
                            case self::voteNode:
                                $checkDoc->checkVotes(false);
                                break;
                            case self::medicine:
                                $checkDoc->checkMedicine(false);
                                break;
                            case self::summary:
                                $checkDoc->checkSummary(false);
                                break;
                            case self::contributorsNodeName:
                                $checkDoc->checkContributors();
                                break;
                            case self::projectdetailsNodeName: // landing page for projectdetails
                                $landingArray = $session->get(self::landing);
                                $hasStudyID = array_key_exists(self::studyID,$landingArray);
                                $hasGroupID = array_key_exists(self::groupID,$landingArray);
                                $hasMeasureID = array_key_exists(self::measureID,$landingArray);
                                $type = $hasMeasureID ? self::measureTimePointNode : ($hasGroupID ? self::groupNode : ($hasStudyID ? self::studyNode : ''));
                                $studies = $checkDoc->addZeroIndex($checkDoc->appArray[self::projectdetailsNodeName][self::studyNode]);
                                $studyIDcheck = ($landingArray[self::studyID] ?? 1)-1;
                                foreach (($hasStudyID ? [$studyIDcheck => $studies[$studyIDcheck]] : $studies) as $studyID => $study) {
                                    $groups = $checkDoc->addZeroIndex($study[self::groupNode]);
                                    $groupIDcheck = ($landingArray[self::groupID] ?? 1)-1;
                                    $checkDoc->addProjectdetailsTitle(subPage: self::studyNode);
                                    foreach (($hasGroupID ? [$groupIDcheck => $groups[$groupIDcheck]] : $groups) as $groupID => $group) {
                                        $measures = $checkDoc->addZeroIndex($group[self::measureTimePointNode]);
                                        $measureIDcheck = ($landingArray[self::measureID] ?? 1)-1;
                                        $checkDoc->addProjectdetailsTitle(subPage: self::groupNode);
                                        foreach (($hasMeasureID ? [$measureIDcheck => $measures[$measureIDcheck]] : $measures) as $measureID => $measure) {
                                            $checkDoc->IDs = [self::studyNode => $studyID+1, self::groupNode => $groupID+1,self::measureTimePointNode => $measureID+1];
                                            $checkDoc->measure = $measure;
                                            $checkDoc->setProjectdetailsVariables();
                                            $checkDoc->checkGroups();
                                            $checkDoc->checkInformation(self::informationNode);
                                            $checkDoc->checkInformation(self::informationIINode);
                                            $checkDoc->checkInformationIII();
                                            $checkDoc->checkMeasures();
                                            $checkDoc->checkBurdensRisks();
                                            $checkDoc->checkConsent();
                                            $checkDoc->checkCompensation();
                                            $checkDoc->checkTexts();
                                            $checkDoc->checkLegal();
                                            $checkDoc->checkDataPrivacy();
                                            $checkDoc->checkDataReuse();
                                            $checkDoc->checkContributor();
                                        }
                                    }
                                    $checkDoc->setProjectdetailsTitle(subPage: self::groupNode);
                                }
                                $checkDoc->setProjectdetailsTitle(subPage: self::studyNode);
                                break;
                            case self::groupsNode:
                                $checkDoc->checkGroups(false);
                                break;
                            case self::informationNode:
                                $checkDoc->checkInformation(self::informationNode,false);
                                break;
                            case self::informationIINode:
                                $checkDoc->checkInformation(self::informationIINode,false);
                                break;
                            case self::informationIIINode:
                                $checkDoc->checkInformationIII(false);
                                break;
                            case self::measuresNode:
                                $checkDoc->checkMeasures(false);
                                break;
                            case self::burdensRisksNode:
                                $checkDoc->checkBurdensRisks(false);
                                break;
                            case self::consentNode:
                                $checkDoc->checkConsent(false);
                                break;
                            case self::compensationNode:
                                $checkDoc->checkCompensation(false);
                                break;
                            case self::textsNode:
                                $checkDoc->checkTexts(false);
                                break;
                            case self::legalNode:
                                $checkDoc->checkLegal(false);
                                break;
                            case self::privacyNode:
                                $checkDoc->checkDataPrivacy(false);
                                break;
                            case self::dataReuseNode:
                                $checkDoc->checkDataReuse(false);
                                break;
                            case self::contributorNode:
                                $checkDoc->checkContributor(false);
                                break;
                        }
                        return trim($checkDoc->translateString('checkDoc.'.($checkDoc->checkLabel==='' ? 'noErrorPage' : 'errorPage'),['page' => $page, 'type' => $type]).$checkDoc->checkLabel);
                    }
                    catch (\Throwable $throwable) {
                        return $returnCheck ? false : '';
                    }
                }
            }
            else {
                return false;
            }
        }
    }

    /** Checks the entire document for error.
     * @param Request $request
     * @param SimpleXMLElement|bool|null $appNode if not null, the document to be checked
     * @return string string containing the errors
     * @throws Exception if an error occurs during the check
     */
    private function checkDocument(Request $request, SimpleXMLElement|bool $appNode = null): string {
        try {
            if ($appNode===null) {
                $appNode = $this->getXMLfromSession($request->getSession(),getRecent: true);
            }
            if (!$appNode) { // no proposal is open
                return false;
            }
            $this->appArray = $this->xmlToArray($appNode);
            // get the parameters for translations involving the committee. As the function is also invoked when loading an xml-file, there may not be a session variable holding the parameters.
            $this->checkLabel = '';

            // application data
            $this->addTitle('pages.appData.title');
            $this->checkCoreData(); // core data
            $this->checkVotes(); // votes
            $this->checkMedicine(); // medicine
            $this->checkSummary(); // summary
            $this->setAppDataTitle();
            $this->setTitle();

            // contributors
            $this->addTitle(self::contributorsPrefix.'title');
            $this->checkLabel = trim($this->checkLabel)."\n"; // no empty line between heading and first error message
            $this->anyMissing = false; // no subpage, therefore no addSubtitle() call
            $this->checkContributors();
            $this->anyWindowMissing = $this->anyMissing; // need to be set because no call of setSubtitle()
            if ($this->anyWindowMissing) {
                $this->checkLabel .= "\n";
            }
            $this->setTitle();

            // project details
            $this->addTitle('pages.projectdetails.title');
            $windowArray = $this->addZeroIndex($this->appArray[self::projectdetailsNodeName][self::studyNode]); // all studies
            $this->isOne[self::studyNode] = count($windowArray)===1;
            $anyInformation = false; // gets true if any information is pre or post
            $allInformationChosen = true; // gets false if any pre or post information question is not yet answered
            foreach ($windowArray as $studyID => $study) {
                $this->studyGroupName[self::studyNode] = $study[self::nameNode]; // is set again in setProjectdetailsVariables, but needed for setStudyGroup
                foreach ($this->setStudyGroup(self::studyNode,$studyID,$study) as $groupID => $group) {
                    $this->studyGroupName[self::groupNode] = $group[self::nameNode]; // is set again in setProjectdetailsVariables, but needed for setStudyGroup
                    foreach ($this->setStudyGroup(self::groupNode,$groupID,$group) as $measureID => $measure) {
                        $this->measure = $measure;
                        $this->IDs[self::measureTimePointNode] = $measureID+1;
                        $this->setProjectdetailsVariables();
                        $anyInformation = $anyInformation || in_array(0,$this->information);
                        $allInformationChosen = $allInformationChosen && ($this->isPre || $this->information[1]!==2);

                        // groups
                        $this->checkGroups();

                        // information
                        $this->checkInformation(self::informationNode);

                        // informationII
                        if ($this->isInformationII) {
                            $this->checkInformation(self::informationIINode);
                        }

                        // informationIII
                        $this->checkInformationIII();

                        // measures
                        $this->checkMeasures();

                        // burdens/risks
                        $this->checkBurdensRisks();

                        // consent
                        $this->checkConsent();

                        // compensation
                        $this->checkCompensation();

                        // texts
                        $this->checkTexts();

                        // legal
                        $this->checkLegal();

                        // data privacy
                        $this->checkDataPrivacy();

                        // data Reuse
                        $this->checkDataReuse();

                        // contributor
                        $this->checkContributor();
                    }
                }
                $this->setProjectdetailsTitle(subPage: self::groupNode);
            }
            $this->setProjectdetailsTitle(subPage: self::studyNode);
            // error messages if a task of a contributor is not selected in any measure time point
            if ($this->getMultiStudyGroupMeasure($appNode)) {
                $contributor = $this->addZeroIndex($this->appArray[self::contributorsNodeName][self::contributorNode]);
                $translationPage = self::projectdetailsPrefix.self::contributorNode.'.task';
                $anyError = false;
                foreach ($this->contributorTasks as $key => $value) { // key: node name of task. value: array with contributors. In this array: key: contributor index. value: empty or 'other' description
                    $tempString = '';
                    if ($value!==[] && $value!=='') {
                        if ($key===self::otherTask) {
                            foreach ($value as $index => $task) {
                                $this->addCheckLabelString($translationPage, ['numContributor' => 1, 'task' => $task, 'contributor' => $contributor[$index][self::infosNode][self::nameNode]]);
                                $anyError = true;
                            }
                        } else {
                            foreach ($value as $index => $description) {
                                $tempString .= ', '.$contributor[$index][self::infosNode][self::nameNode]; // name of the contributor
                            }
                            $this->addCheckLabelString($translationPage, ['numContributor' => count($value), 'task' => $this->translateString('contributors.tasks.'.$key), 'contributor' => substr($tempString, 2)]);
                            $anyError = true;
                        }
                    }
                    if ($anyError) {
                        $this->checkLabel .= "\n";
                        $this->anyWindowMissing = true; // need to be set here because the above checks do not call setProjectdetailsTitle()
                    }
                }
            }
            // error messages for project title participation and information
            if ($allInformationChosen) {
                $tempVal = $this->coreDataArray[self::projectTitleParticipation][self::chosen];
                if ($tempVal===self::projectTitleNotApplicable && $anyInformation) {
                    $this->addCheckLabelString('checkDoc.projectTitleToInformation');
                }
                elseif (!$anyInformation && !in_array($tempVal,['',self::projectTitleNotApplicable])) {
                    $this->addCheckLabelString('checkDoc.informationToProjectTitle');
                }
            }
            $this->setTitle();
            // final check
            $this->checkLabel = trim($this->checkLabel);
            if ($this->checkLabel==='') {
                $this->checkLabel = $this->getNoError();
            }
        } catch (\Throwable $throwable) { // catches exceptions and error
            throw new Exception();
        }
        return $this->checkLabel;
    }

    /** Sets the variables for a study or group
     * @param string $type type. Must equal 'study' or 'group'
     * @param int $id id of the type
     * @param array $array array containing all studies or all groups of a study
     * @return array all groups of the current study if $type equals 'study', all time points for the current group otherwise
     */
    private function setStudyGroup(string $type, int $id, array $array): array {
        $nextLevel = $type===self::studyNode ? self::groupNode : self::measureTimePointNode;
        $this->IDs[$type] = $id+1;
        $this->addProjectdetailsTitle(subPage: $type);
        $returnArray = $this->addZeroIndex($array[$nextLevel]);
        $this->isOne[$nextLevel] = count($returnArray)===1;
        return $returnArray;
    }

    // checks of individual pages

    /** Checks for errors on the core data page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @throws \DateMalformedStringException
     */
    private function checkCoreData(bool $setTitle = true): void {
        $this->addAppDataTitle(self::coreDataNode,$setTitle);
        $translationPrefix = self::appDataPrefix.self::coreDataNode.'.';
        $coreDataArray = $this->appDataArray[self::coreDataNode];
        // project titles
        $this->checkMissingContent($coreDataArray,[self::projectTitle => 'coreData.projectTitle']);
        // project title participation
        $tempVal = $translationPrefix.self::projectTitleParticipation;
        $this->checkMissingTextfield($coreDataArray[self::projectTitleParticipation],null,self::projectTitleDifferent,$tempVal,$tempVal);
        // application type
        $tempArray = $coreDataArray[self::applicationType];
        $appType = $this->checkMissingChosen($tempArray,'coreData.appType.title',null);
        if (array_key_exists(self::descriptionNode,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $translationPrefix.($appType===self::appNew ? 'newType' : 'description')]);
        }
        // qualification
        $isQualification = true;
        if (array_key_exists(self::qualification,$coreDataArray)) {
            $this->checkMissingContent($coreDataArray,[self::qualification => $translationPrefix.self::qualification]);
            $isQualification = $coreDataArray[self::qualification]==='0';
        }
        // applicant and supervisor
        $this->checkApplicantSupervisor($coreDataArray,self::applicant);
        if (array_key_exists(self::supervisor,$coreDataArray)) {
            $this->checkApplicantSupervisor($coreDataArray,self::supervisor);
        }
        // project start and end
        $tempArray = $coreDataArray[self::projectStart];
        $tempPrefix = 'coreData.project.';
        $this->checkMissingContent($tempArray,[self::chosen => $tempPrefix.'start.title'],parameter: $this->committeeParam);
        $this->checkMissingContent($coreDataArray,[self::projectEnd => $tempPrefix.'end.title']);
        $start = $tempArray[self::chosen];
        $isBegun = array_key_exists(self::descriptionNode,$tempArray);
        $today = new DateTime('today');
        $validStart = false; // gets true if a date is selected
        if (!($start==='' || $start==='0' || $isBegun)) { // if 'next' is selected, $start is '0' and $isBegun is false
            $start = (new DateTime($start))->setTime(0,0);
            $validStart = true;
            if ($start<=$today) {
                $this->addCheckLabelString($translationPrefix.'start');
            }
        }
        if ($isBegun) {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $translationPrefix.'begun']);
        }
        $end = $coreDataArray[self::projectEnd];
        if ($end!=='') {
            $end = (new DateTime($end))->setTime(0,0);
            if ($end<=$today) {
                $this->addCheckLabelString($translationPrefix.'end');
            }
            elseif ($validStart && $end<$start) { // $start and $end are either both or neither empty strings
                $this->addCheckLabelString($translationPrefix.'endBeforeStart');
            }
        }
        // funding
        $tempArray = $coreDataArray[self::funding];
        $tempPrefix = 'coreData.funding.';
        $isFunding = $tempArray!=='';
        $tempVal = $isFunding && array_key_exists(self::fundingQuali,$tempArray);
        if (!$isFunding) {
            $this->addCheckLabelString($this->translateString($tempPrefix.'title').$this->translateString(self::missingSingle),colorRed: false);
        }
        elseif (!$tempVal) {
            foreach ($tempArray as $key => $source) {
                $this->checkMissingContent($source,[self::descriptionNode => $tempPrefix.$key],true);
                if (array_key_exists(self::fundingStateNode,$source)) {
                    $this->checkMissingContent($source,[self::fundingStateNode => $translationPrefix.'fundingState'],parameter: ['type' => $key]);
                }
            }
        }
        elseif (!$isQualification) { // isQualification can only be false if the question was asked
            $this->addCheckLabelString($translationPrefix.self::fundingQuali);
        }
        // conflict
        $tempArray = $coreDataArray[self::conflictNode];
        $tempPrefix = $translationPrefix.self::conflictNode.'.';
        $chosen = $this->checkMissingChosen($tempArray,$tempPrefix.'title',2,true);
        if (array_key_exists(self::descriptionNode,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.'description.'.($chosen===0 ? 'yes' : 'no')]);
        }
        if (array_key_exists(self::participantDescription,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::participantDescription => $tempPrefix.'participants'],true);
        }
        // support
        $tempArray = $coreDataArray[self::supportNode];
        if ($this->checkMissingChildren($coreDataArray,self::supportNode,$translationPrefix.self::supportNode) && !array_key_exists(self::noSupport,$tempArray)) { // at least one support type except no support was chosen
            foreach (array_keys($tempArray) as $support) {
                $this->checkMissingContent($tempArray,[$support => 'coreData.support.type.'.$support],true);
            }
        }
        //guidelines
        if (array_key_exists(self::guidelinesNode,$coreDataArray) && $coreDataArray[self::guidelinesNode]!=='') {
            $this->checkMissingContent($coreDataArray[self::guidelinesNode],[self::descriptionNode => $translationPrefix.self::guidelinesNode],true);
        }
        $this->setAppDataTitle($setTitle);
    }

    /** Checks for errors on the votes page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkVotes(bool $setTitle = true): void {
        $this->addAppDataTitle(self::voteNode,$setTitle);
        $translationPrefix = self::appDataPrefix.self::voteNode.'.';
        $voteArray = $this->appDataArray[self::voteNode];
        $pageArray = $voteArray[self::otherVote];
        $otherVote = $translationPrefix.self::otherVote;
        if ($this->checkMissingTextfield($pageArray,2,0,$otherVote,$translationPrefix.'otherVoteCommittee')===0) { // answer was yes
            $chosen = $this->checkMissingChosen($pageArray,$translationPrefix.self::otherVoteResult,null, false,self::otherVoteResult);
            if ($chosen!=='') { // result question was answered
                $this->checkMissingContent($pageArray,[self::otherVoteResultDescription => $otherVote.($chosen===self::otherVoteResultNegative ? 'Negative' : 'PositiveNo')]);
            }
        }
        $pageArray = $voteArray[self::instVote];
        if ($this->checkMissingChosen($pageArray,$translationPrefix.'instVote',2,true,self::chosen,$this->committeeParam)===0) { // answer was yes
            $this->checkMissingContent($pageArray,array_merge(!in_array($this->appType,[self::appExtended,self::appResubmission]) ? [self::instReference => $translationPrefix.'instVoteReference'] : [],[self::instVoteText => $translationPrefix.self::instVoteText]));
        }
        $this->setAppDataTitle($setTitle);
    }

    /** Checks for errors on the medicine page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkMedicine(bool $setTitle = true): void {
        $this->addAppDataTitle(self::medicine,$setTitle);
        $translationPrefix = self::appDataPrefix.self::medicine.'.';
        $tempPrefix = $translationPrefix.self::medicine;
        $pageArray = $this->appDataArray[self::medicine];
        $this->checkMissingTextfield($pageArray[self::medicine],2,0,$tempPrefix,$tempPrefix,true);
        $translationPrefix .= 'physician.';
        $tempArray = $pageArray[self::physicianNode];
        if ($this->checkMissingChosen($tempArray,$translationPrefix.self::chosen,2)===0) {
            $tempArray = $tempArray[self::descriptionNode];
            $translationPrefix .= self::descriptionNode.'.';
            $this->checkMissingTextfieldEmpty($tempArray,$translationPrefix.self::chosen,$translationPrefix.self::descriptionNode,false,parameters: $this->committeeParam);
        }
        $this->setAppDataTitle($setTitle);
    }

    /** Checks for errors on the summary page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkSummary(bool $setTitle = true): void {
        $this->addAppDataTitle(self::summary,$setTitle);
        $this->checkMissingContent($this->appDataArray[self::summary],[self::descriptionNode => 'pages.appData.summary']);
        $this->setAppDataTitle($setTitle);
    }

    /** Checks for errors on the contributors page.
     * @return void
     */
    private function checkContributors(): void {
        $windowArray = $this->appArray[self::contributorsNodeName][self::contributorNode];
        if (array_key_exists(self::infosNode,$windowArray)) { // one contributor
            $windowArray = [0 => $windowArray];
        }
        $tasksPrefix = self::contributorsPrefix.'tasks.';
        // check individual contributors
        $isStudentPhd = in_array($this->coreDataArray[self::applicant][self::position],[self::positionsStudent,self::positionsPhd]) && ($this->coreDataArray[self::qualification] ?? '')==='0';
        foreach ($windowArray as $index => $contributor) {
            $infos = $contributor[self::infosNode];
            $tasks = $contributor[self::taskNode];
            $parameter = ['{index}' => $index+1, '{name}' => $infos[self::nameNode]];
            // infos
            if (!($index===0 || $index===1 && $isStudentPhd)) {
                $lineTitle = $this->translateString(self::contributorsPrefix.'lineTitle',$parameter);
                $this->checkMissingContent($infos,$this->translateArray('multiple.infos.',self::infosMandatory,true),lineTitle: $lineTitle);
                $tempPrefix = self::contributorsPrefix.self::infosNode.'.';
                $tempVal = $infos[self::nameNode];
                if ($tempVal!=='' && count(explode(' ',$tempVal))===1) {
                    $this->addCheckLabelString($lineTitle.': '.$this->translateString($tempPrefix.self::nameNode),colorRed: false);
                }
                $tempVal = $infos[self::eMailNode];
                if ($tempVal!=='' && !filter_var($tempVal,FILTER_VALIDATE_EMAIL)) {
                    $this->addCheckLabelString($lineTitle.': '.$this->translateString($tempPrefix.'validEmail'),colorRed: false);
                }
                $tempVal = $infos[self::phoneNode] ?? '';
                if ($tempVal!=='' && !preg_match("/^\+?([0-9][\s\/-]?)+[0-9]+$/",$tempVal)) {
                    $this->addCheckLabelString($lineTitle.': '.$this->translateString($tempPrefix.'validPhone'),colorRed: false);
                }
            }
            // tasks
            if ($tasks==='') { // contributor does not have any task
                $this->addCheckLabelString($tasksPrefix.'missing',$parameter);
            }
            else {
                foreach ($tasks as $key => $value) { // key: node name, value: empty or description of 'other'
                    if ($key===self::tasksTypes[self::otherTask] && $value==='') { // other task description is empty
                        $this->addCheckLabelString($tasksPrefix.'missingOther',$parameter,false);
                    }
                } // foreach
            } // else
        }
        // check if any mandatory task is missing
        foreach ($this->isMandatory as $task => $value) {
            if (!$value) {
                $this->addCheckLabelString($tasksPrefix.'missingMandatory',['{task}' => $this->translateString('contributors.tasks.'.$task)]);
            }
        }
    }

    /** Checks for errors on the groups page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkGroups(bool $setTitle = true): void {
        $this->addProjectdetailsTitle(setTitle: $setTitle, subPage: self::measureTimePointNode);
        $translationPage = self::projectdetailsPrefix.self::groupsNode.'.';
        $this->addProjectdetailsTitle(self::groupsNode,$setTitle);
        $pageArray = $this->measure[self::groupsNode];

        // age
        $this->checkMissingContent($pageArray,[self::minAge => $translationPage.'minAge',self::maxAge => $translationPage.'maxAge']);
        $minAge = $this->getIntFromString($pageArray[self::minAge],101);
        $maxAge = $this->getIntFromString($pageArray[self::maxAge],101);
        $isMaxAge = $maxAge!==101; // if upper limit of minAge or maxAge is changed in groups template, it maybe has to be changed here, too
        if ($minAge<18) {
            if ($this->isAppTypeShort) {
                $this->addCheckLabelString($translationPage.'minAgeAppType');
            }
            if ($minAge<16 && $isMaxAge && $maxAge>17) {
                $this->addCheckLabelString($translationPage.'minAgeWards');
            }
        }
        if ($maxAge>-1 && $minAge<101 && $isMaxAge && $minAge>$maxAge) {
            $this->addCheckLabelString($translationPage.'maxGreaterMin');
        }
        // examined people
        $isExamined = $this->checkMissingChildrenOther($pageArray, self::examinedPeopleNode, $translationPage.self::examinedPeopleNode);
        if ($isExamined) { // at least one group is selected
            $tempArray = $pageArray[self::examinedPeopleNode]; // has at least one element at this point
            if (count($tempArray)>1 || !array_key_exists(self::healthyExaminedNode,$tempArray)) { // at least one group except healthy is selected
                $this->checkMissingContent($pageArray,[self::peopleDescription => $translationPage.self::descriptionNode],true); // description of groups
            }
            foreach ([self::physicalExaminedNode, self::mentalExaminedNode] as $group) {
                if ($this->isAppTypeShort && array_key_exists($group,$tempArray)) {
                    $this->addCheckLabelString($translationPage.'physicalMental',['group' => $group]);
                }
            }
        }
        // closed group
        $tempPrefix = $translationPage.self::closedNode.'.';
        $tempArray = $pageArray[self::closedNode];
        if($this->checkMissingChosen($tempArray,$tempPrefix.'title',2,true)===0) {
            $this->checkMissingChildrenOther($tempArray, self::closedTypesNode, $tempPrefix.'types', [self::closedOther => $tempPrefix.'other']);
        }
        // criteria
        $tempPrefix = $translationPage.self::criteriaNode.'.';
        $criteria = $pageArray[self::criteriaNode];
        $tempArray = $criteria[self::criteriaIncludeNode];
        $curCriteria = $tempArray[self::criteriaNode];
        if (!$this->getBoolFromString($tempArray[self::noCriteriaNode]) && (is_string($curCriteria) ? 0 : count($curCriteria))<2) {
            $this->addCheckLabelString($tempPrefix.'include',colorRed: false); // age is only inclusion criterion
        }
        $tempArray = $criteria[self::criteriaExcludeNode];
        if (!$this->getBoolFromString($tempArray[self::noCriteriaNode])) {
            $this->checkMissingChildren($tempArray,self::criteriaNode,$tempPrefix.'exclude');
        }
        // sample size
        $this->checkMissingContent($pageArray[self::sampleSizeNode],[self::sampleSizeTotalNode => $translationPage.'sampleSize',self::sampleSizePlanNode => $translationPage.'planning'],default: '0'); // both in same line. Leads also to the planning message if '0' was entered
        // recruitment
        $tempArray = $pageArray[self::recruitment];
        $tempPrefix = $translationPage.self::recruitment.'.';
        if ($this->checkMissingChildrenOther($tempArray,self::recruitmentTypesNode,$tempPrefix.'missing')) {
            $types = $tempArray[self::recruitmentTypesNode];
            if (array_intersect(array_keys($types),['external',self::recruitmentOther])!==[] && $tempArray[self::descriptionNode]==='') {
                $this->addCheckLabelString($tempPrefix.'further',colorRed: false);
            }
            if (array_key_exists(self::recruitmentLecture,$types) && $isExamined && !array_key_exists(self::dependentExaminedNode,$pageArray[self::examinedPeopleNode])) {
                $this->addCheckLabelString($tempPrefix.'dependent');
            }
        }
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the information(II) page.
     * @param string $page page to be checked. Must equal 'information' or 'informationII'
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkInformation(string $page, bool $setTitle = true): void {
        $this->addProjectdetailsTitle($page,$setTitle);
        $pageArray = $this->measure[$page];
        if ($pageArray!=='') { // if informationII and not active, $pageArray is an empty string
            $translationStart = self::projectdetailsPrefix.self::informationNode.'.';
            $pre = $this->checkMissingChosen($pageArray,$translationStart.'missingPre',2,true);
            if ($pre<2) {
                $tempArray = $pageArray[self::informationAddNode];
                if ($pre===0) { // answer was yes
                    $this->checkMissingChosen($pageArray,$translationStart.'typePre',null,name: self::descriptionNode); // type of information
                    $tempVal = $tempArray[self::chosen]; // content
                    if ($tempVal==='') {
                        $this->addCheckLabelString($translationStart.'preContent',colorRed: false);
                    }
                    elseif ($tempVal!==self::complete) { // partial or deceit
                        $this->checkMissingTextfieldEmpty($tempArray,$translationStart.'deceit',$translationStart.'deceitDescription',false,self::complete); // complete post-information and description of information given
                        if (array_key_exists(self::preCompleteType,$tempArray)) {
                            $this->checkMissingChosen($tempArray,$translationStart.'deceitType',null,true,self::preCompleteType);
                        }
                        if ($tempVal===self::deceit && $this->isAppTypeShort) {
                            $this->addCheckLabelString($translationStart.'deceitMain',['{addressee}' => $this->translateString('projectdetails.addressee.'.($page===self::informationNode ? 'thirdParties.' : 'participants.').$this->addressee)]);
                        }
                    }
                    if (array_key_exists(self::attendanceNode,$pageArray)) {
                        $this->checkMissingChosen($pageArray,$translationStart.self::attendanceNode,2,true,self::attendanceNode,$this->paramsAddressee);
                    }
                }
                elseif ($pre===1) { // answer was no
                    $this->checkMissingContent($pageArray,[self::descriptionNode => $translationStart.'descriptionPre']);
                    if ($this->checkMissingTextfield($tempArray,2,1,$translationStart.'missingPost',$translationStart.'descriptionPost')===0) { // post-information
                        $this->checkMissingContent($tempArray,[self::descriptionNode => $translationStart.'typePost']);
                    }
                }
            }
        }
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the informationIII page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkInformationIII(bool $setTitle = true): void {
        $informationIII = $this->measure[self::informationIIINode];
        if ($informationIII!=='') { // input needs to be made on informationIII page
            $this->addProjectdetailsTitle(self::informationIIINode,$setTitle);
            $this->checkMissingContent($informationIII,self::informationIIIInputsTypes);
            $this->setProjectdetailsTitle($setTitle);
        }
    }

    /** Checks for errors on the measures page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkMeasures(bool $setTitle = true): void {
        $this->addProjectdetailsTitle(self::measuresNode,$setTitle);
        $pageArray = $this->measure[self::measuresNode];
        $translationPage = self::projectdetailsPrefix.self::measuresNode.'.';
        // measures
        $tempArray = $pageArray[self::measuresNode];
        $this->checkMultiSelectionTextfield($tempArray,self::measuresTypesNode,self::missingTypes,parameters: ['{type}' => $this->translateString($translationPage.'measures')]);
        // interventions
        $tempArray = $pageArray[self::interventionsNode];
        $tempVal = $translationPage.self::interventionsNode;
        $this->checkMissingChildren($tempArray,self::interventionsTypesNode,self::missingTypes,['{type}' => $this->translateString($tempVal)]);
        if (array_key_exists(self::descriptionNode,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $tempVal],true,$this->translateString(self::projectdetailsPrefixTool.self::measuresNode.'.measuresInterventions.interventions.textHints.defaultStart').'.');
        }
        // other sources
        $tempPrefix = $translationPage.self::otherSourcesNode;
        $this->checkMissingTextfield($pageArray[self::otherSourcesNode],2,0,$tempPrefix,$tempPrefix,true,self::otherSourcesNode.self::descriptionCap);
        // loan
        $tempArray = $pageArray[self::loanNode];
        $tempPrefix = $translationPage.self::loanNode.'.';
        if ($this->checkMissingChosen($tempArray,$tempPrefix.'title',2,true)===0) {
            $this->checkMissingTextfield($tempArray[self::loanReceipt],null,self::templateText,$tempPrefix.self::loanReceipt,$tempPrefix.self::descriptionNode);
            if ($this->noPre) {
                $this->addCheckLabelString($tempPrefix.'information',$this->routeIDs);
            }
        }
        // location
        $tempArray = $pageArray[self::locationNode];
        $tempPrefix = $translationPage.self::locationNode.'.';
        if ($this->checkMissingChosen($tempArray,$tempPrefix.'title',null)!=='') {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.'locationDescription']);
        }
        // presence
        $this->checkMissingChosen($pageArray,$translationPage.self::presenceNode,null,true,self::presenceNode);
        // durations
        $tempPrefix = $translationPage.self::durationNode.'.';
        $this->checkMissingContent($pageArray[self::durationNode],[self::durationMeasureTime => $tempPrefix.self::durationMeasureTime],default: '0');
        $this->checkMissingContent($pageArray[self::durationNode],[self::durationBreaks => $tempPrefix.self::durationBreaks]);
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the burdensRisks page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkBurdensRisks(bool $setTitle = true): void {
        $this->addProjectdetailsTitle(self::burdensRisksNode,$setTitle);
        $translationPage = self::projectdetailsPrefix.self::burdensRisksNode.'.';
        $title = $translationPage.'title';
        $pageArray = $this->measure[self::burdensRisksNode];
        // burdens
        $tempArray = $pageArray[self::burdensNode];
        if ($this->checkBurdensRisksErrors($tempArray,$title,self::burdensTypesNode,self::burdensNode) && array_key_exists(self::noBurdens,$tempArray[self::burdensTypesNode])) {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $translationPage.'noBurdens']);
        }
        // risks
        $tempArray = $pageArray[self::risksNode];
        if ($this->checkBurdensRisksErrors($tempArray,$title,self::risksTypesNode,self::risksNode) && array_key_exists(self::risksIntegrity,$tempArray[self::risksTypesNode]) && $this->isAppTypeShort) { // 'physical' was chosen, but application type is 'short'
            $this->addCheckLabelString($translationPage.'risksPhysical');
        }
        // burdensRisksContributors
        $tempArray = $pageArray[self::burdensRisksContributorsNode];
        $tempVal = $title;
        $params = ['burdensRisksType' => self::burdensRisksContributorsNode];
        if ($this->checkMissingTextfield($tempArray,2,0,$tempVal,$tempVal,true, parameters: $params)===0) {
            $this->checkBurdensRisksCompensation($tempArray,$params);
        }
        // finding
        $tempPrefix = $translationPage.self::findingNode.'.';
        $tempArray = $pageArray[self::findingNode];
        $this->checkMissingTextfield($tempArray,2,0,$tempPrefix.'title',$tempPrefix.self::descriptionNode,true);
        if ($this->isFinding) {
            $this->checkMissingChosen($tempArray,$tempPrefix.self::informingNode,null,true,self::informingNode);
        }
        if ($this->noPre && $this->isFinding) { // no pre information -> no finding
            $this->addCheckLabelString($tempPrefix.'information',$this->routeIDs);
        }
        // feedback
        $tempPrefix = $translationPage.self::feedbackNode.'.';
        $tempArray = $pageArray[self::feedbackNode];
        if ($this->checkMissingTextfield($tempArray,2,0,$tempPrefix.'title',$tempPrefix.self::descriptionNode,true)===0 && !$this->isFeedback) {
            $this->addCheckLabelString($tempPrefix.'feedbackInterventions',parameters: $this->routeIDs);
        }
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the consent page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkConsent(bool $setTitle = true): void {
        $this->addProjectdetailsTitle(self::consentNode,$setTitle);
        $translationPage = self::projectdetailsPrefix.self::consentNode.'.';
        $pageArray = $this->measure[self::consentNode];
        // voluntary
        $tempPrefix = $translationPage.self::voluntaryNode.'.';
        $tempArray = $pageArray[self::voluntaryNode];
        $voluntary = $this->checkVoluntaryConsent($tempArray,$tempPrefix.'title',$tempPrefix.'voluntaryParticipants',$tempPrefix.self::voluntaryNode.self::descriptionCap);
        $voluntaryAddressee = $voluntary[0];
        $voluntaryParticipant = $voluntary[1];
        $isPreParticipants = $this->informationII[0]===0;
        if (array_key_exists(self::voluntaryYesDescription,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::voluntaryYesDescription => $tempPrefix.self::voluntaryYesDescription]);
        }
        // no pre-information -> voluntary not applicable
        if (($this->noPre && $voluntaryAddressee!=='' && $voluntaryAddressee!==self::voluntaryNotApplicable)) {
            $this->addCheckLabelString($translationPage.'informationToNotApplicable',$this->paramsAddressee);
        }
        if ($this->noPreParticipants && $voluntaryParticipant!=='' && $voluntaryParticipant!==self::voluntaryNotApplicable) {
            $this->addCheckLabelString($translationPage.'informationToNotApplicable',$this->paramsParticipants);
        }
        // voluntary not applicable -> no pre-information
        if ($this->isPre && $voluntaryAddressee===self::voluntaryNotApplicable) {
            $this->addCheckLabelString($translationPage.'notApplicableToInformation',$this->paramsAddressee);
        }
        if ($isPreParticipants && $voluntaryParticipant===self::voluntaryNotApplicable) {
            $this->addCheckLabelString($translationPage.'notApplicableToInformation',$this->paramsParticipants);
        }
        // consent
        $tempPrefix = $translationPage.self::consentNode.'.';
        $this->paramsAddressee['type'] = self::consentNode;
        $this->paramsParticipants['type'] = self::consentNode;
        $consent = $this->checkVoluntaryConsent($pageArray[self::consentNode],$tempPrefix.'title',$tempPrefix.'consentParticipants',$tempPrefix.self::consentNode.self::descriptionCap);
        $consentAddressee = $consent[0];
        $isConsentChosen = $consentAddressee!=='';
        $isConsentNotApplicable = $consentAddressee===self::consentNotApplicable;
        $consentParticipant = $consent[1];
        $isConsentChosenParticipant = $consentParticipant!=='';
        $isConsentNotApplicableParticipant = $consentParticipant===self::consentNotApplicable;
        // no voluntariness -> no consent
        if ($voluntaryAddressee===self::voluntaryConsentNo && $isConsentChosen && $consentAddressee!==self::voluntaryConsentNo) {
            $this->addCheckLabelString($tempPrefix.'voluntaryToConsent',$this->paramsAddressee);
        }
        if ($voluntaryParticipant===self::voluntaryConsentNo && $isConsentChosenParticipant && $consentParticipant!==self::voluntaryConsentNo) {
            $this->addCheckLabelString($tempPrefix.'voluntaryToConsent',$this->paramsParticipants);
        }
        // pre-information and no consent -> no voluntariness
        if ($this->isPre && $consentAddressee===self::voluntaryConsentNo && !in_array($voluntaryAddressee,['',self::voluntaryConsentNo])) {
            $this->addCheckLabelString($tempPrefix.'consentToVoluntary',$this->paramsAddressee);
        }
        if ($isPreParticipants && $consentParticipant===self::voluntaryConsentNo && !in_array($voluntaryParticipant,['',self::voluntaryConsentNo])) {
            $this->addCheckLabelString($tempPrefix.'consentToVoluntary',$this->paramsParticipants);
        }
        // testing both elements separately is way faster than count(array_intersect($information,[0,1]))
        // neither pre- nor post-information -> consent not applicable
        if ($this->noPost && $isConsentChosen && !$isConsentNotApplicable) {
            $this->addCheckLabelString($translationPage.'informationToNotApplicable',$this->paramsAddressee);
        }
        if ($this->noPostParticipants && $isConsentChosenParticipant && !$isConsentNotApplicableParticipant) {
            $this->addCheckLabelString($translationPage.'informationToNotApplicable',$this->paramsParticipants);
        }
        // consent not applicable -> neither pre- nor post-information
        if (in_array(0,$this->information) && $isConsentChosen && $isConsentNotApplicable) {
            $this->addCheckLabelString($translationPage.'notApplicableToInformation',$this->paramsAddressee);
        }
        if (in_array(0,$this->informationII) && $isConsentChosenParticipant && $isConsentNotApplicableParticipant) {
            $this->addCheckLabelString($translationPage.'notApplicableToInformation',$this->paramsParticipants);
        }
        if ($this->isNoPresence && $consent[$this->isTwoAddressees ? 1 : 0]===self::consentOral) { // no contributors present -> no oral consent
            $this->addCheckLabelString($tempPrefix.self::presenceNode,$this->routeIDs);
        }
        if ($this->isFinding && ($consentAddressee===self::voluntaryConsentNo || $isConsentNotApplicable)) { // finding -> consent
            $this->addCheckLabelString($tempPrefix.self::findingNode,$this->routeIDs);
        }
        // terminate with disadvantages
        $tempPrefix = $translationPage.self::terminateConsNode.'.';
        $tempArray = $pageArray[self::terminateConsNode];
        $this->checkMissingTextfield($tempArray,2,1,$tempPrefix.'title',$tempPrefix.'description',true);
        if (array_key_exists(self::terminateConsParticipationNode,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::terminateConsParticipationNode => $tempPrefix.'participation'],true,parameter: $this->paramsAddressee);
        }
        // termination by participants
        $tempPrefix = $translationPage.self::terminateParticipantsNode.'.';
        $this->checkMissingTextfield($pageArray[self::terminateParticipantsNode],null,self::terminateParticipantsOther,$tempPrefix.'title',$tempPrefix.self::descriptionNode);
        // terminate criteria
        $this->checkMissingContent($pageArray,[self::terminateCriteriaNode => $translationPage.self::terminateCriteriaNode],true);
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the compensation page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkCompensation(bool $setTitle = true): void {
        $this->addProjectdetailsTitle(self::compensationNode,$setTitle);
        $translationPage = self::projectdetailsPrefix.self::compensationNode.'.';
        $pageArray = $this->measure[self::compensationNode];
        $awardingPrefix = $translationPage.self::awardingNode.'.';
        $isNoCompensation = false; // gets true if no compensation is given
        if ($this->checkMissingChildren($pageArray,self::compensationTypeNode,$translationPage.'missing')) {
            $typeArray = $pageArray[self::compensationTypeNode];
            $isNoCompensation = array_key_exists(self::compensationNo,$typeArray);
            if (!$isNoCompensation) { // at least one type except 'no compensation' was selected
                // types
                $typePrefix = $translationPage.self::compensationTypeNode.'.';
                if ($this->noPre && $this->noPost && (!$this->isInformationII || $this->noPreParticipants && $this->noPostParticipants)) { // no information -> no compensation
                    $this->addCheckLabelString($translationPage.'information');
                }
                foreach ($typeArray as $type => $value) {
                    $isMoney = $type===self::compensationMoney;
                    $isHours = $type===self::compensationHours;
                    $isMoneyHours = $isMoney || $isHours;
                    $prefix = $typePrefix.$type.'.';
                    $this->checkMissingContent($value,[self::descriptionNode => $prefix.'missing'],false,'0');
                    if ($isMoneyHours) {
                        $this->checkMissingContent($value,[self::moneyHourAdditionalNode => $prefix.'amount']); // real or flat
                        if ($isMoney) {
                            $tempPrefix = $prefix.self::moneyFurther;
                            $this->checkMissingTextfield($value[self::moneyFurther],2,0,$tempPrefix,$tempPrefix.self::descriptionCap,true);
                        }
                        if ($isHours && $value[self::moneyHourAdditionalNode]===self::amountFlat) {
                            $this->checkMissingContent($value,[self::hourAdditionalNode2 => $prefix.'amountFlat'],default: '0');
                        }
                    }
                    // awarding
                    $awardingArray = $value[self::awardingNode];
                    $lineTitle = $this->translateString($awardingPrefix.'type.'.$type);
                    $compensationParam = [self::compensationNode => $type];
                    $typeParams = array_merge(['type' => $lineTitle, 'typeDescription' => $this->translateString($awardingPrefix.'typeDescription',$compensationParam)],$compensationParam,$this->routeIDs);
                    if ($type===self::compensationLottery) { // announcement
                        $tempPrefix = $awardingPrefix.self::compensationLottery.'.';
                        $this->checkMissingContent($awardingArray,array_merge([self::lotteryStart.self::descriptionCap => $tempPrefix.'start', self::lotteryStart => $tempPrefix.'announcement'],array_key_exists(self::lotteryStartOtherDescription,$awardingArray) ? [self::lotteryStartOtherDescription => $tempPrefix.'announcementOther'] : []),lineTitle: $lineTitle);
                    }
                    $chosen = $this->checkMissingChosen($awardingArray,$awardingPrefix.'missing',null,parameters: $typeParams);
                    if ($chosen!=='' && $type!==self::compensationOther) {
                        if (array_key_exists(self::descriptionNode,$awardingArray)) { // (first) description text field of chosen option
                            $this->checkMissingContent($awardingArray,[self::descriptionNode => $awardingPrefix.$chosen],lineTitle: $lineTitle, parameter: $typeParams);
                        }
                        $tempPrefix = $awardingPrefix.self::laterTypesName;
                        if (array_key_exists(self::laterTypesName,$awardingArray) && $this->checkMissingChosen($awardingArray,$tempPrefix,null,name: self::laterTypesName,parameters: $typeParams)==='laterEndOther') { // information for later
                            $this->checkMissingContent($awardingArray,[self::laterOtherDescription => $tempPrefix.self::descriptionCap],true,lineTitle: $lineTitle,parameter: $typeParams);
                        }
                        if ($isMoneyHours && $chosen==='immediately' && $this->isNoPresence) { // money and hours immediately -> contributors must be present
                            $this->addCheckLabelString($translationPage.self::presenceNode,$typeParams);
                        }
                    }
                }
                // terminate
                $tempPrefix = $translationPage.self::terminateNode.'.';
                $tempArray = $pageArray[self::terminateNode];
                $chosen = $this->checkMissingChosen($tempArray,$tempPrefix.'missing',null,true);
                if (in_array($chosen,self::terminateTypesDescription)) {
                    $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.$chosen]);
                }
            }
        }
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the data privacy page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkDataPrivacy(bool $setTitle = true): void {
        // data privacy
        $pageArray = $this->measure[self::privacyNode];
        $this->addProjectdetailsTitle(self::privacyNode,$setTitle);
        $translationPage = self::projectdetailsPrefix.self::privacyNode.'.';
        $this->checkMissingContent($pageArray,[self::processingNode => $translationPage.self::processingNode],true); // processing
        // create
        $tempArray = $pageArray[self::createNode];
        $tempPrefix = $translationPage.self::createNode.'.';
        $create = $this->checkMissingChosen($tempArray,$tempPrefix.'missing',null,true);
        if ($this->noPost && !in_array($create,['',self::privacyNotApplicable])) { // neither pre- nor post-information -> create not applicable
            $this->addCheckLabelString($tempPrefix.'informationToNotApplicable',$this->routeIDs);
        }
        elseif ($create===self::privacyNotApplicable && in_array(0,$this->information)) { // create not applicable -> neither pre- nor post-information
            $this->addCheckLabelString($tempPrefix.'notApplicableToInformation',$this->routeIDs);
        }
        $isTool = $create===self::createTool;
        if ($isTool || $create==self::createSeparate) {
            $tempVal = $this->checkMissingChosen($tempArray,$translationPage.($isTool ? self::confirmIntroNode : self::createVerificationNode),null,name: self::descriptionNode);
            if ($isTool && $tempVal) {
                $tempPrefix = $translationPage.self::responsibilityNode.'.';
                $responsibility = $this->checkMissingChosen($pageArray,$tempPrefix.'missing',null,true,self::responsibilityNode);
                if ($responsibility==='private' && ($this->appDataArray[self::coreDataNode][self::qualification] ?? '')==='1') { // responsibility is private -> qualification must be answered with yes
                    $this->addCheckLabelString($tempPrefix.self::qualification);
                }
                $transferOutside = $this->checkMissingChosen($pageArray,$translationPage.self::transferOutsideNode,null,true,self::transferOutsideNode);
                $dataOnline = '';
                $dataOnlineProcessing = '';
                if (in_array($responsibility,[self::responsibilityOnlyOwn,self::privacyNotApplicable]) && // responsibility
                    in_array($transferOutside,[self::transferOutsideNo,self::privacyNotApplicable])) { // transfer outside
                    // data online
                    if (array_key_exists(self::dataOnlineNode,$pageArray)) {
                        $dataOnline = $pageArray[self::dataOnlineNode];
                        $this->checkMissingContent($pageArray,[self::dataOnlineNode => $translationPage.self::dataOnlineNode]);
                        // data processing
                        if (array_key_exists(self::dataOnlineProcessingNode,$pageArray)) {
                            $dataOnlineProcessing = $pageArray[self::dataOnlineProcessingNode];
                            $this->checkMissingContent($pageArray,[self::dataOnlineProcessingNode => $translationPage.self::dataOnlineProcessingNode]);
                        }
                    }
                    $dataPersonal = $this->checkMissingChosen($pageArray, $translationPage.self::dataPersonalNode, null, true, self::dataPersonalNode); // data personal
                    $hasPersonal = in_array($dataPersonal,self::dataPersonal); // true if any personal data are collected
                    // marking
                    $markingSecondString = self::markingNode.self::markingSuffix;
                    $markings = [self::markingNode => '', $markingSecondString => ''];
                    $isMarkingPersonal = false; // gets true if any marking is or may be personal
                    $isMarkingAnswered = true; // gets false if any marking (sub-)question is not answered
                    // variables indicating that a marking of a certain type is used
                    $isMarkingName = false; // gets true if any marking is by name
                    $isMarkingList = false; // gets true if any marking is by list
                    $isCodeMaybe = false; // gets true if any marking is by code 'generation'
                    $isCodeOnlyMarking = false; // gets false if any code is used only for marking of data
                    $isCodeNoDoc = false; // gets true if any code has no further documentation
                    foreach (array_merge([self::markingNode], array_key_exists($markingSecondString, $pageArray) ? [$markingSecondString] : []) as $marking) {
                        $tempArray = $pageArray[$marking];
                        $lineTitle = $marking===self::markingNode ? '' : $this->translateString($translationPage.self::markingNode.self::markingSuffix);
                        $tempPrefix = $translationPage.self::markingNode.'.';
                        $tempVal = $this->checkMissingChosen($tempArray, $tempPrefix.'title', null, lineTitle: $lineTitle);
                        $isMarkingAnswered = $isMarkingAnswered && $tempVal!=='';
                        $isName = $tempVal===self::markingName;
                        $isMarkingPersonal = $isMarkingPersonal || $isName;
                        $isMarkingName = $isMarkingName || $isName;
                        $markings[$marking] = $tempVal;
                        if (array_key_exists(self::descriptionNode, $tempArray)) { // can only be true if external or name
                            $this->checkMissingContent($tempArray, [self::descriptionNode => $tempPrefix.self::descriptionNode], lineTitle: $lineTitle, parameter: ['type' => $tempVal]);
                        }
                        $isInternal = $tempVal===self::markingInternal;
                        if ($tempVal===self::markingExternal || $isInternal) {
                            if ($isInternal) { // how the code is created
                                $isMarkingAnswered = $isMarkingAnswered && $this->checkMissingChosen($tempArray, $tempPrefix.self::markingInternal, null, name: self::markingInternal, lineTitle: $lineTitle)!=='';
                            }
                            if (array_key_exists(self::codePersonal, $tempArray)) { // if internal, key may not exist
                                $tempVal = $this->checkMissingChosen($tempArray, $tempPrefix.self::codePersonal, null, name: self::codePersonal, lineTitle: $lineTitle);
                                $isMarkingAnswered = $isMarkingAnswered && $tempVal!=='';
                                $isMarkingPersonal = $isMarkingPersonal || in_array($tempVal,self::markingDataResearchTypes); // whether the code has personal data
                                $isCodeMaybe = $isCodeMaybe || $tempVal===self::generation;
                                $isMarkingList = $isMarkingList || $tempVal===self::markingList;
                                $isCodeOnlyMarking = $isCodeOnlyMarking || $tempVal==='marking';
                                $isCodeNoDoc = $isCodeNoDoc || $tempVal==='anonymous';
                            }
                        }
                    }
                    if ($markings[self::markingNode]!==self::markingOther) {
                        // marking further
                        if (in_array($pageArray[self::markingNode][self::chosen] ?? '', self::markingValues)) {
                            $isMarkingAnswered = $isMarkingAnswered && $this->checkMissingChosen($pageArray, $translationPage.self::markingFurtherNode, 2, true, self::markingFurtherNode)!==2;
                        }
                        // two markings with name
                        if ($isMarkingAnswered && $markings[self::markingNode]===self::markingName && $markings[$markingSecondString]===self::markingName) {
                            $this->addCheckLabelString($translationPage.'markingName');
                        }
                        $hasMarkingPersonal = [self::markingList => $isMarkingList, self::generation => $isCodeMaybe, self::markingName => $isMarkingName];
                        $allPersonalAnswered = $isMarkingAnswered; // true if all questions which lead to personal data are answered
                        $hasPersonal = $hasPersonal || $isMarkingAnswered && $isMarkingPersonal;
                        // list
                        $hasList = false; // gets true if any data is selected
                        $isListName = false; // gets true if list contains name
                        $isListIP = false; // gets true if list contains ip
                        if (array_key_exists(self::listNode, $pageArray)) {
                            $tempPrefix = $translationPage.self::listNode.'.';
                            if ($this->checkMissingChildrenOther($pageArray, self::listNode, $tempPrefix.'missing', [self::listOther => $tempPrefix.self::descriptionNode])) {
                                $hasList = true;
                                $tempArray = $pageArray[self::listNode];
                                $isListName = array_key_exists(self::nameNode,$tempArray);
                                $isListIP = array_key_exists('listIP',$tempArray);
                            }
                        }
                        // purposes translated -> here because data personal is also needed
                        $purposeTrans = [];
                        $purposeTransGen = [];
                        foreach ($this->translateArray(self::projectdetailsPrefixTool.self::privacyNode.'.'.self::purposeResearchNode.'.typesShort.', array_merge([self::dataPersonalNode],self::allPurposeTypes), true) as $purpose => $translationKey) {
                            $purposeTrans[$purpose] = $this->translateString($translationKey);
                            if ($purpose!==self::dataPersonalNode) {
                                $purposeTransGen[$purpose] = $this->translateString(str_replace('typesShort', 'typesShortGen', $translationKey));
                            }
                        }
                        // data research
                        $isDataResearchVideo = false; // gets true if audio, photo, or video is selected
                        $accessPrefix = $translationPage.self::accessNode.'.';
                        $hasDataResearch = array_key_exists(self::dataResearchNode, $pageArray);
                        $dataResearch = [];
                        if ($hasDataResearch) {
                            $tempPrefix = $translationPage.self::dataResearchNode.'.';
                            if ($this->checkMissingChildrenOther($pageArray, self::dataResearchNode, $tempPrefix.'missing', array_combine(self::dataResearchTextFieldsAll, $this->prefixArray(self::dataResearchTextFieldsAll, $tempPrefix.self::descriptionNode.'.')))) {
                                $dataResearch = $pageArray[self::dataResearchNode];
                                $isDataResearchVideo = array_intersect(array_keys($dataResearch), ['audio','photo','video'])!==[];
                            }
                            // anonymization
                            if (array_key_exists(self::anonymizationNode, $pageArray)) {
                                $tempPrefix = $translationPage.self::anonymizationNode.'.';
                                $this->checkMissingChildrenOther($pageArray, self::anonymizationNode, $tempPrefix.'missing', [self::anonymizationOther => $tempPrefix.self::descriptionNode]);
                            }
                            // storage
                            if (array_key_exists(self::storageNode, $pageArray)) {
                                $tempPrefix = $translationPage.self::storageNode.'.';
                                $this->checkMissingTextfield($pageArray[self::storageNode], null, self::storageDelete, $tempPrefix.'missing', $tempPrefix.self::descriptionNode);
                            }
                            if (array_key_exists(self::personalKeepNode, $pageArray)) {
                                // personal keep
                                $tempPrefix = $translationPage.self::personalKeepNode.'.';
                                if ($this->checkMissingChildren($pageArray, self::personalKeepNode, $tempPrefix.'missing')) {
                                    $tempArray = $pageArray[self::personalKeepConsentNode] ?? [];
                                    foreach ($pageArray[self::personalKeepNode] as $type => $description) {
                                        $typeParam = ['type' => $this->translateString(self::projectdetailsPrefixTool.self::privacyNode.'.'.self::personalKeepNode.'.typesShort.'.$type)];
                                        if ($description==='') {
                                            $this->addCheckLabelString($tempPrefix.self::descriptionNode, $typeParam, colorRed: false);
                                        }
                                        // personal keep consent
                                        if ($tempArray[$type]==='') {
                                            $this->addCheckLabelString($translationPage.self::personalKeepConsentNode, $typeParam, colorRed: false);
                                        }
                                    }
                                }
                            }
                            // access if research data is personal
                            if (array_key_exists(self::accessNode,$pageArray)) {
                                $this->checkAccess($pageArray,self::dataPersonalNode,array_merge($this->committeeParam,[self::purposeNode => $purposeTrans[self::dataPersonalNode]]));
                            }
                        }
                        // purpose research
                        $hasPurpose = [self::purposeResearchNode => false, self::purposeFurtherNode => false];
                        $purposeResearchArray = $pageArray[self::purposeResearchNode] ?? ''; // also empty string if exists, but not yet answered
                        $isPurposeResearch = false; // gets true if questions exists and was answered
                        $hasPurposeResearch = array_key_exists(self::purposeResearchNode, $pageArray); // true if question exists
                        if ($hasPurposeResearch) { // no check of $purposeResearchArray because key may exist, but not yet answered
                            $tempPrefix = $translationPage.self::purposeResearchNode.'.';
                            $isPurposeResearch = $this->checkMissingChildren($pageArray, self::purposeResearchNode, $tempPrefix.'missing');
                            $isPurposeNotNo = $isPurposeResearch && !array_key_exists(self::purposeNo, $purposeResearchArray); // true if any purpose besides 'no purpose' is selected
                            $hasPurpose[self::purposeResearchNode] = $isPurposeNotNo;
                            $allPersonalAnswered = $allPersonalAnswered && $isPurposeResearch;
                            $hasPersonal = $hasPersonal || $isPurposeNotNo;
                            if ($isMarkingAnswered) {
                                // marking personal -> marking must have purpose besides research
                                if ($isMarkingPersonal && $isPurposeResearch && array_key_exists(self::purposeNo, $purposeResearchArray)) {
                                    $this->addCheckLabelString($tempPrefix.'markingResearch');
                                }
                                // marking only for data <-> no further purpose of marking
                                if ($isCodeOnlyMarking && $isPurposeNotNo && !in_array(true,[$isCodeNoDoc,$isMarkingList,$isMarkingName])) {
                                    $this->addCheckLabelString($tempPrefix.self::purposeNode);
                                }
                                // further purpose of marking is relatable or contact -> marking must be personal
                                if ($isPurposeResearch && !in_array(true,[$isMarkingName,$isMarkingList])) {
                                    foreach ([self::purposeRelatable,'contact'] as $type) {
                                        if (array_key_exists($type,$purposeResearchArray)) {
                                            $this->addCheckLabelString($tempPrefix.'markingPurpose',['type' => $type]);
                                        }
                                    }
                                }
                            }
                        }
                        // purpose further
                        $purposeFurtherArray = $pageArray[self::purposeFurtherNode] ?? ''; // also empty string if exists, but not yes answered
                        $isPurposeFurther = $purposeFurtherArray!=='';
                        if (array_key_exists(self::purposeFurtherNode, $pageArray)) { // no check of $purposeFurtherArray because key may exist, but not yet answered
                            $hasChildren = $this->checkMissingChildren($pageArray, self::purposeFurtherNode, $translationPage.self::purposeFurtherNode);
                            $tempVal = $hasChildren && !array_key_exists(self::purposeFurtherNode.self::purposeNo, $pageArray[self::purposeFurtherNode]);
                            $hasPurpose[self::purposeFurtherNode] = $tempVal;
                            $allPersonalAnswered = $allPersonalAnswered && $hasChildren;
                            $hasPersonal = $hasPersonal || $tempVal && !array_key_exists(self::purposeNo.self::purposeFurtherNode, $pageArray[self::purposeFurtherNode]);

                        }
                        // relatable and contact
                        if (array_key_exists(self::relatableNode,$pageArray)) {
                            $this->checkMissingChildren($pageArray,self::relatableNode,$translationPage.self::relatableNode);
                        }
                        $purposeDataCompensation = []; // contains all data that is collected for purpose compensation, if selected
                        $isMarkingRemove = [self::markingList => false, self::generation => false, self::markingName => false]; // each value gets true if any marking remove is by the respective key
                        foreach ($hasPurpose as $purposeType => $isPurposeType) {
                            if ($isPurposeType) { // question was asked and at least one purpose except 'no purpose' was selected
                                foreach ($pageArray[$purposeType] as $purposeName => $questions) { // keys: selected purpose, values: sub-questions for selected purpose
                                    if ($questions!=='') {
                                        $purposeNameWoPrefix = str_replace(self::purposeFurtherNode, '', $purposeName);
                                        $purposeParam = array_merge($this->committeeParam, ['purpose' => $purposeTrans[$purposeNameWoPrefix]]);
                                        $purposeParamGen = ['purpose' => $purposeTransGen[$purposeNameWoPrefix]];
                                        // purpose data
                                        $tempPrefix = $translationPage . self::purposeDataNode . '.';
                                        if (array_key_exists(self::purposeDataNode, $questions) && $this->checkMissingChildrenOther($questions, self::purposeDataNode, $tempPrefix . 'missing', [$purposeNameWoPrefix . self::purposeDataOther => $tempPrefix . self::descriptionNode], $purposeParam)) {
                                            $tempArray = $questions[self::purposeDataNode];
                                            if (!array_key_exists($purposeNameWoPrefix . 'name', $tempArray)) {
                                                foreach (['address', 'iban'] as $type) {
                                                    if (array_key_exists($purposeNameWoPrefix . $type, $tempArray)) {
                                                        $this->addCheckLabelString($tempPrefix . $type, $purposeParam);
                                                    }
                                                }
                                            }

                                        }
                                        if ($purposeNameWoPrefix === self::purposeCompensation) {
                                            $tempArray = $questions[self::purposeDataNode];
                                            if ($tempArray !== '') {
                                                $purposeDataCompensation = array_merge($purposeDataCompensation, array_keys($tempArray));
                                            }
                                        }
                                        // marking remove
                                        $removeUC = [self::markingList => ucfirst(self::markingList), self::generation => 'Code', self::markingName => ucfirst(self::markingName)];
                                        if (array_key_exists(self::markingRemoveNode, $questions)) {
                                            $tempPrefix = $translationPage . self::markingRemoveNode . '.';
                                            $tempArray = $questions[self::markingRemoveNode];
                                            if ($this->checkMissingTextfieldEmpty($tempArray, $tempPrefix . 'missing', $tempPrefix . self::descriptionNode, false, parameters: $purposeParam) !== '') {
                                                if (array_key_exists(self::markingRemoveMiddleNode, $tempArray)) { // middle
                                                    if ($this->checkMissingChildren($tempArray, self::markingRemoveMiddleNode, $tempPrefix . self::markingRemoveMiddleNode, $purposeParam)) {
                                                        $tempVal = implode('', array_keys($tempArray[self::markingRemoveMiddleNode]));
                                                        foreach ($removeUC as $marking => $markingUC) {
                                                            $isMarkingRemove[$marking] = $isMarkingRemove[$marking] || str_contains($tempVal, $markingUC); // each selection is 'purposemiddleSelection'
                                                        }
                                                    }
                                                } else { // later description
                                                    $this->checkMissingContent($tempArray, [self::laterDescription => $tempPrefix . self::laterDescription], parameter: $purposeParam);
                                                }
                                            }
                                            if ($isMarkingAnswered) {
                                                foreach ($isMarkingRemove as $marking => $isRemove) {
                                                    if (!$hasMarkingPersonal[$marking] && $isRemove) {
                                                        $this->addCheckLabelString($tempPrefix.$marking, $purposeParamGen);
                                                    }
                                                }
                                            }
                                        }
                                        // personal remove
                                        $tempArray = $questions[self::personalRemoveNode];
                                        $tempPrefix = $translationPage . self::personalRemoveNode . '.';
                                        $tempVal = $this->checkMissingChosen($tempArray, $tempPrefix . 'missing', null, true, parameters: $purposeParam);
                                        if (array_key_exists(self::descriptionNode, $tempArray)) {
                                            $this->checkMissingContent($tempArray, [self::descriptionNode => $tempPrefix . str_replace($purposeNameWoPrefix, '', $tempVal)], parameter: $purposeParam);
                                        }
                                        // access
                                        $this->checkAccess($questions,$purposeNameWoPrefix,$purposeParam);
                                    }
                                }
                            }
                        }
                        // order processing description
                        if (array_key_exists(self::orderProcessingDescriptionNode,$pageArray)) {
                            $this->checkMissingContent($pageArray[self::orderProcessingDescriptionNode], array_combine(self::orderProcessingKnownTexts, $this->prefixArray(self::orderProcessingKnownTexts,$translationPage.self::orderProcessingDescriptionNode.'.')), true);
                        }
                        // compensation code
                        $isCompensationCode = false; // gets true if compensation code has personal data
                        if (array_key_exists(self::codeCompensationNode, $pageArray)) {
                            $tempArray = $pageArray[self::codeCompensationNode];
                            $tempPrefix = $translationPage.self::codeCompensationNode.'.';
                            if ($this->checkMissingTextfield($tempArray, null, self::codeCompensationExternal, $tempPrefix.'missing', $tempPrefix.self::descriptionNode)===self::codeCompensationInternal) {
                                $this->checkMissingChosen($tempArray, $tempPrefix.'internal', null, true, self::codeCompensationInternal);
                            }
                            if (array_key_exists(self::codeCompensationPersonal, $tempArray)) {
                                $isCompensationCode = $this->checkMissingChosen($tempArray, $tempPrefix.self::codeCompensationPersonal, null, true, self::codeCompensationPersonal)===self::generation;
                                $isCodeMaybe = $isCodeMaybe || $isCompensationCode;
                            }
                        }
                        // further checks
                        $furtherPrefix = $translationPage.'further.';
                        // responsibility/transferOutside not applicable <-> no personal data
                        foreach ([self::responsibilityNode => $responsibility, self::transferOutsideNode => $transferOutside] as $type => $selection) {
                            $isAnswered = $selection!=='';
                            $isNotApplicable = $selection===self::privacyNotApplicable;
                            if ($isNotApplicable && $allPersonalAnswered && $hasPersonal) {
                                $this->addCheckLabelString($furtherPrefix.$type.'ToPersonal');
                            } elseif ($allPersonalAnswered && !$hasPersonal && $isAnswered && !$isNotApplicable) {
                                $this->addCheckLabelString($furtherPrefix.'personalTo'.$type);
                            }
                        }
                        $ipPrefix = $furtherPrefix.'ip.';
                        $isIP = array_key_exists('ip',$dataResearch);
                        $measuresArray = $this->measure[self::measuresNode];
                        $isDataPersonal = in_array($dataPersonal, ['', 'personal']);
                        if ($isIP) {
                            // location not online -> data research must not be ip
                            if (!in_array($measuresArray[self::locationNode][self::chosen],['',self::locationOnline])) {
                                $this->addCheckLabelString($ipPrefix.'noOnline',$this->routeIDs);
                            }
                            // no ip-addresses for online -> data research must not be ip
                            if ($dataOnline==='ipNo') {
                                $this->addCheckLabelString($ipPrefix.'ipNo');
                            }
                        }
                        // list has ip-addresses -> only if ip-addresses are linked to research data
                        $isDataOnlineProcessingLinked = $dataOnlineProcessing===self::dataOnlineProcessingLinked;
                        if ($isListIP && !$isDataOnlineProcessingLinked) {
                            $this->addCheckLabelString($ipPrefix.'ipList',$this->routeIDs);
                        }
                        $tempPrefix = $ipPrefix.self::dataOnlineResearch.'.';
                        if ($dataOnline===self::dataOnlineResearch) {
                            if (!$isDataPersonal) { // ip-addresses for research -> research data must be personal
                                $this->addCheckLabelString($tempPrefix.self::dataPersonalNode);
                            }
                            elseif ($dataResearch!==[] && !$isIP) { // ip-addresses for research -> ip-addresses must be selected as research data
                                $this->addCheckLabelString($tempPrefix.self::dataResearchNode);
                            }
                        }
                        elseif ($dataOnline===self::dataOnlineTechnical) {
                            if ($isIP) { // ip-addresses only for technical reasons -> ip-addresses must not be research data
                                $this->addCheckLabelString($ipPrefix.self::dataOnlineTechnical);
                            }
                            if ($isDataOnlineProcessingLinked && $isMarkingAnswered && (!$isMarkingList || $hasList && !$isListIP)) { // ip-addresses can be linked to research data -> research data must be marked with list which contains the ip
                                $this->addCheckLabelString($ipPrefix.'linkedMarking');
                            }
                            elseif ($dataOnlineProcessing===self::dataOnlineProcessingResearch && !$isDataPersonal) { // ip-addresses are part of research data -> research data must be personal
                                $this->addCheckLabelString($ipPrefix.self::dataOnlineProcessingResearch);
                            }
                        }
                        // code generation -> closed group
                        if ($isCodeMaybe && $this->measure[self::groupsNode][self::closedNode][self::chosen]==='1') {
                            $this->addCheckLabelString($furtherPrefix.self::closedNode, $this->routeIDs);
                        }
                        // video -> research data is personal
                        $measuresTypes = $measuresArray[self::measuresNode][self::measuresTypesNode];
                        $isMeasures = $measuresTypes!=='';
                        $isVideoMeasures = $isMeasures && array_key_exists(self::measuresVideo, $measuresTypes);
                        if ($isVideoMeasures && !$isDataPersonal) {
                            $this->addCheckLabelString($furtherPrefix.'video', $this->routeIDs);
                        }
                        // video in measures <-> audio, photo, or video for data research.
                        if ($isMeasures && $isDataPersonal) {
                            if ($isVideoMeasures && !$isDataResearchVideo) {
                                $this->addCheckLabelString($furtherPrefix.'measuresToVideo', $this->routeIDs);
                            } elseif ($isDataResearchVideo && !$isVideoMeasures) {
                                $this->addCheckLabelString($furtherPrefix.'videoToMeasures', $this->routeIDs);
                            }
                        }
                        // no compensation -> purpose must not be compensation
                        $tempPrefix = $furtherPrefix.self::compensationNode.'.';
                        $purposeArraysMerged = array_merge($isPurposeResearch ? $purposeResearchArray : [], $isPurposeFurther ? $purposeFurtherArray : []);
                        $purposeArraysMergedKeys = array_keys($purposeArraysMerged);
                        $compensation = $this->measure[self::compensationNode];
                        $compensationTypes = $compensation[self::compensationTypeNode];
                        $isCompensation = $compensationTypes!==''; // may also be 'no compensation'
                        if ($isCompensation && array_key_exists(self::compensationNo, $compensationTypes) && array_intersect([self::purposeCompensation, self::purposeFurtherNode.self::purposeCompensation], $purposeArraysMergedKeys)!==[]) {
                            $this->addCheckLabelString($tempPrefix.'noCompensation', $this->routeIDs);
                        }
                        // compensation by name -> purpose data eMail for purpose compensation
                        $isNotPurposeCompensation = ($isPurposeResearch || !$hasPurposeResearch) && $isPurposeFurther && array_intersect([self::purposeCompensation, self::purposeFurtherNode.self::purposeCompensation], $purposeArraysMergedKeys)===[]; // true if purpose research either exists and was answered or does not exist, and research further was answered, and if compensation is not selected as a purpose
                        $isCompensationData = $purposeDataCompensation!==[]; // true if any data for purpose compensation is collected
                        // compensation by a certain type -> certain purpose data for purpose compensation
                        $isPurposeResearchCompensation = $isPurposeResearch && array_key_exists(self::compensationNode,$purposeResearchArray);
                        foreach (['name', 'eMail', 'phone'] as $type) {
                            $tempVal = $this->checkCompensationAwarding($compensation, $type);
                            if ($tempVal && ($isNotPurposeCompensation || $isCompensationData && !in_array(self::compensationNode.$type, $purposeDataCompensation))) {
                                $this->addCheckLabelString($tempPrefix.$type, $this->routeIDs);
                            }
                            // compensation by name and marking with purpose compensation -> either marking by name or list with name
                            if ($type==='name' && $tempVal && $isPurposeResearchCompensation && $isMarkingAnswered && !$isMarkingName && (!$isMarkingList || $hasList && !$isListName)) {
                                $this->addCheckLabelString($tempPrefix.'nameMarking',$this->routeIDs,true);
                            }
                        }
                        // compensation by transfer -> name and IBAN for purpose compensation
                        if ($this->checkCompensationAwarding($compensation,'transfer') && ($isNotPurposeCompensation || $isCompensationData && count(array_intersect([self::compensationNode.'name',self::compensationNode.'iban'],$purposeDataCompensation))!==2)) {
                            $this->addCheckLabelString($tempPrefix.'transfer',$this->routeIDs);;
                        }
                        // compensation by mail -> name and postal address for purpose compensation
                        if ($this->checkCompensationAwarding($compensation, 'mail') && ($isNotPurposeCompensation || $isCompensationData && count(array_intersect([self::compensationNode.'name', self::compensationNode.'address'], $purposeDataCompensation))!==2)) {
                            $this->addCheckLabelString($tempPrefix.'mail', $this->routeIDs);
                        }
                        // code has personal data -> purpose must be compensation
                        if ($isCompensationCode && $isNotPurposeCompensation) {
                            $this->addCheckLabelString($tempPrefix.'code', $this->routeIDs);
                        }
                        // compensation by code and marking with purpose compensation -> marking must be code
                        if ($this->checkCompensationAwarding($compensation) && $isPurposeResearchCompensation && $isMarkingAnswered && !in_array(true,[$isCodeNoDoc,$isMarkingList,$isCodeMaybe])) {
                            $this->addCheckLabelString($tempPrefix.'codeMarking',$this->routeIDs,true);
                        }
                    }
                }
            }
        }
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the data reus page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkDataReuse(bool $setTitle = true): void {
        $pageArray = $this->measure[self::dataReuseNode];
        $translationPage = self::projectdetailsPrefix.self::dataReuseNode.'.';
        $this->addProjectdetailsTitle(self::dataReuseNode,$setTitle);
        if ($this->checkMissingChosen($pageArray,$translationPage.self::confirmIntroNode,null,name: self::confirmIntroNode)==='1') {
            $dataReuseHowPrefix = $translationPage.self::dataReuseHowNode.'.';
            $lineTitlePrefix = $dataReuseHowPrefix.'lineTitle.';
            $privacyArray = $this->measure[self::privacyNode];
            $personal = $this->getPrivacyReuse($privacyArray);
            $isReuseHowTwice = $personal['isPurposeReuse'] && $personal['isAnonymized'];
            $lineTitleReuse = $isReuseHowTwice ? $this->translateString($lineTitlePrefix.self::dataReuseHowNode.self::personalKeepReuse) : '';
            // data reuse how
            foreach (['',self::personalKeepReuse] as $suffix) {
                $dataReuseHow = self::dataReuseHowNode.$suffix;
                if (array_key_exists($dataReuseHow,$pageArray)) {
                    $tempArray = $pageArray[$dataReuseHow];
                    $lineTitle = $isReuseHowTwice ? $this->translateString($lineTitlePrefix.$dataReuseHow) : '';
                    $this->checkMissingChosen($tempArray,$dataReuseHowPrefix.'missing',null,lineTitle: $lineTitle);
                    if (array_key_exists(self::descriptionNode,$tempArray)) {
                        $this->checkMissingContent($tempArray,[self::descriptionNode => $dataReuseHowPrefix.self::descriptionNode],lineTitle: $lineTitle);
                    }
                }
            }
            if (($pageArray[self::dataReuseHowNode][self::chosen] ?? '')==='class0' && $personal['personal']==='purpose' && ($privacyArray[self::transferOutsideNode] ?? '')==='no') { // personal data are made publicly available -> transfer outside can not be answered with 'no'
                $this->addCheckLabelString($dataReuseHowPrefix.'public',$this->routeIDs);
            }
            // data reuse
            if (array_key_exists(self::dataReuseNode,$pageArray)) {
                $this->checkMissingContent($pageArray,[self::dataReuseNode => $translationPage.self::dataReuseNode],lineTitle: $lineTitleReuse);
            }
            if (array_key_exists(self::dataReuseSelfNode,$pageArray)) { // data reuse self
                $this->checkMissingContent($pageArray,[self::dataReuseSelfNode => $translationPage.self::dataReuseSelfNode],lineTitle: $lineTitleReuse);
            }
        }
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the texts page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkTexts(bool $setTitle = true): void {
        $pageArray = $this->measure[self::textsNode];
        if ($pageArray!=='') {
            $this->addProjectdetailsTitle(self::textsNode,$setTitle);
            $translationPage = self::projectdetailsPrefix.self::textsNode.'.';
            // intro
            $tempArray = $pageArray[self::introNode];
            if (array_key_exists(self::descriptionNode,$tempArray) && $tempArray[self::descriptionNode]==='') {
                $this->addCheckLabelString($translationPage.'intro',colorRed: false);
            }
            // goals and procedure
            $this->checkMissingContent($pageArray,[self::goalsNode => $translationPage.self::goalsNode,self::procedureNode => $translationPage.self::procedureNode]);
            // pro
            $tempArray = $pageArray[self::proNode];
            $this->checkMissingContent($tempArray,[$this->getBoolFromString($tempArray[self::proTemplate]) ? self::proTemplateText : self::descriptionNode => $translationPage.'pro'],true);
            // con and finding consent
            foreach ([self::conNode,self::findingTextNode] as $type) {
                if (array_key_exists($type,$pageArray)) { // findingConsent may not exist
                    $tempArray = $pageArray[$type];
                    if (array_key_exists(self::descriptionNode,$tempArray) && $tempArray[self::descriptionNode]==='') {
                        $this->addCheckLabelString($translationPage.$type,colorRed: false);
                    }
                }
            }
            $this->setProjectdetailsTitle($setTitle);
        }
    }

    /** Checks for errors on the legal page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkLegal(bool $setTitle = true): void {
        $pageArray = $this->measure[self::legalNode];
        if ($pageArray!=='') {
            $this->addProjectdetailsTitle(self::legalNode,$setTitle);
            $translationPage = self::projectdetailsPrefix.self::legalNode.'.';
            foreach ($pageArray as $type => $input) {
                $this->checkMissingTextfield($input,null,self::templateText,$translationPage.'missing',$translationPage.'description',parameters: ['type' => $type]);
            }
            $this->setProjectdetailsTitle($setTitle);
        }
    }

    /** Checks for errors on the contributor page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkContributor(bool $setTitle = true): void {
        if (in_array(false,$this->isOne)) {
            $this->addProjectdetailsTitle(self::contributorNode,$setTitle);
            $translationPage = self::projectdetailsPrefix.self::contributorNode.'.';
            $pageArray = $this->measure[self::contributorNode];
            foreach (self::tasksNodes as $task) {
                $tempArray = explode(',',$pageArray[$task]); // array containing the indices of the contributor of the current task. key: continuous index (nodes had the name). value: index of contributor
                $isTask = $tempArray[0]!=='';
                if (in_array($task,self::tasksMandatory) && !$isTask) { // task is mandatory
                    $this->addCheckLabelString($translationPage.'mandatory',['{task}' => $this->translateString(self::tasksTypes[$task]), '{type}' => !$this->isMandatory[$task] ? 'missing' : 'other']);
                }
                elseif ($isTask) {
                    $tasksCopy = &$this->contributorTasks[$task]; // contributor of the current task. key: contributor index, value: empty or other description
                    foreach ($tempArray as $index) {
                        if (array_key_exists($index,$tasksCopy)) { // remove the current task from the current contributor if it is the first measure time point where the task is selected
                            unset($tasksCopy[$index]);
                        }
                    }
                } // elseif
            } // foreach
            $this->setProjectdetailsTitle($setTitle);
        } // !($isOneStudy && $isOneGroup && $isOneMeasure)
    }

    /** Sets the variables needed for the projectdetails pages of checkDocument().
     * @return void
     */
    private function setProjectdetailsVariables(): void {
        $this->routeIDs = ['routeIDs' => $this->createRouteIDs($this->IDs)];
        $tempArray = $this->appArray[self::projectdetailsNodeName];
        foreach ([self::studyNode,self::groupNode,self::measureTimePointNode] as $type) {
            $tempArray = $this->addZeroIndex($tempArray[$type]);
            $this->isOne[$type] = count($tempArray)===1;
            $tempArray = $tempArray[$this->IDs[$type]-1];
            if ($type!==self::measureTimePointNode) {
                $this->studyGroupName[$type] = $tempArray[self::nameNode];
            }
        }
        $this->addressee = $this->getAddressee($this->measure[self::groupsNode]);
        $this->isTwoAddressees = $this->addressee!==self::addresseeParticipants;
        // set parameters for translations -> contains all parameters that are needed somewhere, i.e., not all parameters are used in every translation
        $this->paramsAddressee = array_merge($this->routeIDs,[self::addressee => $this->addressee, 'participant' => 'thirdParty', 'page' => self::informationNode, 'type' => self::voluntaryNode]);
        $this->paramsParticipants = array_merge($this->routeIDs,[self::addressee => $this->addressee, 'participant' => 'participant', 'page' => self::informationIINode, 'type' => self::voluntaryNode]);
        // information
        $tempArray = $this->measure[self::informationNode];
        $chosen = $tempArray[self::chosen];
        $this->isPre = $chosen==='0';
        $this->noPre = $chosen==='1';
        $post = $this->noPre ? $tempArray[self::informationAddNode][self::chosen] : '';
        $this->noPost = $this->noPre && $post==='1';
        $this->information = [(int) ($chosen!=='' ? $chosen : 2), (int) ($post!=='' ? $post : 2)]; // no "?:" because values can be "0" which would translate to false
        // informationII
        $tempArray  = $this->measure[self::informationIINode] ?: [];
        if ($tempArray!==[]) {
            $this->isInformationII = true;
            $chosen = $tempArray[self::chosen];
            $this->noPreParticipants = $chosen==='1';
            $post = $this->noPreParticipants ? $tempArray[self::informationAddNode][self::chosen] : '';
            $this->noPostParticipants = $this->noPreParticipants && $post==='1';
            $this->informationII = [(int) ($chosen!=='' ? $chosen : 2), (int) ($post!=='' ? $post : 2)]; // no "?:" because values can be "0" which would translate to false
        }
        // measures
        $tempArray = $this->measure[self::measuresNode];
        $this->isFeedback = array_key_exists(self::feedbackNode,$tempArray[self::interventionsNode][self::interventionsTypesNode] ?: []);
        $this->isNoPresence = $tempArray[self::presenceNode]==='1';
        // burdensRisks
        $this->isFinding = $this->measure[self::burdensRisksNode][self::findingNode][self::chosen]==='0';
    }

    /** Creates the string saying that no errors were found.
     * @return string translated string
     */
    private function getNoError(): string {
        return $this->translateString('checkDoc.noError',$this->committeeParam);
    }

    // functions for individual pages

    /** Checks for errors on the burdens/risks page.
     * @param array $pageArray array containing the elements of the questions for the type
     * @param string $page translation key for the part of the error message that is added if the description node is empty
     * @param string $typeKey key for the node whose children are checked
     * @param string $type Type to be checked. Must equal 'burdens' or 'risks'
     * @return bool true if at least one option except 'no' is selected, false otherwise
     */
    private function checkBurdensRisksErrors(array $pageArray, string $page, string $typeKey, string $type): bool {
        $noID = $type===self::burdensNode ? self::noBurdens : self::noRisks;
        $typeParam = ['burdensRisksType' => $type];
        $params = array_merge($typeParam,['{type}' => $this->translateString(self::projectdetailsPrefix.self::burdensRisksNode.'.title',$typeParam)]);
        $isSelected = $this->checkMultiSelectionTextfield($pageArray,$typeKey,$page,$noID,$params);
        if ($isSelected && !array_key_exists($noID,$pageArray[$typeKey])) {
            $this->checkBurdensRisksCompensation($pageArray,$params);
        }
        return $isSelected;
    }

    /** Checks for errors on the voluntary or consent type question on the consent page.
     * @param array $pageArray array whose children are checked for content
     * @param string $missingChosen translation key if the 'chosen' key is empty
     * @param string $missingChosenParticipants translation key if the question exists twice and the 'chosen2' key is empty
     * @param string $missingDescription translation key if any of the two questions is answered such that a description is needed or if consent and other is selected
     * @return array first element: answer of first addressee. Second element: answer of second addressee, if any
     */
    private function checkVoluntaryConsent(array $pageArray, string $missingChosen, string $missingChosenParticipants, string $missingDescription): array {
        $returnArray = [$this->checkMissingChosen($pageArray,$missingChosen,null,true), ''];
        $returnArray[1] = $this->isTwoAddressees ? $this->checkMissingChosen($pageArray,$missingChosenParticipants,null,true,self::chosen.'2') : '';
        $otherDescriptionTrans = $missingDescription.'Other';
        if (array_key_exists(self::consentOtherDescription,$pageArray)) {
            $this->checkMissingContent($pageArray,[self::consentOtherDescription => $otherDescriptionTrans],parameter: $this->paramsAddressee);
        }
        $otherDescription = self::consentOtherDescription.'Participants';
        if (array_key_exists($otherDescription,$pageArray)) {
            $this->checkMissingContent($pageArray,[$otherDescription => $otherDescriptionTrans],parameter: $this->paramsParticipants);
        }
        if (array_key_exists(self::descriptionNode,$pageArray)) {
            $this->checkMissingContent($pageArray,[self::descriptionNode => $missingDescription]);
        }
        return $returnArray;
    }

    // methods for individual pages. Added here because at least one method calls a function defined afterwards.

    /** Checks the infos about the applicant or supervisor.
     * @param array $pageArray array containing the infos
     * @param string $type must equal 'applicant' or 'supervisor
     * @return void
     */
    private function checkApplicantSupervisor(array $pageArray, string $type): void {
        $applicant = $pageArray[$type];
        $translationPrefix = self::appDataPrefix.self::coreDataNode.'.';
        $parameters = ['type' => $type];
        $tempArray = $this->translateArray('multiple.infos.',array_diff(self::applicantContributorsInfosTypes,$type===self::applicant && $applicant[self::position]===self::positionsStudent ? [self::phoneNode] : []),true);
        $tempArray[self::institutionInfo] = str_replace(self::institutionInfo,self::institutionInfo.'Applicant',$tempArray[self::institutionInfo]);
        $this->checkMissingContent($applicant,$tempArray,lineTitle: 'coreData.applicant.'.$type);
        $name = $applicant[self::nameNode];
        if ($name!=='' && count(explode(' ',$name))===1) {
            $this->addCheckLabelString($translationPrefix.self::nameNode,$parameters,false);
        }
        if ($applicant[self::position]===self::positionOther) { // 'other' is selected, but no description was entered
            $this->addCheckLabelString($translationPrefix.'positionOther',$parameters,false);
        }
        // validity of eMail and phone
        $tempVal = $applicant[self::eMailNode];
        if ($tempVal!=='' && !filter_var($tempVal,FILTER_VALIDATE_EMAIL)) {
            $this->addCheckLabelString($translationPrefix.self::eMailNode,$parameters,false);
        }
        $tempVal = $applicant[self::phoneNode];
        if ($tempVal!=='' && preg_match("/^\+?([0-9][\s\/-]?)+[0-9]+$/",$tempVal)===0) {
            $this->addCheckLabelString($translationPrefix.self::phoneNode,$parameters,false);
        }
    }

    /** Checks for error of the compensation question on the burdens/risks page.
     * @param array $pageArray array containing the elements of the questions for the type
     * @param array $params parameters for the translation
     * @return void
     */
    private function checkBurdensRisksCompensation(array $pageArray, array $params): void {
        $compensation = $pageArray[self::burdensRisksCompensationNode];
        $compensationString = self::projectdetailsPrefix.self::burdensRisksNode.'.compensation';
        $tempVal = $this->checkMissingChosen($compensation,$compensationString,2,true,parameters: array_merge($params,['isNoCompensation' => 'false']));
        if ($tempVal<2) { // check only if selection was made
            $isCompensation = $tempVal===0;
            $this->checkMissingContent($compensation,[self::descriptionNode => $compensationString],$isCompensation,parameter: array_merge($params,['isNoCompensation' => $this->getStringFromBool(!$isCompensation)]));
        }
    }

    /** Checks the access and order processing questions.
     * @param array $pageArray array with one child containing the access questions
     * @param string $purposeNameWoPrefix purpose for which the access questions are checked
     * @param array $purposeParam translation parameters
     * @return void
     */
    private function checkAccess(array $pageArray, string $purposeNameWoPrefix, array $purposeParam): void {
        $privacyPrefix = 'checkDoc.projectdetails.pages.dataPrivacy.';
        $accessPrefix = $privacyPrefix.self::accessNode.'.';
        if ($this->checkMissingChildrenOther($pageArray, self::accessNode, $accessPrefix . 'missing', array_combine($this->prefixArray(self::accessOthers, $purposeNameWoPrefix), $this->prefixArray(self::accessOthers, $accessPrefix)), $purposeParam)) {
            $accessYes = ['accessExternal','dataService'];
            foreach ($pageArray[self::accessNode] as $accessKey => $accessQuestions) {
                if (is_array($accessQuestions)) { // sub-questions exist for this access type -> if string, it may not be empty (description)
                    $accessWoPrefix = str_replace($purposeNameWoPrefix,'',$accessKey);
                    $typeParam = array_merge($purposeParam,['type' => $accessWoPrefix]);
                    // order processing
                    $tempArray = $accessQuestions[self::orderProcessingNode];
                    $chosen = $tempArray[self::chosen];
                    $tempPrefix = $privacyPrefix.self::orderProcessingNode.'.';
                    $this->checkMissingChosen($tempArray,$tempPrefix.'missing',2,true,parameters: $typeParam);
                    if (array_key_exists(self::descriptionNode,$tempArray)) {
                        $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.self::descriptionNode],true,parameter: $typeParam);
                    }
                    // order processing known
                    if ($chosen==='0') {
                        $this->checkMissingContent($accessQuestions,[self::orderProcessingKnownNode => $privacyPrefix.self::orderProcessingKnownNode],parameter: $typeParam);
                    }
                    elseif (in_array($accessWoPrefix,$accessYes) && $chosen==='1') { // if external, order processing must be answered with 'yes'
                        $this->addCheckLabelString($tempPrefix.'externalService',$typeParam);
                    }
                }
            }
        }
    }

    // functions for checking if a valid input was made

    /** Checks if the 'chosen' key of \$element has content. If not or if the content is not between zero (inclusively) and \$maxVal (exclusively), \$checkLabel is updated. If the value equals \$selected, the element with the key \$description is checked for content. If \$maxVal is null, the 'chosen' key is expected to have a string value.
     * @param array $element array containing the keys
     * @param int|null $maxVal value the 'chosen' element is checked against or null if the element contains a string
     * @param int|string $selected value where the other element is checked
     * @param string $chosenDescription translation key for a part of the error message if the 'chosen' element is empty
     * @param string $description translation key for a part of the error message if the \$descriptionKey element is empty
     * @param bool $addDescription true if the description prefix should be prepended if the \$descriptionKey element is empty, false otherwise
     * @param string $descriptionKey key of the element to be checked if the value equals \$selected. Defaults to self::descriptionNode
     * @param array $parameters parameters for the translation keys
     * @return int|string the value of the 'chosen' element if it is a number or a string, otherwise \$maxVal if not null
     */
    private function checkMissingTextfield(array $element, ?int $maxVal, int|string $selected, string $chosenDescription, string $description = '', bool $addDescription = false, string $descriptionKey = self::descriptionNode, array $parameters = []): int|string {
        $curSel = $this->checkMissingChosen($element,$chosenDescription,$maxVal,true,parameters: $parameters);
        if ($curSel===$selected) {
            $this->checkMissingContent($element,[$descriptionKey => $description],$addDescription,parameter: $parameters);
        }
        return $curSel;
    }

    /** Checks if the 'chosen' key of \$element has content. If not, \$checkLabel is updated. If it has content, the element with the key \$description is checked for content.
     * @param array $element array containing the keys
     * @param string $chosenDescription translation key for a part of the error message if the 'chosen' element is empty
     * @param string $description translation key for a part of the error message if the $description element is empty
     * @param bool $addDescription true if the description prefix should be prepended, false otherwise
     * @param string $chosenKey if provided, key of \$element to be checked. Defaults to self::chosen
     * @param string $descriptionKey key of the element to be checked if \$element is not empty. Defaults to self::descriptionNode
     * @param array $parameters parameters for the translation
     * @return string the value of the 'chosen' element
     */
    private function checkMissingTextfieldEmpty(array $element, string $chosenDescription, string $description, bool $addDescription = true, string $chosenKey = self::chosen, string $descriptionKey = self::descriptionNode, array $parameters = []): string {
        $chosen = $this->checkMissingChosen($element,$chosenDescription,null,name: $chosenKey,parameters: $parameters);
        if ($chosen!=='') {
            $this->checkMissingContent($element,[$descriptionKey => $description],$addDescription,parameter: $parameters);
        }
        return $chosen;
    }

    /** Checks if an array element has children and if so, if another element has content.
     * @param array $pageArray array containing the elements to be checked
     * @param string $multi key for the element that is checked for children
     * @param string $messageContent translation key for the part of the error message that is added if the description node is empty
     * @param string $exclude if provided and the only key in \$multi, then the check for \$content is skipped
     * @param array $parameters parameters for the translation
     * @return bool true if $multi has at least one child, false otherwise
     */
    private function checkMultiSelectionTextfield(array $pageArray, string $multi, string $messageContent, string $exclude = '', array $parameters = []): bool {
        $hasChildren = $this->checkMissingChildren($pageArray,$multi,self::missingTypes,$parameters);
        if ($hasChildren) {
            $multiArray = $pageArray[$multi];
            if ($exclude==='' || !(count($multiArray)==1 && array_key_exists($exclude,$multiArray))) {
                $this->checkMissingContent($pageArray,[self::descriptionNode => $messageContent],true,parameter: $parameters);
            }
        }
        return $hasChildren;
    }

    /** Calls checkMissingChildren. If it returns true and $other is not empty, it is checked if the key(s) exist and if so, if they have content.
     * @param array $element array which contains the elements to be checked
     * @param string $key key in $element to be checked
     * @param string $message translation key for a part in the error message
     * @param array $other if provided: keys: key in the children of the checked element to be checked for existence. value: translation key for the part of the error message
     * @param array $params parameters for the translations
     * @param bool $addDescription if true, the error message for a missing description if prefixed by 'description of'
     * @return bool true if the element has children, false otherwise
     */
    private function checkMissingChildrenOther(array $element, string $key, string $message, array $other = [], array $params = [], bool $addDescription = true): bool {
        $returnBool = $this->checkMissingChildren($element,$key,$message,$params);
        if ($returnBool && $other!==[]) {
            $children = $element[$key];
            foreach ($other as $key => $translationKey) {
                if (array_key_exists($key,$children)) { // choice that needs input is selected
                    $this->checkMissingContent($children,[$key => $translationKey],$addDescription,parameter: $params);
                }
            }
        }
        return $returnBool;
    }

    /** Checks if the element with the key \$key has children and if not, adds an error message to \$checkLabel.
     * @param array $element array which contains the element to be checked
     * @param string $key key in $element to be checked
     * @param string $message translation key for a part in the error message
     * @param array $parameters parameters to be added to the translation
     * @return bool true if the element has children, false otherwise
     */
    private function checkMissingChildren(array $element, string $key, string $message, array $parameters = []): bool {
        if ($element[$key]==='') {
            $this->addCheckLabelString($this->translateString($message,$parameters).$this->translateString(self::missingPrefix.'multiple'),colorRed: false);
            return false;
        }
        return true;
    }

    /** Checks if a specific key of \$element is either empty, not a number, or not between zero (inclusively) and \$maxVal (exclusively). If so, an error message is added to \$checkLabel. If \$maxVal is null, then it is only checked if the key is empty.
     * @param array $element array containing the key to be checked
     * @param string $question translation key for a part in the error message
     * @param int|null $maxVal maximum value that the array element is checked against
     * @param bool $addPrefix true if the "answer to" prefix should be added, false otherwise
     * @param string $name if provided, name of the key to be checked. Defaults to self::chosen
     * @param array $parameters if provided, parameters for the translation
     * @param string $lineTitle string that is added at the beginning of the error message. Must be translated. May only be used if \$addPrefix is false
     * @return int|string the value of the array element if it is a number in the range, \$maxVal otherwise. If \$maxVal is null, the value as a string
     */
    private function checkMissingChosen(array $element, string $question, ?int $maxVal, bool $addPrefix = false, string $name = self::chosen, array $parameters = [], string $lineTitle = ''): int|string {
        $curVal = $element[$name] ?? '';
        $curValAsInt = (int)($curVal); // if $curVal is a string, 0 is returned
        $returnVal = $curValAsInt;
        $isString = $maxVal===null;
        if ($curVal==='' || !$isString && (preg_match("/\D/",$curVal) || $curValAsInt<0 || $curValAsInt>=$maxVal)) {
            $message = $this->translateString($question,$parameters);
            $this->addCheckLabelString($addPrefix ? $this->translateString('checkDoc.missingChosen',['{question}' => $message]) : $lineTitle.($lineTitle!=='' ? ': ' : '').$message.$this->translateString(self::missingSingle),colorRed: false);
            $returnVal = $maxVal;
        }
        return $isString ? $curVal : $returnVal;
    }

    // methods for checking if a valid input was made

    /** Checks every key of \$element whose name is in \$inArray if the value is empty or equals \$default. If so, an error message is added to \$checkLabel.
     * @param array $element array whose elements are checked for content. The keys equal the node names
     * @param array $inArray key: corresponding key in \$element. value: translation key for a part of the error message that is added
     * @param bool $addDescription if provided and true, the translation is prefixed by "description of"
     * @param string $default default value where the error message is added
     * @param string $lineTitle translation key that is added at the beginning of the error message if at least one the checked elements is empty
     * @param array $parameter parameter for the translation of either \$lineTitle (if given) or the description error
     * @return void
     */
    private function checkMissingContent(array $element, array $inArray, bool $addDescription = false, string $default = '', string $lineTitle = '', array $parameter = []): void {
        $missingString = '';
        $countMissing = 0;
        foreach ($inArray as $key => $value) {
            $curElement = $element[$key];
            if ($curElement==='' || $curElement===$default) {
                $missingString .= ', '.($addDescription ? $this->translateString(self::missingPrefix.'description') : '').$this->translateString($value,$parameter);
                ++$countMissing;
            }
        }
        if ($countMissing>0) { // at least one element is empty
            $this->addCheckLabelString(($lineTitle!=='' ? ($this->translateString($lineTitle, $parameter).': ') : '').
                substr($missingString,2).
                $this->translateString(self::missingPrefix.($countMissing===1 ? 'single' : 'multiple')),colorRed: false);
        }
    }

    // methods for setting the (sub)headings

    /** Sets \$curWindow, adds it as a subtitle to \$checkLabel and sets \$anyWindowMissing to false.
     * @param string $title title to add. Must be a valid key in the translation file
     * @return void
     */
    private function addTitle(string $title): void {
        $translated = $this->translateString($title);
        $this->curWindow = $translated;
        $this->checkLabel .= $translated.":\n\n";
        $this->anyWindowMissing = false;
    }

    /** Sets the heading for the appData subpages and $anyMissing to false.
     * @param string $subPage subPage page name that is added to the heading
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function addAppDataTitle(string $subPage, bool $setTitle = true): void {
        if ($setTitle) {
            $this->curPage = $this->curWindow.' - '.$this->translateString(self::appDataPrefix.'title', ['page' => $subPage, 'title' => $this->translateString('pages.appData.'.$subPage)])."\n";
            $this->checkLabel .= $this->curPage;
            $this->anyMissing = false;
        }
    }

    /** Sets the page heading of a projectdetails subpage and $anyMissing to false.
     * @param string $pageName page name that is added to the heading if the heading for a single page should be added, otherwise an empty string
     * @param bool $setTitle if true, the page title will be added above the errors
     * @param string|null $subPage which heading to add. Must equal 'study', 'group' or 'measureTimePoint'. Null if $pageName is empty.
     * @return void
     */
    private function addProjectdetailsTitle(string $pageName = '', bool $setTitle = true, ?string $subPage = null): void {
        if ($setTitle) {
            if ($pageName!=='') {
                $this->curPage = $this->curSubPageHeading.$this->convertStringToLink($this->translateString('pages.projectdetails.'.$pageName), $pageName, $this->routeIDs[self::routeIDs]).':';
                $this->checkLabel .= $this->curPage."\n";
                $this->anyMissing = false;
            } elseif ($subPage===self::measureTimePointNode) {
                $isOneStudy = $this->isOne[self::studyNode];
                $studyName = $this->studyGroupName[self::studyNode];
                $groupName = $this->studyGroupName[self::groupNode];
                $isOneGroup = $this->isOne[self::groupNode];
                $headingPrefix = 'projectdetails.headings.';
                $this->curSubPageHeading = $this->translateString($headingPrefix.self::studyNode).($isOneStudy ? '' : ' '.$this->IDs[self::studyNode]).($studyName!=='' ? ' '.($isOneStudy ? '' : ' (')."\u{201E}".$studyName."\u{201D}".($isOneStudy ? '' : ')') : '').
                    ', '.$this->translateString($headingPrefix.self::groupNode).($isOneGroup ? '' : ' '.$this->IDs[self::groupNode]).($groupName!=='' ? ($isOneGroup ? '' : ' (')."\u{201E}".$groupName."\u{201D}".($isOneGroup ? '' : ')') : '').
                    ', '.$this->translateString($headingPrefix.self::measureTimePointNode).($this->isOne[self::measureTimePointNode] ? '' : ' '.$this->IDs[self::measureTimePointNode]).' - ';
            }
        }
    }

    /** Checks if there is an error on either the current page or any page of the current type and sets the corresponding variables.
     * @param bool $setTitle if true, the page title will be checked
     * @param string|null $subPage which heading to remove if there is no error. Must equal 'study', 'group', or 'measureTimePoint'. Null if errors on a single page are checked
     * @return void
     */
    private function setProjectdetailsTitle(bool $setTitle = true, ?string $subPage = null): void {
        if ($setTitle) {
            if ($subPage===null) {
                if ($this->anyMissing) {
                    $this->anyWindowMissing = true;
                    $this->checkLabel .= "\n";
                } else {
                    $this->checkLabel = str_replace($this->curPage."\n", '', $this->checkLabel);
                }
            }
        }
    }

    /** Checks if there is an error on the current page and if so, adds a line break to \$checkLabel and sets \$anyWindowMissing to true. Otherwise, the heading from $checkLabel is removed.
     * @param bool $setTitle if true, the page title will be checked
     * @return void
     */
    private function setAppDataTitle(bool $setTitle = true): void {
        if ($setTitle) {
            if ($this->anyMissing) {
                $this->checkLabel .= "\n";
                $this->anyWindowMissing = true;
            } else {
                $this->checkLabel = str_replace($this->curPage, '', $this->checkLabel);
            }
        }
    }

    /** Checks if there is an error on any page of the current group by checking \$anyWindowMissing and if not, the heading from \$checkLabel is removed.
     * @return void
     */
    private function setTitle(): void {
        if (!$this->anyWindowMissing) {
            $this->checkLabel = trim(str_replace($this->curWindow.":\n",'',$this->checkLabel))."\n\n";
        }
    }

    // further methods

    /** Translates a string, adds it to \$checkLabel, and sets \$anyMissing to true.
     * @param string $label translation key for the String to be added
     * @param array $parameters parameters for the translation
     * @param bool $colorRed if true, a span with color style red will be added around the string. May only be used if the resulting string contains the entire message for this line.
     * @return void
     */
    private function addCheckLabelString(string $label, array $parameters = [], bool $colorRed = true): void {
        $label = ($colorRed ? '<span style="color: red">' : '').$this->translateString($label,$parameters).($colorRed ? '</span>' : '');
        $this->checkLabel .= ucfirst($label)."\n";
        $this->anyMissing = true;
    }
}