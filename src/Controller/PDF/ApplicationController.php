<?php

namespace App\Controller\PDF;

use App\Abstract\PDFAbstract;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class ApplicationController extends PDFAbstract
{
    use AppDataTrait;
    use ProjectdetailsTrait;

    private array $content = []; // array that is passed to the template
    private array $boxContent; // array which contains the content for the project details questions
    private string $studyID;
    private string $groupID;
    private string $measureID;
    private bool $isAnyTwoAddressees = false; // gets true if two addressees exist in at least one variant
    private const main = 'main'; // top part of the content of a box
    private const sub = 'sub'; // bottom part (e.g., further description) of the content of a box
    private const subHeading = 'subHeading'; // italic part of the heading
    private const paragraph = 'paragraph'; // main heading above a box
    private const inputPage = 'inputPage'; // key for $boxContent
    private string $inputPage = ''; // page name that gets visible if hovered over heading
    private string $documentHint = ''; // hint saying the wording is identical to participation information
    private const noBox = 'noBox'; // key indicating that the box is empty
    private string $noBoxTrans; // translation of box content indicating that no inputs were necessary
    private const dummyBox = 'dummyBox'; // key indicating that the levels should not be added to the box
    private array $levelTrans; // translations of levels

    public function createPDF(Request $request, array $routeIDs = []): ?Response {
        $session = $request->getSession();
        try {
            $committeeParam = $session->get(self::committeeParams);
            $appNode = $this->getXMLfromSession($session, getRecent: true); // if supervisor was added while on core data page, indices of contributors have changed
            $this->noBoxTrans = $this->translateStringPDF(self::noBox);

            // projectdetails information
            $studyArray = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
            /*
             * $allAddressees: all possible addressees with a value true if the addressee occurs, false otherwise. Only addressees where either pre or post information happens are considered.
             * $isAnyOtherSources: true if any other sources question was answered with yes.
             * $isAnyOnline: true if any location is online
             * $isAnyBurdensRisks: indicates if any burdens (0), risks (1), or burdens/risks for contributors (2) are selected (respectively answered with no in the last case).
             * $isAnyBurdensNo: true if any 'noBurdens' was selected.
             * $anyVoluntary: array with two elements regarding the voluntary question: 0: true if any yes-description needs to be given, 1: true is any no-description needs to be given, otherwise false in both cases.
             * anyConsent: array with two elements regarding the consent question: 0: consent question was answered with 'no', 1: true if any assent question was answered with 'no', otherwise false in both cases.
             * $isAnyCompensationDescription: true if any further description of compensation is given, false otherwise (i.e., either no compensation or no further description is given).
             * $isAnyDataPrivacy: true if any data privacy should be created with the tool, false otherwise
             * $isAnyDataOnlineTechnical: true if any data online question was answered with technical
             * $isAnyMarking: true if any marking is used, false otherwise
             * $isAnyMarkingPersonal: true if any personal marking is used, false otherwise. May only be true if $isAnyMarking is true
             * $isAnyDataReuse: true if the data reuse questions is asked at any time point, false otherwise
             * $isAnyDataReuseSelf: true if the data reuse self question is asked at any time point, false otherwise
             * $isAnyDataReuseHow: true is the data reuse how question is asked at any time point, false otherwise
            */
            [$allAddressees, $isAnyOtherSources, $isAnyOnline, $isAnyBurdensRisks, $isAnyBurdensNo, $anyVoluntary, $anyConsent, $isAnyCompensationDescription, $isAnyDataPrivacy, $isAnyMarking, $isAnyMarkingPersonal, $isAnyDataReuse, $isAnyDataReuseSelf, $isAnyDataReuseHow] = [[self::addresseeParticipants => false, self::addresseeChildren => false, self::addresseeWards => false], false, false, [self::burdensNode => false, self::risksNode => false, self::burdensRisksContributorsNode => false], false, [false, false], [false, false], false, false, false, false, false, false, false];
            /* The following values are true if either for third parties or participants at least one of the pre information questions was answered in the respective way:
             * $isAnyPre: yes
             * $isAnyNotPre: no
             * $isNotAnyPreYet: not yet
             * Further variables:
             * $isAnyCompletePost: true if any pre information is partial or deceit and complete post information is given. May only be true is $isAnyPre is true
             * $isAnyPost: indicates true if any post information of the third parties was answered with yes
             */
            [$isAnyPre, $isAnyNotPre, $isNotAnyPreYet, $isAnyCompletePost, $isAnyPost] = [false, false, false, false, false];
            $preNo = self::pre.'No';
            $preNotYet = self::pre.'NotYet';
            $completePost = 'completePost';
            $burdensNo = self::burdensNode.'No';
            $voluntaryNo = self::voluntaryNode.'No';
            $consentNo = self::consentNode.'No';
            $allTrue = [self::addresseeParticipants => false, self::addresseeChildren => false, self::addresseeWards => false, self::pre => false, $preNo => false, $preNotYet => false, $completePost => false, self::post => false, self::otherSourcesNode => false, self::locationNode => false, self::burdensNode => false, $burdensNo => false, self::risksNode => false, self::burdensRisksContributorsNode => false, self::voluntaryNode => false, $voluntaryNo => false, self::consent => false, $consentNo => false, self::compensationNode => false, self::privacyNode => false, self::dataOnlineNode => false, self::markingNode => false, self::markingNode.'personal' => false, self::dataReuseNode => false, self::dataReuseSelfNode => false, self::dataReuseHowNode => false]; // Each entry gets true if the respective value in one of the preceding variables gets true
            foreach ($studyArray as $study) {
                foreach ($this->addZeroIndex($study[self::groupNode]) as $group) {
                    foreach ($this->addZeroIndex($group[self::measureTimePointNode]) as $measureTimePoint) {
                        // information
                        // pre information
                        $tempArray = $measureTimePoint[self::informationNode];
                        $tempArrayParticipants = $measureTimePoint[self::informationIINode];
                        $chosen = $tempArray[self::chosen];
                        $chosenParticipants = $tempArrayParticipants[self::chosen] ?? '';
                        $isPre = $chosen==='0';
                        $isNotPre = $chosen==='1';
                        $isPreParticipants = $chosenParticipants==='0';
                        $additionalChosen = $tempArray[self::informationAddNode][self::chosen] ?? '';
                        if ($isPre || $isPreParticipants) {
                            [$isAnyPre, $allTrue[self::pre]] = [true, true];
                            if ($isPre && in_array($additionalChosen, self::preContentIncomplete) || $isPreParticipants && in_array($tempArrayParticipants[self::informationAddNode][self::chosen] ?? '', self::preContentIncomplete)) {
                                [$isAnyCompletePost, $allTrue[$completePost]] = [true, true];
                            }
                        }
                        if ($isNotPre || $chosenParticipants==='1') {
                            [$isAnyNotPre, $allTrue[$preNo]] = [true, true];
                        }
                        if ($chosen==='' || $tempArrayParticipants!=='' && $chosenParticipants==='') {
                            [$isNotAnyPreYet, $allTrue[$preNotYet]] = [true, true];
                        }
                        $isPost = $isNotPre && $additionalChosen==='0';
                        if ($isPost) {
                            [$isAnyPost, $allTrue[self::post]] = [true, true];
                        }
                        // addressee
                        $groupsArray = $measureTimePoint[self::groupsNode];
                        if ($isPre || $isPost) {
                            $curAddressee = $this->getAddressee($groupsArray);
                            [$allAddressees[$curAddressee], $allTrue[$curAddressee]] = [true, true];
                        }
                        // other sources
                        $measuresArray = $measureTimePoint[self::measuresNode];
                        if ($measuresArray[self::otherSourcesNode][self::chosen]==='0') {
                            [$isAnyOtherSources,$allTrue[self::otherSourcesNode]] = [true, true];
                        }
                        // location online
                        if ($measuresArray[self::locationNode][self::chosen]===self::locationOnline) {
                            [$isAnyOnline,$allTrue[self::locationNode]] = [true,true];
                        }
                        // burdens and risks
                        $burdensRisksArray = $measureTimePoint[self::burdensRisksNode];
                        foreach ([self::burdensNode, self::risksNode, self::burdensRisksContributorsNode] as $type) {
                            $tempArray = $this->getBurdensOrRisks($burdensRisksArray, $type);
                            if ($tempArray[0]) { // at least one option except 'no' is selected
                                [$isAnyBurdensRisks[$type], $allTrue[$type]] = [true, true];
                            }
                            elseif ($type===self::burdensNode && $tempArray[1]) {
                                [$isAnyBurdensNo, $allTrue[$burdensNo]] = [true, true];
                            }
                        }
                        // voluntary
                        $consentArray = $measureTimePoint[self::consentNode];
                        $voluntaryArray = $consentArray[self::voluntaryNode];
                        $chosen = [$voluntaryArray[self::chosen], $voluntaryArray[self::chosen2Node] ?? ''];
                        if ($this->getClosedDependent($groupsArray) && in_array('yes', $chosen)) {
                            [$anyVoluntary[0], $allTrue[self::voluntaryNode]] = [true, true];
                        }
                        if (in_array(self::voluntaryConsentNo, $chosen)) {
                            [$anyVoluntary[1], $allTrue[$voluntaryNo]] = [true, true];
                        }
                        // consent
                        $consentArray = $consentArray[self::consent];
                        if ($consentArray[self::chosen]==='no') {
                            [$anyConsent[0], $allTrue[self::consentNode]] = [true, true];
                        }
                        if (($consentArray[self::chosen2Node] ?? '')==='no') {
                            [$anyConsent[1], $allTrue[$consentNo]] = [true, true];
                        }
                        // compensation
                        if (($measureTimePoint[self::compensationNode][self::awardingTextNode] ?? '')!=='') {
                            [$isAnyCompensationDescription, $allTrue[self::compensationNode]] = [true, true];
                        }
                        // data privacy
                        $privacyArray = $measureTimePoint[self::privacyNode];
                        if ($privacyArray[self::createNode][self::chosen]===self::createTool) {
                            [$isAnyDataPrivacy, $allTrue[self::privacyNode]] = [true, true];
                            // data online
                            if (($privacyArray[self::dataOnlineNode] ?? '')===self::dataOnlineTechnical) {
                                [$isAnyDataOnlineTechnical,$allTrue[self::dataOnlineNode]] = [true, true];
                            }
                        }
                        // marking
                        if (array_key_exists(self::markingNode,$privacyArray) || array_key_exists(self::markingNode.self::markingSuffix,$privacyArray)) {
                            [$isAnyMarking, $allTrue[self::markingNode]] = [true, true];
                            if ($this->getMarkingPersonal($privacyArray)) {
                                [$isAnyMarkingPersonal, $allTrue[self::markingNode.'personal']] = [true, true];
                            }
                        }
                        // data reuse
                        $tempArray = $measureTimePoint[self::dataReuseNode];
                        if (array_key_exists(self::dataReuseNode,$tempArray)) {
                            [$isAnyDataReuse, $allTrue[self::dataReuseNode]] = [true, true];
                        }
                        if (array_key_exists(self::dataReuseSelfNode,$tempArray)) {
                            [$isAnyDataReuseSelf, $allTrue[self::dataReuseSelfNode]] = [true, true];
                        }
                        if (array_key_exists(self::dataReuseHowNode,$tempArray)) {
                            [$isAnyDataReuseHow,$allTrue[self::dataReuseHowNode]] = [true,true];
                        }
                        if (!in_array(false, $allTrue)) {
                            break(3);
                        }
                    }
                }
            }
            $isParticipants = $this->getStringFromBool($allAddressees[self::addresseeParticipants]); // 'true' if any addressee is participants
            $isChildren = $this->getStringFromBool($allAddressees[self::addresseeChildren]); // 'true' if any addressee is children
            $childrenWardsParams = [self::addresseeChildren => $isChildren, self::addresseeWards => $this->getStringFromBool($allAddressees[self::addresseeWards])];
            $isChildrenWards = in_array('true', $childrenWardsParams);
            $this->documentHint = in_array(true, $allAddressees) ? trim($this->translateStringPDF('documentHint', array_merge($childrenWardsParams, [self::addresseeParticipants => $isParticipants, 'isParticipantsChildren' => $this->getStringFromBool(in_array('true', [$isParticipants, $isChildren]))]))) : '';

            // application data
            self::$isPageLink = true;
            // core data
            self::$linkedPage = self::coreDataNode;
            $pagePrefix = self::coreDataNode.'.';
            $appDataArray = $this->xmlToArray($appNode->{self::appDataNodeName});
            $pageArray = $appDataArray[self::coreDataNode];
            // application type
            $translationPrefix = $pagePrefix.'appType.';
            $tempArray = $pageArray[self::applicationType];
            $applicationType = $tempArray[self::chosen];
            $isExtendedResubmission = in_array($applicationType, [self::appExtended, self::appResubmission]);
            $appTypeDescription = $tempArray[self::descriptionNode] ?? '';
            $appType = $this->addHeadingLink($translationPrefix.'title',fragment: self::applicationType);
            if ($applicationType!=='') {
                $translationPrefix .= 'types.';
                $appType .= ' '.$this->translateStringPDF($translationPrefix.$applicationType, array_merge($committeeParam, ['newType' => $appTypeDescription]));
            }
            // guidelines
            $tempPrefix = 'coreData.';
            $guidelines = [$this->addHeadingLink($tempPrefix.'guidelines',fragment: self::guidelinesNode.'Div'), $pageArray[self::guidelinesNode][self::descriptionNode] ?? ''];
            // project title
            $tempArray = $pageArray[self::projectTitleParticipation];
            $chosen = $tempArray[self::chosen];
            $this->addBox($pagePrefix.self::projectTitle, $pageArray[self::projectTitle], $chosen!=='' ? ($chosen===self::projectTitleDifferent ? $tempArray[self::descriptionNode] : $this->translateString($tempPrefix.self::projectTitleParticipation.'.types.'.$chosen)) : '',fragment: self::projectTitle);
            // applicant and supervisor
            $applicantSupervisor = [];
            $translationPrefix = $pagePrefix.self::applicant.'.';
            $applicantWidth = 0; // width of the divs containing the labels
            $tempVal = ($pageArray[self::qualification] ?? '')==='0';
            $isSupervisor = array_key_exists(self::supervisor, $pageArray);
            foreach (array_merge([self::applicant], $isSupervisor ? [self::supervisor] : []) as $type) {
                $tempArray = $pageArray[$type];
                $isApplicant = $type===self::applicant;
                $infos = [];
                foreach (self::applicantContributorsInfosTypes as $info) {
                    $key = $this->translateString('multiple.infos.'.$info.($info===self::institutionInfo ? 'Applicant' : ''));
                    $applicantWidth = max($applicantWidth, ceil(mb_strwidth($key) / 1.5));
                    $curInfo = $tempArray[$info];
                    $infos[$key] = array_key_exists($curInfo, self::positionsTypes) ? $this->translateString(self::positionsTypes[$curInfo]).($isApplicant && $tempVal && in_array($curInfo, [self::positionsStudent, self::positionsPhd]) ? $this->translateStringPDF($translationPrefix.self::qualification) : '') : $curInfo;
                }
                $name = $this->translateString($translationPrefix.$type);
                if ($isApplicant) {
                    $name = $this->addHeadingLink($name,fragment: self::applicant);
                }
                $applicantSupervisor[$type] = [self::nameNode => $name, self::infosNode => $infos];
            }
            // summary
            self::$linkedPage = self::summary;
            $this->addBox(self::summary, $appDataArray[self::summary][self::descriptionNode]);
            // support
            self::$linkedPage = self::coreDataNode;
            $tempArray = $pageArray[self::supportNode];
            $tempVal = '';
            $supportPrefix = $pagePrefix.self::supportNode;
            $tempPrefix .= self::supportNode.'.type.';
            if ($tempArray!=='') {
                foreach ($tempArray as $support => $description) {
                    $tempVal .= "\n".($support!==self::noSupport ? '- ' : '').$this->translateString($tempPrefix.$support).($support!==self::noSupport ? ': '.$description : '');
                }
            }
            $this->addBox($supportPrefix, trim($tempVal),fragment: self::supportNode);
            // funding
            $tempArray = $pageArray[self::funding];
            $tempVal = '';
            if ($tempArray!=='') { // at least one type was selected
                $tempPrefix = $pagePrefix.self::funding.'.';
                foreach ($tempArray as $type => $source) {
                    $fundingState = $source[self::fundingStateNode] ?? '';
                    $tempVal .= "\n- ".$this->translateString($tempPrefix.$type).($type!==self::fundingQuali ? ": ".$source[self::descriptionNode].($fundingState!=='' ? "\n".$this->translateString($tempPrefix.self::fundingStateNode.'.'.$fundingState).'.' : '') : '');
                }
            }
            $this->addBox($pagePrefix.self::funding, trim($tempVal), subHeadingKey: $this->documentHint,fragment: self::funding);
            // conflict
            $tempArray = $pageArray[self::conflictNode];
            $tempVal = $tempArray[self::chosen];
            $this->addBox($pagePrefix.self::conflictNode, $this->translateBinaryAnswer($tempVal), $tempArray[self::descriptionNode] ?? '', parameters: ['conflictChosen' => $tempVal],fragment: self::conflictNode);
            // description of conflict
            $this->addBox($pagePrefix.self::participantDescription, $tempArray[self::participantDescription] ?? $this->noBoxTrans, subHeadingKey: $this->documentHint,fragment: self::conflictNode);
            // project dates
            $tempPrefix = $pagePrefix.'projectDates.';
            $projectStart = $pageArray[self::projectStart];
            $start = $projectStart[self::chosen];
            $hasBegun = false;
            $content = $this->translateStringPDF($tempPrefix.'start');
            if ($start!=='') {
                $isNextBegun = $start==='0';
                if ($isNextBegun) { // next possible time point or has already start
                    $hasBegun = array_key_exists(self::descriptionNode, $projectStart);
                    $content .= $this->translateStringPDF($tempPrefix.($hasBegun ? 'started' : 'next'));
                }
                else {
                    $content .= $this->convertDate($start);
                }
            }
            $content .= ' '.$this->translateStringPDF($tempPrefix.'end').$this->convertDate($pageArray[self::projectEnd]).($hasBegun ? $this->translateStringPDF($tempPrefix.'startedDescription').$projectStart[self::descriptionNode] : '');
            $this->addBox($pagePrefix.'projectDates', $content, fragment: 'projectDates');

            // votes and medicine
            foreach ([self::voteNode => [self::otherVote, self::instVote], self::medicine => [self::medicine, self::physicianNode]] as $page => $subPages) {
                self::$linkedPage = $page;
                $pagePrefix = $page.'.';
                $pageArray = $appDataArray[$page];
                foreach ($subPages as $subPage) {
                    $tempPrefix = $pagePrefix.$subPage.'.';
                    $tempArray = $pageArray[$subPage];
                    $tempVal = $tempArray[self::chosen];
                    $content = $this->translateBinaryAnswer($tempVal);
                    $tempVal = $tempVal==='0';
                    if ($tempVal || $subPage===self::instVote && $isExtendedResubmission) { // if application type is changed, it may not yet be updated on votes
                        switch ($subPage) {
                            case self::otherVote:
                                $content .= $this->translateStringPDF($tempPrefix.'committee').' '.$tempArray[self::descriptionNode];
                                $content .= $this->translateStringPDF($tempPrefix.'result', ['result' => $tempArray[self::otherVoteResult]]).$tempArray[self::otherVoteResultDescription];
                                break;
                            case self::instVote:
                                if ($isExtendedResubmission) {
                                    $content = $this->translateBinaryAnswer('0');
                                }
                                if ($isExtendedResubmission || $tempVal) {
                                    $content .= $this->translateStringPDF($tempPrefix.self::instReference).' '.($isExtendedResubmission ? $appTypeDescription : $tempArray[self::instReference]);
                                    $content .= $this->translateStringPDF($tempPrefix.self::descriptionNode, ['type' => $applicationType]).($tempArray[self::instVoteText] ?? '');
                                }
                                break;
                            case self::medicine:
                                $content .= $this->translateStringPDF($pagePrefix.self::medicine).$tempArray[self::descriptionNode];
                                break;
                            case self::physicianNode:
                                $tempArray = $tempArray[self::descriptionNode];
                                $content .= $this->translateStringPDF($pagePrefix.self::physicianNode, array_merge($committeeParam, ['result' => $tempArray[self::chosen]])).$tempArray[self::descriptionNode];
                                break;
                            default:
                                break;
                        }
                    }
                    $this->addBox($pagePrefix.$subPage, $content, parameters: $committeeParam, fragment: $subPage);
                }
            }

            // contributors infos and tasks
            self::$linkedPage = self::contributorsSessionName;
            $contributorsInfos = '';
            $nameTasks = [];
            $contributorsHeading = $this->addHeadingLink('boxHeadings.contributorsTasks');
            $firstInfoIndex = $isSupervisor ? 1 : 0;
            $positionKeys = array_keys(self::positionsTypes);
            foreach ($this->getContributors($session) as $index => $contributor) {
                if ($index>$firstInfoIndex) { // applicant and supervisor infos are already in the previous box
                    $curInfos = $contributor[self::infosNode];
                    $curPosition = $curInfos[self::position];
                    if (in_array($curPosition, $positionKeys)) {
                        $curInfos[self::position] = $this->translateString('multiple.position.'.$curPosition);
                    }
                    $contributorsInfos .= '- '.implode(', ', $curInfos)."\n";
                }
                $contributorTasks = $contributor[self::taskNode] ?: [];
                $tasks = [];
                foreach (self::tasksNodes as $task) {
                    $tasks[$task] = $task===self::otherTask ? $contributorTasks[self::otherTask] ?? '' : array_key_exists($task, $contributorTasks);
                }
                $nameTasks[$index] = [self::nameNode => $contributor[self::infosNode][self::nameNode], 'hasTasks' => in_array(true, $tasks) || $tasks[self::otherTask]!=='', self::taskNode => $tasks];
            }
            $contributorsInfos = ['heading' => $this->addHeadingLink('boxHeadings.contributors'), self::content => $contributorsInfos!=='' ? $contributorsInfos : $this->noBoxTrans];

            // projectdetails
            // $boxContent: one element for each question. key: key of the question. value: array of two elements ('main' and 'sub'). For each of the two keys: value: array of different answers for the question. In this array: key: box content. value: One element for each study. key: study index. value: array of array. Same for groups. For measure time points the values of the array are indices.
            // Example: ['question1Key' =>
            //              ['main' =>
            //                  ['AnswerX' =>
            //                      [study0 =>
            //                          [group1 => [1,3]
            //                          ]
            //                      ]
            //                  ],
            //               'sub' =>
            //                  ['AnswerY' =>
            //                      [study0 =>
            //                          [group1 => [0,2]
            //                          ]
            //                  ]
            //              ]
            //          ]
            $this->boxContent = [];
            // numIndices: number of measure time points for each group. key: study ID, value: array. In this array: key: group ID, value: number of measure time points
            // Example: [0 =>
            //              [0 => 2]
            //          ]
            $numIndices = [];
            // names: names of studies and groups and indices of measure time points. key: study ID, value: array. In this array; first element: name of study, second element: array of arrays (one for each group in this study). In each of these arrays: same for groups. if any of the arrays is empty, there is at most one element in the level underneath.
            // Example: [0 =>
            //               [0 => 'study 1: studyName',
            //                1 => [0 =>
            //                          [0 => 'group 1: groupName',
            //                           1 => [0 => 'measure time point 1'
            //                                ]
            //                          ]
            //                     ]
            //               ]
            //          ]
            $names = [];
            $projecdetailsPrefix = 'projectdetails.';
            $projecdetailsPagesPrefix = $projecdetailsPrefix.'pages.';
            $privacyPrefix = $projecdetailsPagesPrefix.self::privacyNode.'.';
            $isDocuments = $this->documentHint!=='';
            $isMultiple = $this->getMultiStudyGroupMeasure($appNode);
            $anyVoluntaryYes = $anyVoluntary[0];
            // translations
            $projectdetailsHeadingPrefix = 'boxHeadings.'.$projecdetailsPrefix;
            $this->levelTrans[self::studyNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::studyNode);
            $studyAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'studyAll');
            $this->levelTrans[self::groupNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::groupNode);
            $groupAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'groupAll');
            $this->levelTrans[self::measureTimePointNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::measureTimePointNode);
            $measureAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'measureTimePointAll');
            $multipleStudies = count($studyArray)>1;
            self::$isPageLink = false;
            foreach ($studyArray as $studyID => $study) {
                $this->studyID = $studyID;
                $names[$studyID] = $this->getStudyGroupName($study[self::nameNode], self::studyNode, $studyID, $multipleStudies);
                $groupArray = $this->addZeroIndex($study[self::groupNode]);
                $multipleGroups = count($groupArray)>1;
                $groupIndices = [];
                foreach ($groupArray as $groupID => $group) {
                    $this->groupID = $groupID;
                    $groupIndices[$groupID] = $this->getStudyGroupName($group[self::nameNode], self::groupNode, $groupID, $multipleGroups);
                    $measureTimePointArray = $this->addZeroIndex($group[self::measureTimePointNode]);
                    $isMultipleMeasure = count($measureTimePointArray)>1; // true if at least two measure time points for the current group exist
                    $numIndices[$studyID][$groupID] = count($measureTimePointArray);
                    $multipleMeasures = count($measureTimePointArray)>1;
                    $measureIndices = [];
                    foreach ($measureTimePointArray as $measureID => $measureTimePoint) {
                        $isFirstMeasureTimePoint = $studyID===0 && $groupID===0 && $measureID===0;
                        $this->measureID = $measureID;
                        $measureIndices[$measureID] = $this->getStudyGroupName('', self::measureTimePointNode, $measureID, $multipleMeasures);
                        $groupsPrefix = $projecdetailsPrefix.self::groupsNode.'.';
                        $groupsArray = $measureTimePoint[self::groupsNode];
                        // get current addressee(s) and create variables that are needed in several questions
                        $addressee = $this->getAddressee($groupsArray);
                        $addresseeParam = [self::addressee => $addressee];
                        $isNotParticipants = $addressee!==self::addresseeParticipants; // true if two addressees
                        // examined (groups)
                        $this->inputPage = self::groupsNode;
                        $tempArray = $this->getSelectedCheckboxes($groupsArray[self::examinedPeopleNode], $groupsPrefix.'examined.types.', false);
                        $minAge = $groupsArray[self::minAge];
                        $maxAge = $groupsArray[self::maxAge];
                        $this->addBoxContent(self::examinedPeopleNode, implode(', ', $tempArray).$this->translateStringPDF($groupsPrefix.'examined.title', ['number' => count($tempArray), 'limits' => $maxAge==='-1' ? 'noUpperLimit' : ($minAge===$maxAge ? 'sameLimit' : 'other'), self::minAge => $minAge, self::maxAge => $maxAge]), $groupsArray[self::peopleDescription], paragraph: 'methods');
                        // sample size (groups)
                        $tempArray = $groupsArray[self::sampleSizeNode];
                        $this->addBoxContent(self::sampleSizeNode, $tempArray[self::sampleSizeTotalNode], $tempArray[self::sampleSizeFurtherNode]);
                        $this->addBoxContent(self::sampleSizePlanNode, $tempArray[self::sampleSizePlanNode]);
                        // recruitment (groups)
                        $tempArray = $groupsArray[self::recruitment];
                        $this->addBoxContent(self::recruitment, $this->getSelectedCheckboxes($tempArray[self::recruitmentTypesNode], $projecdetailsPrefix.'groups.recruitment.'), $tempArray[self::descriptionNode] ?? '');
                        $measuresPrefix = $projecdetailsPrefix.self::measuresNode.'.';
                        $measuresArray = $measureTimePoint[self::measuresNode];
                        // measures and interventions (measures)
                        $this->inputPage = self::measuresNode;
                        foreach ([self::measuresNode, self::interventionsNode] as $type) {
                            $tempArray = $measuresArray[$type];
                            $types = $tempArray[$type.'Type'];
                            $tempVal = $tempArray[self::descriptionNode] ?? '';
                            $numTypes = $types!=='' ? count($types) : 0;
                            if ($type===self::interventionsNode && $numTypes>0 && array_key_exists(self::interventionsSurvey, $types)) {
                                $tempVal = trim($this->translateStringPDF($measuresPrefix.'survey', ['numInterventions' => $numTypes]).' '.$tempVal);
                            }
                            $this->addBoxContent($type, $this->getSelectedCheckboxes($tempArray[$type.'Type'], $measuresPrefix.$type.'Types.'), $tempVal);
                        }
                        // other sources (measures)
                        $tempArray = $measuresArray[self::otherSourcesNode];
                        $this->addBoxContent(self::otherSourcesNode, $this->translateBinaryAnswer($tempArray[self::chosen],true).($tempArray[self::otherSourcesNode.self::descriptionCap] ?? ''), subHeading: $isAnyOtherSources ? $this->documentHint : '');
                        // feedback (burdens/risks)
                        $this->inputPage = self::burdensRisksNode;
                        $burdensRisksArray = $measureTimePoint[self::burdensRisksNode];
                        $tempArray = $burdensRisksArray[self::feedbackNode];
                        $this->addBoxContent(self::feedbackNode, $this->translateBinaryAnswer($tempArray[self::chosen]), $tempArray[self::descriptionNode] ?? '');
                        // loan (measures)
                        $this->inputPage = self::measuresNode;
                        $this->addBoxContent(self::loanNode, $this->translateBinaryAnswer($measuresArray[self::loanNode][self::chosen]));

                        // procedure (texts)
                        $this->inputPage = self::textsNode;
                        $informationArray = $measureTimePoint[self::informationNode];
                        $isInformation = in_array($this->getInformationString($informationArray), [self::pre, self::post]);
                        if ($isInformation) {
                            $this->addBoxContent(self::procedureNode, $measureTimePoint[self::textsNode][self::procedureNode] ?? '', subHeading: $this->documentHint);
                        }
                        elseif ($isFirstMeasureTimePoint) { // add the dummy box once to ensure order of questions
                            $this->addBoxContent(self::procedureNode, $isDocuments ? self::dummyBox : self::noBox);
                        }

                        // durations (measures)
                        $this->inputPage = self::measuresNode;
                        $this->addBoxContent(self::durationNode, $this->getDuration($measuresArray[self::durationNode], $isMultipleMeasure), subHeading: $isAnyPre ? $this->documentHint : '');
                        // location (measures)
                        $this->addBoxContent(self::locationNode, $this->getLocation($measureTimePoint, $committeeParam), subHeading: $this->documentHint);

                        // burdens and risks (burdens/risks)
                        $this->inputPage = self::burdensRisksNode;
                        $burdensRisksPrefix = $projecdetailsPrefix.self::burdensRisksNode.'.';
                        foreach ([self::burdensNode, self::risksNode, self::burdensRisksContributorsNode] as $type) {
                            $typeArray = $burdensRisksArray[$type];
                            $this->addBoxContent($type, $type!==self::burdensRisksContributorsNode ? $this->getSelectedCheckboxes($typeArray[$type.'Type'], $burdensRisksPrefix.$type.'.') : $this->translateBinaryAnswer($typeArray[self::chosen]), $typeArray[self::descriptionNode] ?? '', paragraph: $type===self::burdensNode ? 'burdens' : '');
                            $tempArray = $typeArray[self::burdensRisksCompensationNode] ?? '';
                            $this->addBoxContent($type.'Compensation', $isAnyBurdensRisks[$type] ? ($this->getBurdensOrRisks($burdensRisksArray, $type)[0] ? $this->translateBinaryAnswer($tempArray[self::chosen], addHyphenNo: true).$tempArray[self::descriptionNode] : self::dummyBox) : self::noBox);
                        }

                        // con including description of burdens/risks (texts)
                        $this->inputPage = self::textsNode;
                        $this->addBoxContent(self::burdensRisksNode.self::descriptionCap, $isDocuments ? ($isInformation ? $this->getConTemplateText($measureTimePoint) : self::dummyBox) : self::noBox, subHeading: $this->documentHint);

                        // finding (burdens/risks)
                        $this->inputPage = self::burdensRisksNode;
                        $tempArray = $burdensRisksArray[self::findingNode];
                        $tempVal = $tempArray[self::chosen];
                        $this->addBoxContent(self::findingNode, $this->translateBinaryAnswer($tempVal,addHyphenYes: true).($tempArray[self::descriptionNode] ?? ''), $tempVal==='0' ? $this->translateStringPDF($burdensRisksPrefix.self::findingNode, array_merge($addresseeParam, [self::chosen => $tempArray[self::informingNode]])) : '');

                        // criteria (groups)
                        $this->inputPage = self::groupsNode;
                        $this->addBoxContent(self::criteriaNode, implode("\n\n", $this->getCriteria($measureTimePoint, $addressee)), subHeading: $isAnyPre ? $this->documentHint : '', paragraph: self::criteriaNode);

                        // translated addressees
                        $tempPrefix = $projecdetailsPrefix.self::addressee.'.';
                        $addresseeTrans = $this->translateString($tempPrefix.'thirdParties.'.$addressee);
                        $participantsTrans = $this->translateString($tempPrefix.'participants.'.$addressee);
                        // prefix is answers are given for both addressees
                        $addresseeHeading = $this->translateStringPDF('addresseePrefix', array_merge($addresseeParam, ['isParticipants' => 'false']));
                        $participantsHeading = $this->translateStringPDF('addresseePrefix', array_merge($addresseeParam, ['isParticipants' => 'true']));
                        $participantsHeadingShort = substr($participantsHeading, 3); // without the ' | '

                        // closed (groups)
                        $this->inputPage = self::groupsNode;
                        $tempArray = $groupsArray[self::closedNode];
                        $closedTypes = $tempArray[self::closedTypesNode] ?? [];
                        $this->addBoxContent(self::closedNode, $this->translateBinaryAnswer($tempArray[self::chosen],addHyphenYes: true).str_replace('{description}', $closedTypes[self::closedOther] ?? '', $this->getSelectedCheckboxes($closedTypes, $groupsPrefix.self::closedNode.'.')), $tempArray[self::closedNode.self::descriptionCap] ?? '');

                        // pre-information (information/II)
                        $this->inputPage = self::informationNode;
                        $informationIIArray = $measureTimePoint[self::informationIINode];
                        $informationIIArray = $informationIIArray ?: [];
                        $informationPrefix = $projecdetailsPrefix.self::informationNode.'.';
                        $preTranslation = $informationPrefix.'type';
                        $pre = $informationArray[self::chosen];
                        $preParticipants = $isNotParticipants ? ($informationIIArray[self::chosen] ?? '') : '';
                        $tempVal = $addresseeHeading.$this->translateStringPDF($preTranslation, [self::chosen => $pre, self::descriptionNode => $informationArray[self::descriptionNode] ?? '']).($isNotParticipants ? $participantsHeading.$this->translateStringPDF($preTranslation, [self::chosen => $preParticipants, self::descriptionNode => $informationIIArray[self::descriptionNode] ?? '']) : '');
                        $this->addBoxContent(self::informationNode, $tempVal, subHeading: $isAnyPost ? $this->documentHint : '');

                        // pre content (information/II)
                        $content = $isAnyPre ? self::dummyBox : self::noBox;
                        // is pre information
                        $isPre = $pre==='0';
                        $isPreParticipants = $preParticipants==='0';
                        // is either partial or deceit
                        $isIncomplete = false;
                        $isIncompleteParticipants = false;
                        // additional array. Either containing infos about complete post information or about post information
                        $informationAddArray = $informationArray[self::informationAddNode];
                        $informationAddArrayParticipants = $informationIIArray[self::informationAddNode] ?? [];
                        $additionalChosen = $informationAddArray[self::chosen];
                        $additionalChosenParticipants = $informationAddArrayParticipants[self::chosen] ?? '';
                        $additionalDescription = $informationAddArray[self::descriptionNode] ?? '';
                        $additionalDescriptionParticipants = $informationAddArrayParticipants[self::descriptionNode] ?? '';
                        if ($isPre || $isPreParticipants) {
                            $tempPrefix = $informationPrefix.self::preContent;
                            $chosen = $isPre ? $additionalChosen : '';
                            $isIncomplete = in_array($chosen, self::preContentIncomplete);
                            $isIncompleteParticipants = in_array($additionalChosenParticipants, self::preContentIncomplete);
                            $content = ($isPre ? $addresseeHeading.$this->translateStringPDF($tempPrefix, [self::chosen => $chosen, self::addressee => $addresseeTrans]) : '').($isPreParticipants ? ($isPre ? $participantsHeading : $participantsHeadingShort).$this->translateStringPDF($tempPrefix, [self::chosen => $additionalChosenParticipants, self::addressee => $participantsTrans]) : '');
                        }
                        $this->addBoxContent(self::preContent, $content);

                        // pre complete (information/II)
                        $content = $isAnyCompletePost ? self::dummyBox : self::noBox;
                        $subContent = '';
                        if ($isIncomplete || $isIncompleteParticipants) {
                            $completePost = $informationAddArray[self::complete] ?? '';
                            $completePostParticipants = $informationAddArrayParticipants[self::complete] ?? '';
                            $isCompletePost = $completePost==='0';
                            $isCompletePostParticipants = $completePostParticipants==='0';
                            $content = ($isIncomplete ? $addresseeHeading.$this->translateBinaryAnswer($completePost, addHyphenNo: true).$additionalDescription : '').($isIncompleteParticipants ? ($isIncomplete ? $participantsHeading : $participantsHeadingShort).$this->translateBinaryAnswer($completePostParticipants, addHyphenNo: true).$additionalDescriptionParticipants : '');
                            if ($isCompletePost || $isCompletePostParticipants) {
                                $subContent = ($isCompletePost ? $addresseeHeading.$this->translateStringPDF($preTranslation, [self::chosen => '', self::descriptionNode => $informationAddArray[self::preCompleteType]]) : '').($isCompletePostParticipants ? ($isCompletePost ? $participantsHeading : $participantsHeadingShort).$this->translateStringPDF($preTranslation, [self::chosen => '', self::descriptionNode => $informationAddArrayParticipants[self::preCompleteType]]) : '');
                            }
                        }
                        $this->addBoxContent(self::preComplete, $content, $subContent);

                        // post information (information/II)
                        $content = $isAnyNotPre ? self::dummyBox : self::noBox;
                        $isNotPre = $pre==='1';
                        $isNotPreParticipants = $preParticipants==='1';
                        if ($isNotPre || $isNotPreParticipants) {
                            $content = ($isNotPre ? $addresseeHeading.$this->translateStringPDF($preTranslation, [self::chosen => $additionalChosen, self::descriptionNode => $additionalDescription]) : '').($isNotPreParticipants ? $this->translateStringPDF($preTranslation, [self::chosen => $additionalChosenParticipants, self::descriptionNode => $additionalDescriptionParticipants]) : '');
                        }
                        $this->addBoxContent(self::post, $content);

                        // voluntary and consent (consent)
                        $this->inputPage = self::consentNode;
                        $addresseeString = $this->getAddresseeString($addressee);
                        $participantsString = $this->getAddresseeString($addressee, false, true, $addressee===self::addresseeParticipants);
                        $addresseeStringParams = [self::addressee => $addresseeString, self::participant => $participantsString];
                        $participantsStringParams = [self::addressee => $this->translateString($projecdetailsPrefix.'addressee.participants.'.$addressee), self::participant => $this->getAddresseeString($addressee, false, true, true)];
                        $consentArray = $measureTimePoint[self::consentNode];
                        $consentPrefix = $projecdetailsPrefix.self::consentNode.'.';
                        foreach ([self::voluntaryNode, self::consentNode] as $type) {
                            $tempArray = $consentArray[$type];
                            $tempVal = $consentPrefix.$type;
                            $chosen = $tempArray[self::chosen];
                            $chosen2 = $tempArray[self::chosen2Node] ?? '';
                            $this->addBoxContent($type, $addresseeHeading.$this->translateStringPDF($tempVal, array_merge($addresseeStringParams, [self::chosen => $chosen, 'otherDescription' => $tempArray[self::consentOtherDescription] ?? '', self::addresseeType => self::addresseeParticipants])).($isNotParticipants ? $participantsHeading.$this->translateStringPDF($tempVal, array_merge($participantsStringParams, [self::chosen => $chosen2, 'otherDescription' => $tempArray[self::consentOtherDescription.'Participants'] ?? '', self::addresseeType => $addressee])) : ''), $tempArray[self::descriptionNode] ?? '');
                            if ($type===self::voluntaryNode) {
                                $this->addBoxContent('voluntaryYes', $anyVoluntaryYes ? ($this->getClosedDependent($groupsArray) && in_array('yes', [$chosen, $chosen2]) ? $tempArray[self::voluntaryYesDescription] ?? '' : self::dummyBox) : self::noBox);
                            }
                        }
                        // compensation (compensation)
                        $this->inputPage = self::compensationNode;
                        $pageArray = $measureTimePoint[self::compensationNode];
                        $terminateConsArray = $consentArray[self::terminateConsNode];
                        $terminateConsChosen = $terminateConsArray[self::chosen];
                        $this->addBoxContent(self::compensationNode, $this->getCompensation($pageArray, $addresseeParam, $session), $this->getCompensationTerminate($pageArray, array_merge($addresseeParam,[self::terminateConsNode => $this->getStringFromBool($terminateConsChosen==='0')])), $this->documentHint);
                        // further description of compensation (compensation)
                        $tempVal = $pageArray[self::awardingTextNode] ?? '';
                        $this->addBoxContent(self::awardingTextNode, $isAnyCompensationDescription ? ($tempVal ?: self::dummyBox) : self::noBox);

                        // attendance (information)
                        $this->inputPage = self::informationNode;
                        $this->addBoxContent(self::attendanceNode, $isChildrenWards ? (array_key_exists(self::attendanceNode, $informationArray) ? $this->translateBinaryAnswer($informationArray[self::attendanceNode]) : self::dummyBox) : self::noBox);

                        // terminate without cons (consent)
                        $this->inputPage = self::consentNode;
                        $this->addBoxContent(self::terminateConsNode, $this->translateBinaryAnswer($terminateConsChosen), $terminateConsArray[self::descriptionNode] ?? '');
                        // presence (measures)
                        $this->inputPage = self::measuresNode;
                        $this->addBoxContent(self::presenceNode, $this->translateBinaryAnswer($measuresArray[self::presenceNode]));
                        // terminate by experimenter (consent)
                        $this->inputPage = self::consentNode;
                        $this->addBoxContent(self::terminateCriteriaNode, $consentArray[self::terminateCriteriaNode], subHeading: $this->translateStringPDF($projecdetailsPrefix.self::terminateCriteriaNode));

                        // data privacy
                        $this->inputPage = self::privacyNode;
                        $pageArray = $measureTimePoint[self::privacyNode];
                        // processing
                        $this->addBoxContent(self::processingNode,$pageArray[self::processingNode],paragraph: self::processingNode);
                        $tempArray = $pageArray[self::createNode];
                        $create = $tempArray[self::chosen];
                        $description = $tempArray[self::descriptionNode] ?? ''; // either confirm intro or verification of separate pdf, if any of these two was chosen
                        $tempPrefix = $projecdetailsPrefix.self::privacyNode.'.';
                        // create
                        $this->addBoxContent(self::createNode,$this->translateStringPDF($tempPrefix.self::createNode,array_merge($addresseeStringParams,['type' => $create!==self::createSeparate && $this->getPrivacyNoTool($pageArray) ? 'noTool' : $create, self::createVerificationNode => $description])));
                        $tempVal = $isAnyDataPrivacy ? self::dummyBox : self::noBox;
                        $content = [self::responsibilityNode => $tempVal, self::transferOutsideNode => $tempVal];
                        if ($create===self::createTool && $description==='1') {
                            // responsibility and transfer outside
                            foreach ([self::responsibilityNode,self::transferOutsideNode] as $type) {
                                $tempVal = $pageArray[$type];
                                $content[$type] = $tempVal!=='' ? $this->translateString($privacyPrefix.$type.'.types.'.$tempVal,$committeeParam) : '';
                            }
                        }
                        $this->addBoxContent(self::responsibilityNode,$content[self::responsibilityNode]);
                        $this->addBoxContent(self::transferOutsideNode,$content[self::transferOutsideNode]);

                        // data online
                        $tempArray = [self::dataOnlineNode => $isAnyOnline ? self::dummyBox : self::noBox, self::dataOnlineProcessingNode => ''];
                        foreach ([self::dataOnlineNode,self::dataOnlineProcessingNode] as $type) {
                            $tempVal = $pageArray[$type] ?? '';
                            if ($tempVal!=='') {
                                $tempArray[$type] = $this->translateString($privacyPrefix.$type.'.types.'.$tempVal);
                            }
                        }
                        $this->addBoxContent(self::dataOnlineNode,$tempArray[self::dataOnlineNode],$tempArray[self::dataOnlineProcessingNode]);

                        // marking
                        $content = $isAnyMarking ? self::dummyBox : self::noBox;
                        $subContent = ''; // description for name
                        if (array_key_exists(self::markingNode,$pageArray)) {
                            $markingChosen = $pageArray[self::markingNode][self::chosen];
                            if (in_array($markingChosen,[self::markingNo,self::markingOther])) {
                                $content = $this->translateStringPDF($tempPrefix.self::markingNode.'.'.$markingChosen);
                            }
                            else {
                                $content = $this->getMarkingSentences($pageArray,$addresseeParam,false)[0];
                                foreach ([self::markingNode,self::markingNode.self::markingSuffix] as $type) {
                                    $tempArray = $pageArray[$type] ?? [];
                                    if (($tempArray[self::chosen] ?? '')===self::markingName) {
                                        $subContent .= $tempArray[self::descriptionNode]; // no space between first and second marking because must not be selected twice
                                    }
                                }
                            }
                        }
                        $this->addBoxContent(self::markingNode,$content,$subContent,$isAnyMarkingPersonal ? $this->documentHint : '');

                        // data reuse
                        $pageArray = $measureTimePoint[self::dataReuseNode];
                        $content = $isAnyDataReuse ? self::dummyBox : self::noBox;
                        $tempPrefix = $projecdetailsPagesPrefix.self::dataReuseNode.'.';
                        if (array_key_exists(self::dataReuseNode,$pageArray)) {
                            $tempVal = $pageArray[self::dataReuseNode];
                            $content = $tempVal!=='' ? $this->translateString($tempPrefix.self::dataReuseNode.'.types.'.$tempVal) : '';
                        }
                        $this->addBoxContent(self::dataReuseNode,$content);
                        // data reuse self
                        $content = $isAnyDataReuseSelf ? self::dummyBox : self::noBox;
                        if (array_key_exists(self::dataReuseSelfNode,$pageArray)) {
                            $content = $this->translateBinaryAnswer($pageArray[self::dataReuseSelfNode]);
                        }
                        $this->addBoxContent(self::dataReuseSelfNode,$content);
                        // data reuse how
                        $content = $isAnyDataReuseHow ? self::dummyBox : self::noBox;
                        if (array_key_exists(self::dataReuseHowNode,$pageArray)) {
                            $content = $this->getDataReuseHow($measureTimePoint)[0];
                        }
                        $this->addBoxContent(self::dataReuseHowNode,$content,subHeading: $this->documentHint);
                        // set isAnyTwoAddressees
                        $this->isAnyTwoAddressees |= $isNotParticipants;
                    } // for measure time point
                    $groupIndices[$groupID][1] = false;
                    $groupIndices[$groupID][2] = $measureIndices;
                } // for group
                // if the current study has only one group, but multiple measure time points, the measure time points need extra indentation
                $names[$studyID][1] = !$multipleGroups; // extra indentation if only one group in current study. If no multiple groups at all, extra indentation will also be added
                $names[$studyID][2] = $groupIndices;
            } // for study
            if (!$isMultiple) { // simplify $names
                $tempArray = $names[0];
                $names = $tempArray[0].' - '.$tempArray[2][0][0].' - '.$this->translateStringPDF($projecdetailsPrefix.'overview.'.self::measureTimePointNode);
            }
            $parameters = array_merge($childrenWardsParams, [self::burdensNode => $this->getStringFromBool($isAnyBurdensRisks[self::burdensNode]), 'noBurdens' => $this->getStringFromBool($isAnyBurdensNo), self::risksNode => $this->getStringFromBool($isAnyBurdensRisks[self::risksNode]), 'isPre' => $this->getStringFromBool($isAnyPre || $isNotAnyPreYet), 'isVoluntaryNo' => $this->getStringFromBool($anyVoluntary[1]), 'isAssent' => $this->getStringFromBool($isChildrenWards), 'anyConsentNo' => $this->getStringFromBool($anyConsent[0]), 'anyAssentNo' => $this->getStringFromBool($anyConsent[1])]); // all parameters for all translations of headings and subContent headings
            // box with names of levels
            self::$linkedPage = self::landing;
            self::$isPageLink = true;
            $levelHeading = $this->addHeadingLink($projectdetailsHeadingPrefix.'overview'); // needed in template to identify the box
            $this->addBox($projecdetailsPrefix.'overview', $isMultiple);
            self::$isPageLink = false;
            // setting the content for the boxes
            $studyTrans = $this->levelTrans[self::studyNode];
            $groupTrans = $this->levelTrans[self::groupNode];
            $measureTrans = $this->levelTrans[self::measureTimePointNode];
            foreach ($this->boxContent as $title => $types) { // loop over questions
                $curContent = [self::main => '', self::sub => '', self::paragraph => '', self::inputPage => ''];
                foreach ($types as $type => $answers) {
                    if (in_array($type, [self::main, self::sub])) {
                        if (!array_key_exists(self::noBox, $answers)) {
                            foreach ($answers as $answer => $studies) { // loop over different answers
                                if ($answer!==self::dummyBox) {
                                    $numStudies = count($numIndices); // total number of studies
                                    $multipleStudies = $numStudies>1; // true if at least two studies exist
                                    $isAllSameGroups = true; // // gets true only if all groups of all studies with that answer have the same answer (i.e., there may still be studies with a different answer)
                                    $curCombination = ''; // all combinations of studies, groups and measure time points which have the same answer
                                    $anyMultipleGroup = false; // gets true if any study has more than one group
                                    $anyMultipleMeasureTimePoint = false; // gets true if any group of any study has more than one measure time point
                                    foreach ($studies as $studyIndex => $groups) {
                                        $numGroupsIndices = count($numIndices[$studyIndex]); // total number of groups in this study
                                        $multipleGroups = $numGroupsIndices>1; // true if current study has more than one group
                                        $anyMultipleGroup |= $multipleGroups;
                                        $multipleStudiesGroups = $multipleStudies || $multipleGroups;
                                        $isAllSameMeasure = true; // gets true only if all measure time points of all groups with that answer have the same answer (i.e., there may still be groups with a different answer)
                                        $curGroupCombination = ''; // all groups and measure time points of the current study which have the same answer
                                        $anyMultipleMeasure = false; // gets true if any group of the current study has more than one measure time point
                                        foreach ($groups as $groupIndex => $measureTimePoints) {
                                            foreach ($measureTimePoints as $index => $measureTimePoint) {
                                                ++$measureTimePoints[$index];
                                            }
                                            $measure = implode(', ', $measureTimePoints);
                                            $numMeasures = count($measureTimePoints); // number of measure time points of current group with same answer
                                            $multipleMeasures = $numMeasures>1; // true if multiple measure time points of the current group have the same answer
                                            $anyMultipleMeasure |= $multipleMeasures;
                                            $temp = $measure;
                                            if ($multipleMeasures) {
                                                $measure = str_replace(',', ' &', $measure);
                                            }
                                            $numMeasureIndices = $numIndices[$studyIndex][$groupIndex]; // total number of measure time points of current group
                                            $isSameMeasure = $numMeasures===$numMeasureIndices; // true if all measure time points of current group have same answer
                                            $isAllSameMeasure &= $isSameMeasure;
                                            $curGroupCombination .= "; ".($multipleStudies ? $studyTrans.($studyIndex + 1) : '').($multipleGroups ? ($multipleStudies ? ', ' : '').$groupTrans.($groupIndex + 1) : '').($numMeasureIndices>1 ? ($multipleStudiesGroups ? ', ' : '').($isSameMeasure ? $measureAllTrans : $measureTrans.$measure) : '');
                                        } // foreach $groups
                                        $isAllSameMeasure &= count($groups)===$numGroupsIndices;
                                        $isAllSameGroups &= $isAllSameMeasure;
                                        $anyMultipleMeasureTimePoint |= $anyMultipleMeasure;
                                        $curCombination .= $isMultiple ? ($isAllSameMeasure ? '; '.($multipleStudies ? $studyTrans.($studyIndex + 1) : '').($multipleGroups ? ($multipleStudies ? ', ' : '').$groupAllTrans : '').($anyMultipleMeasure ? ($multipleStudiesGroups ? ', ' : '').$measureAllTrans : '') : $curGroupCombination) : '';
                                    } // foreach $studies
                                    $curContent[$type] .= ($isMultiple ? "\n- ".($isAllSameGroups && $numStudies===count($studies) && $multipleStudies ? $studyAllTrans.($anyMultipleGroup ? ', '.$groupAllTrans : '').($anyMultipleMeasureTimePoint ? ', '.$measureAllTrans : '') : substr($curCombination, 2)).":\n" : '').$answer;
                                }
                            } // foreach $answers
                        }
                        else { // no content for this box
                            $curContent[self::main] = $this->noBoxTrans;
                        }
                    } // if main or sub
                    else { // paragraph, inputPage, or subHeading
                        $curContent[$type] = $answers;
                    }
                } // foreach $types
                $this->addBox($projecdetailsPrefix.$title, trim($curContent[self::main]), trim($curContent[self::sub]), $curContent[self::subHeading] ?? '', $parameters, $curContent[self::paragraph] ?? '', $curContent[self::inputPage] ?? '');
            } // foreach $boxContent
            $html = $this->renderView('PDF/_application.html.twig', array_merge($committeeParam,
                ['heading' => $this->translateStringPDF('heading', $committeeParam),
                    'appType' => $appType,
                    self::guidelinesNode => $guidelines,
                    'applicantInfos' => $applicantSupervisor,
                    'applicantWidth' => $applicantWidth,
                    'contributorsInfos' => $contributorsInfos,
                    'contributorsTasks' => $nameTasks,
                    'contributorsHeading' => $contributorsHeading,
                    'tasks' => array_keys(self::tasksTypes),
                    'levelNames' => $names,
                    'levelHeading' => $levelHeading,
                    'boxContent' => $this->content,
                    'savePDF' => self::$savePDF]));
            $session->set(self::pdfApplication, $html);
            $this->forward('App\Controller\PDF\ParticipationController::createPDF', ['routeIDs' => $routeIDs, 'additional' => [self::consentNode, self::loanReceipt, 'completePost']]);
            if (self::$savePDF) { // single documents or complete form
                $this->generatePDF($session, $html, 'application');
                self::$pdf->removeTemporaryFiles();
                if (!self::$isCompleteForm) {
                    return $this->getDownloadResponse($session, false);
                }
            }
            return new Response($html.(!self::$savePDF ? $session->get(self::pdfParticipation) : ''));
        }
        catch (\Throwable $throwable) {
            return $this->setErrorAndRedirect($session);
        }
    }

    /** Adds an element to $boxContent.
     * @param string $key key to be added
     * @param string $content key to be used for the value of the 'main' key
     * @param string $subContent if provided, key to be used for the value of the 'sub' key
     * @param string $subHeading if provided, key to be used for the value of the 'subHeading' key
     * @param string $paragraph if provided, key for the main heading to be placed above the box
     * @return void
     */
    private function addBoxContent(string $key, string $content, string $subContent = '', string $subHeading = '', string $paragraph = ''): void {
        $this->boxContent[$key][self::main][$content][$this->studyID][$this->groupID][] = $this->measureID;
        if ($subContent!=='') {
            $this->boxContent[$key][self::sub][$subContent][$this->studyID][$this->groupID][] = $this->measureID;
        }
        if ($this->inputPage!=='') {
            $this->boxContent[$key][self::inputPage] = $this->inputPage;
        }
        if ($subHeading!=='') {
            $this->boxContent[$key][self::subHeading] = $subHeading;
        }
        if ($paragraph!=='') {
            $this->boxContent[$key][self::paragraph] = $paragraph;
        }
    }

    /** Adds an element to the $content array with the keys 'heading' and 'boxContent' and eventually 'subHeading' and 'paragraph'.
     * @param string $headingKey translation key for the 'heading' element
     * @param string $boxContent string for the 'boxContent' element
     * @param string $boxContentSub string for the 'subContent' element. If not empty, a heading will be added which must be $headingKey.'Sub'
     * @param string $subHeadingKey if not empty, translation key for the 'subHeading' element or 'documentHint' if the hint should be added
     * @param array $parameters parameters for the translations
     * @param string $paragraph if not empty, translation key for a paragraph heading will be added before the box
     * @param string $inputPage if not empty, translation key for the page that will be displayed after the heading if hovered over the heading
     * @param string $fragment fragment for the heading link
     */
    private function addBox(string $headingKey, string $boxContent, string $boxContentSub = '', string $subHeadingKey = '', array $parameters = [], string $paragraph='', string $inputPage = '', string $fragment = ''): void {
        $translation = 'boxHeadings.'.$headingKey;
        $pagePrefix = 'pages.projectdetails.';
        $this->content = array_merge($this->content,
            [array_merge(['heading' => $this->addHeadingLink($translation,$parameters,$fragment),'content' => $boxContent],
                $boxContentSub!=='' ? ['subContent' => "\n".$this->translateStringPDF($translation.'Sub',$parameters)."\n".$boxContentSub] : [],
                $subHeadingKey!=='' ? [self::subHeading => $subHeadingKey===$this->documentHint ? $this->documentHint : $this->translateStringPDF('boxSubHeadings.'.$subHeadingKey,$parameters)] : [],
                $paragraph!=='' ? [self::paragraph => $this->translateStringPDF('paragraphHeadings.'.$paragraph)] : [],
                $inputPage!=='' ? [self::inputPage => $this->translateStringPDF('boxHeadings.projectdetails.hint',[self::inputPage => $this->translateString($pagePrefix.$inputPage), 'numPages' => $inputPage===self::informationNode && $this->isAnyTwoAddressees && !str_contains($headingKey,self::attendanceNode) ? 2 : 1, 'noBox' => $this->getStringFromBool($boxContent===$this->noBoxTrans)])] : [])]);
    }

    /** If \$array is not an empty string, a string is created where all elements in \$array are translated and eventually are concatenated with a comma.
     * @param array|string $array array whose keys are translation keys
     * @param string $translationPrefix prefix for the translation keys
     * @param bool $implode if true, the values are concatenated with a comma
     * @return array|string all array keys translated
     */
    private function getSelectedCheckboxes(array|string $array, string $translationPrefix, bool $implode = true): array|string {
        $returnArray = [];
        if ($array!=='') {
            foreach ($array as $selection => $value) {
                $returnArray[] = $this->translateStringPDF($translationPrefix.$selection);
            }
        }
        return $implode ? implode(', ',$returnArray) : $returnArray;
    }

    /** If there are either multiple elements of the passed level or the passed name is no an empty string, prefix the passed name by the level.
     * @param string $name name to be eventually prefixed
     * @param string $level name of the level
     * @param int $levelID id of the level
     * @param bool $isMultiple true if multiple elements of the level exist, false otherwise
     * @return array $name eventually prefixed by the level name
     */
    private function getStudyGroupName(string $name, string $level, int $levelID, bool $isMultiple): array {
        if ($isMultiple) {
            return [$this->levelTrans[$level].($levelID+1).($name!=='' ? ': '.$name : '')];
        }
        else {
            return [$this->translateStringPDF('projectdetails.overview.'.$level,['is'.ucfirst($level).'Name' => $this->getStringFromBool($name!==''), $level.'Name' => $name])];
        }
    }

    // further functions

    /** Checks if the 'dependent' group is selected or the closed group question is answered with yes.
     * @param array $groupsArray array containing the information of the groups page
     * @return bool true if the 'dependent' group is selected or the closed group question is answered with yes, false otherwise
     */
    private function getClosedDependent(array $groupsArray): bool {
        $examined = $groupsArray[self::examinedPeopleNode];
        return ($examined!=='' && array_key_exists(self::dependentExaminedNode,$examined) || $groupsArray[self::closedNode][self::chosen]==='0');
    }

    /** Translates the answer to a yes/no question and eventually adds a hyphen.
     * @param string $chosen selected answer
     * @param bool $addHyphenYes if true, the hyphen is added if the answer is ye
     * @param bool $addHyphenNo if true, the hyphen is added if the answer is no
     * @return string translated answer
     */
    private function translateBinaryAnswer(string $chosen, bool $addHyphenYes = false , bool $addHyphenNo = false): string {
        return $this->translateStringPDF('answer',[self::chosen => $chosen]).(($chosen==='0' && $addHyphenYes || $chosen==='1' && $addHyphenNo) ? ' - ' : '');
    }
}