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
    private array $content;
    private const isPersonal = 'isPersonal'; // select parameter in translations

    public function createPDF(Request $request, array $routeIDs = []): Response {
        $session = $request->getSession();
        $committeeParam = $session->get(self::committeeParams);
        $savePDFParam = ['savePDF' => self::$savePDF, 'isComplete' => self::$isCompleteForm];
        $appNode = $this->getXMLfromSession($session,getRecent: true); // if supervisor was added while on core data page, indices of contributors have changed
        $coreDataArray = $this->xmlToArray($appNode->{self::appDataNodeName})[self::coreDataNode];
        $contributors = $this->getContributors($session);
        $isMultiple = $this->getMultiStudyGroupMeasure($appNode); // true if multiple studies, groups, or measure time points exist
        $studyArray = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
        $multipleStudies = count($studyArray)>1; // true if multiple studies exist
        $levelNames = ['study' => ['isMultiple' => $multipleStudies]];
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
        foreach ($allIDs as $studyID => $groupIDs) {
            $study = $studyArray[$studyID];
            foreach ($groupIDs as $groupID => $measureIDs) {
                foreach ($measureIDs as $measureID) {
                    $this->content = [];
                    $levelNames['study']['id'] = $multipleStudies ? $studyID+1 : 0;
                    $levelNames['study'][self::nameNode] = $study[self::nameNode];
                    $curRouteIDs = $this->createRouteIDs($this->increaseArrayValues([self::studyNode => $studyID, self::groupNode => $groupID, self::measureTimePointNode => $measureID]));
                    $groupArray = $this->addZeroIndex($study[self::groupNode]); // all groups of the current study
                    $multipleGroups = count($groupArray)>1;
                    $group = $groupArray[$groupID];
                    $levelNames['group'] = ['isMultiple' => $multipleGroups, 'id' => $multipleGroups ? $groupID+1 : 0, self::nameNode => $group[self::nameNode]];
                    $measureTimePointArray = $this->addZeroIndex($group[self::measureTimePointNode]);
                    $multipleMeasures = count($measureTimePointArray)>1;
                    $levelNames['measureTimePoint'] = ['isMultiple' => $multipleMeasures, self::nameNode => ''];
                    $measureTimePoint = $measureTimePointArray[$measureID];
                    $levelNames['measureTimePoint']['id'] = $multipleMeasures ? $measureID+1 : 0;
                    $groupsArray = $measureTimePoint[self::groupsNode];
                    $measuresArray = $measureTimePoint[self::measuresNode];
                    $addressee = $this->getAddressee($groupsArray);
                    $addresseeParam = [self::addressee => $addressee];
                    $informationArray = $measureTimePoint[self::informationNode];
                    $information = $this->getInformationString($informationArray);
                    $informationParam = [self::informationNode => $information];
                    $translationParams = array_merge($addresseeParam,$committeeParam,$informationParam); // contains all parameters that are needed in several combinations
                    $isPre = $information===self::pre;
                    $isNotPost = $information!==self::post;
                    $isOral = (!$isNotPost ? $informationArray[self::informationAddNode][self::descriptionNode] ?? '' : $informationArray[self::descriptionNode] ?? '')===self::consentOral;
                    $isLoanReceipt = false; // loan receipt is only possible if pre information
                    [$loanReceiptParameters,$consent] = [[],[]]; // $loanReceiptParameters: parameters for the view of the loan receipt
                    $parameters = array_merge($translationParams,[self::studyID => $multipleStudies ? $studyID+1 : 0, self::groupID => $multipleGroups ? $groupID+1 : 0, self::measureID => $multipleMeasures ? $measureID : 0]);
                    $boxHeadingPrefix = 'boxHeadings.projectdetails.';
                    $projectdetailsPrefix = 'projectdetails.pages.';
                    $participationPrefix = 'participation.';
                    $privacyPrefix = self::privacyNode.'.';
                    $isInformation = in_array($information,[self::pre,self::post]);
                    $isConsent = false; // gets true if any consent is given
                    $isToolPersonal = false; // gets true if document should be created by the tool and either personal data are collected or responsibility or transferOutside is answered such that the document can not be created by the tool
                    if ($isInformation) {
                        // contributors
                        $pageArray = $measureTimePoint[self::contributorNode];
                        $tempArray = $pageArray['leader'];
                        $leaderIndices = [];
                        $contributorsLeaderInstitution = []; // needed in study information. Contains leader with institution. Each element contains name and institution, separated by a comma
                        $contributorsLeader = []; // needed if deceit with complete post information. Each element is a string containing name, e-mail and eventually phone number, separated by a comma
                        $contributorsFurther = []; // needed if deceit with complete post information. One element for each contributor with task 'contact' and without task 'leader'. Each element contains name and eventually contact information equally to $contributorsLeader
                        $contributorsFurtherTasks = []; // needed in study information. One element for each contributor with at least one task except 'leader'. Each element contains only name and eventually tasks
                        $contributorsContact = []; // needed in study information and consent. One element for each contributor with task 'contact'. Each element is a string consisting of name, e-Mail and eventually phone number
                        if ($tempArray!=='') { // at least one contributor is leader
                            $leaderIndices = explode(',',$tempArray); // indices of contributors who are leader
                            foreach ($leaderIndices as $index) {
                                $curInfos = $contributors[$index][self::infosNode];
                                $contributorsLeader[] = rtrim(str_replace("\n",', ',$this->addContributorInfo($curInfos,true)),', '); // in complete post information without institution
                                $contributorsLeaderInstitution[] = $curInfos[self::nameNode].', '.$curInfos[self::institutionInfo];
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
                        foreach (array_diff(self::tasksNodes,['leader']) as $task) {
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
                                        $contributorTasks[$curIndex][] = $task!==self::otherTask ? $taskTranslated : $curContributor[self::taskNode][self::otherTask];
                                    }
                                    if ($task===self::taskData) {
                                        $contributorsData .= ', '.$curName.' ('.$eMail.($hasPhone ? ', '.$phone : '').')'; // names and eMails must not contain commas
                                        $contributorsDataContact[] = $nameTrans.$curName."\n".$mailTrans.$eMail.($hasPhone ? "\n".$phoneTrans.$phone : '');
                                    }
                                }
                            }
                        }
                        if (strlen($contributorsData)>0) {
                            $contributorsData = substr($contributorsData,2); // remove ', ' at the beginning
                        }
                        $taskDataTrans = $this->translateString('contributors.tasks.'.self::taskData);
                        foreach ($contributorTasks as $index => $tasks) {
                            $isCurContact = $isContact[$index];
                            if (in_array($taskDataTrans,$tasks)) { // do not name task 'data'
                                unset($tasks[array_search($taskDataTrans,$tasks)]);
                            }
                            $contInfos = $contributors[$index][self::infosNode];
                            $curInfos = implode(', ',array_merge([$contInfos[self::nameNode],$contInfos[self::eMailNode]],array_key_exists(self::phoneNode,$contInfos) ? [$contInfos[self::phoneNode]] : []));
                            if (!in_array($index,$leaderIndices)) { // contributor has further tasks, but not leader, in current variant
                                $curTasks = ' ('.implode(', ',$tasks).')';
                                $contributorsFurtherTasks[] = $contInfos[self::nameNode].( $tasks!==[] ? $curTasks : '');
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
                        $textsArray = $measureTimePoint[self::textsNode];
                        $projectTitleParam = [self::projectTitle => $this->getProjectTitleParticipants($session)];
                        $translationParams = array_merge($translationParams,$projectTitleParam);
                        $intro = [$textsArray[self::introNode][self::descriptionNode] ?? $this->translateString($textsPrefix.self::introNode.'.template',$translationParams)];
                        $tempVal = implode('; ',$contributorsLeaderInstitution);
                        $leaderPrefix = $tempPrefix.'leader.';
                        $leaderHeading = $this->addHeadingLink($leaderPrefix.'title',fragment: $isMultiple ? 'leader' : ''); // set here to avoid routeIDs if only one variant
                        $intro[] = $this->translateStringPDF($leaderPrefix.'text',['leader' => $this->replaceString($tempVal,';')]);
                        if ($contributorsFurtherTasks!==[]) {
                            $intro[] = $this->translateStringPDF($tempPrefix.'contributors',['contributors' => $this->replaceString(implode(', ',$contributorsFurtherTasks))]);
                        }
                        $tempArray = $coreDataArray[self::funding];
                        $tempVal = '';
                        if ($tempArray!=='') {
                            $numFunding = count($tempArray);
                            $isQualiFunding = array_key_exists(self::fundingQuali,$tempArray);
                            $funding = '';
                            if (!$isQualiFunding) {
                                $fundingPrefix = self::coreDataNode.'.'.self::funding.'.';
                                $fundingIndex = 0;
                                foreach ($tempArray as $type => $source) {
                                    $funding .= ($fundingIndex>0 && $fundingIndex===($numFunding-1) ? $this->translateString('multiple.inputs.lastAnd').' ' : ', ').$this->translateString($fundingPrefix.$type).' ('.$source[self::descriptionNode].')';
                                    ++$fundingIndex;
                                }
                            }
                            $tempVal = $this->translateStringPDF($tempPrefix.self::funding,['isQualiFunding' => $this->getStringFromBool($isQualiFunding), self::funding => substr($funding,2)]); // no replaceString because string ends with custom text which may contain commas
                        }
                        $intro[] = $tempVal;

                        // background and goals
                        self::$routeIDs = $curRouteIDs;
                        self::$linkedPage = self::textsNode;
                        $this->addParagraph(self::goalsNode,$textsArray[self::goalsNode] ?? '');

                        // conflict
                        $conflict = $coreDataArray[self::conflictNode][self::participantDescription] ?? '';
                        if ($conflict!=='') {
                            self::$routeIDs = '';
                            self::$linkedPage = self::coreDataNode;
                            $this->addParagraph(self::conflictNode,$conflict);
                        }

                        // procedure
                        self::$routeIDs = $curRouteIDs;
                        self::$linkedPage = self::textsNode;
                        $content = [$this->getLocation($measureTimePoint,$committeeParam),$textsArray[self::procedureNode] ?? ''];
                        if ($isNotPost) { // add duration
                            $content[] = $this->getDuration($measuresArray[self::durationNode], $multipleMeasures);
                        }
                        $this->linkedSubHeadings = [self::measuresNode,self::textsNode,self::measuresNode]; // loop in addParagraph is based on subHeadings, therefore all links can be added here
                        $this->addParagraph(self::procedureNode,$content,array_merge([$boxHeadingPrefix.self::locationNode,$participationPrefix.self::procedureNode],$isNotPost ? [$boxHeadingPrefix.self::durationNode] : []));

                        // finding
                        self::$linkedPage = self::textsNode;
                        $this->linkedSubHeadings = [];
                        $findingArray = $measureTimePoint[self::burdensRisksNode][self::findingNode];
                        $findingConsent = '';
                        $participationConsentPrefix = self::consentNode.'.';
                        if ($textsArray!=='' && array_key_exists(self::findingTextNode,$textsArray) && $findingArray[self::chosen]==='0') {
                            $tempArray = $textsArray[self::findingTextNode];
                            $this->addParagraph(self::findingNode, $tempArray[self::findingTemplate]==='1' ? $this->translateString($textsPrefix.self::findingTextNode.'.template',[self::consentNode => $this->getStringFromBool($findingArray[self::informingNode]===self::informingConsent), self::addressee => $addressee]) : $tempArray[self::descriptionNode]);
                            // finding consent
                            if ($findingArray[self::informingNode]===self::informingConsent) {
                                $findingConsent = $this->translateStringPDF($participationConsentPrefix.self::findingNode);
                            }
                        }

                        // get texts for paragraphs compensation, pro/con, criteria and voluntary. $paragraphs: Keys: key used for addParagraph() call. Values: 0: further arguments for the addParagraph() call
                        // compensation
                        [$linkedString, $subHeadingsString,$parametersString,$noPageBreakString,$subColonString] = ['linked','subHeadings','parameters','noPageBreak','subColon'];
                        $paragraphs = [self::compensationNode => [$linkedString => self::compensationNode,self::content => $this->getCompensation($measureTimePoint[self::compensationNode],$addresseeParam,$information,$session,true),$subHeadingsString => [], $parametersString => [],$noPageBreakString => false, $subColonString => true]];

                        // pro, con
                        $translationPrefix = $participationPrefix.'proCon.';
                        $tempArray = $textsArray[self::proNode] ?? '';
                        $tempVal = $tempArray[self::descriptionNode] ?? '';
                        $tempPrefix = $textsPrefix.'pro.template.';
                        $content = [(($tempArray[self::proTemplate] ?? '')==='1' ? $this->translateString($tempPrefix.'start',$translationParams).' '.$tempArray[self::proTemplateText].' '.$this->translateString($tempPrefix.'middle').' '.$tempVal."\n".$this->translateString($tempPrefix.'end',$addresseeParam) : $tempVal)];
                        $content[] = trim($this->getConTemplateText($measureTimePoint));
                        $paragraphs[self::proNode] = [$linkedString => self::textsNode, self::content => $content, $subHeadingsString => [$translationPrefix.self::proNode,$translationPrefix.self::conNode],$parametersString => ['isBurdensRisks' => $this->getStringFromBool($this->getBurdensRisks($measureTimePoint[self::burdensRisksNode]))],$noPageBreakString => false, $subColonString => true];

                        // criteria
                        if ($isNotPost) {
                            $tempPrefix = $participationPrefix.self::criteriaNode.'.';
                            $tempArray = $this->getCriteria($measureTimePoint,$addressee,false);
                            $paragraphs[self::criteriaNode] = [$linkedString => self::groupsNode, self::content => $tempArray, $subHeadingsString => array_merge([$tempPrefix.self::criteriaIncludeNode],count($tempArray)===2 ? [$tempPrefix.self::criteriaExcludeNode] : []),$parametersString => ['type' => $addressee],$noPageBreakString => false, $subColonString => false];
                        }

                        // voluntary
                        $consentArray = $measureTimePoint[self::consentNode];
                        $voluntaryArray = $consentArray[self::voluntaryNode];
                        $chosen2 = $voluntaryArray[self::chosen2Node] ?? '';
                        $consentType = $consentArray[self::consentNode][self::chosen];
                        $isConsent = in_array($consentType,self::consentTypesAny);
                        $tempArray = $consentArray[self::terminateConsNode];
                        $terminateConsParam = [self::terminateConsNode => $this->getStringFromBool($tempArray[self::chosen]==='0')];
                        $terminateParticipantsArray = $consentArray[self::terminateParticipantsNode];
                        $tempPrefix = $participationPrefix.self::voluntaryNode.'.';
                        $voluntaryParams = array_merge($addresseeParam,$terminateConsParam,['isVoluntary' => $this->getStringFromBool(in_array((!array_key_exists(self::chosen2Node,$voluntaryArray) || $chosen2===self::voluntaryNotApplicable) ? $voluntaryArray[self::chosen] : $chosen2,['','yes']))]);
                        $content = trim($this->translateStringPDF($tempPrefix.(!$isNotPost ? self::post : self::pre), array_merge($voluntaryParams,[
                                self::descriptionNode => $tempArray[self::terminateConsParticipationNode] ?? '',
                                self::attendanceNode => $this->getStringFromBool(($informationArray[self::attendanceNode] ?? '')==='0'),
                                'isConsent' => $this->getStringFromBool($isConsent)])))
                            .($isNotPost ? ' '.$this->getCompensationTerminate($measureTimePoint[self::compensationNode],$voluntaryParams) : '')
                            .' '.$this->translateStringPDF($tempPrefix.self::terminateParticipantsNode,['type' => $terminateParticipantsArray[self::chosen], self::descriptionNode => $terminateParticipantsArray[self::descriptionNode] ?? '']);
                        $paragraphs[self::voluntaryNode] = [$linkedString => self::consentNode,self::content => $content,$subHeadingsString => [],$parametersString => [],$noPageBreakString => false, $subColonString => true];

                        // privacy
                        $translationPrefix = $participationPrefix.self::privacyNode.'.';
                        $privacyArray = $measureTimePoint[self::privacyNode];
                        $createArray = $privacyArray[self::createNode];
                        $privacyCreate = $createArray[self::chosen];
                        $dataPersonal = '';
                        $isDataPersonal = false; // gets true if research data is/may be personal
                        $dataResearch = $privacyArray[self::dataResearchNode] ?? ''; // contains the selected research data
                        $isDataResearch = array_key_exists(self::dataResearchNode,$privacyArray); // research data is/may be personal or a personal marking is used
                        $hasDataResearch = $dataResearch!==''; // true if at least one data type is selected
                        $isDataSpecial = $isDataResearch && $hasDataResearch && array_intersect(array_keys($dataResearch),self::dataSpecialTypes)!==[]; // true if any data special is collected for research
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
                        if ($privacyCreate===self::createTool) {
                            if ($createArray[self::descriptionNode]==='1') {
                                $responsibility = $privacyArray[self::responsibilityNode];
                                $transferOutside = $privacyArray[self::transferOutsideNode];
                                if ($responsibility===self::responsibilityOnlyOwn && $transferOutside===self::transferOutsideNo) {
                                    if ($hasDataResearch) {
                                        $otherTypes = ['dataResearchOther',self::dataResearchSpecialOther];
                                        $tempPrefix = $dataResearchPrefix.'types.';
                                        foreach ($dataResearch as $type => $description) {
                                            $isNotOther = !in_array($type,$otherTypes);
                                            $translated = ($isNotOther ? $this->translateStringPDF($tempPrefix.$type) : '').($description!=='' ? ($isNotOther ? ' (' : '').$description.($isNotOther ? ')' : '') : '');
                                            if (in_array($type,self::dataSpecialTypes)) {
                                                $dataResearchSpecialTrans[] = $translated;
                                            }
                                            else {
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
                                    $purposeResearch = $privacyArray[self::purposeResearchNode] ?? '';
                                    $isPurposeResearch = $purposeResearch!=='' && !array_key_exists(self::purposeNo,$purposeResearch);
                                    $purposeFurther = $privacyArray[self::purposeFurtherNode] ?? ''; // if marking is 'other', keys does not exist
                                    $isPurposeFurther = $purposeFurther!=='' && !array_key_exists(self::purposeFurtherNode.self::purposeNo,$purposeFurther);
                                    $isPurpose =  $isPurposeResearch || $isPurposeFurther;
                                    $isToolPersonal = $markingChosen!==self::markingOther && ($isDataPersonal || $isMarkingPersonal || $isPurpose);
                                }
                                elseif (in_array($responsibility,['onlyOther','multiple','private']) || $transferOutside==='yes') {
                                    $isToolPersonal = true;
                                }
                            }
                        }
                        $isPersonal = $privacyCreate===self::createSeparate || $isToolPersonal; // true if personal data is collected. Also true if processing is checked later. False if research data are personal, but marking is other.
                        $translationParams[self::isPersonal] = $this->getStringFromBool($isPersonal);
                        // other sources
                        $otherSourcesArray = $measuresArray[self::otherSourcesNode];
                        $otherSourcesSentence = '';
                        if ($otherSourcesArray[self::chosen]==='0') {
                            $otherSourcesSentence = $this->translateStringPDF($translationPrefix.self::otherSourcesNode,[self::descriptionNode => $otherSourcesArray[self::otherSourcesNode.self::descriptionCap] ?? '']);
                        }
                        $otherSourcesParam = [self::otherSourcesNode => $otherSourcesSentence];
                        [$markingSentences,$codeCompensationSentences] = $this->getMarkingSentences($privacyArray,$addresseeParam);
                        $isMarking = $markingSentences!=='';
                        $isMarkingOtherPersonal = $isMarkingOther && $isDataPersonal;
                        if ($isPersonal || $isMarkingOtherPersonal) {
                            $content = $this->translateStringPDF($translationPrefix.(($isToolPersonal && $dataPersonal===self::dataPersonalMaybe && !$isMarkingPersonal && !$isPurpose) ? self::dataPersonalMaybe : 'personal'),$otherSourcesParam); // self::dataPersonalMaybe if the only personal data are research data that may be personal
                        }
                        // data reuse
                        $dataReuseArray = $measureTimePoint[self::dataReuseNode];
                        $tempArray = $this->getDataReuseHow($measureTimePoint);
                        [$dataReuseContent,$isNotOwn,,$dataReuseHow] = [$tempArray[0],$tempArray[1],$tempArray[2],$tempArray[3]];
                        $personalParam = $this->getPrivacyReuse($privacyArray);
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
                        if ($isNotOwn || $isSelf || $isReuseHowTwice) {
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
                        if ($isPersonal) {
                            $content .= "\n".$dataReuseContent;
                        }
                        elseif (!$isMarkingOtherPersonal) {
                            $content = $this->translateStringPDF(($privacyCreate==='separateLater' || $isMarkingOther) ? $translationPrefix.'noTool' : $translationPrefix.self::markingNode.'.start',array_merge($otherSourcesParam,['codeMarking' => ($isMarking ? $markingSentences : '')."\n".$dataReuseContent]));
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
                            $this->addParagraph($key,$paragraph[self::content],$paragraph[$subHeadingsString],$paragraph[$parametersString],$paragraph[$noPageBreakString],$paragraph[$subColonString],$key!==self::compensationNode);
                        }
                        // add data privacy paragraph
                        self::$linkedPage = self::privacyNode;
                        $this->addParagraph(self::privacyNode,trim($content),addFragment: false);

                        // contact
                        $contributorsLink = $isMultiple ? self::contributorNode : 'contributors';
                        self::$linkedPage = $contributorsLink;
                        $translationParams['contributors'] = $this->replaceString(implode('; ',$contributorsContact),';');
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
                                $loanReceiptParameters = ['content' => $this->getTemplateChoice($chosen) ? ($chosen===self::templateText ? [$apparatusArray[self::descriptionNode]] : [$this->translateString($tempPrefix.self::template), $this->translateString($tempPrefix.'loan')]) : [], 'confirm' => $receiptArray[self::chosen]===self::templateText ? $receiptArray[self::descriptionNode] : $this->translateString($projectdetailsPrefix.self::measuresNode.'.'.self::loanNode.'.'.self::template), 'heading' => $this->addHeadingLink('loanReceipt.title',fragment: self::loanNode)];
                                $isLoanReceipt = true;
                            }
                        }

                        // consent -> one array containing all the information needed for the consent because it may be a separate document
                        $consentHeading = '';
                        $optionalConsent = []; // consent for finding or personalKeep if informing/keep is optional
                        $dataSpecialParam = ['isDataSpecial' => $this->getStringFromBool($isDataSpecial)];
                        if ($isConsent) { // consent is given
                            $tempPrefix = 'consent.';
                            self::$linkedPage = self::consentNode;
                            $consentHeading = $this->addHeadingLink($tempPrefix.'title',$translationParams,self::consentNode);
                            $translationParams['informationType'] = $isPre ? $informationArray[self::descriptionNode] : $informationArray[self::informationAddNode][self::descriptionNode];
                            $consent = [$this->translateStringPDF($tempPrefix.'start',array_merge($translationParams,$terminateConsParam,$voluntaryParams))];
                            $consent[] = $this->translateStringPDF($tempPrefix.'personal',array_merge($translationParams,$dataSpecialParam,['dataSpecial' => $this->replaceString(implode(', ',$dataResearchSpecialTrans)), 'contributors' => $this->replaceString($contributorsData,replace: $this->translateStringPDF('or'))]));
                            if ($isPre) {
                                $isNotLocation = in_array($measuresArray[self::locationNode][self::chosen],[self::locationOnline,'']); // for insuranceWay
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
                                            $consent[] = $tempVal===self::templateText ? $tempArray[self::descriptionNode] : $this->translateString($typePrefix.self::template,$committeeParam).($isApparatus && $isLoan ? $this->translateString($typePrefix.'loan') : '');
                                        }
                                    }
                                }
                            }
                            $consent[] = $this->translateStringPDF($tempPrefix.'copy',$translationParams);
                        } // if consent is given
                        $isConsent = $consent!==[];

                        // data privacy: one array containing all the information because it may be a separate document
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
                            $isDataOnlineProcessingResearch = ($privacyArray[self::dataOnlineProcessingNode] ?? '')===self::dataOnlineProcessingResearch;
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
                                        }
                                        else {
                                            $tempPrefix = $anonymizationPrefixTool.'types.';
                                            $tempVal = [];
                                            foreach ($tempArray as $type => $description) {
                                                $tempVal[] = $type!==self::anonymizationOther ? $this->translateString($tempPrefix.$type) : $description;
                                            }
                                            $anonymizationResearch = $this->translateString($anonymizationPrefixTool.'start').$this->replaceString(implode(', ', $tempVal)).'.';
                                        }
                                    }
                                    // storage
                                    if (array_key_exists(self::storageNode, $privacyArray)) {
                                        $tempArray = $privacyArray[self::storageNode];
                                        $storage = $tempArray[self::chosen];
                                        if ($storage===self::storageDelete) {
                                            $anonymizationResearch .= "\n".$this->translateStringPDF($privacyPrefix.self::storageNode, [self::descriptionNode => $tempArray[self::descriptionNode].'.']);
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
                                                $tempVal[] = $trans.' '.$description.(($personalKeepConsentArray[$type] ?? '')==='optional'? $optionalTrans : '');
                                            }
                                            $anonymizationResearch .= "\n".$this->translateString($personalKeepPrefixTool.'start').$this->replaceString(implode(', ',$tempVal)).$this->translateString($personalKeepPrefixTool.'end');
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
                                                }
                                                elseif ($type===self::personalKeepDemonstration) {
                                                    $isDemonstrationConsent = true;
                                                }
                                                elseif ($type===self::personalKeepTeaching) {
                                                    $isTeachingConsent = true;
                                                }
                                                $optionalConsent[] = $this->translateStringPDF($tempVal,['type' => $personalKeepTrans[$type]]);
                                            }
                                        }
                                    }
                                    // access if research data is personal
                                    $tempArray = $privacyArray[self::accessNode] ?? '';
                                    if ($tempArray!=='') {
                                        $tempVal = $this->addAccess($tempArray,self::dataPersonalNode,$committeeParam,$anyOrderProcessingKnown,$purposesKnownTrans,$allPurposesTranslated[self::dataPersonalNode]);
                                        foreach (array_merge([self::dataPersonalNode],$isDataOnlineProcessingResearch ? [self::purposeTechnical] : []) as $type) {
                                            $transferContent[] = $this->translateStringPDF($accessStart,[self::purposeNode => $allPurposesTranslated[$type]])."\n".$tempVal;
                                        }
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
                                                $tempVal .= "• ".($typeWoPrefix!==self::purposeDataOther ? $this->translateString($purposeDataPrefix.$typeWoPrefix) : $description)."\n";
                                            }
                                        }
                                    }
                                    else {
                                        $tempVal = "• ".$this->translateString($purposeDataPrefix.'ip');
                                    }
                                    if ($tempVal!=='') {
                                        $purposes[] = trim($purposeStart.$this->translateStringPDF($processingPrefix.'purposeTypes.'.$purposeWoPrefix).($purposeWoPrefix===self::purposeRelatable ? ' ('.$this->replaceString(implode(', ',$relatable)).')' : '').":\n".$tempVal)."\n\n";
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
                                                $markingRemove = $this->translateString($markingRemovePrefix.'start', [self::purposeNode => $this->translateStringPDF( $processingPrefix.'purposeTypesGen.'.$purposeWoPrefix)]).$this->translateStringPDF($privacyPrefix.self::markingRemoveNode.'.'.$tempVal, [self::descriptionNode => ($tempArray[self::descriptionNode] ?? '').($tempVal===self::markingRemoveNode.'Later' ? '.' : ',')]);
                                                if ($tempVal===self::markingRemoveLater) {
                                                    $markingRemove .= $this->translateString($markingRemovePrefix.'laterEnd', ['isName' => $this->getStringFromBool($isMarkingName)]).' '.$tempArray['laterDescription'];
                                                }
                                                else { // immediately
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
                                                $personalRemove = $this->translateString($privacyPrefixTool.self::personalRemoveNode.'.start', $purposeParam).$this->translateStringPDF($privacyPrefix.self::personalRemoveNode.'.'.$tempVal, [self::descriptionNode => ($tempArray[self::descriptionNode] ?? '').($tempVal===self::personalRemoveImmediately ? '.' : '')]);
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
                                            $listArray[] = $type!==self::listOther ? $this->translateString($tempPrefix.$type) : $description;
                                        }
                                    }
                                    $tempVal = $this->translateStringPDF($processingPrefix.self::listNode).$this->replaceString(implode(', ',$listArray)).'.';
                                }
                                $processingContent[] = $markingSentences.$tempVal;
                            }
                            if ($isCodeCompensation && $isPurposeFurtherCompensation) {
                                $processingContent[] = $codeCompensationSentences;
                            }
                            $tempVal = $privacyArray[self::processingFurtherNode] ?? '';
                            if ($tempVal!=='') { // avoid multiple empty lines if nothing was entered
                                $processingContent[] = $tempVal;
                            }
                            $tempPrefix = $processingPrefix.'end.';
                            $personal = $personalParam['personal'];
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
                                        $tempVal .= $this->translateString($tempPrefix.$textPart).$tempArray[$textPart].($textPart!==self::orderProcessingNode.'Start' ? '.' : '');
                                    }
                                    $transferContent[] = $this->translateStringPDF($orderProcessingKnownPrefix.'known',[self::purposeNode => $this->replaceString(implode(', ',$purposesKnownTrans[0]))]).$tempVal;
                                }
                                if ($anyOrderProcessingKnown[1]) {
                                    $transferContent[] = $this->translateStringPDF($orderProcessingKnownPrefix.'unknown',[self::purposeNode => $this->replaceString(implode(', ',$purposesKnownTrans[1])), 'isKnown' => $this->getStringFromBool($isKnown)]);
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
                        $privacyParameters = array_merge($committeeParam,['privacyContent' => $privacyContent, 'data' => $contributorsDataContact, 'privacyHeading' => $this->addHeadingLink($privacyPrefix.'title'), self::committeeParams => $committeeParam]);
                        if ($findingConsent!=='') { // finding consent is optional -> after the optional privacy consents
                            $optionalConsent[] = $findingConsent;
                        }

                        self::$linkedPage = self::informationNode;
                        $participationHeading = $this->addHeadingLink($participationPrefix.'title',$informationParam);
                        $tempVal = $this->addHeadingLink($participationPrefix.'oral');
                        self::$linkedPage = $contributorsLink; // for leaderHeading
                        // create string for header containing the project title and eventually the level IDs
                        $levelString = '';
                        $levelIDs = [];
                        $tempPrefix = 'projectdetails.headings.';
                        foreach ($levelNames as $level => $infos) {
                            if ($infos['isMultiple']) {
                                $levelIDs[] = $this->translateString($tempPrefix.$level).' '.$infos['id'];
                            }
                        }
                        if ($levelIDs!==[]) {
                            $levelString = ' - '.implode(', ',$levelIDs);
                        }
                        $parameters = array_merge($parameters,$savePDFParam,$projectTitleParam,$privacyParameters,
                            ['participationHeading' => $participationHeading,
                             'intro' => $intro,
                             'leaderHeading' => $leaderHeading,
                             'leaderInstitution' => $contributorsLeaderInstitution,
                             'levelNames' => $levelString,
                             'isOral' => $isOral,
                             'content' => $this->content,
                             self::consentNode => $consent,
                             'consentType' => $consentType,
                             'consentHeading' => $consentHeading,
                             'yesTrans' => $this->translateString('buttons.yes'), // for optional consent
                             'noTrans' => $this->translateString('buttons.no'), // for optional consent
                             'optionalConsent' => $optionalConsent,
                             'oralHint' => $tempVal,
                             'isPersonal' => $this->getStringFromBool($isPersonal || $isMarkingOtherPersonal),
                             'privacyParameters' => $privacyParameters,
                             'numStudies' => count($this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode])),
                             self::studyID => $studyID+1]);
                    }
                    else { // no documents are created
                        $levelArray = [];
                        $numLevels = 0;
                        foreach ($levelNames as $level => $info) {
                            if ($info['isMultiple']) { // multiple elements exist
                                $name = $info[self::nameNode];
                                ++$numLevels;
                                $levelArray[] = $this->translateStringPDF($boxHeadingPrefix.$level).' '.$info['id'].($level!==self::measureTimePointNode ? ($name!=='' ? ' ('.$name.')' : '') : '');
                            }
                        }
                        $parameters = array_merge($translationParams,$savePDFParam,['parameters' => array_merge($parameters,$informationParam,['levelNames' => implode(', ',$levelArray), 'numLevels' => $numLevels, 'routeIDs' => $curRouteIDs])]);
                    }
                    // render the current document
                    try {
                        $curHtml = $this->renderView('PDF/_participation.html.twig',$parameters);
                        if ($isConsent) { // consent is given
                            self::$linkedPage = self::consentNode;
                            $curHtml .= $this->renderView('PDF/_participationConsent.html.twig',array_merge($parameters,[],$isOral ? ['isSeparate' => true] : []));
                        }
                        // data privacy
                        if ($isToolPersonal) {
                            self::$linkedPage = self::privacyNode;
                            $curHtml .= $this->renderView('PDF/_dataPrivacy.html.twig',$parameters);
                        }
                        // complete post information
                        if ($isPre && $this->getInformationIII($informationArray)) { // partial or deceit with post information
                            $translationPrefix = 'completePost.';
                            self::$linkedPage = self::informationIIINode;
                            $completePostHeading = $this->addHeadingLink($translationPrefix.'title');
                            self::$linkedPage = self::informationNode;
                            $pageArray = $measureTimePoint[self::informationIIINode] ?: [];
                            $content = ['intro' => [$this->translateStringPDF($translationPrefix.'intro',$translationParams),'']];
                            foreach (array_keys(self::informationIIIInputsTypes) as $input) {
                                $content[$input] = [$this->translateStringPDF($translationPrefix.$input,$addresseeParam),$pageArray[$input] ?? ''];
                            }
                            $content['end'] = [$this->translateStringPDF($translationPrefix.'end',$addresseeParam),''];
                            // contributors* variables are defined. They are defined if information is either pre or post and this if can only be true if information is pre
                            $contributorsLeader[0] = $this->translateStringPDF($translationPrefix.'contributors').($contributorsLeader[0] ?? '');

                            $curHtml .= $this->renderView('PDF/_completePost.html.twig',array_merge($parameters,[
                                'content' => $content,
                                'completePostHeading' => $completePostHeading,
                                'isOral' => $informationArray[self::informationAddNode][self::preCompleteType]==='oral',
                                'contributors' => array_merge($contributorsLeader,$contributorsFurther), // $contributorsFurther is defined if information is pre or post
                                'oralHint' => $this->addHeadingLink($translationPrefix.'oral')]));
                        }
                        // loan receipt
                        if ($isLoanReceipt) {
                            $curHtml .= $this->renderView('PDF/_loanReceipt.html.twig',array_merge($parameters,$loanReceiptParameters));
                        }
                        $ids = [$studyID,$groupID,$measureID];
                        $html[] = $ids;
                        $allHtml .= $curHtml;
                        if (self::$savePDF) {
                            $this->generatePDF($session,$curHtml,$this->concatIDs($ids,'participation'));
                        }
                    }
                    catch (\Throwable $throwable) { // catches exceptions and error
                        return $this->setErrorAndRedirect($session);
                    }
                } // for measureID
            } // for groupID
        } // for studyID
        $session->set(self::pdfParticipation,$allHtml);
        $session->set(self::pdfParticipationArray,$html);
        return new Response($allHtml);
    }

    /** Creates a string containing information about a contributor. Each information is added in a new line.
     * @param array $infos array containing the information
     * @param bool $addContact if true, the phone (if existing) and the eMail are added
     * @param bool $addInstitution if true, the institution is added
     * @return string information about a contributor
     */
    private function addContributorInfo(array $infos, bool $addContact, bool $addInstitution = false): string {
        $returnString = '';
        foreach (array_merge([self::nameNode],$addInstitution ? [self::institutionInfo] : [],$addContact ? array_merge(array_key_exists(self::phoneNode,$infos) ? [self::phoneNode] : [],[self::eMailNode]) : []) as $info) {
            $returnString .= $infos[$info]."\n";
        }
        return $returnString;
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
    private function addAccess(array $accessArray, string $purposeWoPrefix, array $committeeParam, array &$anyOrderProcessingKnown, array &$purposeKnownTrans, string $purposeTrans): string {
        $accessPrefix = self::privacyNode.'.transfer.'.self::accessNode.'.';
        $tempVal = '';
        $tempPrefix = $accessPrefix.'types.';
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
            $tempVal .= "• ".($typeWoPrefix!=='accessOther' ? $this->translateStringPDF($tempPrefix.$typeWoPrefix,array_merge($committeeParam,[self::descriptionNode => in_array($typeWoPrefix,self::accessOthers) ? $description : ($isContributorsOther && $orderProcessing[self::chosen]==='1' ? ' ('.$orderProcessing[self::descriptionNode].')' : '')])) : $description)."\n";
        }
        $purposeKnownTrans = [$purposeTransKnown,$purposeTransNotKnown];
        return trim($tempVal);
    }

    /** Adds a paragraph to $this->content.
     * @param string $heading translation key for the heading
     * @param string|array $content content of the paragraph
     * @param array $subHeadings if \$content is an array, translation keys for the subheadings for each sub-paragraph. Length must equal the length of \$content
     * @param array $parameters if $subHeadings is not empty, translation parameters for the subheadings
     * @param bool $noPageBreak true if the content of each element in $content should be on the same page, false otherwise
     * @param bool $subColon true if the subheadings should be followed by a colon, false otherwise
     * @param bool $addFragment if true, the fragment equal to $heading will be added to the heading link
     * @param bool $addFragmentSubheading if true, the fragment equal to the respective keys of $subHeadings will be added to the subheadings links
     * @return void
     */
    private function addParagraph(string $heading, string|array $content, array $subHeadings = [], array $parameters = [], bool $noPageBreak = false, bool $subColon = true, bool $addFragment = true, bool $addFragmentSubheading = true): void {
        $isEmpty = $content===[]; // true if first paragraph
        $linkedPageType = self::$isPageLink;
        $linkSubHeadings = $this->linkedSubHeadings!==[];
        if (is_string($content) || $isEmpty) {
            $content = [['',$isEmpty ? '' : $content]];
        }
        elseif ($subHeadings!==[]) {
            if (!$linkSubHeadings) { // subheadings, but link only in heading
                self::$isPageLink = false;
            }
            foreach ($subHeadings as $index => $subHeading) {
                self::$linkedPage = $this->linkedSubHeadings[$index] ?? self::$linkedPage;
                $content[$index] = [$subHeading!=='' ? $this->addHeadingLink($subHeading,$parameters,$addFragmentSubheading ? substr($subHeading,strrpos($subHeading,'.')+1) : '').($subColon ? ':' : '')."\n" : '',$content[$index]];
            }
        }
        self::$isPageLink = !$linkSubHeadings; // if links are in subheadings, avoid link in heading
        $this->content[$this->addHeadingLink('participation.headings.'.$heading,fragment: $addFragment ? $heading : '')] = [$content,$noPageBreak];
        self::$isPageLink = $linkedPageType; // reset in case it was changed
    }
}