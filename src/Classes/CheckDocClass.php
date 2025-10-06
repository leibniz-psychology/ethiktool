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
    private bool $anyError = false; // indicates if there is any error excluding missing inputs
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
    private bool $addPageHash = false; // if true, the page will be prepended to the hash linking to the question
    private string $linkedPage = ''; // page to be linked to. Is used only if $addPageHash is true
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
    private string $consentAddressee = ''; // consent of participants or (if third parties are involved) of third parties
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
     * @param bool $onlyError if true, a boolean is returned indicating whether any inconsistencies (i.e., excluding missing inputs) were found. May only be true if $page is not an empty string
     * @param array $routeIDs if $page equals 'Projectdetails' or a projectdetails subpage, routeIDs. If empty, the routeIDs from the session landing variable (if landing) or from the request (if subpage) will be used
     * @return string|bool if \$page is an empty string and \$returnCheck is true: true is no errors were found, false otherwise; otherwise: string with errors or message that no errors were found
     * @throws Exception if an error occurs during the check
     */
    public static function getDocumentCheck(Request $request, string $page = '', bool $returnCheck = false, SimpleXMLElement|bool $element = null, bool $onlyError = true, array $routeIDs = []): string|bool {
        $checkDoc = new CheckDocClass(self::$translator);
        $session = $request->getSession();
        // set variables
        $checkDoc->appNode = $checkDoc->getXMLfromSession($session,getRecent: true);
        $checkDoc->appArray = $checkDoc->xmlToArray($checkDoc->appNode);
        $checkDoc->committeeParam = $session->get(self::committeeParams);
        $checkDoc->appDataArray = $checkDoc->appArray[self::appDataNodeName];
        $checkDoc->coreDataArray = $checkDoc->appDataArray[self::coreDataNode];
        $tempArray = $checkDoc->coreDataArray[self::applicationType];
        $checkDoc->appType = $tempArray[self::chosen];
        $checkDoc->isAppTypeShort = $checkDoc->committeeParam[self::committeeType]===self::committeeTUC && $checkDoc->appType===self::appNew && $tempArray[self::descriptionNode]===self::appTypeShort;
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
        $hasRouteIDs = $routeIDs!==[];
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
                        $routeParams = $hasRouteIDs ? $routeIDs : $request->get('_route_params');
                        if (array_key_exists(self::measureID,$routeParams)) { // check of a projectdetails page
                            $studyID = $routeParams[self::studyID];
                            $groupID = $routeParams[self::groupID];
                            $measureID = $routeParams[self::measureID];
                            $checkDoc->IDs = [self::studyNode => $studyID, self::groupNode => $groupID,self::measureTimePointNode => $measureID];
                            $checkDoc->measure = $checkDoc->xmlToArray($checkDoc->getMeasureTimePointNode($checkDoc->appNode,[self::studyID => $studyID, self::groupID => $groupID, self::measureID => $measureID]));
                            $checkDoc->setProjectdetailsVariables();
                        }
                        $type = '';
                        $checkDoc->anyError = false;
                        switch ($page) {
                            case self::appDataNodeName: // landing page for application data
                                $checkDoc->addPageHash = true;
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
                            case 'contributors':
                                $checkDoc->checkContributors();
                                break;
                            case self::projectdetailsNodeName: // landing page for projectdetails
                                $checkDoc->addPageHash = true;
                                $landingArray = $hasRouteIDs || $onlyError ? $routeIDs : ($session->get(self::landing) ?? []);
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
                                            $checkDoc->checkConsent();
                                            $checkDoc->checkMeasures();
                                            $checkDoc->checkBurdensRisks();
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
                            case self::consentNode:
                                $checkDoc->checkConsent(false);
                                break;
                            case self::measuresNode:
                                $checkDoc->checkMeasures(false);
                                break;
                            case self::burdensRisksNode:
                                $checkDoc->checkBurdensRisks(false);
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
                        return !$onlyError ? trim($checkDoc->translateString('checkDoc.'.($checkDoc->checkLabel==='' ? 'noErrorPage' : 'errorPage'),['page' => $page, 'type' => $type]).$checkDoc->checkLabel) : $checkDoc->anyError;
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
            $this->addPageHash = true;
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

                        // consent
                        $this->checkConsent();

                        // measures
                        $this->checkMeasures();

                        // burdens/risks
                        $this->checkBurdensRisks();

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
                $this->checkLabel = trim($this->checkLabel)."\n\n";
                $contributor = $this->addZeroIndex($this->appArray[self::contributorsNodeName][self::contributorNode]);
                $translationPage = self::projectdetailsPrefix.self::contributorNode.'.task';
                $anyError = false;
                foreach ($this->contributorTasks as $key => $value) { // key: node name of task. value: array with contributors. In this array: key: contributor index. value: empty or 'other' description
                    $tempString = '';
                    if ($value!==[] && $value!=='') {
                        if ($key===self::otherTask) {
                            foreach ($value as $index => $task) {
                                $this->addCheckLabelString($translationPage,'', ['numContributor' => 1, 'task' => $task, 'contributor' => $contributor[$index][self::infosNode][self::nameNode]]);
                                $anyError = true;
                            }
                        }
                        else {
                            foreach ($value as $index => $description) {
                                $tempString .= ', '.$contributor[$index][self::infosNode][self::nameNode]; // name of the contributor
                            }
                            $this->addCheckLabelString($translationPage,'', ['numContributor' => count($value), 'task' => $this->translateString('contributors.tasks.'.$key), 'contributor' => substr($tempString, 2)]);
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
                $this->checkLabel = trim($this->checkLabel)."\n";
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
     * @return void
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
        $this->checkMissingTextfield($coreDataArray[self::projectTitleParticipation],null,self::projectTitleDifferent,$tempVal,self::projectTitleParticipation,$tempVal);
        // application type
        $tempArray = $coreDataArray[self::applicationType];
        if (in_array($this->checkMissingChosen($tempArray,'coreData.appType.title',null,self::applicationType),self::appExtendedResubmission)) {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $translationPrefix.'reference'],hash: 'exReDiv');
        }
        // application process
        $tempArray = $coreDataArray[self::applicationProcessNode];
        $tempPrefix = $translationPrefix.self::applicationProcessNode.'.';
        $this->checkMissingChosen($tempArray,$tempPrefix.'missing',null,self::applicationProcessNode,true);
        if (array_key_exists(self::shortDocsNode,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::shortDocsNode => $tempPrefix.self::shortDocsNode]);
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
        $this->checkMissingContent($tempArray,[self::chosen => $tempPrefix.'start.title'],parameter: $this->committeeParam,hash: 'projectDates');
        $this->checkMissingContent($coreDataArray,[self::projectEnd => $tempPrefix.'end.title']);
        $start = $tempArray[self::chosen];
        $isBegun = array_key_exists(self::descriptionNode,$tempArray);
        $today = $this->getCurrentDate();
        $validStart = false; // gets true if a date is selected
        if (!($start==='' || $start==='0' || $isBegun)) { // if 'next' is selected, $start is '0' and $isBegun is false
            $start = (new DateTime($start))->setTime(0,0);
            $validStart = true;
            if ($start<=$today) {
                $this->addCheckLabelString($translationPrefix.'start','projectDates');
            }
        }
        if ($isBegun) {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $translationPrefix.'begun'],hash: $this->addDiv(self::projectStartBegun,true));
        }
        $end = $coreDataArray[self::projectEnd];
        if ($end!=='') {
            $end = (new DateTime($end))->setTime(0,0);
            if ($end<=$today) {
                $this->addCheckLabelString($translationPrefix.'end',self::projectEnd);
            }
            elseif ($validStart && $end<$start) { // $start and $end are either both or neither empty strings
                $this->addCheckLabelString($translationPrefix.'endBeforeStart','projectDates');
            }
        }
        // funding
        $tempArray = $coreDataArray[self::funding];
        $tempPrefix = 'coreData.funding.';
        $isFunding = $tempArray!=='';
        $tempVal = $isFunding && array_key_exists(self::fundingQuali,$tempArray);
        if (!$isFunding) {
            $this->addCheckLabelString($this->translateString($tempPrefix.'title').$this->translateString(self::missingSingle),self::funding,colorRed: false);
        }
        elseif (!$tempVal) {
            foreach ($tempArray as $key => $source) {
                $this->checkMissingContent($source,[self::descriptionNode => $tempPrefix.$key],true,hash: $this->addDiv($key,true));
                if (array_key_exists(self::fundingStateNode,$source)) {
                    $this->checkMissingContent($source,[self::fundingStateNode => $translationPrefix.'fundingState'],parameter: ['type' => $key],hash: $this->addDiv($key.self::fundingStateNode));
                }
            }
        }
        elseif (!$isQualification) { // isQualification can only be false if the question was asked
            $this->addCheckLabelString($translationPrefix.self::fundingQuali);
        }
        // conflict
        $tempArray = $coreDataArray[self::conflictNode];
        $tempPrefix = $translationPrefix.self::conflictNode.'.';
        $chosen = $this->checkMissingChosen($tempArray,$tempPrefix.'title',2,self::conflictNode,true);
        if (array_key_exists(self::descriptionNode,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.'description.'.($chosen===0 ? 'yes' : 'no')],hash: $this->addDiv(self::conflictNode,true,false));
        }
        // support
        $tempArray = $coreDataArray[self::supportNode];
        if ($this->checkMissingChildren($coreDataArray,self::supportNode,$translationPrefix.self::supportNode) && !array_key_exists(self::noSupport,$tempArray)) { // at least one support type except no support was chosen
            foreach (array_keys($tempArray) as $support) {
                $this->checkMissingContent($tempArray,[$support => 'coreData.support.type.'.$support],true,hash: $this->addDiv($support,true));
            }
        }
        //guidelines
        if (array_key_exists(self::guidelinesNode,$coreDataArray) && $coreDataArray[self::guidelinesNode]!=='') {
            $this->checkMissingContent($coreDataArray[self::guidelinesNode],[self::descriptionNode => $translationPrefix.self::guidelinesNode],true,hash: $this->addDiv(self::guidelinesNode,true));
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
        if ($this->checkMissingTextfield($pageArray,2,0,$otherVote,self::otherVote,$translationPrefix.'otherVoteCommittee',$this->addDiv(self::descriptionNode))===0) { // answer was yes
            $chosen = $this->checkMissingChosen($pageArray,$translationPrefix.self::otherVoteResult,null,$this->addDiv(self::otherVote), false,self::otherVoteResult);
            if ($chosen!=='') { // result question was answered
                $this->checkMissingContent($pageArray,[self::otherVoteResultDescription => $otherVote.($chosen===self::otherVoteResultNegative ? 'Negative' : 'PositiveNo')],hash: $this->addDiv(self::otherVoteResult,true,false));
            }
        }
        $pageArray = $voteArray[self::instVote];
        if ($this->checkMissingChosen($pageArray,$translationPrefix.'instVote',2,self::instVote,true,self::chosen,$this->committeeParam)===0) { // answer was yes
            $hasReference = $pageArray[self::instReference]!=='';
            $this->checkMissingContent($pageArray,array_merge(!in_array($this->appType,self::appExtendedResubmission) ? [self::instReference => $translationPrefix.'instVoteReference'] : [],[self::instVoteText => $translationPrefix.self::instVoteText]),hash: $this->addDiv($hasReference ? self::instVote : self::instReference,$hasReference));
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
        $tempPrefix = $translationPrefix.self::medicine.'.';
        $pageArray = $this->appDataArray[self::medicine];
        $this->checkMissingTextfield($pageArray[self::medicine],2,0,$tempPrefix.'missing',self::medicine,$tempPrefix.self::descriptionNode, parameters: $this->committeeParam);
        $translationPrefix .= 'physician.';
        $tempArray = $pageArray[self::physicianNode];
        if ($this->checkMissingChosen($tempArray,$translationPrefix.self::chosen,2,self::physicianNode)===0) {
            $tempArray = $tempArray[self::descriptionNode];
            $translationPrefix .= self::descriptionNode.'.';
            $this->checkMissingTextfieldEmpty($tempArray,$translationPrefix.self::chosen,$translationPrefix.self::descriptionNode,$this->addDiv(self::physicianNode,true,false),false,hashDescription: $this->addDiv(self::descriptionNode),parameters: $this->committeeParam);
        }
        $this->setAppDataTitle($setTitle);
    }

    /** Checks for errors on the summary page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkSummary(bool $setTitle = true): void {
        $this->addAppDataTitle(self::summary,$setTitle);
        $this->checkMissingContent($this->appDataArray[self::summary],[self::descriptionNode => 'pages.appData.summary'],hash: self::summary);
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
            $parameter = ['index' => $index+1, 'name' => $infos[self::nameNode], 'isSupervisor' => $this->getStringFromBool($isStudentPhd)];
            // infos
            if (!($index===0 || $index===1 && $isStudentPhd)) {
                $lineTitle = $this->translateString(self::contributorsPrefix.'lineTitle',$parameter);
                $this->checkMissingContent($infos,$this->translateArray('multiple.infos.',self::infosMandatory,true),lineTitle: $lineTitle, addHash: false);
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
            $numTasks = $tasks==='' ? 0 : count($tasks);
            if ($numTasks===0 || $numTasks===1 && ($index===0 || $index===1 && $isStudentPhd)) { // contributor does not have any task
                $this->addCheckLabelString($tasksPrefix.'missing',parameters: $parameter);
            }
            else {
                foreach ($tasks as $key => $value) { // key: node name, value: empty or description of 'other'
                    if ($key===self::tasksTypes[self::otherTask] && $value==='') { // other task description is empty
                        $this->addCheckLabelString($tasksPrefix.'missingOther',parameters: $parameter,colorRed: false);
                    }
                } // foreach
            } // else
        }
        // check if any mandatory task is missing
        foreach ($this->isMandatory as $task => $value) {
            if (!$value) {
                $this->addCheckLabelString($tasksPrefix.'missingMandatory',parameters: ['task' => $this->translateString('contributors.tasks.'.$task)]);
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
            if (array_key_exists(self::peopleDescription,$pageArray)) {
                $this->checkMissingContent($pageArray,[self::peopleDescription => $translationPage.self::descriptionNode],true,hash: $this->addDiv(self::peopleDescription)); // description of groups
            }
            $tempArray = $pageArray[self::examinedPeopleNode]; // has at least one element at this point
            foreach ([self::physicalExaminedNode, self::mentalExaminedNode] as $group) {
                if ($this->isAppTypeShort && array_key_exists($group,$tempArray)) {
                    $this->addCheckLabelString($translationPage.'physicalMental',parameters: ['group' => $group]);
                }
            }
        }
        // closed group
        $tempPrefix = $translationPage.self::closedNode.'.';
        $tempArray = $pageArray[self::closedNode];
        if($this->checkMissingChosen($tempArray,$tempPrefix.'title',2,self::closedNode,true)===0) {
            $this->checkMissingChildrenOther($tempArray, self::closedTypesNode, $tempPrefix.'types', [self::closedOther => $tempPrefix.'other']);
        }
        // criteria
        $tempPrefix = $translationPage.self::criteriaNode.'.';
        $criteria = $pageArray[self::criteriaNode];
        $tempArray = $criteria[self::criteriaIncludeNode];
        $curCriteria = $tempArray[self::criteriaNode];
        if (!$this->getBoolFromString($tempArray[self::noCriteriaNode]) && (is_string($curCriteria) ? 0 : count($curCriteria))<2) {
            $this->addCheckLabelString($tempPrefix.'include',self::criteriaNode,colorRed: false); // age is only inclusion criterion
        }
        $tempArray = $criteria[self::criteriaExcludeNode];
        if (!$this->getBoolFromString($tempArray[self::noCriteriaNode]) && $tempArray[self::criteriaNode]==='') {
            $this->addCheckLabelString($tempPrefix.self::criteriaExcludeNode,self::criteriaExcludeNode.'Start',colorRed: false);
        }
        // sample size
        $tempArray = $pageArray[self::sampleSizeNode];
        $this->checkMissingContent($tempArray,[self::sampleSizeTotalNode => $translationPage.'sampleSize',self::sampleSizePlanNode => $translationPage.'planning'],default: '0'); // both in same line. Leads also to the planning message if '0' was entered
        // recruitment
        $tempArray = $pageArray[self::recruitment];
        $tempPrefix = $translationPage.self::recruitment.'.';
        if ($this->checkMissingChildrenOther($tempArray,self::recruitmentTypesNode,$tempPrefix.'missing',array_combine(self::recruitmentTypesOther,$this->createPrefixArray(self::recruitmentTypesOther,'',$tempPrefix.self::descriptionNode.'.')))) {
            $types = $tempArray[self::recruitmentTypesNode];
            if ($isExamined && !array_key_exists(self::dependentExaminedNode,$pageArray[self::examinedPeopleNode])) {
                $tempPrefix .= 'dependent';
                foreach ([self::recruitmentLecture,'recruitmentPrivate'] as $type) {
                    if (array_key_exists($type,$types)) {
                        $this->addCheckLabelString($tempPrefix,parameters: ['type' => $type]);
                    }
                }
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
            $pre = $this->checkMissingChosen($pageArray,$translationStart.'missingPre',2,self::pre,true);
            if ($pre<2) {
                $tempArray = $pageArray[self::informationAddNode];
                if ($pre===0) { // answer was yes
                    $this->checkMissingChosen($pageArray,$translationStart.'typePre',null,self::preType,name: self::descriptionNode); // type of information
                    $tempVal = $tempArray[self::chosen]; // content
                    if ($tempVal==='') {
                        $this->addCheckLabelString($translationStart.'preContent',self::preContent,colorRed: false);
                    }
                    elseif ($tempVal!==self::complete) { // partial or deceit
                        $this->checkMissingTextfieldEmpty($tempArray,$translationStart.'deceit',$translationStart.'deceitDescription',self::preComplete,false,self::complete,hashDescription: $this->addDiv(self::preComplete,true)); // complete post-information and description of information given
                        if (array_key_exists(self::preCompleteType,$tempArray)) {
                            $this->checkMissingChosen($tempArray,$translationStart.'deceitType',null,'completePostType',true,self::preCompleteType);
                        }
                        if ($tempVal===self::deceit && $this->isAppTypeShort) {
                            $this->addCheckLabelString($translationStart.'deceitMain',parameters: ['addressee' => $this->translateString('projectdetails.addressee.'.($page===self::informationNode ? 'thirdParties.' : 'participants.').$this->addressee)]);
                        }
                    }
                }
                elseif ($pre===1) { // answer was no
                    $this->checkMissingContent($pageArray,[self::descriptionNode => $translationStart.'descriptionPre'],hash: $this->addDiv(self::pre,true));
                    if ($this->checkMissingTextfield($tempArray,2,1,$translationStart.'missingPost','preNo',$translationStart.'descriptionPost',$this->addDiv(self::post,true))===0) { // post-information
                        $this->checkMissingContent($tempArray,[self::descriptionNode => $translationStart.'typePost'],hash: 'postType');
                    }
                }
            }
            // attendance
            if (array_key_exists(self::attendanceNode,$pageArray)) {
                $this->checkMissingChosen($pageArray,$translationStart.self::attendanceNode,2,self::attendanceNode,true,self::attendanceNode,$this->paramsAddressee);
            }
            // document translation
            if (array_key_exists(self::documentTranslationNode,$pageArray)) {
                $tempPrefix = $translationStart.self::documentTranslationNode.'.';
                $this->checkMissingTextfield($pageArray[self::documentTranslationNode],2,0,$tempPrefix.'missing',self::documentTranslationNode,$tempPrefix.self::descriptionNode);
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
        $voluntary = $this->checkVoluntaryConsent($tempArray,self::voluntaryNode,$tempPrefix.'title',$tempPrefix.'voluntaryParticipants',$tempPrefix.self::voluntaryNode.self::descriptionCap);
        $voluntaryAddressee = $voluntary[0];
        $voluntaryParticipant = $voluntary[1];
        $isPreParticipants = $this->informationII[0]===0;
        if (array_key_exists(self::voluntaryYesDescription,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::voluntaryYesDescription => $tempPrefix.self::voluntaryYesDescription],hash: $this->addDiv(self::voluntaryYesDescription));
        }
        // no pre-information -> voluntary not applicable
        if (($this->noPre && $voluntaryAddressee!=='' && $voluntaryAddressee!==self::voluntaryNotApplicable)) {
            $this->addCheckLabelString($translationPage.'informationToNotApplicable',parameters: $this->paramsAddressee);
        }
        if ($this->noPreParticipants && $voluntaryParticipant!=='' && $voluntaryParticipant!==self::voluntaryNotApplicable) {
            $this->addCheckLabelString($translationPage.'informationToNotApplicable',parameters: $this->paramsParticipants);
        }
        // voluntary not applicable -> no pre-information
        if ($this->isPre && $voluntaryAddressee===self::voluntaryNotApplicable) {
            $this->addCheckLabelString($translationPage.'notApplicableToInformation',parameters: $this->paramsAddressee);
        }
        if ($isPreParticipants && $voluntaryParticipant===self::voluntaryNotApplicable) {
            $this->addCheckLabelString($translationPage.'notApplicableToInformation',parameters: $this->paramsParticipants);
        }
        // consent
        $tempPrefix = $translationPage.self::consentNode.'.';
        $this->paramsAddressee['type'] = self::consentNode;
        $this->paramsParticipants['type'] = self::consentNode;
        $consentParticipant = $this->checkVoluntaryConsent($pageArray[self::consentNode],self::consentNode,$tempPrefix.'title',$tempPrefix.'consentParticipants',$tempPrefix.self::consentNode.self::descriptionCap)[1];
        $isConsentChosen = $this->consentAddressee!=='';
        $isConsentNotApplicable = $this->consentAddressee===self::consentNotApplicable;
        $isConsentChosenParticipant = $consentParticipant!=='';
        $isConsentNotApplicableParticipant = $consentParticipant===self::consentNotApplicable;
        // no voluntariness -> no consent
        if ($voluntaryAddressee===self::voluntaryConsentNo && $isConsentChosen && $this->consentAddressee!==self::voluntaryConsentNo) {
            $this->addCheckLabelString($tempPrefix.'voluntaryToConsent',parameters: $this->paramsAddressee);
        }
        if ($voluntaryParticipant===self::voluntaryConsentNo && $isConsentChosenParticipant && $consentParticipant!==self::voluntaryConsentNo) {
            $this->addCheckLabelString($tempPrefix.'voluntaryToConsent',parameters: $this->paramsParticipants);
        }
        // pre-information and no consent -> no voluntariness
        if ($this->isPre && $this->consentAddressee===self::voluntaryConsentNo && !in_array($voluntaryAddressee,['',self::voluntaryConsentNo])) {
            $this->addCheckLabelString($tempPrefix.'consentToVoluntary',parameters: $this->paramsAddressee);
        }
        if ($isPreParticipants && $consentParticipant===self::voluntaryConsentNo && !in_array($voluntaryParticipant,['',self::voluntaryConsentNo])) {
            $this->addCheckLabelString($tempPrefix.'consentToVoluntary',parameters: $this->paramsParticipants);
        }
        // testing both elements separately is way faster than count(array_intersect($information,[0,1]))
        // neither pre- nor post-information -> consent not applicable
        if ($this->noPost && $isConsentChosen && !$isConsentNotApplicable) {
            $this->addCheckLabelString($translationPage.'informationToNotApplicable',parameters: $this->paramsAddressee);
        }
        if ($this->noPostParticipants && $isConsentChosenParticipant && !$isConsentNotApplicableParticipant) {
            $this->addCheckLabelString($translationPage.'informationToNotApplicable',parameters: $this->paramsParticipants);
        }
        // consent not applicable -> neither pre- nor post-information
        if (in_array(0,$this->information) && $isConsentChosen && $isConsentNotApplicable) {
            $this->addCheckLabelString($translationPage.'notApplicableToInformation',parameters: $this->paramsAddressee);
        }
        if (in_array(0,$this->informationII) && $isConsentChosenParticipant && $isConsentNotApplicableParticipant) {
            $this->addCheckLabelString($translationPage.'notApplicableToInformation',parameters: $this->paramsParticipants);
        }
        // terminate with disadvantages
        $tempPrefix = $translationPage.self::terminateConsNode.'.';
        $tempArray = $pageArray[self::terminateConsNode];
        $this->checkMissingTextfield($tempArray,2,1,$tempPrefix.'title',self::terminateConsNode,$tempPrefix.'description',addDescription: true);
        if (array_key_exists(self::terminateConsParticipationNode,$tempArray)) {
            $this->checkMissingContent($tempArray,[self::terminateConsParticipationNode => $tempPrefix.'participation'],true,parameter: $this->paramsAddressee,hash: $this->addDiv(self::terminateConsNode.self::terminateConsParticipationNode));
        }
        // termination by participants
        $tempPrefix = $translationPage.self::terminateParticipantsNode.'.';
        if (!in_array($this->checkMissingTextfield($pageArray[self::terminateParticipantsNode],null,self::terminateParticipantsOther,$tempPrefix.'title',self::terminateParticipantsNode,$tempPrefix.self::descriptionNode,$this->addDiv(self::terminateParticipantsNode,true)),['','remove','choose']) && $this->noPre && in_array($this->consentAddressee,self::consentTypesAll)) { // no pre information and consent is given -> data must either be deleted or participants must choose whether to delete or keep
            $this->addCheckLabelString($tempPrefix.self::informationNode,parameters: array_merge($this->paramsAddressee,$this->routeIDs));
        }
        // terminate criteria
        $this->checkMissingContent($pageArray,[self::terminateCriteriaNode => $translationPage.self::terminateCriteriaNode],true,hash: $this->addDiv(self::terminateCriteriaNode));
        $this->setProjectdetailsTitle($setTitle);
    }

    /** Checks for errors on the measures page.
     * @param bool $setTitle if true, the page title will be added above the errors
     * @return void
     */
    private function checkMeasures(bool $setTitle = true): void {
        $this->addProjectdetailsTitle(self::measuresNode,$setTitle);
        $pageArray = $this->measure[self::measuresNode];
        $translationPage = self::projectdetailsPrefix.self::measuresNode.'.';
        // procedure
        $this->checkMissingContent($pageArray,[self::procedureNode => $translationPage.self::procedureNode]);
        // measures and interventions
        foreach ([self::measuresNode,self::interventionsNode] as $type) {
            $tempPrefix = $translationPage.$type.'.';
            $tempArray = $pageArray[$type];
            $otherTypes = self::measuresInterventionsOther[$type];
            if ($this->checkMissingChildrenOther($tempArray,$type.'Type',self::missingTypes,array_combine($otherTypes,$this->createPrefixArray($otherTypes,valuePrefix: $tempPrefix.'otherTypes.')),['type' => $this->translateString($tempPrefix.'missing')]) && array_key_exists(self::descriptionNode,$tempArray)) {
                $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.'missing'],true,$this->addDiv($type,true,false),hash: $this->addDiv($type,true,false));
            }
        }
        // other sources
        $tempPrefix = $translationPage.self::otherSourcesNode;
        $this->checkMissingTextfield($pageArray[self::otherSourcesNode],2,0,$tempPrefix,self::otherSourcesNode,$tempPrefix,addDescription: true,descriptionKey: self::otherSourcesNode.self::descriptionCap);
        // loan
        $tempArray = $pageArray[self::loanNode];
        $tempPrefix = $translationPage.self::loanNode.'.';
        if ($this->checkMissingChosen($tempArray,$tempPrefix.'title',2,self::loanNode,true)===0) {
            $this->checkMissingTextfield($tempArray[self::loanReceipt],null,self::templateText,$tempPrefix.self::loanReceipt,$this->addDiv(self::loanReceipt),$tempPrefix.self::descriptionNode,$this->addDiv(self::loanReceipt,true));
            if ($this->noPre) {
                $this->addCheckLabelString($tempPrefix.'information',parameters: $this->routeIDs);
            }
        }
        // location
        $tempArray = $pageArray[self::locationNode];
        $tempPrefix = $translationPage.self::locationNode.'.';
        if ($this->checkMissingChosen($tempArray,$tempPrefix.'title',null,self::locationNode)!=='') {
            $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.'locationDescription'],hash: $this->addDiv(self::locationNode,true,false));
        }
        // presence
        $tempPrefix = $translationPage.self::presenceNode.'.';
        if ($this->checkMissingChosen($pageArray,$tempPrefix.'missing',2,self::presenceNode,true,self::presenceNode)===1 && ($this->isTwoAddressees ? $this->measure[self::consentNode][self::consentNode][self::chosen2Node] : $this->consentAddressee)===self::consentOral) { // oral consent -> contributors must be present
            $this->addCheckLabelString($tempPrefix.self::consentNode,parameters: $this->routeIDs);
        }
        // durations
        $tempPrefix = $translationPage.self::durationNode.'.';
        $this->checkMissingContent($pageArray[self::durationNode],[self::durationMeasureTime => $tempPrefix.self::durationMeasureTime],default: '0',hash: self::durationNode);
        $this->checkMissingContent($pageArray[self::durationNode],[self::durationBreaks => $tempPrefix.self::durationBreaks],hash: self::durationNode);
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
        if ($this->checkMissingTextfield($tempArray,2,0,$tempVal,self::burdensRisksContributorsNode.'Type',$tempVal,$this->addDiv(self::burdensRisksContributorsNode,true,false),true, parameters: $params)===0) {
            $this->checkBurdensRisksCompensation($tempArray,self::burdensRisksContributorsNode,$params);
        }
        // finding
        $tempPrefix = $translationPage.self::findingNode.'.';
        $tempArray = $pageArray[self::findingNode];
        if ($this->checkMissingTextfield($tempArray,2,0,$tempPrefix.'title',self::findingNode,$tempPrefix.self::descriptionNode,$this->addDiv(self::descriptionNode),true)===0) {
            $this->checkMissingChosen($tempArray,$tempPrefix.self::informingNode,null,$this->addDiv(self::findingNode),true,self::informingNode);
            if ($this->noPre) { // no pre information -> no finding
                $this->addCheckLabelString($tempPrefix.'information',parameters: $this->routeIDs);
            }
            if (in_array($this->consentAddressee,[self::voluntaryConsentNo,self::consentNotApplicable])) { // finding -> consent
                $this->addCheckLabelString($tempPrefix.self::consentNode,parameters: $this->routeIDs);
            }
        }
        // feedback
        $tempPrefix = $translationPage.self::feedbackNode.'.';
        $tempArray = $pageArray[self::feedbackNode];
        if ($this->checkMissingTextfield($tempArray,2,0,$tempPrefix.'title',self::feedbackNode,$tempPrefix.self::descriptionNode,addDescription: true)===0 && !array_key_exists(self::feedbackNode,$this->measure[self::measuresNode][self::interventionsNode][self::interventionsTypesNode] ?: [])) {
            $this->addCheckLabelString($tempPrefix.'feedbackInterventions',parameters: $this->routeIDs);
        }
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
        if ($this->checkMissingChildren($pageArray,self::compensationTypeNode,$translationPage.'missing')) {
            $typeArray = $pageArray[self::compensationTypeNode];
            $isNoCompensation = array_key_exists(self::compensationNo,$typeArray); // true if no compensation is given
            if (!$isNoCompensation) { // at least one type except 'no compensation' was selected
                // types
                $typePrefix = $translationPage.self::compensationTypeNode.'.';
                if ($this->noPre && $this->noPost && (!$this->isInformationII || $this->noPreParticipants && $this->noPostParticipants)) { // no information -> no compensation
                    $this->addCheckLabelString($translationPage.'information',parameters: array_merge($this->routeIDs,[self::addressee => $this->addressee]));
                }
                foreach ($typeArray as $type => $value) {
                    $isMoney = $type===self::compensationMoney;
                    $isHours = $type===self::compensationHours;
                    $isLottery = $type===self::compensationLottery;
                    $isMoneyHours = $isMoney || $isHours;
                    $prefix = $typePrefix.$type.'.';
                    $this->checkMissingContent($value,[self::descriptionNode => $prefix.'missing'],false,'0',hash: $type);
                    if ($isMoneyHours) {
                        $this->checkMissingContent($value,[self::moneyHourAdditionalNode => $prefix.'amount'],hash: $type); // real or flat
                        if ($isMoney) {
                            $tempPrefix = $prefix.self::moneyFurther;
                            $this->checkMissingTextfield($value[self::moneyFurther],2,0,$tempPrefix,self::moneyFurther,$tempPrefix.self::descriptionCap,true);
                        }
                        if ($isHours && $value[self::moneyHourAdditionalNode]===self::amountFlat) {
                            $this->checkMissingContent($value,[self::hourAdditionalNode2 => $prefix.'amountFlat'],default: '0',hash: $type);
                        }
                    }
                    // awarding
                    $awardingArray = $value[self::awardingNode];
                    $hashPrefix = self::awardingNode.$type;
                    $hashDiv = $this->addDiv($hashPrefix);
                    $lineTitle = $this->translateString($awardingPrefix.'type.'.$type);
                    $compensationParam = [self::compensationNode => $type];
                    $typeParams = array_merge(['type' => $lineTitle, 'typeDescription' => $this->translateString($awardingPrefix.'typeDescription',$compensationParam)],$compensationParam,$this->routeIDs);
                    if ($isLottery) { // announcement
                        $tempPrefix = $awardingPrefix.self::compensationLottery.'.';
                        $this->checkMissingContent($awardingArray,array_merge([self::lotteryStart.self::descriptionCap => $tempPrefix.'start', self::lotteryStart => $tempPrefix.'announcement'],array_key_exists(self::lotteryStartOtherDescription,$awardingArray) ? [self::lotteryStartOtherDescription => $tempPrefix.'announcementOther'] : []),lineTitle: $lineTitle,hash: $hashDiv);
                    }
                    $chosen = $this->checkMissingChosen($awardingArray,$awardingPrefix.'missing',null,!$isLottery ? $hashDiv : self::awardingNode.self::compensationLottery.'Heading',parameters: $typeParams);
                    if ($chosen!=='' && $type!==self::compensationOther) {
                        $hashDiv = $type.$chosen;
                        if (array_key_exists(self::descriptionNode,$awardingArray)) { // (first) description text field of chosen option
                            $this->checkMissingContent($awardingArray,[self::descriptionNode => $awardingPrefix.$chosen],lineTitle: $lineTitle, parameter: $typeParams,hash: $hashDiv);
                        }
                        $tempPrefix = $awardingPrefix.self::laterTypesName;
                        if (array_key_exists(self::laterTypesName,$awardingArray) && $this->checkMissingChosen($awardingArray,$tempPrefix,null,$hashDiv,name: self::laterTypesName,parameters: $typeParams)==='laterEndOther') { // information for later
                            $this->checkMissingContent($awardingArray,[self::laterOtherDescription => $tempPrefix.self::descriptionCap],true,lineTitle: $lineTitle,parameter: $typeParams,hash: $hashDiv);
                        }
                        if ($isMoneyHours && $chosen==='immediately' && $this->measure[self::measuresNode][self::presenceNode]==='1') { // money and hours immediately -> contributors must be present
                            $this->addCheckLabelString($translationPage.self::presenceNode,parameters: $typeParams);
                        }
                    }
                }
                // terminate
                $tempPrefix = $translationPage.self::terminateNode.'.';
                $tempArray = $pageArray[self::terminateNode];
                $chosen = $this->checkMissingChosen($tempArray,$tempPrefix.'missing',null,self::terminateNode,true);
                if (array_key_exists(self::descriptionNode,$tempArray)) {
                    $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.$chosen],hash: $this->addDiv(self::terminateNode,true,false));
                }
                // compensation voluntary
                if ($this->checkMissingChosen($pageArray,$translationPage.self::compensationVoluntaryNode,2,self::compensationVoluntaryNode,true,self::compensationVoluntaryNode)===0) {
                    // further description
                    $this->checkMissingContent($pageArray,[self::compensationTextNode => $translationPage.self::compensationTextNode],hash: $this->addDiv(self::compensationTextNode));
                }
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
                $this->addCheckLabelString($translationPage.'intro',self::introNode,colorRed: false);
            }
            // goals
            $this->checkMissingContent($pageArray,[self::goalsNode => $translationPage.self::goalsNode]);
            // pro
            $tempArray = $pageArray[self::proNode];
            $this->checkMissingContent($tempArray,[$this->getBoolFromString($tempArray[self::proTemplate]) ? self::proTemplateText : self::descriptionNode => $translationPage.'pro'],true,hash: self::proNode);
            // con and finding consent
            foreach ([self::conNode,self::findingTextNode] as $type) {
                if (array_key_exists($type,$pageArray)) { // findingConsent may not exist
                    $tempArray = $pageArray[$type];
                    if (array_key_exists(self::descriptionNode,$tempArray) && $tempArray[self::descriptionNode]==='') {
                        $this->addCheckLabelString($translationPage.$type,$type,colorRed: false);
                    }
                }
            }
            // conflict text
            if (array_key_exists(self::conflictTextNode,$pageArray) && $pageArray[self::conflictTextNode]==='') {
                $this->addCheckLabelString($translationPage.self::conflictTextNode,self::conflictTextNode,colorRed: false);
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
                $this->checkMissingTextfield($input,null,self::templateText,$translationPage.'missing',$type,$translationPage.'description',$this->addDiv($type,true),parameters: ['type' => $type]);
            }
            $this->setProjectdetailsTitle($setTitle);
        }
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
        $furtherPrefix = $translationPage.'further.';
        $furtherOtherSourcesPrefix = $furtherPrefix.self::otherSourcesNode.'.';
        $measuresArray = $this->measure[self::measuresNode];
        $isOtherSources = $measuresArray[self::otherSourcesNode][self::chosen]==='0';
        // processing
        $this->checkMissingContent($pageArray,[self::processingNode => $translationPage.self::processingNode],true,hash: $this->addDiv(self::processingNode));

        // create
        $tempArray = $pageArray[self::createNode];
        $tempPrefix = $translationPage.self::createNode.'.';
        $create = $this->checkMissingChosen($tempArray,$tempPrefix.'missing',null,self::createNode,true);
        if ($this->noPost && !in_array($create,['',self::privacyNotApplicable])) { // neither pre- nor post-information -> create not applicable
            $this->addCheckLabelString($tempPrefix.'informationToNotApplicable',parameters: $this->routeIDs);
        }
        elseif ($create===self::privacyNotApplicable && in_array(0,$this->information)) { // create not applicable -> neither pre- nor post-information
            $this->addCheckLabelString($tempPrefix.'notApplicableToInformation',parameters: $this->routeIDs);
        }
        $isSeparate = $create===self::createSeparate;
        $support = $this->coreDataArray[self::supportNode];
        $isVerified = $isSeparate && $tempArray[self::descriptionNode]==='verified';
        $isCreateAnonymous = $create==='anonymous';
        if (($isVerified || $isCreateAnonymous) && $support!=='' && !array_key_exists('supportData',$support)) { // privacy separate and verified or anonymous -> support by data privacy
            $this->addCheckLabelString($tempPrefix.self::supportNode,parameters: ['isVerified' => $this->getStringFromBool($isVerified)]);
        }
        $isTool = $create===self::createTool;
        if ($isTool || $isSeparate) {
            $tempVal = $isTool ? self::confirmIntroNode : self::createVerificationNode;
            $tempVal = $this->checkMissingChosen($tempArray,$translationPage.$tempVal,null,$this->addDiv($tempVal),name: self::descriptionNode);
            if ($isTool && $tempVal) {
                $tempPrefix = $translationPage.self::responsibilityNode.'.';
                $responsibility = $this->checkMissingChosen($pageArray,$tempPrefix.'missing',null,self::responsibilityNode,true,self::responsibilityNode);
                if ($responsibility==='private' && ($this->appDataArray[self::coreDataNode][self::qualification] ?? '')==='1') { // responsibility is private -> qualification must be answered with yes
                    $this->addCheckLabelString($tempPrefix.self::qualification);
                }
                $transferOutside = $this->checkMissingChosen($pageArray,$translationPage.self::transferOutsideNode,null,self::transferOutsideNode,true,self::transferOutsideNode);
                $dataOnline = '';
                $dataOnlineProcessing = '';
                $tempArray = ['',self::privacyNotApplicable];
                if ($responsibility===self::privacyNotApplicable && !in_array($transferOutside,$tempArray)) { // responsibility not applicable -> transfer outside must also be not applicable
                    $this->addCheckLabelString($translationPage.self::responsibilityNode.'To'.self::transferOutsideNode);
                }
                elseif ($transferOutside===self::privacyNotApplicable && !in_array($responsibility,$tempArray)) { // transfer outside not applicable -> responsibility must also be not applicable
                    $this->addCheckLabelString($translationPage.self::transferOutsideNode.'To'.self::responsibilityNode);
                }
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
                    $dataPersonal = $this->checkMissingChosen($pageArray, $translationPage.self::dataPersonalNode, null,self::dataPersonalNode, true, self::dataPersonalNode); // data personal
                    $hasPersonal = in_array($dataPersonal,self::dataPersonal); // true if any personal data are collected
                    // marking
                    $markingSecondString = self::markingNode.self::markingSuffix;
                    $markings = [self::markingNode => '', $markingSecondString => ''];
                    $isMarkingPersonal = false; // gets true if any marking is or may be personal
                    $isMarkingPersonalNotGeneration = false; // gets true if any marking is by name or by code with list, or external
                    $isMarkingAnswered = true; // gets false if any marking (sub-)question is not answered
                    // variables indicating that a marking of a certain type is used
                    $isMarkingName = false; // gets true if any marking is by name
                    $isMarkingList = false; // gets true if any marking is by list
                    $isCodeMaybe = false; // gets true if any marking is by code 'generation'
                    $isCodeOnlyMarking = false; // gets false if any code is used only for marking of data
                    $isCodeNoDoc = false; // gets true if any code has no further documentation
                    foreach (array_merge([self::markingNode], array_key_exists($markingSecondString, $pageArray) ? [$markingSecondString] : []) as $marking) {
                        $tempArray = $pageArray[$marking];
                        $isFirstMarking = $marking===self::markingNode;
                        $suffix = $isFirstMarking ? '' : self::markingSuffix;
                        $markingWithSuffix = self::markingNode.$suffix;
                        $lineTitle = $isFirstMarking ? '' : $this->translateString($translationPage.$markingWithSuffix);
                        $tempPrefix = $translationPage.self::markingNode.'.';
                        $markingChosen = $this->checkMissingChosen($tempArray, $tempPrefix.'title', null,$this->addDiv(self::markingNode).$suffix, lineTitle: $lineTitle);
                        $isMarkingAnswered = $isMarkingAnswered && $markingChosen!=='';
                        $isExternal = $markingChosen===self::markingExternal;
                        $isName = $markingChosen===self::markingName;
                        $isMarkingPersonal = $isMarkingPersonal || $isName;
                        $isMarkingPersonalNotGeneration = $isMarkingPersonalNotGeneration || $isName || $isExternal;
                        $isMarkingName = $isMarkingName || $isName;
                        $markings[$marking] = $markingChosen;
                        if (array_key_exists(self::descriptionNode, $tempArray)) { // can only be true if external or name
                            $this->checkMissingContent($tempArray, [self::descriptionNode => $tempPrefix.self::descriptionNode], lineTitle: $lineTitle, parameter: ['type' => $markingChosen],hash: $this->addDiv($markingWithSuffix,true,false));
                        }
                        $isInternal = $markingChosen===self::markingInternal;
                        if ($isExternal || $isInternal) {
                            if ($isInternal) { // how the code is created
                                $markingChosen = $this->checkMissingChosen($tempArray, $tempPrefix.self::markingInternal, null,'internalCreateDiv'.$suffix, name: self::markingInternal, lineTitle: $lineTitle);
                                $isMarkingAnswered = $isMarkingAnswered && $markingChosen!=='';
                            }
                            if (array_key_exists(self::codePersonal, $tempArray)) { // if internal, key may not exist
                                $tempVal = $this->checkMissingChosen($tempArray, $tempPrefix.self::codePersonal, null,$this->addDiv($markingChosen).$suffix, name: self::codePersonal, lineTitle: $lineTitle);
                                $isMarkingAnswered = $isMarkingAnswered && $tempVal!=='';
                                $isMarkingPersonal = $isMarkingPersonal || in_array($tempVal, self::markingDataResearchTypes); // whether the code has personal data
                                $isCurList = $tempVal===self::markingList;
                                $isMarkingPersonalNotGeneration = $isMarkingPersonalNotGeneration || $isCurList;
                                $isCodeMaybe = $isCodeMaybe || $tempVal===self::generation;
                                $isMarkingList = $isMarkingList || $isCurList;
                                $isCodeOnlyMarking = $isCodeOnlyMarking || $tempVal==='marking';
                                $isCodeNoDoc = $isCodeNoDoc || $tempVal==='anonymous';
                            }
                        }
                    }
                    if ($markings[self::markingNode]!==self::markingOther) {
                        // marking further
                        if (in_array($pageArray[self::markingNode][self::chosen] ?? '', self::markingValues)) {
                            $isMarkingAnswered = $isMarkingAnswered && $this->checkMissingChosen($pageArray, $translationPage.self::markingFurtherNode, 2, $this->addDiv(self::markingFurtherNode),true, self::markingFurtherNode)!==2;
                        }
                        // two markings with name
                        if ($isMarkingAnswered && $markings[self::markingNode]===self::markingName && $markings[$markingSecondString]===self::markingName) {
                            $this->addCheckLabelString($translationPage.'markingName');
                        }
                        $hasMarkingPersonal = [self::markingList => $isMarkingList, self::generation => $isCodeMaybe, self::markingName => $isMarkingName];
                        $allPersonalAnswered = $isMarkingAnswered && $dataPersonal!==''; // true if all questions which lead to personal data are answered
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
                                $this->checkMissingTextfield($pageArray[self::storageNode], null, self::storageDelete, $tempPrefix.'missing', self::storageNode,$tempPrefix.self::descriptionNode);
                            }
                            if (array_key_exists(self::personalKeepNode, $pageArray)) {
                                // personal keep
                                $tempPrefix = $translationPage.self::personalKeepNode.'.';
                                if ($this->checkMissingChildren($pageArray, self::personalKeepNode, $tempPrefix.'missing')) {
                                    $tempArray = $pageArray[self::personalKeepConsentNode] ?? [];
                                    foreach ($pageArray[self::personalKeepNode] as $type => $description) {
                                        $typeParam = ['type' => $this->translateString(self::projectdetailsPrefixTool.self::privacyNode.'.'.self::personalKeepNode.'.typesShort.'.$type)];
                                        if ($description==='') {
                                            $this->addCheckLabelString($tempPrefix.self::descriptionNode,$this->addDiv($type,true), $typeParam, colorRed: false);
                                        }
                                        // personal keep consent
                                        if ($tempArray[$type]==='') {
                                            $this->addCheckLabelString($translationPage.self::personalKeepConsentNode,$type.'PersonalKeepConsentDiv', $typeParam, colorRed: false);
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
                                            $this->addCheckLabelString($tempPrefix.'markingPurpose',parameters: ['type' => $type]);
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
                                        $tempPrefix = $translationPage.self::purposeDataNode.'.';
                                        if (array_key_exists(self::purposeDataNode, $questions) && $this->checkMissingChildrenOther($questions, self::purposeDataNode, $tempPrefix.'missing', [$purposeNameWoPrefix.self::purposeDataOther => $tempPrefix.self::descriptionNode], $purposeParam,hash: $purposeNameWoPrefix.self::purposeDataNode)) {
                                            $tempArray = $questions[self::purposeDataNode];
                                            if (!array_key_exists($purposeNameWoPrefix.'name', $tempArray)) {
                                                foreach (['address', 'iban'] as $type) {
                                                    if (array_key_exists($purposeNameWoPrefix.$type, $tempArray)) {
                                                        $this->addCheckLabelString($tempPrefix.$type, parameters: $purposeParam);
                                                    }
                                                }
                                            }

                                        }
                                        if ($purposeNameWoPrefix===self::purposeCompensation) {
                                            $tempArray = $questions[self::purposeDataNode];
                                            if ($tempArray!=='') {
                                                $purposeDataCompensation = array_merge($purposeDataCompensation, array_keys($tempArray));
                                            }
                                        }
                                        // marking remove
                                        $removeUC = [self::markingList => ucfirst(self::markingList), self::generation => 'Code', self::markingName => ucfirst(self::markingName)];
                                        if (array_key_exists(self::markingRemoveNode, $questions)) {
                                            $tempPrefix = $translationPage.self::markingRemoveNode.'.';
                                            $tempArray = $questions[self::markingRemoveNode];
                                            $tempVal = $purposeNameWoPrefix.self::markingRemoveNode;
                                            if ($this->checkMissingTextfieldEmpty($tempArray, $tempPrefix.'missing', $tempPrefix.self::descriptionNode, $this->addDiv($tempVal),false,hashDescription: $this->addDiv($tempVal,true,false), parameters: $purposeParam)!=='') {
                                                if (array_key_exists(self::markingRemoveMiddleNode, $tempArray)) { // middle
                                                    if ($this->checkMissingChildren($tempArray, self::markingRemoveMiddleNode, $tempPrefix.self::markingRemoveMiddleNode, $purposeParam,$purposeNameWoPrefix.'MiddleDiv')) {
                                                        $tempVal = implode('', array_keys($tempArray[self::markingRemoveMiddleNode]));
                                                        foreach ($removeUC as $marking => $markingUC) {
                                                            $isMarkingRemove[$marking] = $isMarkingRemove[$marking] || str_contains($tempVal, $markingUC); // each selection is 'purposemiddleSelection'
                                                        }
                                                    }
                                                }
                                                else { // later description
                                                    $this->checkMissingContent($tempArray, [self::laterDescription => $tempPrefix.self::laterDescription], parameter: $purposeParam,hash: $this->addDiv($purposeNameWoPrefix.self::laterDescription));
                                                }
                                            }
                                            if ($isMarkingAnswered) {
                                                foreach ($isMarkingRemove as $marking => $isRemove) {
                                                    if (!$hasMarkingPersonal[$marking] && $isRemove) {
                                                        $this->addCheckLabelString($tempPrefix.$marking, parameters: $purposeParamGen);
                                                    }
                                                }
                                            }
                                        }
                                        // personal remove
                                        $tempArray = $questions[self::personalRemoveNode];
                                        $tempPrefix = $translationPage.self::personalRemoveNode.'.';
                                        $tempVal = $purposeNameWoPrefix.self::personalRemoveNode;
                                        $personalRemoveChosen = $this->checkMissingChosen($tempArray, $tempPrefix.'missing', null, $tempVal,true, parameters: $purposeParam);
                                        if (array_key_exists(self::descriptionNode, $tempArray)) {
                                            $this->checkMissingContent($tempArray, [self::descriptionNode => $tempPrefix.str_replace($purposeNameWoPrefix, '', $personalRemoveChosen)], parameter: $purposeParam,hash: $this->addDiv($personalRemoveChosen===$purposeNameWoPrefix.self::personalRemoveImmediately ? $tempVal : $purposeNameWoPrefix.self::personalRemoveKeep,true,false));
                                        }
                                        // access
                                        $this->checkAccess($questions,$purposeNameWoPrefix,$purposeParam);
                                    }
                                }
                            }
                        }
                        // order processing description
                        if (array_key_exists(self::orderProcessingDescriptionNode,$pageArray)) {
                            $this->checkMissingContent($pageArray[self::orderProcessingDescriptionNode], array_combine(self::orderProcessingKnownTexts, $this->prefixArray(self::orderProcessingKnownTexts,$translationPage.self::orderProcessingDescriptionNode.'.')), true,hash: self::orderProcessingDescriptionNode);
                        }
                        // compensation code
                        $isCompensationCode = false; // gets true if compensation code has personal data
                        if (array_key_exists(self::codeCompensationNode, $pageArray)) {
                            $tempArray = $pageArray[self::codeCompensationNode];
                            $tempPrefix = $translationPage.self::codeCompensationNode.'.';
                            $tempVal = self::codeCompensationExternal;
                            if ($this->checkMissingTextfield($tempArray, null, self::codeCompensationExternal, $tempPrefix.'missing', self::codeCompensationNode,$tempPrefix.self::descriptionNode)===self::codeCompensationInternal) {
                                $tempVal = $this->checkMissingChosen($tempArray, $tempPrefix.'internal', null, $this->addDiv(self::codeCompensationInternal),true, self::codeCompensationInternal);
                            }
                            if (array_key_exists(self::codeCompensationPersonal, $tempArray)) {
                                $isCompensationCode = $this->checkMissingChosen($tempArray, $tempPrefix.self::codeCompensationPersonal, null,'code'.$this->addDiv($tempVal), true, self::codeCompensationPersonal)===self::generation;
                                $isCodeMaybe = $isCodeMaybe || $isCompensationCode;
                            }
                        }
                        // further checks
                        $this->checkResponsibilityTransfer($responsibility,$transferOutside,$hasPersonal,$allPersonalAnswered,true);
                        $ipPrefix = $furtherPrefix.'ip.';
                        $isIP = array_key_exists('ip',$dataResearch);
                        $isDataPersonal = in_array($dataPersonal, ['', 'personal']);
                        if ($isIP) {
                            // location not online -> data research must not be ip
                            if (!in_array($measuresArray[self::locationNode][self::chosen],['',self::locationOnline])) {
                                $this->addCheckLabelString($ipPrefix.'noOnline',parameters: $this->routeIDs);
                            }
                            // no ip-addresses for online -> data research must not be ip
                            if ($dataOnline==='ipNo') {
                                $this->addCheckLabelString($ipPrefix.'ipNo');
                            }
                        }
                        // list has ip-addresses -> only if ip-addresses are linked to research data
                        if ($isListIP && (!in_array($dataOnline,['',self::dataOnlineTechnical]) || $dataOnline===self::dataOnlineTechnical && !in_array($dataOnlineProcessing,['',self::dataOnlineProcessingLinked]))) {
                            $this->addCheckLabelString($ipPrefix.'ipList',parameters: $this->routeIDs);
                        }
                        $isDataResearch = $dataResearch!==[];
                        if ($dataOnline===self::dataOnlineResearch) {
                            if ($isDataResearch && !$isIP) { // ip-addresses for research -> ip-addresses must be selected as research data
                                $this->addCheckLabelString($ipPrefix.self::dataOnlineResearch);
                            }
                        }
                        elseif ($dataOnline===self::dataOnlineTechnical) {
                            if ($isIP) { // ip-addresses only for technical reasons -> ip-addresses must not be research data
                                $this->addCheckLabelString($ipPrefix.self::dataOnlineTechnical);
                            }
                            if ($dataOnlineProcessing===self::dataOnlineProcessingLinked && $isMarkingAnswered && (!$isMarkingList || $hasList && !$isListIP)) { // ip-addresses can be linked to research data -> research data must be marked with list which contains the ip
                                $this->addCheckLabelString($ipPrefix.'linkedMarking');
                            }
                        }
                        // code generation -> closed group
                        if ($isCodeMaybe && $this->measure[self::groupsNode][self::closedNode][self::chosen]==='1') {
                            $this->addCheckLabelString($furtherPrefix.self::closedNode, parameters: $this->routeIDs);
                        }
                        // video -> research data is personal
                        $measuresTypes = $measuresArray[self::measuresNode][self::measuresTypesNode];
                        $isMeasures = $measuresTypes!=='';
                        $isVideoMeasures = $isMeasures && array_key_exists(self::measuresVideo, $measuresTypes);
                        if ($isVideoMeasures && !$isDataPersonal) {
                            $this->addCheckLabelString($furtherPrefix.'video', parameters: $this->routeIDs);
                        }
                        // video in measures <-> audio, photo, or video for data research.
                        if ($isMeasures && $isDataResearch) {
                            if ($isVideoMeasures && !$isDataResearchVideo) {
                                $this->addCheckLabelString($furtherPrefix.'measuresToVideo', parameters: $this->routeIDs);
                            }
                            elseif ($isDataResearchVideo && !$isVideoMeasures) {
                                $this->addCheckLabelString($furtherPrefix.'videoToMeasures', parameters: $this->routeIDs);
                            }
                        }
                        // other sources -> an external code or a person-related label (either name or code by list) must be used
                        if ($isOtherSources && $isMarkingAnswered && !$isMarkingPersonalNotGeneration) {
                            $this->addCheckLabelString($furtherOtherSourcesPrefix.self::markingNode, parameters: $this->routeIDs);
                        }
                        // no compensation -> purpose must not be compensation
                        $tempPrefix = $furtherPrefix.self::compensationNode.'.';
                        $purposeArraysMerged = array_merge($isPurposeResearch ? $purposeResearchArray : [], $isPurposeFurther ? $purposeFurtherArray : []);
                        $purposeArraysMergedKeys = array_keys($purposeArraysMerged);
                        $compensation = $this->measure[self::compensationNode];
                        $compensationTypes = $compensation[self::compensationTypeNode];
                        $isCompensation = $compensationTypes!==''; // may also be 'no compensation'
                        if ($isCompensation && array_key_exists(self::compensationNo, $compensationTypes) && array_intersect([self::purposeCompensation, self::purposeFurtherNode.self::purposeCompensation], $purposeArraysMergedKeys)!==[]) {
                            $this->addCheckLabelString($tempPrefix.'noCompensation', parameters: $this->routeIDs);
                        }
                        // compensation by name -> purpose data eMail for purpose compensation
                        $isNotPurposeCompensation = ($isPurposeResearch || !$hasPurposeResearch) && $isPurposeFurther && array_intersect([self::purposeCompensation, self::purposeFurtherNode.self::purposeCompensation], $purposeArraysMergedKeys)===[]; // true if purpose research either exists and was answered or does not exist, and research further was answered, and if compensation is not selected as a purpose
                        $isCompensationData = $purposeDataCompensation!==[]; // true if any data for purpose compensation is collected
                        // compensation by a certain type -> certain purpose data for purpose compensation
                        $isPurposeResearchCompensation = $isPurposeResearch && array_key_exists(self::compensationNode,$purposeResearchArray);
                        foreach (['name', 'eMail', 'phone'] as $type) {
                            $tempVal = $this->checkCompensationAwarding($compensation, $type);
                            if ($tempVal && ($isNotPurposeCompensation || $isCompensationData && !in_array(self::compensationNode.$type, $purposeDataCompensation))) {
                                $this->addCheckLabelString($tempPrefix.$type, parameters: $this->routeIDs);
                            }
                            // compensation by name and marking with purpose compensation -> either marking by name or list with name
                            if ($type==='name' && $tempVal && $isPurposeResearchCompensation && $isMarkingAnswered && !$isMarkingName && (!$isMarkingList || $hasList && !$isListName)) {
                                $this->addCheckLabelString($tempPrefix.'nameMarking',parameters: $this->routeIDs);
                            }
                        }
                        // compensation by transfer -> name and IBAN for purpose compensation
                        if ($this->checkCompensationAwarding($compensation,'transfer') && ($isNotPurposeCompensation || $isCompensationData && count(array_intersect([self::compensationNode.'name',self::compensationNode.'iban'],$purposeDataCompensation))!==2)) {
                            $this->addCheckLabelString($tempPrefix.'transfer',parameters: $this->routeIDs);;
                        }
                        // compensation by mail -> name and postal address for purpose compensation
                        if ($this->checkCompensationAwarding($compensation, 'mail') && ($isNotPurposeCompensation || $isCompensationData && count(array_intersect([self::compensationNode.'name', self::compensationNode.'address'], $purposeDataCompensation))!==2)) {
                            $this->addCheckLabelString($tempPrefix.'mail', parameters: $this->routeIDs);
                        }
                        // code has personal data -> purpose must be compensation
                        if ($isCompensationCode && $isNotPurposeCompensation) {
                            $this->addCheckLabelString($tempPrefix.'code', parameters: $this->routeIDs);
                        }
                        // compensation by code and marking with purpose compensation -> marking must be code
                        if ($this->checkCompensationAwarding($compensation) && $isPurposeResearchCompensation && $isMarkingAnswered && !in_array(true,[$isCodeNoDoc,$isMarkingList,$isCodeMaybe])) {
                            $this->addCheckLabelString($tempPrefix.'codeMarking',parameters: $this->routeIDs);
                        }
                    }
                    else { // marking is 'other'
                        $this->checkResponsibilityTransfer($responsibility,$transferOutside,$hasPersonal);
                        if (in_array($dataPersonal,['',self::dataPersonalNo]) && ($pageArray[self::dataOnlineProcessingNode] ?? '')===self::dataOnlineProcessingLinked) { // ip-addresses can be linked to research data -> research data must be marked with list which contains the ip
                            $this->addCheckLabelString($translationPage.'further.ip.linkedMarking');
                        }
                    }
                }
            }
        } // if isTool || isSeparate
        elseif ($isCreateAnonymous && $isOtherSources) { // other sources -> create selection can not be that the study includes only anonymous data
            $this->addCheckLabelString($furtherOtherSourcesPrefix.self::createNode,parameters: $this->routeIDs);
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
        if ($this->checkMissingChosen($pageArray,$translationPage.self::confirmIntroNode,null,$this->addDiv(self::confirmIntroNode),name: self::confirmIntroNode)==='1') {
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
                    $this->checkMissingChosen($tempArray,$dataReuseHowPrefix.'missing',null,$this->addDiv($dataReuseHow),lineTitle: $lineTitle);
                    if (array_key_exists(self::descriptionNode,$tempArray)) {
                        $this->checkMissingContent($tempArray,[self::descriptionNode => $dataReuseHowPrefix.self::descriptionNode],lineTitle: $lineTitle,hash: self::descriptionNode.'Start'.$suffix);
                    }
                }
            }
            if (($pageArray[self::dataReuseHowNode][self::chosen] ?? '')==='class0' && in_array($personal['personal'],['personal','purpose']) && ($privacyArray[self::transferOutsideNode] ?? '')==='no') { // personal data are made publicly available -> transfer outside can not be answered with 'no'
                $this->addCheckLabelString($dataReuseHowPrefix.'public',parameters: $this->routeIDs);
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
                    $isTaskAvailable = $this->isMandatory[$task]; // true if task was not selected for at least one contributor on 'contributors' page
                    $this->addCheckLabelString($translationPage.'mandatory',$isTaskAvailable ? $task : '', ['task' => $this->translateString(self::tasksTypes[$task]), 'type' => !$isTaskAvailable ? 'missing' : 'other'],!$isTaskAvailable);
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
        // consent
        $this->consentAddressee = $this->measure[self::consentNode][self::consentNode][self::chosen];
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
     * @param string $type type to be checked. Must equal 'burdens' or 'risks'
     * @return bool true if at least one option except 'no' is selected, false otherwise
     */
    private function checkBurdensRisksErrors(array $pageArray, string $page, string $typeKey, string $type): bool {
        $typeParam = ['burdensRisksType' => $type];
        $params = array_merge($typeParam,['type' => $this->translateString(self::projectdetailsPrefix.self::burdensRisksNode.'.title',$typeParam)]);
        $isSelected = $this->checkMissingChildren($pageArray,$typeKey,self::missingTypes,$params);
        if ($isSelected) {
            $multiArray = $pageArray[$typeKey];
            $isNoID = array_key_exists($type===self::burdensNode ? self::noBurdens : self::noRisks,$multiArray);
            if (!(count($multiArray)==1 && $isNoID)) {
                $this->checkMissingContent($pageArray,[self::descriptionNode => $page],true,parameter: $params,hash: $this->addDiv($type,true,false));
            }
            if (!$isNoID) {
                $this->checkBurdensRisksCompensation($pageArray,$type,$params);
            }
        }
        return $isSelected;
    }

    /** Checks for errors on the voluntary or consent type question on the consent page.
     * @param array $pageArray array whose children are checked for content
     * @param string $type type to be checked
     * @param string $missingChosen translation key if the 'chosen' key is empty
     * @param string $missingChosenParticipants translation key if the question exists twice and the 'chosen2' key is empty
     * @param string $missingDescription translation key if any of the two questions is answered such that a description is needed or if consent and other is selected
     * @return array first element: answer of first addressee. Second element: answer of second addressee, if any
     */
    private function checkVoluntaryConsent(array $pageArray, string $type, string $missingChosen, string $missingChosenParticipants, string $missingDescription): array {
        $returnArray = [$this->checkMissingChosen($pageArray,$missingChosen,null,$type,true), ''];
        $returnArray[1] = $this->isTwoAddressees ? $this->checkMissingChosen($pageArray,$missingChosenParticipants,null,$type.'TypeParticipants',true,self::chosen.'2') : '';
        $otherDescriptionTrans = $missingDescription.'Other';
        if (array_key_exists(self::consentOtherDescription,$pageArray)) {
            $this->checkMissingContent($pageArray,[self::consentOtherDescription => $otherDescriptionTrans],parameter: $this->paramsAddressee,hash: self::consentNode.self::consentOtherDescription);
        }
        $otherDescription = self::consentOtherDescription.'Participants';
        if (array_key_exists($otherDescription,$pageArray)) {
            $this->checkMissingContent($pageArray,[$otherDescription => $otherDescriptionTrans],parameter: $this->paramsParticipants,hash: self::consentNode.$otherDescription);
        }
        if (array_key_exists(self::descriptionNode,$pageArray)) {
            $this->checkMissingContent($pageArray,[self::descriptionNode => $missingDescription],parameter: $this->paramsAddressee,hash: $this->addDiv($type,true,false));
        }
        return $returnArray;
    }

    /** Checks if the responsibility and transfer outside questions are answered consistently with the collected (personal) data.
     * @param string $responsibility answer to responsibility question
     * @param string $transferOutside answer to transfer outside question
     * @param bool $hasPersonal true if personal data are collected, false otherwise
     * @param bool $allPersonalAnswered true if all relevant questions about personal data are answered, false otherwise
     * @param bool $checkBoth if true, both directions of the dependency are checked, otherwise only the not applicable -> no personal data direction
     * @return void
     */
    private function checkResponsibilityTransfer(string $responsibility, string $transferOutside, bool $hasPersonal, bool $allPersonalAnswered = true, bool $checkBoth = false): void {
        // responsibility/transferOutside not applicable <-> no personal data
        $translationPrefix = self::projectdetailsPrefix.self::privacyNode.'.further.';
        foreach ([self::responsibilityNode => $responsibility, self::transferOutsideNode => $transferOutside] as $type => $selection) {
            $isAnswered = $selection!=='';
            $isNotApplicable = $selection===self::privacyNotApplicable;
            if ($isNotApplicable && $allPersonalAnswered && $hasPersonal) {
                $this->addCheckLabelString($translationPrefix.$type.'ToPersonal');
            }
            elseif ($checkBoth && $allPersonalAnswered && !$hasPersonal && $isAnswered && !$isNotApplicable) {
                $this->addCheckLabelString($translationPrefix.'personalTo'.$type);
            }
        }
    }

    // methods for individual pages. Added here because at least one method calls a function defined afterward.

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
        $this->checkMissingContent($applicant,$tempArray,lineTitle: 'coreData.applicant.'.$type, hash: $type);
        $name = $applicant[self::nameNode];
        if ($name!=='' && count(explode(' ',$name))===1) {
            $this->addCheckLabelString($translationPrefix.self::nameNode,$type.self::nameNode,$parameters);
        }
        // validity of eMail
        $tempVal = $applicant[self::eMailNode];
        if ($tempVal!=='' && !filter_var($tempVal,FILTER_VALIDATE_EMAIL)) {
            $this->addCheckLabelString($translationPrefix.self::eMailNode,$type.self::eMailNode,$parameters);
        }
        // description of 'other' position
        if ($applicant[self::position]===self::positionOther) {
            $this->addCheckLabelString($translationPrefix.'positionOther',$type.self::position,$parameters,false);
        }
        // validity of phone
        $tempVal = $applicant[self::phoneNode];
        if ($tempVal!=='' && preg_match("/^\+?([0-9][\s\/-]?)+[0-9]+$/",$tempVal)===0) {
            $this->addCheckLabelString($translationPrefix.self::phoneNode,$type.self::phoneNode,$parameters);
        }
    }

    /** Checks for error of the compensation question on the burdens/risks page.
     * @param array $pageArray array containing the elements of the questions for the type
     * @param string $type type to be checked. Must equal 'burdens', 'risks', or 'burdensRisksContributors
     * @param array $params parameters for the translation
     * @return void
     */
    private function checkBurdensRisksCompensation(array $pageArray, string $type, array $params): void {
        $compensation = $pageArray[self::burdensRisksCompensationNode];
        $compensationString = self::projectdetailsPrefix.self::burdensRisksNode.'.compensation';
        $typePrefix = $type.'Compensation';
        $tempVal = $this->checkMissingChosen($compensation,$compensationString,2,$this->addDiv($typePrefix),true,parameters: array_merge($params,['isNoCompensation' => 'false']));
        if ($tempVal<2) { // check only if selection was made
            $isCompensation = $tempVal===0;
            $this->checkMissingContent($compensation,[self::descriptionNode => $compensationString],$isCompensation,parameter: array_merge($params,['isNoCompensation' => $this->getStringFromBool(!$isCompensation)]),hash: $this->addDiv($typePrefix,true,false));
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
        if ($this->checkMissingChildrenOther($pageArray, self::accessNode, $accessPrefix.'missing', array_combine($this->prefixArray(self::accessOthers, $purposeNameWoPrefix), $this->prefixArray(self::accessOthers, $accessPrefix)), $purposeParam,hash: self::accessNode.$purposeNameWoPrefix)) {
            $accessYes = ['accessExternal','dataService'];
            foreach ($pageArray[self::accessNode] as $accessKey => $accessQuestions) {
                if (is_array($accessQuestions)) { // sub-questions exist for this access type -> if a string, it may not be empty (description)
                    $accessWoPrefix = str_replace($purposeNameWoPrefix,'',$accessKey);
                    $typeParam = array_merge($purposeParam,['type' => $accessWoPrefix]);
                    // order processing
                    $tempArray = $accessQuestions[self::orderProcessingNode];
                    $chosen = $tempArray[self::chosen];
                    $tempPrefix = $privacyPrefix.self::orderProcessingNode.'.';
                    $tempVal = $accessKey.self::orderProcessingNode;
                    $this->checkMissingChosen($tempArray,$tempPrefix.'missing',2,$this->addDiv($tempVal),true,parameters: $typeParam);
                    if (array_key_exists(self::descriptionNode,$tempArray)) {
                        $this->checkMissingContent($tempArray,[self::descriptionNode => $tempPrefix.self::descriptionNode],true,parameter: $typeParam,hash: $this->addDiv($tempVal,true,false));
                    }
                    // order processing known
                    if ($chosen==='0') {
                        $this->checkMissingContent($accessQuestions,[self::orderProcessingKnownNode => $privacyPrefix.self::orderProcessingKnownNode],parameter: $typeParam,hash: $this->addDiv($accessKey.self::orderProcessingKnownNode));
                    }
                    elseif (in_array($accessWoPrefix,$accessYes) && $chosen==='1') { // if external, order processing must be answered with 'yes'
                        $this->addCheckLabelString($tempPrefix.'externalService',parameters: $typeParam);
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
     * @param string $hash id of element to be linked to
     * @param string $description translation key for a part of the error message if the \$descriptionKey element is empty
     * @param string $hashDescription id of element for description to be linked to. If empty, $hash with 'DescriptionDiv' appended will be used
     * @param bool $addDescription true if the description prefix should be prepended if the \$descriptionKey element is empty, false otherwise
     * @param string $descriptionKey key of the element to be checked if the value equals \$selected. Defaults to self::descriptionNode
     * @param array $parameters parameters for the translation keys
     * @return int|string the value of the 'chosen' element if it is a number or a string, otherwise \$maxVal if not null
     */
    private function checkMissingTextfield(array $element, ?int $maxVal, int|string $selected, string $chosenDescription, string $hash, string $description = '', string $hashDescription = '', bool $addDescription = false, string $descriptionKey = self::descriptionNode, array $parameters = []): int|string {
        $curSel = $this->checkMissingChosen($element,$chosenDescription,$maxVal,$hash,true,parameters: $parameters);
        if ($curSel===$selected) {
            $this->checkMissingContent($element,[$descriptionKey => $description],$addDescription,parameter: $parameters,hash: $hashDescription!=='' ? $hashDescription : $this->addDiv($hash,true,false));
        }
        return $curSel;
    }

    /** Checks if the 'chosen' key of \$element has content. If not, \$checkLabel is updated. If it has content, the element with the key \$description is checked for content.
     * @param array $element array containing the keys
     * @param string $chosenDescription translation key for a part of the error message if the 'chosen' element is empty
     * @param string $description translation key for a part of the error message if the $description element is empty
     * @param string $hash id of the element to be linked to
     * @param bool $addDescription true if the description prefix should be prepended, false otherwise
     * @param string $chosenKey if provided, key of \$element to be checked. Defaults to self::chosen
     * @param string $descriptionKey key of the element to be checked if \$element is not empty. Defaults to self::descriptionNode
     * @param string $hashDescription id of element for description to be linked to. If empty, $hash with 'DescriptionDiv' appended will be used
     * @param array $parameters parameters for the translation
     * @return string the value of the 'chosen' element
     */
    private function checkMissingTextfieldEmpty(array $element, string $chosenDescription, string $description, string $hash, bool $addDescription = true, string $chosenKey = self::chosen, string $descriptionKey = self::descriptionNode, string $hashDescription = '', array $parameters = []): string {
        $chosen = $this->checkMissingChosen($element,$chosenDescription,null,$hash,name: $chosenKey,parameters: $parameters);
        if ($chosen!=='') {
            $this->checkMissingContent($element,[$descriptionKey => $description],$addDescription,parameter: $parameters,hash: $hashDescription!=='' ? $hashDescription : $this->addDiv($hash,true,false));
        }
        return $chosen;
    }

    /** Calls checkMissingChildren. If it returns true and $other is not empty, it is checked if the key(s) exist and if so, if they have content.
     * @param array $element array which contains the elements to be checked
     * @param string $key key in $element to be checked
     * @param string $message translation key for a part in the error message
     * @param array $other if provided: keys: key in the children of the checked element to be checked for existence. value: translation key for the part of the error message
     * @param array $params parameters for the translations
     * @param bool $addDescription if true, the error message for a missing description if prefixed by 'description of'
     * @param string $hash id of element to be linked to for the checkMissingChildren() call. If empty, $key will be used
     * @return bool true if the element has children, false otherwise
     */
    private function checkMissingChildrenOther(array $element, string $key, string $message, array $other = [], array $params = [], bool $addDescription = true, string $hash  = ''): bool {
        $returnBool = $this->checkMissingChildren($element,$key,$message,$params,$hash);
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
     * @param string $hash id of element to be linked to. If empty, $key will be used
     * @return bool true if the element has children, false otherwise
     */
    private function checkMissingChildren(array $element, string $key, string $message, array $parameters = [], string $hash = ''): bool {
        if ($element[$key]==='') {
            $this->addCheckLabelString($this->translateString($message,$parameters).$this->translateString(self::missingPrefix.'multiple'),$hash!== '' ? $hash : $key,colorRed: false);
            return false;
        }
        return true;
    }

    /** Checks if a specific key of \$element is either empty, not a number, or not between zero (inclusively) and \$maxVal (exclusively). If so, an error message is added to \$checkLabel. If \$maxVal is null, then it is only checked if the key is empty.
     * @param array $element array containing the key to be checked
     * @param string $question translation key for a part in the error message
     * @param int|null $maxVal maximum value that the array element is checked against
     * @param string $hash id of element to be linked to
     * @param bool $addPrefix true if the "answer to" prefix should be added, false otherwise
     * @param string $name if provided, name of the key to be checked. Defaults to self::chosen
     * @param array $parameters if provided, parameters for the translation
     * @param string $lineTitle string that is added at the beginning of the error message. Must be translated. May only be used if \$addPrefix is false
     * @return int|string the value of the array element if it is a number in the range, \$maxVal otherwise. If \$maxVal is null, the value as a string
     */
    private function checkMissingChosen(array $element, string $question, ?int $maxVal, string $hash, bool $addPrefix = false, string $name = self::chosen, array $parameters = [], string $lineTitle = ''): int|string {
        $curVal = $element[$name] ?? '';
        $curValAsInt = (int)($curVal); // if $curVal is a string, 0 is returned
        $returnVal = $curValAsInt;
        $isString = $maxVal===null;
        if ($curVal==='' || !$isString && (preg_match("/\D/",$curVal) || $curValAsInt<0 || $curValAsInt>=$maxVal)) {
            $message = $this->translateString($question,$parameters);
            $this->addCheckLabelString($addPrefix ? $this->translateString('checkDoc.missingChosen',['question' => $message]) : $lineTitle.($lineTitle!=='' ? ': ' : '').$message.$this->translateString(self::missingSingle), hash: $hash, colorRed: false);
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
     * @param string $hash id of the element to be linked to. If empty, the first key of $inArray which is added to the error message will be used
     * @param bool $addHash if true, a link will be added
     * @return void
     */
    private function checkMissingContent(array $element, array $inArray, bool $addDescription = false, string $default = '', string $lineTitle = '', array $parameter = [], string $hash = '', bool $addHash = true): void {
        $missingString = '';
        $countMissing = 0;
        $firstMissing = '';
        foreach ($inArray as $key => $value) {
            $curElement = $element[$key];
            if ($curElement==='' || $curElement===$default) {
                $missingString .= ', '.($addDescription ? $this->translateString(self::missingPrefix.'description') : '').$this->translateString($value,$parameter);
                ++$countMissing;
                if ($firstMissing==='') {
                    $firstMissing = $key;
                }
            }
        }
        if ($countMissing>0) { // at least one element is empty
            $this->addCheckLabelString(($lineTitle!=='' ? ($this->translateString($lineTitle, $parameter).': ') : '').
                substr($missingString,2).
                $this->translateString(self::missingPrefix.($countMissing===1 ? 'single' : 'multiple')), $addHash ? ($hash!=='' ? $hash : $firstMissing) : '',colorRed: false);
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
        $this->linkedPage = $this->addPageHash ? $subPage : '';
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
                $this->linkedPage = $this->addPageHash ? $pageName : '';
                $this->curPage = $this->curSubPageHeading.$this->convertStringToLink($this->translateString('pages.projectdetails.'.$pageName), $pageName, $this->routeIDs[self::routeIDs]).':';
                $this->checkLabel .= $this->curPage."\n";
                $this->anyMissing = false;
            }
            elseif ($subPage===self::measureTimePointNode) {
                $isOneStudy = $this->isOne[self::studyNode];
                $studyName = $this->studyGroupName[self::studyNode];
                $groupName = $this->studyGroupName[self::groupNode];
                $isOneGroup = $this->isOne[self::groupNode];
                $headingPrefix = 'projectdetails.headings.';
                $this->curSubPageHeading = $this->translateString($headingPrefix.self::studyNode).($isOneStudy ? '' : ' '.$this->IDs[self::studyNode]).($studyName!=='' ? ' '.($isOneStudy ? '' : ' (')."\u{201E}".$studyName."\u{201D}".($isOneStudy ? '' : ')') : '').
                    ', '.$this->translateString($headingPrefix.self::groupNode).($isOneGroup ? '' : ' '.$this->IDs[self::groupNode]).($groupName!=='' ? ' '.($isOneGroup ? '' : ' (')."\u{201E}".$groupName."\u{201D}".($isOneGroup ? '' : ')') : '').
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
                }
                else {
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
            }
            else {
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
     * @param string $hash id of the element to be linked to. If empty, no link will be added
     * @param array $parameters parameters for the translation
     * @param bool $colorRed if true, a span with color style red will be added around the string. May only be used if the resulting string contains the entire message for this line. Additionally, $anyError will be set to true
     * @return void
     */
    private function addCheckLabelString(string $label, string $hash = '', array $parameters = [], bool $colorRed = true): void {
        $hasHash = $hash!=='';
        $labelTrans = ucfirst($this->translateString($label,$parameters));
        $label = ($colorRed ? '<span style="color: red">' : '').
            ($hasHash ? ($this->addPageHash ? $this->convertStringToLink($labelTrans,$this->linkedPage,$this->routeIDs[self::routeIDs] ?? '',$hash,true) : '<a href="'.$this->linkedPage.'#'.$hash.'" style="color: inherit">'.$labelTrans.'</a>') : $labelTrans).
            ($colorRed ? '</span>' : '');
        $this->checkLabel .= "<li>".ucfirst($label)."</li>";
        $this->anyMissing = true;
        $this->anyError = true;
    }
}