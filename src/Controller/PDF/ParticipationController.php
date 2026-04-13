<?php

namespace App\Controller\PDF;

use App\Abstract\PDFAbstract;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ParticipationController extends PDFAbstract
{
    use AppDataTrait;
    use ProjectdetailsTrait;

    private array $linkedSubHeadings = []; // pages where links of subheadings should go to
    // $content: array passed to the template. Keys: headings, values: array with two elements: First element: array of sub-paragraphs. Each of these arrays consists of two elements: sub-heading and content of the sub-paragraph. Second element: boolean if the content of each sub-paragraph should be on the same page. If false, the sub-heading and the first line will be on the same page.
    private string $linkedSubHeadingsString = 'linkedSubHeadings';
    private array $content;
    private const isPersonal = 'isPersonal'; // select parameter in translations
    private const intermediateDocument = 'PDF/_intermediateDocument.html.twig';

    public function createPDF(Request $request, array $routeIDs = [], bool $markInput = false): Response
    {
        $session = $request->getSession();
        $reviewProcess = $session->get(self::reviewProcess);
        $isShortService = $reviewProcess===self::reviewShortService;
        $isDocs = in_array($reviewProcess,self::reviewDocs);
        $hasDocs = $isDocs && !($isShortService && $markInput);
        $isBegun = $this->getBegunDocs($reviewProcess,$session);
        $isRequested = in_array($reviewProcess,[self::reviewShortRequested,self::reviewFullRequested]);
        self::$markInput = !self::$savePDF || self::$isCompleteForm && $markInput;
        $singleDocsHint = $this->getSingleDocsHint($request,'participation',$isDocs && !$isShortService);
        $markedSuffix = self::$markInput ? 'Marked' : ''; // suffix for pdf
        $committeeParam = $session->get(self::committeeParams);
        $isShort = str_contains($reviewProcess,self::reviewProcessShort);
        $isShortChoose = $isShort && in_array($committeeParam[self::committeeType],self::reviewShortChoose); // true if review process is any short and creation of participation documents can be chosen for short review processes
        $isFullParam = ['isFull' => $this->getStringFromBool(!$isShort)];
        $savePDFParam = ['savePDF' => self::$savePDF, 'isComplete' => self::$isCompleteForm];
        $appNode = $this->getXMLfromSession($session,getRecent: true); // if supervisor was added while on core data page, indices of contributors have changed
        $coreDataArray = $this->xmlToArray($appNode->{self::appDataNodeName})[self::coreDataNode];
        $contributors = $this->getContributors($session);
        $isMultiple = $this->getMultiStudyGroupMeasure($appNode); // true if multiple studies, groups, or measure time points exist
        $studyArray = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
        $multipleStudies = count($studyArray)>1; // true if multiple studies exist
        $levelNames = ['study' => ['isMultiple' => $multipleStudies]];
        $numStudiesParam = ['numStudies' => count($this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode])), 'curDate' => $this->getCurrentDate()->format($this->translateString('dateFormat',['noTime' => 'true']))];
        // get indices of variants for which documents should be created
        // keys: studies, values: array. In this array: keys: group IDs, value: array of measure time points IDs.
        // Example: [1 ->
        //              [0 ->
        //                  [0,1]
        //               1 ->
        //                  [0]
        //              ]
        //          ]
        // => three participation documents are created for study 1: for group 0 two documents (measure time points 0 and 1), for group 1 one document (measure time point 0).
        $allIDs = [];
        $isRouteIDs = $routeIDs!==[];
        $numIndices = $isRouteIDs ? count($routeIDs) : 0;
        $isNotOnlyStudy = $numIndices>1;
        $isMeasure = $numIndices===3;
        $groupID = $isNotOnlyStudy ? $routeIDs[1]-1 : [];
        foreach ($isRouteIDs ? [$routeIDs[0]-1] : range(0,count($studyArray)-1) as $studyID) {
            $groupArray = $this->addZeroIndex($studyArray[$studyID][self::groupNode]);
            $groupIDs = [];
            foreach ($isNotOnlyStudy ? [$groupID] : range(0,count($groupArray)-1) as $curGroupID) {
                $groupIDs[$curGroupID] = $isMeasure ? [$routeIDs[2]-1] : range(0,count($this->addZeroIndex($groupArray[$curGroupID][self::measureTimePointNode]))-1);
            }
            $allIDs[$studyID] = $groupIDs;
        }
        // create documents
        $html = [];
        $allHtml = '';
        $customPDFs = [];
        $personal = '';
        $savePDFstringParam = ['savePDF' => $this->getStringFromBool(self::$savePDF)];
        foreach ($allIDs as $studyID => $groupIDs) {
            $study = $studyArray[$studyID];
            $studyIDincreased = $studyID+1;
            foreach ($groupIDs as $groupID => $measureIDs) {
                $groupIDincreased = $groupID+1;
                foreach ($measureIDs as $measureID) {
                    $measureIDincreased = $measureID+1;
                    $this->content = [];
                    $levelNames['study']['id'] = $multipleStudies ? $studyIDincreased : 0;
                    $levelNames['study'][self::nameNode] = $study[self::nameNode];
                    $curRouteIDs = $this->createRouteIDs([self::studyNode => $studyIDincreased, self::groupNode => $groupIDincreased, self::measureTimePointNode => $measureIDincreased]);
                    $groupArray = $this->addZeroIndex($study[self::groupNode]); // all groups of the current study
                    $multipleGroups = count($groupArray)>1;
                    $group = $groupArray[$groupID];
                    $levelNames['group'] = ['isMultiple' => $multipleGroups, 'id' => $multipleGroups ? $groupIDincreased : 0, self::nameNode => $group[self::nameNode]];
                    $measureTimePointArray = $this->addZeroIndex($group[self::measureTimePointNode]);
                    $multipleMeasures = count($measureTimePointArray)>1;
                    $levelNames['measureTimePoint'] = ['isMultiple' => $multipleMeasures, self::nameNode => ''];
                    $measureTimePoint = $measureTimePointArray[$measureID];
                    $levelNames['measureTimePoint']['id'] = $multipleMeasures ? $measureIDincreased : 0;
                    $boxHeadingPrefix = 'boxHeadings.projectdetails.';
                    $customPrefix = 'projectdetails.custom.';
                    // create an array containing the number of levels and the level names -> needed if no information is given or no/other consent is given
                    $levelArray = [];
                    $numLevels = 0;
                    foreach ($levelNames as $level => $info) {
                        if ($info['isMultiple']) { // multiple elements exist
                            $name = $info[self::nameNode];
                            ++$numLevels;
                            $levelArray[] = $this->translateStringPDF($boxHeadingPrefix.$level).' '.$info['id'].($level!==self::measureTimePointNode ? ($name!=='' ? ' ('.$name.')' : '') : '');
                        }
                    }
                    $groupsArray = $measureTimePoint[self::groupsNode];
                    $measuresArray = $measureTimePoint[self::measuresNode];
                    $addressee = $this->getAddressee($groupsArray);
                    $addresseeParam = [self::addressee => $addressee];
                    $informationArray = $measureTimePoint[self::informationNode];
                    $information = $this->getInformationString($informationArray);
                    $informationParam = [self::informationNode => $information];
                    $translationParams = array_merge($addresseeParam,$committeeParam,$informationParam,[
                        self::createNode => '', // how the data privacy document should be created
                        'personal' => '',
                        'levelNames' => implode(', ',$levelArray),
                        'numLevels' => $numLevels,
                        self::routeIDs => $curRouteIDs]); // contains all parameters that are needed in several combinations
                    $isPre = $information===self::pre;
                    $translationParams['levelNamesString'] = $this->translateStringPDF($customPrefix.'levelNames',$translationParams);
                    $isNotPost = $information!==self::post;
                    $preType = $informationArray[self::preType] ?? '';
                    $postArray = $informationArray[self::post] ?? [];
                    $preCompleteArray = $informationArray[self::preComplete] ?? [];
                    $isOral = (!$isNotPost ? ($postArray[self::chosen] ?? '') : $preType)===self::consentOral;
                    $isLoanReceipt = false; // loan receipt is only possible if pre information
                    [$loanReceiptParameters,$consent] = [[],[]]; // $loanReceiptParameters: parameters for the view of the loan receipt
                    $parameters = array_merge($translationParams,[self::studyID => $multipleStudies ? $studyIDincreased : 0, self::groupID => $multipleGroups ? $groupIDincreased : 0, self::measureID => $multipleMeasures ? $measureID : 0, 'singleDocsHint' => $singleDocsHint]);
                    $projectdetailsPrefix = 'projectdetails.pages.';
                    $participationPrefix = 'participation.';
                    $privacyPrefix = self::privacyNode.'.';
                    $consentPrefix = 'consent.';
                    $consentType = ''; // type of consent
                    $noConsentParams = []; // contains consent type, description, and whether review process is full
                    $isConsent = false; // gets true if any consent is given
                    $isToolPersonal = false; // gets true if document should be created by the tool and personal data are collected
                    $hasCustomPDF = array_fill_keys(self::customPDForder,false); // each element gets true if a custom pdf will be added
                    $hasCustomPDF['begun'] = $isBegun;
                    $hasCustomPDF[self::informationNode] = array_key_exists(self::documentTranslationPDF,$informationArray);
                    $informationIIArray = $measureTimePoint[self::informationIINode];
                    $informationII = '';
                    $hasInformationII = $informationIIArray!=='';
                    if ($hasInformationII) {
                        $informationII = $this->getInformationString($informationIIArray);
                        $hasCustomPDF[self::informationIINode] = !$isRequested && in_array($informationII,self::prePostArray);
                    }
                    $hasCustomPDF[self::measuresNode] = array_key_exists(self::measuresPDF,$measuresArray);
                    $hasCustomPDF[self::interventionsNode] = array_key_exists(self::interventionsPDF,$measuresArray);
                    [$linkedString, $subHeadingsString,$parametersString,$subColonString] = ['linked','subHeadings','parameters','subColon'];
                    // create paragraphs which are added even if no information is given
                    // criteria
                    if (array_key_exists(self::criteriaIncludeNode,$groupsArray)) { // either both include and exclude or neither exist
                        $content = [];
                        $tempPrefix = $participationPrefix.self::criteriaNode.'.';
                        foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
                            $tempArray = $groupsArray[$type][self::criteriaNode];
                            if ($tempArray!=='') { // at least one criterion
                                $isInclude = $type===self::criteriaIncludeNode;
                                $criteria = $isInclude ? '• '.$tempArray['include0']."\n" : '';
                                if ($isInclude) {
                                    if (($measureTimePoint[self::burdensRisksNode][self::findingNode][self::informingNode] ?? '')===self::informingAlways) {
                                        $criteria .= "• ".$this->translateStringPDF($tempPrefix.'finding')."\n";
                                    }
                                    $tempArray = array_slice($tempArray, 1);
                                }
                                $content[] = $criteria.($tempArray!==[] ? "• ".$this->addMarkInput(implode("\n• ",$tempArray),self::$markInput) : '');
                            }
                        }
                        $paragraphsAll = [self::criteriaNode => [$linkedString => self::groupsNode, self::content => $content, $subHeadingsString => array_merge([$tempPrefix.self::criteriaIncludeNode],count($content)===2 ? [$tempPrefix.self::criteriaExcludeNode] : []),$parametersString => $translationParams, $subColonString => false]];
                    }
                    // location
                    $tempArray = [];
                    $location = '';
                    $linkedSubHeadings = [];
                    $subHeadings = [];
                    if (array_key_exists(self::locationNode,$measuresArray)) {
                        $locationArray = $measuresArray[self::locationNode];
                        $location = $locationArray[self::chosen];
                        $translationPrefix = $participationPrefix.'locationContent.';
                        $tempArray = [$location!=='' ? $this->translateStringPDF($translationPrefix.'start',array_merge($translationParams,[self::locationNode => $location])).$this->addMarkInput($locationArray[self::descriptionNode],self::$markInput).($location===self::locationOnline ? $this->translateStringPDF($translationPrefix.'end') : '') : ''];
                        $linkedSubHeadings = [self::measuresNode];
                        $subHeadings = [self::locationNode];
                    }
                    // procedure
                    if (array_key_exists(self::procedureNode,$measuresArray)) {
                        $tempArray[] = $this->addMarkInput($measuresArray[self::procedureNode],self::$markInput);
                        $linkedSubHeadings[] = self::measuresNode;
                        $subHeadings[] = self::procedureNode;
                    }
                    // duration
                    $durationsArray = $measuresArray[self::durationNode];
                    $tempArray[] = $this->getDuration($durationsArray, false, $translationParams, self::$markInput, $multipleMeasures);
                    $linkedSubHeadings[] = self::measuresNode;
                    $subHeadings[] = self::durationNode;
                    $paragraphsAll[self::procedureNode] = [self::content => $tempArray,$this->linkedSubHeadingsString => $linkedSubHeadings, $subHeadingsString => $this->prefixArray($subHeadings,$participationPrefix)];
                    // terminate participants
                    $consentArray = $measureTimePoint[self::consentNode];
                    $terminateParticipants = '';
                    $terminateParticipantsDescription = '';
                    if (array_key_exists(self::terminateParticipantsNode,$consentArray)) {
                        $terminateParticipantsArray = $consentArray[self::terminateParticipantsNode];
                        $terminateParticipants = $terminateParticipantsArray[self::chosen];
                        $terminateParticipantsDescription = $terminateParticipantsArray[self::descriptionNode] ?? '';
                        $paragraphsAll[self::terminateParticipantsNode] = [$linkedString => self::consentNode,self::content => $terminateParticipantsDescription!=='' ? $terminateParticipantsDescription : ($terminateParticipants!=='' ? $this->translateString($projectdetailsPrefix.self::consentNode.'.'.self::terminateParticipantsNode.'.types.'.$terminateParticipants,$informationParam) : '')];
                    }
                    // compensation
                    $isNotShortServiceSingleDocs = !($isShortService && self::$isCompleteForm && self::$savePDF && self::$markInput); // gets false if review process is 'shortService' and single documents are created
                    $compensationArray = $measureTimePoint[self::compensationNode];
                    $compensationTerminateArray = $compensationArray[self::terminateNode] ?? [];
                    $isCompensationTerminate = $compensationTerminateArray!==[];
                    $compensationTerminateChosen = $compensationTerminateArray[self::chosen] ?? '';
                    $compensationTerminateDescription = $this->addMarkInput($compensationTerminateArray[self::descriptionNode] ?? '',self::$markInput);
                    $compensationTypes = $compensationArray['type'];
                    $content = '';
                    $numCompensation = 0;
                    $compensationPrefix = $projectdetailsPrefix.self::compensationNode.'.';
                    $compensationPrefixPDF = $participationPrefix.self::compensationNode.'.';
                    $voluntaryArray = $consentArray[self::voluntaryNode];
                    $chosen2 = $voluntaryArray[self::chosen2Node] ?? '';
                    $terminateConsParam = [self::terminateConsNode => $this->getStringFromBool($consentArray[self::terminateConsNode][self::chosen]!=='1')];
                    $voluntaryParams = array_merge($addresseeParam,$terminateConsParam,['isVoluntary' => $this->getStringFromBool(in_array((!array_key_exists(self::chosen2Node,$voluntaryArray) || $chosen2===self::voluntaryNotApplicable) ? $voluntaryArray[self::chosen] : $chosen2,['','yes']))]);
                    $compensationTerminateParams = array_merge($voluntaryParams,[self::compensationNode => $compensationTerminateChosen, 'isDuration' => $this->getStringFromBool($this->getDuration($durationsArray)>30)]);
                    $compensationTerminateTrans = $compensationPrefixPDF.self::terminateNode;
                    if ($compensationTypes!=='') { // at least one compensation type was chosen
                        $laterPrefix = $compensationPrefixPDF.'laterEnd.';
                        $isMoney = false;
                        $moneyFurther = '';
                        $isHours = false;
                        $numCompensation = count($compensationTypes);
                        $multipleCompensation  = $numCompensation>1;
                        $lastOr = $multipleCompensation ? $this->translateStringPDF('or') : '';
                        $isGerman = $session->get(self::language)==='de';
                        $awardingString = '';
                        $namelyString = $this->translateStringPDF($compensationPrefixPDF.'namely');
                        $compensationTypesKeys = array_keys($compensationTypes);
                        $hasDescription = array_key_exists($compensationTypesKeys[0].self::descriptionCap,$compensationArray) && $isNotShortServiceSingleDocs;
                        $hasDescriptionParam = ['hasDescription' => $this->getStringFromBool($hasDescription)];
                        foreach ($compensationTypesKeys as $index => $type) {
                            $description = $compensationArray[$type.self::descriptionCap] ?? [];
                            $isMoneyCompensation = $type===self::compensationMoney;
                            $isHoursCompensation = $type===self::compensationHours;
                            $isReal = ($description[self::moneyHourAdditionalNode] ?? '')==='real';
                            $isMoney = $isMoney || $isMoneyCompensation && $isReal;
                            $isHours = $isHours || $isHoursCompensation && $isReal;
                            $value = $description[self::descriptionNode] ?? '';
                            if ($isMoneyCompensation && array_key_exists(self::moneyFurther,$compensationArray) && $isNotShortServiceSingleDocs) {
                                $tempArray = $compensationArray[self::moneyFurther];
                                if (array_key_exists(self::descriptionNode,$tempArray)) {
                                    $moneyFurther = $this->translateString($compensationPrefix.self::compensationMoney.'.textHint').$this->addMarkInput($tempArray[self::descriptionNode],self::$markInput);
                                }
                                if ($value!=='') {
                                    $value = number_format((float) $value,2,$isGerman ? ',' : '.');
                                }
                            }
                            $hoursValue = $description[self::hourAdditionalNode2] ?? '';
                            if ($hoursValue!=='') {
                                $hoursValue = str_replace($isGerman ? '.' : ',', $isGerman ? ',' : '.',$hoursValue);
                            }
                            $tempPrefix = $compensationPrefixPDF.'types.'.$type.'.';
                            $tempParams = array_merge($hasDescriptionParam,['type' => $type,self::descriptionNode => $value, self::moneyHourAdditionalNode => $description[self::moneyHourAdditionalNode] ?? '', 'hoursValue' => $hoursValue, 'amount' => $hoursValue!='' ? (int)($hoursValue) : 0]);
                            $content .= ($index===($numCompensation - 1) ? $lastOr : ',').' '.$this->translateStringPDF($tempPrefix.'start', $tempParams);
                            if ($hasDescription) {
                                $content .= $this->translateStringPDF($compensationPrefixPDF.'details',$tempParams).(!$isMoneyCompensation ? ($isHoursCompensation ? ' ' : '').$this->addMarkInput($value, self::$markInput) : '').$this->translateStringPDF($tempPrefix.'end');
                            }
                            // awarding
                            $awardingKey = $type.self::awardingNode;
                            if ($type!==self::compensationNo && array_key_exists($awardingKey,$compensationArray) && $isNotShortServiceSingleDocs) {
                                $tempPrefix = $compensationPrefix.self::awardingNode.'.'.$type.'.';
                                $awardingArray = $compensationArray[$awardingKey];
                                if ($type===self::compensationLottery) { // announcement
                                    $lotteryPrefix = $tempPrefix.'result.';
                                    $lotteryStart = $awardingArray[self::lotteryStart];
                                    $awardingString .= ' '.$this->translateString($lotteryPrefix.'start').$this->addMarkInput($awardingArray[self::lotteryStart.self::descriptionCap],self::$markInput).' '.(!in_array($lotteryStart,['',self::lotteryResultOther]) ? $this->translateString($lotteryPrefix.'types.'.$lotteryStart) : $this->addMarkInput($awardingArray[self::lotteryStartOtherDescription] ?? '',self::$markInput)).$this->translateString($lotteryPrefix.'end');
                                }
                                $chosen = $awardingArray[self::chosen];
                                $isLater = $chosen===self::awardingLater;
                                $awardingString .= ' '.($type!==self::compensationOther ? $this->translateString($tempPrefix.'title').(!in_array($chosen,['','other']) ? $this->translateString($tempPrefix.$chosen) : '') : $this->addMarkInput($chosen,self::$markInput));
                                $description = $awardingArray[self::descriptionNode] ?? '';
                                if ($isLater || $chosen==='external') {
                                    $awardingString .= $namelyString;
                                }
                                if ($description!=='') { // a description or further choice is needed and was made
                                    $awardingString .= $chosen===self::awardingDeliver ? $this->translateString($tempPrefix.'deliverTypes.'.$description) : $this->addMarkInput($description,self::$markInput);
                                }
                                if ($chosen===self::awardingLater) { // information for later
                                    $laterChosen = $awardingArray[self::laterTypesName];
                                    $awardingString .= ' '.$this->translateStringPDF($laterPrefix.'title').(array_key_exists(self::laterOtherDescription,$awardingArray) ? $this->addMarkInput($awardingArray[self::laterOtherDescription],self::$markInput) : ($laterChosen!=='' ? $this->translateStringPDF($laterPrefix.$laterChosen,$addresseeParam) : ''));
                                }
                            }
                        }
                        $content = substr($content,$multipleCompensation ? 2 : 0).'. '.($isNotShortServiceSingleDocs ? $this->translateStringPDF($compensationPrefixPDF.self::moneyFurther,['isMoneyHours' => $this->getStringFromBool($isMoney || $isHours), 'isMoney' => $this->getStringFromBool($isMoney), 'isHours' => $this->getStringFromBool($isHours)]).$moneyFurther."\n".$awardingString : '');
                    }
                    $paragraphsAll[self::compensationNode] = [$linkedString => self::compensationNode,self::content => $this->translateStringPDF($compensationPrefixPDF.'start',array_merge($translationParams,['number' => $numCompensation])).trim($content),$subHeadingsString => [], $parametersString => [], $subColonString => true];
                    $isSeparateLater = false; // gets true if data privacy create is 'separate later'
                    $isInformation = in_array($information,self::prePostArray);
                    $noInformationPrefix = $participationPrefix.'noInformation.';
                    $isMissingInformation = false; // gets true if either pre or post information was not answered yet
                    if ($hasDocs && $isInformation) {
                        // contributors
                        $pageArray = $measureTimePoint[self::contributorNode];
                        $tempArray = $pageArray[self::taskLeader];
                        $leaderIndices = [];
                        $contributorsLeaderInstitution = []; // needed for intro in study information. Contains leader with institution. Each element contains name and institution, separated by a comma
                        $contributorsLeader = []; // needed if deceit with complete post information. Each element is a string containing name, e-mail and eventually phone number, separated by a comma
                        $contributorsFurther = []; // needed if deceit with complete post information. One element for each contributor with task 'contact' and without task 'leader'. Each element contains name and eventually contact information equally to $contributorsLeader
                        $contributorsFurtherTasks = []; // needed in study information. One element for each contributor with at least one task except 'leader'. Each element contains only name and eventually tasks
                        $contributorsContact = []; // needed in study information and consent. One element for each contributor with task 'contact'. Each element is a string consisting of name, e-Mail and eventually phone number
                        if ($tempArray!=='') { // at least one contributor is leader
                            $leaderIndices = explode(',',$tempArray); // indices of contributors who are leader
                            foreach ($leaderIndices as $index) {
                                $curInfos = $contributors[$index][self::infosNode];
                                $contributorsLeader[] = $this->addContributorInfo($curInfos); // in complete post information without institution
                                $contributorsLeaderInstitution[] = $this->addContributorInfo($curInfos,false,true);
                            }
                        }
                        $numContributors = count($contributors);
                        $contributorTasks = array_fill(0,$numContributors,[]); // array of translated tasks the respective contributor has
                        $contributorsData = ''; // needed for data privacy. One element for each contributor with task 'data'. Each element is a string containing name and eMail.
                        $isContact = array_fill(0,$numContributors,false); // for each contributor, gets true if contributor has task 'contact'
                        $contributorsDataContact = []; // needed in data privacy. Contains all contributors with task data. Each element contains name, eMail and phone, prefixed by the translated type and separated by a newline
                        $tempPrefix = $privacyPrefix.'contact.infos.';
                        $nameTrans = $this->translateStringPDF($tempPrefix.self::nameNode);
                        $mailTrans = $this->translateStringPDF($tempPrefix.self::eMailNode);
                        $phoneTrans = $this->translateStringPDF($tempPrefix.self::phoneNode);
                        foreach (array_diff(self::tasksNodes,[self::taskLeader]) as $task) {
                            $taskTranslated = $this->translateString('contributors.tasks.'.$task);
                            $tempArray = $pageArray[$task];
                            if ($tempArray!=='') { // at least one contributor has the current task
                                $isCurContact = $task==='contact';
                                foreach (explode(',', $tempArray) as $curIndex) {
                                    $curContributor = $contributors[$curIndex];
                                    $curInfos = $curContributor[self::infosNode];
                                    $phone = $curInfos[self::phoneNode] ?? '';
                                    $eMail = $curInfos[self::eMailNode];
                                    $hasPhone = $phone!=='';
                                    $curName = $curInfos[self::nameNode];
                                    $isContact[$curIndex] = $isContact[$curIndex] || $isCurContact;
                                    if (!$isCurContact) { // add translated task only if it is neither 'contact' nor 'data'
                                        $contributorTasks[$curIndex][] = $task!==self::otherTask ? $taskTranslated : $this->addMarkInput($curContributor[self::taskNode][self::otherTask],self::$markInput);
                                    }
                                    if ($task===self::taskData) {
                                        $contributorsData .= self::dummyString.$this->addMarkInput($curName.' ('.$eMail.($hasPhone ? ', '.$phone : '').')',self::$markInput);
                                        $contributorsDataContact[] = $nameTrans.$this->addMarkInput($curName,self::$markInput)."\n".$mailTrans.$this->addMarkInput($eMail,self::$markInput).($hasPhone ? "\n".$phoneTrans.$this->addMarkInput($phone,self::$markInput) : '');
                                    }
                                }
                            }
                        }
                        if (strlen($contributorsData)>0) {
                            $contributorsData = substr($contributorsData,strlen(self::dummyString)); // remove 'dummyString' at the beginning
                        }
                        $taskDataTrans = $this->translateString('contributors.tasks.'.self::taskData);
                        foreach ($contributorTasks as $index => $tasks) {
                            $isCurContact = $isContact[$index];
                            if (in_array($taskDataTrans,$tasks)) { // do not name task 'data'
                                unset($tasks[array_search($taskDataTrans,$tasks)]);
                            }
                            $contInfos = $contributors[$index][self::infosNode];
                            $curInfos = $this->addContributorInfo($contInfos);
                            if (!in_array($index,$leaderIndices)) { // contributor has further tasks, but not leader, in current variant
                                $curTasks = ' ('.implode(', ',$tasks).')';
                                $contributorsFurtherTasks[] = $this->addMarkInput($contInfos[self::nameNode],self::$markInput).($tasks!==[] ? $curTasks : '');
                                if ($isCurContact) {
                                    $contributorsFurther[] = $curInfos;
                                }
                            }
                            if ($isCurContact) {
                                $contributorsContact[] = $curInfos;
                            }
                        }
                        // if multiple elements exist, link to contributor page on projectdetails, otherwise to contributors page
                        self::$routeIDs = $isMultiple ? $curRouteIDs : '';
                        self::$linkedPage = $isMultiple ? self::contributorNode : 'contributors';
                        self::$isPageLink = true;
                        $this->linkedSubHeadings = [];

                        // intro
                        $textsPrefix = $projectdetailsPrefix.self::textsNode.'.';
                        $tempPrefix = $participationPrefix.self::introNode.'.';
                        [$projectTitle,$isDifferent] = $this->getProjectTitleParticipants($session,true);
                        $projectTitle = $this->addMarkInput($projectTitle,self::$markInput);
                        $projectTitleParam = [self::projectTitle => [self::projectTitle => $projectTitle, 'isDifferent' => $this->getStringFromBool($isDifferent)]];
                        $translationParams = array_merge($translationParams,$projectTitleParam[self::projectTitle]);
                        $textsArray = $measureTimePoint[self::textsNode];
                        $tempArray = $textsArray[self::introNode];
                        $intro = [$this->mergeContent(array_key_exists(self::descriptionNode,$tempArray) ? ['',$tempArray[self::descriptionNode]] : $this->translateString($textsPrefix.self::introNode.'.template',$translationParams))];
                        $leaderPrefix = $tempPrefix.'leader.';
                        $leaderHeading = $this->addHeadingLink($leaderPrefix.'title',fragment: $isMultiple ? 'leader' : ''); // set here to avoid routeIDs if only one variant
                        $intro[] = $this->translateStringPDF($leaderPrefix.'text').$this->replaceDummyString($contributorsLeaderInstitution,'; ').'.';
                        if ($contributorsFurtherTasks!==[]) {
                            $intro[] = $this->translateStringPDF($tempPrefix.'contributors',['contributors' => $this->replaceDummyString($contributorsFurtherTasks)]);
                        }
                        $tempArray = $coreDataArray[self::funding];
                        $tempVal = '';
                        if ($tempArray!=='') {
                            $isQualiFunding = array_key_exists(self::fundingQuali,$tempArray);
                            $funding = [];
                            if (!$isQualiFunding) {
                                $fundingPrefix = self::coreDataNode.'.'.self::funding.'.';
                                foreach ($tempArray as $type => $source) {
                                    $funding[] = $this->mergeContent([$this->translateString($fundingPrefix.$type).' (',$source[self::descriptionNode],')']);
                                }
                            }
                            $tempVal = $this->translateStringPDF($tempPrefix.self::funding,['isQualiFunding' => $this->getStringFromBool($isQualiFunding), self::funding => $this->replaceDummyString($funding)]);
                        }
                        $intro[] = $tempVal;

                        // background and goals
                        self::$routeIDs = $curRouteIDs;
                        self::$linkedPage = self::textsNode;
                        $this->addParagraph(self::goalsNode,$this->addMarkInput($textsArray[self::goalsNode] ?? '',self::$markInput));

                        // conflict
                        if (array_key_exists(self::conflictTextNode,$textsArray)) {
                            self::$linkedPage = self::textsNode;
                            $this->addParagraph(self::conflictNode,$this->addMarkInput($textsArray[self::conflictTextNode],self::$markInput));
                        }

                        // procedure
                        self::$linkedPage = self::textsNode;
                        $tempArray = $paragraphsAll[self::procedureNode];
                        $this->linkedSubHeadings = $tempArray[$this->linkedSubHeadingsString];
                        $this->addParagraph(self::procedureNode,$tempArray[self::content],$tempArray[$subHeadingsString]);

                        // finding
                        self::$linkedPage = self::textsNode;
                        $this->linkedSubHeadings = [];
                        $findingArray = $measureTimePoint[self::burdensRisksNode][self::findingNode];
                        $findingConsent = '';
                        $participationConsentPrefix = self::consentNode.'.';
                        if ($textsArray!=='' && array_key_exists(self::findingTextNode,$textsArray) && $findingArray[self::chosen]==='0') {
                            $tempArray = $textsArray[self::findingTextNode];
                            $this->addParagraph(self::findingNode, $tempArray[self::findingTemplate]==='1' ? $this->translateString($textsPrefix.self::findingTextNode.'.template',[self::consentNode => $this->getStringFromBool($findingArray[self::informingNode]===self::informingConsent), self::addressee => $addressee]) : $this->addMarkInput($tempArray[self::descriptionNode],self::$markInput));
                            // finding consent
                            if ($findingArray[self::informingNode]===self::informingConsent) {
                                $findingConsent = $this->translateStringPDF($participationConsentPrefix.self::findingNode);
                            }
                        }

                        // get texts for paragraphs compensation, pro/con, criteria and voluntary. $paragraphs: Keys: key used for addParagraph() call. Values: 0: further arguments for the addParagraph() call
                        // compensation
                        $paragraphs = [self::compensationNode => $paragraphsAll[self::compensationNode]];

                        // pro, con
                        $translationPrefix = $participationPrefix.'proCon.';
                        $tempArray = $textsArray[self::proNode] ?? '';
                        $tempVal = $tempArray[self::descriptionNode] ?? '';
                        $tempPrefix = $textsPrefix.'pro.template.';
                        $content = [$this->mergeContent(($tempArray[self::proTemplate] ?? '')==='1' ? [$this->translateString($tempPrefix.'start',$translationParams).' ',$tempArray[self::proTemplateText],' '.$this->translateString($tempPrefix.'middle').' ',$tempVal,"\n".$this->translateString($tempPrefix.'end',$addresseeParam)] : ['',$tempVal])];
                        $content[] = trim($this->getConTemplateText($measureTimePoint,markInput: self::$markInput));
                        $paragraphs[self::proNode] = [$linkedString => self::textsNode, self::content => $content, $subHeadingsString => [$translationPrefix.self::proNode,$translationPrefix.self::conNode],$parametersString => ['isBurdensRisks' => $this->getStringFromBool($this->getBurdensRisks($measureTimePoint[self::burdensRisksNode]))], $subColonString => true];

                        // criteria
                        $paragraphs[self::criteriaNode] = $paragraphsAll[self::criteriaNode];

                        // voluntary
                        $tempArray = $consentArray[self::consentNode];
                        $consentType = $tempArray[self::chosen];
                        $noConsentParams = [self::consent => $consentType, self::descriptionNode => $tempArray[self::descriptionNode] ?? ($tempArray[self::otherDescription] ?? '')];
                        $isConsent = in_array($consentType,self::consentTypesAny);
                        $tempPrefix = $participationPrefix.self::voluntaryNode.'.';
                        $tempVal = $isCompensationTerminate ? ' '.$this->translateStringPDF($compensationTerminateTrans,array_merge($compensationTerminateParams,['isDocs' => 'true'])).$compensationTerminateDescription : ''; // compensation terminate
                        $tempParams = array_merge($voluntaryParams, [
                            self::descriptionNode => $this->addMarkInput(in_array($information, ['noPre', self::post]) ? $informationArray[self::preText] : $consentArray[self::terminateConsParticipationNode] ?? '', self::$markInput),
                            self::attendanceNode => $this->getStringFromBool(($informationArray[self::attendanceNode] ?? '')==='0'),
                            self::informationIINode => $informationII,
                            'isConsent' => $this->getStringFromBool(in_array($consentType, self::consentTypesAll))]);
                        $content = trim($this->translateStringPDF($tempPrefix.(!$isNotPost ? self::post : self::pre), $tempParams))
                            .($isPre && in_array(self::preAbortButton,[$preCompleteArray[self::preAbort][self::chosen] ?? '',$hasInformationII ? $informationIIArray[self::preComplete][self::preAbort][self::chosen] ?? '' : '']) ? $this->translateStringPDF($tempPrefix.self::preAbort,$tempParams) : '')
                            .($isNotPost ? $tempVal : '')
                            .' '.$this->translateStringPDF($tempPrefix.self::terminateParticipantsNode, array_merge($informationParam, ['type' => $terminateParticipants, self::descriptionNode => $this->addMarkInput($terminateParticipantsDescription, self::$markInput)]))
                            .(!$isNotPost ? $tempVal : '');
                        $paragraphs[self::voluntaryNode] = [$linkedString => self::consentNode,self::content => $content,$subHeadingsString => [],$parametersString => [], $subColonString => true];

                        // privacy
                        $privacyArray = $measureTimePoint[self::privacyNode];
                        $createArray = $privacyArray[self::createNode];
                        $privacyCreate = $createArray[self::chosen];
                        $translationParams[self::createNode] = $privacyCreate;
                        $translationParams[self::addOwnNode] = $this->getStringFromBool(($privacyArray[self::addOwnNode] ?? '')==='0'); // only relevant if complete pdf is created
                        $translationParams[self::createVerificationNode] = $privacyArray[self::createVerificationNode] ?? ''; // only relevant if complete pdf is created
                        $isSeparate = $privacyCreate===self::createSeparate;
                        $isSeparateLater = $privacyCreate===self::createSeparateLater;
                        $customPrivacy = $isSeparate; // (gets) true if a custom privacy document needs to be added
                        $dataPersonal = '';
                        $isDataPersonal = false; // gets true if research data is/may be personal
                        $dataResearch = $privacyArray[self::dataResearchNode] ?? ''; // contains the selected research data
                        $isDataResearch = array_key_exists(self::dataResearchNode,$privacyArray); // research data is/may be personal or a personal marking is used
                        $hasDataResearch = $dataResearch!==''; // true if at least one data type is selected
                        $isDataSpecial = $isDataResearch && $hasDataResearch && array_intersect(array_keys($dataResearch),self::dataSpecialTypes)!==[]; // true if any data special is collected for research
                        $dataOnlineArray = $privacyArray[self::dataOnlineNode] ?? [];
                        $isMarkingName = false; // gets true is any marking is by name
                        $isMarkingPersonal = false; // gets true if any marking is or may be personal
                        $isMarkingOther = false; // gets true if 'other' marking is selected
                        $purposeResearch = ''; // contains all research purposes
                        $purposeFurther = ''; // contains all further purposes
                        $isPurposeResearch = false; // gets true if any research purpose except 'no purpose' is selected
                        $isPurposeFurther = false; // gets true if any further purpose except 'no purpose' is selected
                        $isPurpose = false; // gets true if $isPurposeResearch or $isPurposeFurther is true
                        $dataResearchTrans = ''; // translated data research types
                        $dataResearchSpecialTrans = []; // translated data special research types
                        $processingPrefix = $privacyPrefix.'processing.';
                        $dataResearchPrefix = $processingPrefix.self::dataResearchNode.'.';
                        $privacyPrefixTool = $projectdetailsPrefix.self::privacyNode.'.';
                        $isPersonal = false; // gets true if personal data is collected. Also true if processing is checked later. Also true if ip-addresses are collected. False if research data are personal, but marking is other.
                        $createOther = ''; // list of reasons why the tool can not create the document
                        $createOtherPrefix = $customPrefix.'createOther.';
                        if ($privacyCreate===self::createTool && $createArray[self::descriptionNode]==='1') {
                            $responsibility = $privacyArray[self::responsibilityNode];
                            $transferOutside = $privacyArray[self::transferOutsideNode];
                            $isOutside = $transferOutside==='yes';
                            $isNotResponsible = in_array($responsibility,self::responsibilityNotOwn);
                            if (in_array($responsibility,[self::responsibilityOnlyOwn,self::privacyNotApplicable]) && in_array($transferOutside,[self::transferOutsideNo,self::privacyNotApplicable])) {
                                if ($hasDataResearch) {
                                    $otherTypes = ['dataResearchOther',self::dataResearchSpecialOther];
                                    $tempPrefix = $dataResearchPrefix.'types.';
                                    foreach ($dataResearch as $type => $description) {
                                        $isNotOther = !in_array($type,$otherTypes);
                                        $translated = ($isNotOther ? $this->translateStringPDF($tempPrefix.$type) : '').($description!=='' ? $this->mergeContent([$isNotOther ? ' (' : '',$description,$isNotOther ? ')' : '']) : '');
                                        if (in_array($type,self::dataSpecialTypes)) {
                                            $dataResearchSpecialTrans[] = $translated;
                                        } else {
                                            $dataResearchTrans .= "• ".$translated."\n";
                                        }
                                    }
                                    if ($dataResearchSpecialTrans!==[]) {
                                        $dataResearchTrans .= $this->translateStringPDF($dataResearchPrefix.'dataSpecial',['isDataResearch' => $this->getStringFromBool($dataResearchTrans!=='')])."\n• ".implode("\n• ",$dataResearchSpecialTrans);
                                    }
                                }
                                $dataPersonal = $privacyArray[self::dataPersonalNode];
                                $isDataPersonal = in_array($dataPersonal,self::dataPersonal);
                                $markingArray = $privacyArray[self::markingNode];
                                $markingChosen = $markingArray[self::chosen];
                                $isChosenName = $markingChosen===self::markingName;
                                $markingSecondArray = $privacyArray[self::markingNode.self::markingSuffix] ?? [];
                                $isChosenNameSecond = ($markingSecondArray[self::chosen] ?? '')===self::markingName;
                                $isMarkingName = $isChosenName || $isChosenNameSecond;
                                $isMarkingPersonal = $isChosenName || in_array($markingArray[self::codePersonal] ?? '',self::markingDataResearchTypes) || $isChosenNameSecond || in_array($markingSecondArray[self::codePersonal] ?? '',self::markingDataResearchTypes);
                                $isMarkingOther = $markingChosen===self::markingOther;
                                $customPrivacy = $customPrivacy || $isMarkingOther;
                                $purposeResearch = $privacyArray[self::purposeResearchNode] ?? '';
                                $isPurposeResearch = $purposeResearch!=='' && !array_key_exists(self::purposeNo,$purposeResearch);
                                $purposeFurther = $privacyArray[self::purposeFurtherNode] ?? ''; // if marking is 'other', keys does not exist
                                $isPurposeFurther = $purposeFurther!=='' && !array_key_exists(self::purposeFurtherNode.self::purposeNo,$purposeFurther);
                                $isPurpose =  $isPurposeResearch || $isPurposeFurther;
                                $isToolPersonal = $markingChosen!==self::markingOther && ($isDataPersonal || $isMarkingPersonal || $isPurpose);
                            } elseif ($isNotResponsible || $isOutside) {
                                $customPrivacy = true;
                                $isPersonal = true;
                                foreach ([self::responsibilityNode => $isNotResponsible, self::transferOutsideNode => $isOutside] as $type => $value) { // only relevant if complete pdf is created
                                    if ($value) {
                                        $createOther .= "<li>".$this->translateStringPDF($createOtherPrefix.$type,$committeeParam)."</li>";
                                    }
                                }
                            }
                        }
                        $hasCustomPDF[self::privacyNode] = $customPrivacy;
                        $isPersonal = $isPersonal || $isSeparate || $isToolPersonal || in_array($dataOnlineArray[self::chosen] ?? '',self::dataOnlinePersonal); // true if personal data is collected. Also true if processing is checked later. Also true if ip-addresses are collected False if research data are personal, but marking is other.
                        $translationParams[self::isPersonal] = $this->getStringFromBool($isPersonal);
                        // other sources
                        $otherSourcesArray = $measuresArray[self::otherSourcesNode];
                        $hasCustomPDF[self::otherSourcesNode] = array_key_exists(self::otherSourcesPDF,$otherSourcesArray);
                        $otherSourcesSentence = '';
                        $translationPrefix = $participationPrefix.self::privacyNode.'.';
                        if ($otherSourcesArray[self::chosen]==='0') {
                            $otherSourcesSentence = $this->mergeContent([$this->translateStringPDF($translationPrefix.self::otherSourcesNode),$otherSourcesArray[self::otherSourcesNode.self::descriptionCap] ?? '']);
                        }
                        $otherSourcesParam = [self::otherSourcesNode => $otherSourcesSentence];
                        // create the marking and code compensation sentences
                        $markingSentences = '';
                        $codeCompensationSentences = '';
                        $markingSecondString = self::markingNode.self::markingSuffix;
                        $marking = $privacyArray[self::markingNode] ?? '';
                        $hasMarking = $marking!=='';
                        if ($hasMarking && $marking[self::chosen]===self::markingOther) {
                            $createOther .= "<li>".$this->translateStringPDF($createOtherPrefix.self::markingNode)."</li>";
                        }
                        $translationParams['createOther'] = "\n".$createOther."\n";
                        $markingSecond = $privacyArray[$markingSecondString] ?? '';
                        $codeCompensation = $privacyArray[self::codeCompensationNode] ?? '';
                        $isPurposeCompensation = false;
                        foreach ([self::purposeNode,self::purposeFurtherNode] as $type) {
                            $tempArray = $privacyArray[$type] ?? '';
                            $isPurposeCompensation = $isPurposeCompensation || $tempArray!=='' && array_key_exists(($type===self::purposeFurtherNode ? self::purposeFurtherNode : '').self::purposeCompensation,$tempArray);
                        }
                        $purposeCompensationParam = ['purposeCompensation' => $this->getStringFromBool($isPurposeCompensation)];
                        // the following variables get true if any marking is of that type
                        $isConsecutive = false;
                        $isExternal = false;
                        $isInternal = false;
                        $codePersonal = ['isName' => false, 'isList' => false, 'isGeneration' => false, 'isNameList' => false];
                        foreach (array_merge($hasMarking ? [self::markingNode] : [], $markingSecond!=='' ? [$markingSecondString] : [], $codeCompensation!=='' ? [self::codeCompensationNode] : []) as $type) {
                            $isMarkingCode = $type!==self::codeCompensationNode;
                            $markingPrefix = $translationPrefix.($isMarkingCode ? self::markingNode : self::codeCompensationNode).'.';
                            $tempArray = $privacyArray[$type];
                            $chosen = $tempArray[self::chosen];
                            $chosenWoPrefix = lcfirst(str_replace('code','',$chosen));
                            $curSentences = ' ';
                            $curCodePersonal = '';
                            $isCurInternal = false;
                            if (in_array($chosenWoPrefix,self::markingValues)) { // second marking or code compensation may not be chosen yet
                                $description = $tempArray[self::descriptionNode] ?? '';
                                $curSentences .= $this->mergeContent([$this->translateStringPDF($markingPrefix.'codeMarking',array_merge($addresseeParam,array_merge($purposeCompensationParam,['isSecond' => $this->getStringFromBool( $type!==self::markingNode), 'type' => $chosenWoPrefix]))),$description!=='' ? $description.'. ' : '']); // if $type equals codeCompensation, isSecond is true, but not needed
                                $isCurInternal = $chosenWoPrefix===self::markingInternal;
                                $internalChosen = '';
                                if ($isCurInternal) {
                                    $internalChosen = $tempArray[$chosen];
                                    if ($internalChosen!=='') { // internal type
                                        $curSentences .= $this->translateStringPDF($markingPrefix.self::markingInternal.'.'.$internalChosen,$addresseeParam).' ';
                                    }
                                }
                                $curCodePersonal = $tempArray[self::codePersonal] ?? '';
                                if ($curCodePersonal!=='') {
                                    $curSentences .= $this->translateStringPDF($markingPrefix.$chosenWoPrefix.'.types.'.($internalChosen!=='' ? $internalChosen.'.' : '').$curCodePersonal,$addresseeParam);
                                }
                            }
                            if ($isMarkingCode) {
                                $markingSentences .= $curSentences;
                                $isConsecutive = $isConsecutive || $chosenWoPrefix===self::markingConsecutive;
                                $isExternal = $isExternal || $chosenWoPrefix===self::markingExternal;
                                $isInternal = $isInternal || $isCurInternal;
                                $codePersonal['isName'] = $codePersonal['isName'] || $chosenWoPrefix===self::markingName;
                                $codePersonal['isList'] = $codePersonal['isList'] || $curCodePersonal===self::listNode;
                                $codePersonal['isGeneration'] = $codePersonal['isGeneration'] || $curCodePersonal===self::generation;
                            } else {
                                $codeCompensationSentences .= $curSentences;
                            }
                        } // foreach
                        $markingSentences = trim($markingSentences); // if no marking is chosen yet, $curSentences may be only one space
                        $isDataPersonalMaybe = $dataPersonal===self::dataPersonalMaybe;
                        $tempPrefix = $translationPrefix.'markingPersonal.';
                        $tempParams = array_merge($addresseeParam,['isPurposeFurther' => $this->getStringFromBool($isPurposeFurther)]);
                        if ($dataPersonal!=='') {
                            $isConsecutiveExternalInternal = $isConsecutive || $isExternal || $isInternal;
                            $isCodePersonal = in_array(true,$codePersonal);
                            if ($isDataPersonal && ($isConsecutiveExternalInternal || $isCodePersonal)) {
                                $tempVal = $isCodePersonal ? 'codePersonal' : 'anonymous';
                            } elseif ($isDataPersonalMaybe) {
                                $tempVal = $isCodePersonal ? 'codePersonal' : ($isConsecutiveExternalInternal ? 'anonymous' : self::markingNo);
                            } else { // research data are anonymous
                                $tempVal = $isCodePersonal ? 'codePersonal' : ($isConsecutive ? self::markingConsecutive : ($isExternal ? self::markingExternal : ($isInternal ? self::markingInternal : self::markingNo)));
                            }
                            if (!(in_array($dataPersonal,['','personal']) && $tempVal===self::markingNo)) { // if no marking is chosen, tempVal equals markingNo
                                $codePersonal['isNameList'] = $codePersonal['isName'] || $codePersonal['isList'];
                                foreach ($codePersonal as $key => $value) {
                                    $codePersonal[$key] = $this->getStringFromBool($value);
                                }
                                $markingSentences .= "\n".$this->translateStringPDF($tempPrefix.$dataPersonal.'.'.$tempVal,array_merge($tempParams,[self::codePersonal => $this->translateStringPDF($tempPrefix.self::codePersonal,$codePersonal)]));
                            }
                        } elseif ($privacyCreate==='anonymous') {
                            $markingSentences .= $this->translateStringPDF($tempPrefix.self::dataPersonalNo.'.no',$tempParams);
                        }
                        $markingSentences = trim($markingSentences);
                        $codeCompensationSentences = trim($codeCompensationSentences);
                        $isMarking = $markingSentences!=='';
                        $isMarkingOtherPersonal = $isMarkingOther && $isDataPersonal;
                        if ($isPersonal || $isMarkingOtherPersonal) {
                            $content = $this->translateStringPDF($translationPrefix.(($isToolPersonal && $isDataPersonalMaybe && !$isMarkingPersonal && !$isPurpose) ? self::dataPersonalMaybe : 'personal'),$otherSourcesParam); // self::dataPersonalMaybe if the only personal data are research data that may be personal
                        }
                        // data reuse
                        $dataReuseArray = $measureTimePoint[self::dataReuseNode];
                        // create string for data reuse how
                        [$dataReuseContent,$isNotOwn,$dataReuseHow] = ['',true,'']; // overwriting $isNotOwn and $dataReuseHow is ok because of available questions and answers in this case
                        $personalParam = $this->getPrivacyReuse($privacyArray);
                        if (($isSeparate || $isSeparateLater) && $dataReuseArray[self::confirmIntroNode]==='1') {
                            $personalParam['personal'] = match ($dataReuseArray[self::dataReuseNode]) {
                                'anonymous' => 'other',
                                'anonymized' => self::personalRemoveImmediately,
                                'personal' => 'personal',
                                default => 'noTool'
                            };
                        }
                        $tempPrefix = $projectdetailsPrefix.self::dataReuseNode.'.'.self::dataReuseHowNode.'.';
                        if (array_key_exists(self::dataReuseHowNode,$dataReuseArray)) {
                            foreach (['',self::personalKeepReuse] as $suffix) {
                                $curKey = self::dataReuseHowNode.$suffix;
                                $isFirst = $suffix==='';
                                if (array_key_exists($curKey,$dataReuseArray)) {
                                    $tempArray = $dataReuseArray[$curKey];
                                    $dataReuseHow = $tempArray[self::chosen];
                                    $isNotOwn = $isNotOwn && $dataReuseHow!=='own';
                                    $dataReuseContent .= ' '.$this->translateString($tempPrefix.'start',array_merge($isFirst ? $personalParam : ['personal' => 'keep'],['isSecond' => $this->getStringFromBool(!$isFirst)])).($dataReuseHow!=='' ? $this->translateString($tempPrefix.'types.'.$dataReuseHow) : '');
                                    $description = $tempArray[self::descriptionNode] ?? '';
                                    if ($description!=='') {
                                        $dataReuseContent .= $this->mergeContent([$this->translateString($tempPrefix.'descriptionStart'),$description,'.']);
                                    }
                                }
                            }
                        }
                        $isSelf = false;
                        $dataReuseSelfPrefix = $translationPrefix.self::dataReuseSelfNode.'.';
                        $isAnonymized = $personalParam['isAnonymized']; // also false if research data are not personal
                        $isAnonymousAnonymized = !$isDataPersonal || $isAnonymized; // research dara are either not personal or anonymized
                        if (array_key_exists(self::dataReuseSelfNode,$dataReuseArray)) {
                            $tempVal = $dataReuseArray[self::dataReuseSelfNode];
                            if ($tempVal!=='') {
                                $isSelf = $tempVal==='0';
                                if ($isSelf || $isAnonymousAnonymized) {
                                    $dataReuseContent .= ' '.$this->translateStringPDF($dataReuseSelfPrefix.($isSelf ? 'yes' : 'no'),$personalParam);
                                }
                            }
                        }
                        $isReuseHowTwice = $personalParam['isPurposeReuse'] && $isAnonymized;
                        $isDataReuse = false;
                        $isTwicePublicAnonymized = false;
                        $dataReuseHowChosen = $dataReuseArray[self::dataReuseHowNode][self::chosen] ?? ''; // answer to first daa reuse how question if asked twice
                        if ($dataReuseContent!=='' && ($isNotOwn || $isSelf || $isReuseHowTwice)) {
                            $dataReuseHowPrefix = $translationPrefix.self::dataReuseHowNode.'.';
                            $dataReuseChosen = $dataReuseArray[self::dataReuseNode] ?? 'yes';
                            $isDataReuse = in_array($dataReuseChosen,['yes','anonymous','anonymized']);
                            $isTwicePublicAnonymized = $isReuseHowTwice && $dataReuseChosen==='yes' && $dataReuseHowChosen==='own'; // true if personal research data is kept, but only anonymized data is published
                            if ($isTwicePublicAnonymized || !$isReuseHowTwice && $isDataReuse && $isAnonymousAnonymized && !$isSelf) {
                                $dataReuseContent .= $this->translateStringPDF($dataReuseHowPrefix.'public');
                            }
                            if ($isDataReuse || $dataReuseChosen==='personal') {
                                $dataReuseContent .=  $this->translateStringPDF($dataReuseHowPrefix.'reuse');
                            }
                            if (!in_array($dataReuseHow,['','own']) && ($isAnonymized || !$isDataPersonal)) {
                                $dataReuseContent .= ' '.$this->translateStringPDF($dataReuseSelfPrefix.'yesEnd',$personalParam);
                            }
                            if (!$isReuseHowTwice && $isNotOwn && $dataReuseChosen!=='no' || $isReuseHowTwice && ($isNotOwn || $isDataReuse)) {
                                $dataReuseContent .= $this->translateStringPDF($dataReuseHowPrefix.'guidelines');
                            }
                        }
                        if ($isPersonal || $isMarkingOtherPersonal) {
                            $content .= "\n".$dataReuseContent;
                        } else {
                            $content = $this->translateStringPDF(($isSeparateLater || $isMarkingOther) ? $translationPrefix.'noTool' : $translationPrefix.self::markingNode.'.start',array_merge($otherSourcesParam,['codeMarking' => ($isMarking ? $markingSentences : '')]))."\n".$dataReuseContent;
                        }
                        // add code compensation sentences if purpose research is not compensation
                        $isCodeCompensation = $codeCompensationSentences!=='';
                        $isPurposeFurtherCompensation = $isPurposeFurther && array_key_exists(self::purposeFurtherNode.self::compensationNode,$purposeFurther); // if compensation is selected as purpose research, code compensation question is not asked
                        if ($isCodeCompensation && !$isPurposeFurtherCompensation) {
                            $paragraphs[self::compensationNode][self::content] .= "\n".$codeCompensationSentences;
                        }
                        // add paragraphs from compensation to voluntary
                        foreach ($paragraphs as $key => $paragraph) {
                            self::$linkedPage = $paragraph[$linkedString];
                            $this->addParagraph($key,$paragraph[self::content],$paragraph[$subHeadingsString],$paragraph[$parametersString],$paragraph[$subColonString],$key!==self::compensationNode);
                        }
                        // add data privacy paragraph
                        self::$linkedPage = self::privacyNode;
                        $this->addParagraph(self::privacyNode,trim($content),addFragment: false);

                        // contact
                        $contributorsLink = $isMultiple ? self::contributorNode : 'contributors';
                        self::$linkedPage = $contributorsLink;
                        $translationParams['contributors'] = $this->replaceDummyString($contributorsContact,'; ');
                        $this->addParagraph('contact',$this->translateStringPDF($participationPrefix.'contact',$translationParams),addFragment: $isMultiple);

                        // loan receipt -> check here because either the receipt or the consent may contain the hint
                        $loanArray = $measuresArray[self::loanNode];
                        $isLoan = $loanArray[self::chosen]==='0'; // needed in consent
                        $legalPrefix = $projectdetailsPrefix.self::legalNode.'.';
                        if ($isLoan) {
                            $receiptArray = $loanArray[self::loanReceipt];
                            if ($this->getTemplateChoice($receiptArray[self::chosen])) { // receipt should be confirmed
                                $tempPrefix = $legalPrefix.self::apparatusNode.'.';
                                $apparatusArray = $measureTimePoint[self::legalNode][self::apparatusNode] ?? [];
                                $chosen = $apparatusArray[self::chosen] ?? self::template; // if no consent is given, the template text on the legal page (which is deactivated in that case) is added to the receipt
                                self::$linkedPage = self::measuresNode;
                                $isConfirmTemplate = $receiptArray[self::chosen]===self::template;
                                $loanReceiptParameters = ['content' => $this->getTemplateChoice($chosen) ? ($chosen===self::templateText ? [$this->addMarkInput($apparatusArray[self::descriptionNode],self::$markInput)] : [$this->translateString($tempPrefix.self::template), $this->translateString($tempPrefix.'loan')]) : [], 'confirm' => $this->mergeContent([$isConfirmTemplate ? $this->translateString($projectdetailsPrefix.self::measuresNode.'.'.self::loanNode.'.'.self::template) : '', !$isConfirmTemplate ? $receiptArray[self::descriptionNode] : '']), 'heading' => $this->addHeadingLink('loanReceipt.title',fragment: self::loanNode)];
                                $isLoanReceipt = true;
                            }
                        }

                        // consent -> one array containing all the information needed for the consent because it may be a separate document
                        $consentHeading = '';
                        $optionalConsent = []; // consent for finding or personalKeep if informing/keep is optional
                        $dataSpecialParam = ['isDataSpecial' => $this->getStringFromBool($isDataSpecial)];
                        if ($isConsent) { // consent is given
                            self::$linkedPage = self::consentNode;
                            $consentHeading = $this->addHeadingLink($consentPrefix.'title',$translationParams,self::consentNode);
                            $translationParams['informationType'] = $isPre ? $preType : $postArray[self::descriptionNode];
                            $consent = [$this->translateStringPDF($consentPrefix.'start',array_merge($translationParams,$terminateConsParam,$voluntaryParams))];
                            $consent[] = $this->translateStringPDF($consentPrefix.'personal',array_merge($translationParams,$dataSpecialParam,['dataSpecial' => $this->replaceDummyString($dataResearchSpecialTrans), 'contributors' => $this->replaceDummyString($contributorsData,replace: 'or')]));
                            if ($isPre) {
                                $isNotLocation = in_array($location,[self::locationOnline,'']); // for insuranceWay
                                // legal
                                $legalArray = $measureTimePoint[self::legalNode];
                                $legalArray = $legalArray ?: [];
                                foreach (self::legalTypes as $type) {
                                    if (array_key_exists($type,$legalArray)) {
                                        $isApparatus = $type===self::apparatusNode;
                                        $tempArray = $legalArray[$type];
                                        $tempVal = $tempArray[self::chosen];
                                        if ($this->getTemplateChoice($tempVal) && !($type===self::insuranceWayNode && $isNotLocation || $isApparatus && ($isLoanReceipt || !$isLoan && $isNotLocation))) { // consent should contain hint
                                            $typePrefix = $legalPrefix.$type.'.';
                                            $isTemplate = $tempVal===self::template;
                                            $consent[] = $this->mergeContent([$isTemplate ? $this->translateString($typePrefix.self::template,$committeeParam).($isApparatus && $isLoan ? $this->translateString($typePrefix.'loan') : '') : '',!$isTemplate ? $tempArray[self::descriptionNode] : '']);
                                        }
                                    }
                                }
                            }
                            $consent[] = $this->translateStringPDF($consentPrefix.'copy',$translationParams);
                        } // if consent is given
                        $isConsent = $consent!==[];

                        // data privacy: one array containing all the information because it may be a separate document
                        $personal = $personalParam['personal'];
                        $translationParams['personal'] = $personal;
                        $privacyContent = [];
                        if ($isToolPersonal) { // personal data are collected and the document should be created automatically
                            self::$linkedPage = self::privacyNode;
                            $tempPrefix = $privacyPrefix.'basis.';
                            $privacyContent = [$this->translateStringPDF($tempPrefix.'title') => $this->translateStringPDF($tempPrefix.'text',['isDataSpecial' => $this->getStringFromBool($isDataSpecial)])]; // basis
                            $anonymizationPrefix = $privacyPrefix.self::anonymizationNode.'.';
                            $transferContent = []; // transfer
                            $anonymizationContent = []; // anonymization (how and when)
                            $purposes = []; // one element for each purpose
                            $isReuseConsent = false; // gets true if keeping personal research data for reuse purpose is optional
                            $isTeaching = false; // gets true if personal research data is kept for teaching purpose
                            $isTeachingConsent = false; // gets true if keeping personal research data for teaching purpose is optional
                            $isDemonstration = false; // gets true if personal research data is kept for demonstration purpose
                            $isDemonstrationConsent = false; // gets true if keeping personal research data for demonstration purpose is optional
                            $storage = '';
                            $transferPrefix = $privacyPrefix.'transfer.';
                            $accessPrefix = $transferPrefix.self::accessNode.'.';
                            $accessStart = $accessPrefix.'start';
                            $purposeStart = $this->translateStringPDF($processingPrefix.'purposeStart'); // 'For' translated
                            $isDataOnlineProcessingResearch = ($dataOnlineArray[self::descriptionNode] ?? '')===self::dataOnlineProcessingResearch;
                            $anyOrderProcessingKnown = [false,false]; // gets true if any order processing is known (0) or not known (1)
                            $purposesKnownTrans = [[],[]]; // translated purposes for which order processing is known (0) or not known (1)
                            $allPurposesTranslated = [];
                            foreach (array_merge([self::dataPersonalNode],array_slice(self::purposeResearchTypes,1),array_slice(self::purposeFurtherTypes,1)) as $purpose) {
                                $allPurposesTranslated[$purpose] = $this->translateStringPDF($processingPrefix.'purposeTypes.'.$purpose);
                            }
                            if ($isDataResearch) { // research data is/may be personal or a personal marking is used
                                $purposes[] = trim($purposeStart.$allPurposesTranslated[self::dataPersonalNode].":\n".$dataResearchTrans)."\n\n";
                                if ($isDataPersonal) {
                                    // anonymization
                                    $anonymizationPrefixTool = $privacyPrefixTool.self::anonymizationNode.'.';
                                    $tempArray = $privacyArray[self::anonymizationNode];
                                    $anonymizationResearch = '';
                                    if ($tempArray!=='') { // at least one type of anonymization was selected
                                        if (array_key_exists(self::anonymizationNo, $tempArray)) {
                                            $anonymizationResearch = $this->translateStringPDF($anonymizationPrefix.self::dataResearchNode.'No');
                                        } else {
                                            $tempPrefix = $anonymizationPrefixTool.'types.';
                                            $tempVal = [];
                                            foreach ($tempArray as $type => $description) {
                                                $tempVal[] = $type!==self::anonymizationOther ? $this->translateString($tempPrefix.$type) : $this->addMarkInput($description,self::$markInput);
                                            }
                                            $anonymizationResearch = $this->translateString($anonymizationPrefixTool.'start').$this->replaceDummyString($tempVal).'.';
                                        }
                                    }
                                    // storage
                                    if (array_key_exists(self::storageNode, $privacyArray)) {
                                        $tempArray = $privacyArray[self::storageNode];
                                        $storage = $tempArray[self::chosen];
                                        if ($storage===self::storageDelete) {
                                            $anonymizationResearch .= $this->mergeContent(["\n".$this->translateStringPDF($privacyPrefix.self::storageNode),$tempArray[self::descriptionNode],'.']) ;
                                        }
                                    }
                                    // personal keep
                                    $personalKeepTrans = [];
                                    $personalKeepConsentArray = $privacyArray[self::personalKeepConsentNode] ?? '';
                                    if ($personalKeepConsentArray==='') {
                                        $personalKeepConsentArray = [];
                                    }
                                    $optionalTrans = ' '.$this->translateString('multiple.optional');
                                    if (array_key_exists(self::personalKeepNode, $privacyArray)) {
                                        $tempArray = $privacyArray[self::personalKeepNode];
                                        if ($tempArray!=='') {
                                            $isTeaching = array_key_exists(self::personalKeepTeaching,$tempArray);
                                            $isDemonstration = array_key_exists(self::personalKeepDemonstration,$tempArray);
                                            $personalKeepPrefixTool = $privacyPrefixTool.self::personalKeepNode.'.';
                                            $personalKeepPrefix = $privacyPrefix.self::personalKeepNode.'.';
                                            $tempPrefix = $personalKeepPrefix.'types.';
                                            $tempVal = [];
                                            foreach ($tempArray as $type => $description) {
                                                $trans = $this->translateStringPDF($tempPrefix.$type);
                                                $personalKeepTrans[$type] = $trans;
                                                $tempVal[] = $this->mergeContent([$trans.' ',$description,(($personalKeepConsentArray[$type] ?? '')==='optional'? $optionalTrans : '')]);
                                            }
                                            $anonymizationResearch .= "\n".$this->translateString($personalKeepPrefixTool.'start').$this->replaceDummyString($tempVal).$this->translateString($personalKeepPrefixTool.'end');
                                        }
                                    }
                                    if ($anonymizationResearch!=='') {
                                        $anonymizationContent[] = $anonymizationResearch;
                                    }
                                    // personal keep consent
                                    if ($isConsent && $personalKeepConsentArray!==[]) {
                                        $tempVal = $participationConsentPrefix.self::personalKeepConsentNode;
                                        foreach ($personalKeepConsentArray as $type => $description) {
                                            if ($description==='optional') {
                                                if ($type===self::personalKeepReuse) {
                                                    $isReuseConsent = true;
                                                } elseif ($type===self::personalKeepDemonstration) {
                                                    $isDemonstrationConsent = true;
                                                } elseif ($type===self::personalKeepTeaching) {
                                                    $isTeachingConsent = true;
                                                }
                                                $optionalConsent[] = $this->translateStringPDF($tempVal,['type' => $personalKeepTrans[$type]]);
                                            }
                                        }
                                    }
                                }
                                // access if research data is personal -> if research data is not personal, but marking is personal, access is asked
                                $tempArray = $privacyArray[self::accessNode] ?? '';
                                if ($tempArray!=='') {
                                    $tempVal = $this->addAccess($tempArray,self::dataPersonalNode,$committeeParam,$anyOrderProcessingKnown,$purposesKnownTrans,$allPurposesTranslated[self::dataPersonalNode]);
                                    foreach (array_merge([self::dataPersonalNode],$isDataOnlineProcessingResearch ? [self::purposeTechnical] : []) as $type) {
                                        $transferContent[] = $this->translateStringPDF($accessStart,[self::purposeNode => $allPurposesTranslated[$type]])."\n".$tempVal;
                                    }
                                }
                            }
                            $purposesMerged = array_merge($isPurposeResearch ? $purposeResearch : [], $isDataOnlineProcessingResearch && !$isMarking ? [self::purposeTechnical => []] : [],$isPurposeFurther ? $purposeFurther : []);
                            $purposeDataPrefix = $privacyPrefixTool.self::purposeDataNode.'.types.';
                            $andTrans = $this->translateString('multiple.inputs.lastAnd');
                            $relatable = [];
                            if (array_key_exists(self::relatableNode,$privacyArray)) { // purpose 'relatable' was selected
                                $tempArray = $privacyArray[self::relatableNode];
                                $tempPrefix = $privacyPrefixTool.self::relatableNode.'.types.';
                                foreach ($tempArray!=='' ? $tempArray : [] as $type => $description) {
                                    $relatable[] = $this->translateString($tempPrefix.$type);
                                }
                            }
                            foreach ($purposesMerged as $purpose => $questions) {
                                $purposeWoPrefix = str_replace(self::purposeFurtherNode,'',$purpose);
                                $purposeTrans = $allPurposesTranslated[$purposeWoPrefix];
                                $purposeParam = [self::purposeNode => $purposeTrans];
                                if ($purpose!==self::purposeNo) {
                                    // purpose data
                                    $tempVal = '';
                                    if ($purposeWoPrefix!==self::purposeTechnical) {
                                        $tempArray = $questions[self::purposeDataNode];
                                        if ($tempArray!=='') { // at least one data type was selected
                                            foreach ($tempArray as $type => $description) {
                                                $typeWoPrefix = str_replace($purposeWoPrefix,'',$type);
                                                $tempVal .= "• ".($typeWoPrefix!==self::purposeDataOther ? $this->translateString($purposeDataPrefix.$typeWoPrefix) : $this->addMarkInput($description,self::$markInput))."\n";
                                            }
                                        }
                                    } else {
                                        $tempVal = "• ".$this->translateString($purposeDataPrefix.'ip');
                                    }
                                    if ($tempVal!=='') {
                                        $purposes[] = trim($purposeStart.$this->translateStringPDF($processingPrefix.'purposeTypes.'.$purposeWoPrefix).($purposeWoPrefix===self::purposeRelatable ? ' ('.$this->replaceDummyString($relatable).')' : '').":\n".$tempVal)."\n\n";
                                    }
                                    if ($questions!=='') {
                                        // access
                                        $tempArray = $questions[self::accessNode] ?? '';
                                        if ($tempArray!=='') {
                                            $transferContent[] = $this->translateStringPDF($accessStart, $purposeParam)."\n".$this->addAccess($tempArray,$purposeWoPrefix,$committeeParam,$anyOrderProcessingKnown,$purposesKnownTrans,$purposeTrans);
                                        }
                                        // marking remove
                                        $markingRemove = '';
                                        if (array_key_exists(self::markingRemoveNode, $questions)) {
                                            $markingRemovePrefix = $privacyPrefixTool.self::markingRemoveNode.'.';
                                            $tempArray = $questions[self::markingRemoveNode];
                                            $tempVal = str_replace($purposeWoPrefix, '', $tempArray[self::chosen]);
                                            if ($tempVal!=='') {
                                                $markingRemove = $this->mergeContent([$this->translateString($markingRemovePrefix.'start',[self::purposeNode => $this->translateStringPDF( $processingPrefix.'purposeTypesGen.'.$purposeWoPrefix)]).$this->translateStringPDF($privacyPrefix.self::markingRemoveNode.'.'.$tempVal),$tempArray[self::descriptionNode] ?? '', $tempVal===self::markingRemoveNode.'Later' ? '. ' : ', ']);
                                                if ($tempVal===self::markingRemoveLater) {
                                                    $markingRemove .= $this->mergeContent([$this->translateString($markingRemovePrefix.'laterEnd', ['isName' => $this->getStringFromBool($isMarkingName)]).' ',$tempArray['laterDescription']]);
                                                } else { // immediately
                                                    $tempVal = '';
                                                    $tempArray = $tempArray[self::markingRemoveMiddleNode];
                                                    if ($tempArray!=='') {
                                                        $tempPrefix = $markingRemovePrefix.self::markingRemoveMiddleNode.'.types.';
                                                        foreach ($tempArray as $type => $value) {
                                                            $tempVal .= $andTrans.$this->translateString($tempPrefix.str_replace($purposeWoPrefix, '', $type));
                                                        }
                                                    }
                                                    $markingRemove .= substr($tempVal, strlen($andTrans)).$this->translateString($markingRemovePrefix.'immediatelyEnd');
                                                }
                                            }
                                        }
                                        // personal remove
                                        if (array_key_exists(self::personalRemoveNode, $questions)) {
                                            $tempArray = $questions[self::personalRemoveNode];
                                            $tempVal = $tempArray[self::chosen];
                                            $personalRemove = '';
                                            if ($tempVal!=='') {
                                                $tempVal = str_replace($purposeWoPrefix, '', $tempVal);
                                                $personalRemove = $this->mergeContent([$this->translateString($privacyPrefixTool.self::personalRemoveNode.'.start', $purposeParam).$this->translateStringPDF($privacyPrefix.self::personalRemoveNode.'.'.$tempVal),$tempArray[self::descriptionNode] ?? '',$tempVal===self::personalRemoveImmediately ? '.' : '']);
                                            }
                                            $anonymizationContent[] = trim($markingRemove."\n".$personalRemove);
                                        }
                                    }
                                } // if not purposeNo
                            } // foreach purpose
                            $processingContent = [trim($this->translateStringPDF($processingPrefix.'start').trim(implode(' ',array: $purposes)))];
                            // marking
                            if ($isMarking) {
                                $tempVal = '';
                                if (array_key_exists(self::listNode,$privacyArray)) { // marking is by list
                                    $tempArray = $privacyArray[self::listNode];
                                    $listArray = [];
                                    if ($tempArray!=='') { // at least one data type was selected
                                        $tempPrefix = $privacyPrefixTool.self::listNode.'.types.';
                                        foreach ($tempArray as $type => $description) {
                                            $listArray[] = $type!==self::listOther ? $this->translateString($tempPrefix.$type) : $this->addMarkInput($description,self::$markInput);
                                        }
                                    }
                                    $tempVal = $this->translateStringPDF($processingPrefix.self::listNode).$this->replaceDummyString($listArray).'.';
                                }
                                $processingContent[] = $markingSentences.$tempVal;
                            }
                            if ($isCodeCompensation && $isPurposeFurtherCompensation) {
                                $processingContent[] = $codeCompensationSentences;
                            }
                            $tempVal = $privacyArray[self::processingFurtherNode] ?? '';
                            if ($tempVal!=='') { // avoid multiple empty lines if nothing was entered
                                $processingContent[] = $this->addMarkInput($tempVal,self::$markInput);
                            }
                            $tempPrefix = $processingPrefix.'end.';
                            $isReusePersonal = $personal==='personal';
                            $tempVal = $this->translateStringPDF($tempPrefix.'start'.(($isReusePersonal && !($isDataReuse || $isSelf) || in_array($personal,['immediately','keep','marking','anonymous'])) ? 'NoUse' : ''),$translationParams);
                            $isDataReuseHowChosen = $dataReuseHowChosen!=='';
                            $reuseEnd = $personal==='purpose' && $isDataReuseHowChosen ? ($dataReuseHowChosen==='own' ? self::dataReuseSelfNode : self::dataReuseHowNode) : ($isReusePersonal ? ($isSelf ? self::dataReuseSelfNode : ($isDataReuseHowChosen ? self::dataReuseHowNode : '')) : '');
                            if ($reuseEnd!=='') {
                                $tempVal .= $this->translateStringPDF($tempPrefix.$reuseEnd);
                            }
                            $processingContent[] = $tempVal.($otherSourcesSentence!=='' ? "\n".$this->translateStringPDF($processingPrefix.self::otherSourcesNode) : '');
                            // transfer
                            if (in_array(true,$anyOrderProcessingKnown)) { // order processing exists
                                $isKnown = $anyOrderProcessingKnown[0];
                                $orderProcessingKnownPrefix = $transferPrefix.self::orderProcessingKnownNode.'.';
                                if ($isKnown) {
                                    $tempPrefix = $privacyPrefixTool.self::orderProcessingDescriptionNode.'.text.';
                                    $tempVal = '';
                                    $tempArray = $privacyArray[self::orderProcessingDescriptionNode];
                                    foreach (self::orderProcessingKnownTexts as $textPart) {
                                        $tempVal .= $this->mergeContent([$this->translateString($tempPrefix.$textPart),$tempArray[$textPart],$textPart!==self::orderProcessingNode.'Start' ? '.' : '']);
                                    }
                                    $transferContent[] = $this->translateStringPDF($orderProcessingKnownPrefix.'known',[self::purposeNode => $this->replaceDummyString($purposesKnownTrans[0])]).$tempVal;
                                }
                                if ($anyOrderProcessingKnown[1]) {
                                    $transferContent[] = $this->translateStringPDF($orderProcessingKnownPrefix.'unknown',[self::purposeNode => $this->replaceDummyString($purposesKnownTrans[1]), 'isKnown' => $this->getStringFromBool($isKnown)]);
                                }
                            }
                            $transferContent[] = $this->translateStringPDF($transferPrefix.self::transferOutsideNode);
                            $privacyContent[$this->translateStringPDF($processingPrefix.'title')] = trim(implode("\n\n",$processingContent));
                            $privacyContent[$this->translateStringPDF($transferPrefix.'title')] = trim(implode("\n\n",$transferContent));
                            $privacyContent[$this->translateStringPDF($anonymizationPrefix.'title')] = trim(implode("\n\n",$anonymizationContent));
                            // public
                            $tempPrefix = $privacyPrefix.'public.';
                            $tempVal = $this->translateStringPDF($tempPrefix.'start');
                            if ($isDemonstration) {
                                $tempVal .= $this->translateStringPDF($tempPrefix.self::personalKeepDemonstration,array_merge($addresseeParam,['optional' => $this->getStringFromBool($isDemonstrationConsent)]));
                            }
                            $dataReuseHow = $dataReuseArray[self::dataReuseHowNode][self::chosen] ?? ''; // old $dataReuseHow value might be the value for anonymized data
                            $isReuse = str_contains($dataReuseHow,'class') && (!$isAnonymized || $storage==='keep' && $isReuseHowTwice && !$isTwicePublicAnonymized);
                            if ($isDataPersonal && ($isReuse || $isTeaching)) {
                                $tempVal .= $this->translateStringPDF($tempPrefix.self::dataReuseHowNode).
                                    ($isReuse ? (rtrim($this->translateStringPDF($tempPrefix.'reuseTypes.'.$dataReuseHow),'.').$this->translateStringPDF($tempPrefix.'end',['optional' => $this->getStringFromBool($isReuseConsent && !$isTeachingConsent), 'isTeaching' => $this->getStringFromBool($isTeaching)])) : '').
                                    ($isTeaching ? $this->translateStringPDF($tempPrefix.self::personalKeepTeaching,['optional' => $this->getStringFromBool($isTeachingConsent), 'isReuse' => $this->getStringFromBool($isReuse)]) : '').'.';
                            }
                            $privacyContent[$this->translateStringPDF($tempPrefix.'title')] = $tempVal;
                            $tempPrefix = $privacyPrefix.'revocation.';
                            $privacyContent[$this->translateStringPDF($tempPrefix.'title')] = $this->translateStringPDF($tempPrefix.'text'); // revocation
                            $tempPrefix = $privacyPrefix.'rights.';
                            $privacyContent[$this->translateStringPDF($tempPrefix.'title')] = $this->translateStringPDF($tempPrefix.'text',$translationParams).$this->translateStringPDF($tempPrefix.'textEnd'); // rights
                        }
                        $privacyParameters = array_merge($committeeParam,[
                            'privacyIntro' => $this->translateStringPDF($privacyPrefix.'intro',array_merge($translationParams,$numStudiesParam,[self::studyID => $studyIDincreased])),
                            'privacyContent' => $privacyContent,
                            'personal' => $personal,
                            self::createNode => $privacyCreate,
                            'data' => $contributorsDataContact,
                            'privacyHeading' => $this->addHeadingLink($privacyPrefix.'title'),
                            self::committeeParams => $committeeParam]);
                        if ($findingConsent!=='') { // finding consent is optional -> after the optional privacy consents
                            $optionalConsent[] = $findingConsent;
                        }

                        self::$linkedPage = self::informationNode;
                        $participationHeading = $this->addHeadingLink($participationPrefix.'title',$informationParam);
                        $tempVal = $this->addHeadingLink($participationPrefix.self::informationOral);
                        self::$linkedPage = $contributorsLink; // for leaderHeading
                        // create string for header containing the project title and eventually the level IDs
                        $levelIDs = [];
                        $tempPrefix = 'projectdetails.headings.';
                        foreach ($levelNames as $level => $infos) {
                            if ($infos['isMultiple']) {
                                $levelIDs[] = $this->translateString($tempPrefix.$level).' '.$infos['id'];
                            }
                        }
                        $parameters = array_merge($parameters,$savePDFParam,$projectTitleParam,$privacyParameters,
                            ['participationHeading' => $participationHeading,
                             'intro' => $intro,
                             'leaderHeading' => $leaderHeading,
                             'levelNames' => $levelIDs!==[] ? implode(', ',$levelIDs) : '',
                             'isOral' => $isOral,
                             'content' => $this->content,
                             self::consentNode => $consent,
                             'consentType' => $consentType,
                             'consentHeading' => $consentHeading,
                             'yesTrans' => $this->translateString('buttons.yes'), // for optional consent
                             'noTrans' => $this->translateString('buttons.no'), // for optional consent
                             'optionalConsent' => $optionalConsent,
                             'oralHint' => $tempVal,
                             self::isPersonal => $this->getStringFromBool($isPersonal || $isMarkingOtherPersonal),
                             'privacyParameters' => $privacyParameters]);
                    } else { // no documents are created
                        $isMissingInformation = $hasDocs && in_array($information,['','noPre']); // no pre or no post information is selected
                        $noDocStart = $this->translateStringPDF($noInformationPrefix.'start',array_merge($parameters,['isService' => $this->getStringFromBool($isShortService && !$isMissingInformation)]));
                        $tempParams = array_merge($parameters,$savePDFstringParam);
                        if (!$isShortService) {
                            $noInformationSentence = $this->translateStringPDF($noInformationPrefix.($isMissingInformation ? 'informationMissing' : self::informationNode),$tempParams);
                            $noDocStart .= ' '.($hasDocs ? $noInformationSentence : $this->translateStringPDF($noInformationPrefix.(!$isShortChoose
                                        ? ($isBegun
                                            ? self::projectStart
                                            : ($isRequested ? self::funding : self::reviewProcessShort))
                                        : self::reviewProcessShort),array_merge($tempParams,['informationSentence' => $noInformationSentence])));
                            $noDocStart .= $this->translateStringPDF($noInformationPrefix.'end',['isInformation' => $this->getStringFromBool(!$isMissingInformation)]);
                        }
                        $noDocStart .= "\n\n";
                        // add compensation by termination
                        if ($isCompensationTerminate) {
                            $paragraphsAll[self::compensationNode][self::content] .= "\n".$this->translateStringPDF($compensationTerminateTrans,array_merge($compensationTerminateParams,[self::terminateConsNode => 'false', 'isDocs' => 'false'])).$compensationTerminateDescription; // set terminateCons to false as there is sentence about terminate cons in this case
                        }
                        $isService = $reviewProcess===self::reviewShortService;
                        $serviceParagraphs = [self::procedureNode,self::compensationNode];
                        foreach ($paragraphsAll as $key => $paragraph) {
                            if (!$isService || in_array($key,$serviceParagraphs)) {
                                self::$linkedPage = $paragraph[$linkedString] ?? '';
                                $this->linkedSubHeadings = $paragraph[$this->linkedSubHeadingsString] ?? [];
                                $content = $paragraph[self::content];
                                $subHeadings = $paragraph[$subHeadingsString] ?? [];
                                if ($key===self::procedureNode && $isService) { // only duration
                                    $this->linkedSubHeadings = [$this->linkedSubHeadings[2]];
                                    $content = [$content[2]];
                                    $subHeadings = [$subHeadings[2]];
                                }
                                $this->addParagraph($key, $content, $subHeadings, $paragraph[$parametersString] ?? [], $paragraph[$subColonString] ?? true, $key!==self::compensationNode);
                            }
                        }
                        $parameters = array_merge($translationParams,$savePDFParam,['noDocStart' => $noDocStart, self::content => $this->content, 'parameters' => array_merge($parameters,$informationParam)]);
                    }
                    // render the current document
                    try {
                        $translationParams = array_merge($translationParams,$savePDFstringParam);
                        $curHtml = $this->renderView('PDF/_participation.html.twig',array_merge($parameters,['markInputParams' => array_merge($savePDFstringParam,['singleDocsName' => $session->get(self::fileName).'_'.$this->translateStringPDF('filenames.singleDocs').'_'.$this->getCurrentDate()->format('Ymd')]), 'isMarkInput' => self::$markInput, 'hasDocs' => $hasDocs]));
                        if ($hasDocs) {
                            if ($isConsent) { // consent is given
                                self::$linkedPage = self::consentNode;
                                $curHtml .= $this->renderView('PDF/_participationConsent.html.twig',array_merge($parameters,[],$isOral ? ['isSeparate' => true] : []));
                            } elseif (in_array($consentType,[self::voluntaryConsentNo,self::consentOther])) {
                                $curHtml .= $this->renderView(self::intermediateDocument,array_merge($parameters,[self::content => $this->translateStringPDF($consentPrefix.'noConsent',array_merge($translationParams,$noConsentParams,$isFullParam))]));
                            }
                            // data privacy
                            if (!$isMissingInformation) {
                                if ($isToolPersonal) {
                                    self::$linkedPage = self::privacyNode;
                                    $curHtml .= $this->renderView('PDF/_dataPrivacy.html.twig',$parameters);
                                } elseif ($personal==='anonymous' || $isSeparateLater) {
                                    $curHtml .= $this->renderView(self::intermediateDocument,array_merge($parameters,[self::content => $this->translateStringPDF($customPrefix.self::privacyNode,$translationParams)]));
                                }
                            }
                            // complete post information
                            if ($isPre && $this->getInformationIII($informationArray)) { // partial or deceit with post information
                                $translationPrefix = 'completePost.';
                                self::$linkedPage = self::informationIIINode;
                                $completePostHeading = $this->addHeadingLink($translationPrefix.'title');
                                self::$linkedPage = self::informationNode;
                                $pageArray = $measureTimePoint[self::informationIIINode] ?: [];
                                $content = ['intro' => [$this->translateStringPDF($translationPrefix.'intro',$translationParams),'']];
                                foreach (self::informationIIIInputsTypes as $input) {
                                    $content[$input] = [$this->translateStringPDF($translationPrefix.$input,$addresseeParam),$this->addMarkInput($pageArray[$input] ?? '',self::$markInput)];
                                }
                                $content['end'] = [$this->translateStringPDF($translationPrefix.'end',$addresseeParam),''];
                                // contributors* variables are defined. They are defined if information is either pre or post and this if can only be true if information is pre
                                $contributorsLeader[0] = $this->translateStringPDF($translationPrefix.'contributors').($contributorsLeader[0] ?? '');

                                $curHtml .= $this->renderView('PDF/_completePost.html.twig',array_merge($parameters,[
                                    'content' => $content,
                                    'completePostHeading' => $completePostHeading,
                                    'isOral' => $preCompleteArray[self::preCompleteType]===self::informationOral,
                                    'contributors' => array_merge($contributorsLeader,$contributorsFurther ?? []), // $contributorsFurther is defined if information is pre or post
                                    'oralHint' => $this->addHeadingLink($translationPrefix.self::informationOral)]));
                            }
                            // loan receipt
                            if ($isLoanReceipt) {
                                $curHtml .= $this->renderView('PDF/_loanReceipt.html.twig',array_merge($parameters,$loanReceiptParameters));
                            }
                        }
                        $ids = [$studyID,$groupID,$measureID];
                        $html[] = $ids;
                        $allHtml .= $curHtml;
                        if (self::$savePDF) {
                            $this->generatePDF($session,$curHtml,$this->concatIDs($ids,'participation'.$markedSuffix));
                            if (self::$isCompleteForm) { // check for custom PDFs
                                $customValues = [];
                                foreach (self::customPDForder as $custom) {
                                    $isInformationII = $custom===self::informationIINode;
                                    $hasCustom = $hasCustomPDF[$custom];
                                    if ($hasCustom && in_array($reviewProcess,self::reviewTypesPDF[$custom]) && ($custom!=='begun' || $isInformation) || $isInformationII) {
                                        $content = '';
                                        if (!$isInformationII || $hasCustom) {
                                            $content = $this->translateStringPDF($customPrefix.$custom,$translationParams);
                                        } elseif ($hasInformationII) { // informationII is active, but no custom PDF needs to be added
                                            $tempPrefix = $noInformationPrefix.self::informationIINode.'.';
                                            $tempVal = '';
                                            if ($informationII==='noPost') {
                                                $tempVal = $this->translateStringPDF($tempPrefix.'noPost',$translationParams);
                                            }
                                            $content = $this->translateStringPDF($tempPrefix.'start',$translationParams).' '.($isRequested ? $this->translateStringPDF($noInformationPrefix.self::funding,array_merge($translationParams,['informationSentence' => $tempVal, self::informationNode => $informationII])) : $tempVal).'.';
                                        }
                                        if ($content!=='') {
                                            $customValues[] = $custom;
                                            $customHtml = $this->renderView(self::intermediateDocument,array_merge($parameters,[self::content => $content]));
                                            $this->generatePDF($session,$customHtml,$this->concatIDs($ids,'participation',$custom));
                                        }
                                    }
                                }
                                $customPDFs[$studyID][$groupID][$measureID] = $customValues;
                            }
                        }
                    } catch (\Throwable) {
                        return $this->setErrorAndRedirect($session);
                    }
                } // for measureID
            } // for groupID
        } // for studyID
        if (self::$savePDF && self::$isCompleteForm) {
            $session->set(self::pdfParticipationCustom,$customPDFs);
        }
        $session->set('pdfParticipation'.$markedSuffix,$allHtml);
        $session->set(self::pdfParticipationArray,$html);
        return new Response($allHtml);
    }

    /** Creates a string containing information about a contributor. The information is marked.
     * @param array $infos array containing the information
     * @param bool $addContact if true, the eMail and the phone (if existing) are added
     * @param bool $addInstitution if true, the institution is added
     * @return string information about a contributor
     */
    private function addContributorInfo(array $infos, bool $addContact = true, bool $addInstitution = false): string
    {
        $returnString = [];
        foreach (array_merge([self::nameNode],$addInstitution ? [self::institutionInfo] : [],$addContact ? array_merge([self::eMailNode],array_key_exists(self::phoneNode,$infos) ? [self::phoneNode] : []) : []) as $info) {
            $returnString[] = $infos[$info];
        }
        return $this->addMarkInput(implode(', ',$returnString),self::$markInput);
    }

    /** Creates the sub-paragraph for access.
     * @param array $accessArray array containing the data about the access questions
     * @param string $purposeWoPrefix purpose for which access is created
     * @param array $committeeParam translation parameter containing the committee
     * @param array $anyOrderProcessingKnown 0: any order processing is known, 1: any order processing is not known
     * @param array $purposeKnownTrans translated purposes for which order processing is known (0) or not known (1)
     * @param string $purposeTrans translated purpose
     * @return string access string
     */
    private function addAccess(array $accessArray, string $purposeWoPrefix, array $committeeParam, array &$anyOrderProcessingKnown, array &$purposeKnownTrans, string $purposeTrans): string
    {
        $tempVal = '';
        $tempPrefix = self::privacyNode.'.transfer.'.self::accessNode.'.types.';
        $purposeTransKnown = $purposeKnownTrans[0];
        $purposeTransNotKnown = $purposeKnownTrans[1];
        foreach ($accessArray as $type => $description) {
            $typeWoPrefix = str_replace($purposeWoPrefix,'',$type);
            $isContributorsOther = $typeWoPrefix==self::accessContributorsOther;
            $orderProcessing = $isContributorsOther ? $description[self::orderProcessingNode] : [];
            $orderProcessingKnown = is_array($description) ? ($description[self::orderProcessingKnownNode] ?? '') : '';
            if ($orderProcessingKnown!=='') {
                $isKnown = in_array($orderProcessingKnown,self::orderProcessingYesTypes);
                $isNotKnown = $orderProcessingKnown==='knownNo';
                $anyOrderProcessingKnown = [$anyOrderProcessingKnown[0] || $isKnown, $anyOrderProcessingKnown[1] || $isNotKnown];
                if ($isKnown && !in_array($purposeTrans,$purposeTransKnown)) {
                    $purposeTransKnown[] = $purposeTrans;
                }
                if ($isNotKnown && !in_array($purposeTrans,$purposeTransNotKnown)) {
                    $purposeTransNotKnown[] = $purposeTrans;
                }
            }
            $isContributorsOtherNoProcessing = $isContributorsOther && $orderProcessing[self::chosen]==='1';
            $tempVal .= $this->mergeContent(["• ".$this->translateStringPDF($tempPrefix.$typeWoPrefix,$committeeParam).($isContributorsOtherNoProcessing ? ' (' : ''),in_array($typeWoPrefix,self::accessOthers) ? $description : ($isContributorsOtherNoProcessing ? $orderProcessing[self::descriptionNode] : ''),$isContributorsOtherNoProcessing ? ')' : ''])."\n";
        }
        $purposeKnownTrans = [$purposeTransKnown,$purposeTransNotKnown];
        return trim($tempVal);
    }

    /** Adds a paragraph to $this->content.
     * @param string $heading translation key for the heading
     * @param string|array $content content of the paragraph
     * @param array $subHeadings if \$content is an array, translation keys for the subheadings for each sub-paragraph. Length must equal the length of \$content
     * @param array $parameters if $subHeadings is not empty, translation parameters for the subheadings
     * @param bool $subColon true if the subheadings should be followed by a colon, false otherwise
     * @param bool $addFragment if true, the fragment equal to $heading will be added to the heading link
     * @param bool $addFragmentSubheading if true, the fragment equal to the respective keys of $subHeadings will be added to the subheadings links
     * @return void
     */
    private function addParagraph(string $heading, string|array $content, array $subHeadings = [], array $parameters = [], bool $subColon = true, bool $addFragment = true, bool $addFragmentSubheading = true): void
    {
        $isEmpty = $content===[]; // true if first paragraph
        $linkedPageType = self::$isPageLink;
        $linkSubHeadings = $this->linkedSubHeadings!==[];
        if (is_string($content) || $isEmpty) {
            $content = [['',$isEmpty ? '' : $content]];
        } else {
            if (!$linkSubHeadings) { // subheadings, but link only in heading
                self::$isPageLink = false;
            }
            foreach ($subHeadings as $index => $subHeading) {
                self::$linkedPage = $this->linkedSubHeadings[$index] ?? self::$linkedPage;
                $content[$index] = [$subHeading!=='' ? $this->addHeadingLink($subHeading,$parameters,$addFragmentSubheading ? substr($subHeading,strrpos($subHeading,'.')+1) : '').($subColon ? ':' : '')."\n" : '',$content[$index]];
            }
        }
        self::$isPageLink = !$linkSubHeadings; // if links are in subheadings, avoid link in heading
        $this->content[$this->addHeadingLink('participation.headings.'.$heading,fragment: $addFragment ? $heading : '')] = $content;
        self::$isPageLink = $linkedPageType; // reset in case it was changed
    }

    /** Concatenates each element in $content and adds a span-tag to every second element.
     * @param string|array $content array whose contents need to be concatenated
     * @return string concatenated array
     */
    private function mergeContent(string|array $content): string
    {
        if (is_string($content)) {
            $content = [$content];
        }
        $returnString = '';
        foreach ($content as $index => $text) {
            $addSpan = self::$markInput && ($index%2)===1;
            $returnString .= $this->addMarkInput($text,$addSpan);
        }
        return $returnString;
    }
}