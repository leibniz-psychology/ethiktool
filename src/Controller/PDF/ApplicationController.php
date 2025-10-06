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
    private const fragment = 'fragment'; // key for $boxContent
    private string $documentHint = ''; // hint saying the wording is identical to participation information
    private const noBox = 'noBox'; // key indicating that the box is empty
    private string $noBoxTrans; // translation of box content indicating that no inputs were necessary
    private const dummyBox = 'dummyBox'; // key indicating that the levels should not be added to the box
    private array $levelTrans; // translations of levels

    public function createPDF(Request $request, array $routeIDs = []): ?Response {
        $session = $request->getSession();
        try {
            self::$markInput = false; // do not mark custom text in the application document
            $committeeParam = $session->get(self::committeeParams);
            $appNode = $this->getXMLfromSession($session, getRecent: true); // if supervisor was added while on core data page, indices of contributors have changed
            $this->noBoxTrans = $this->translateStringPDF(self::noBox);

            // projectdetails information
            $supplementTypes = [self::measuresNode,self::interventionsNode,self::otherSourcesNode,self::informationNode,self::post];
            $studyArray = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
            /*
             * $allAddressees: all possible addressees with a value true if the addressee occurs, false otherwise. Only addressees where either pre or post information happens are considered.
             * $isAnySupplement: true if for any measures (0), interventions (1) or other sources (2) supplementary materials are or translated documents for pre (3) or post (4) added.
             * $isAnyTranslated: true if any translated participant documents are added for pre (0) or post (1) information
             * $isAnyOtherSources: true if any other sources question was answered with yes.
             * $isAnyBurdensRisks: indicates if any burdens (0), risks (1), or burdens/risks for contributors (2) are selected (respectively answered with no in the last case).
             * $isAnyBurdensNo: true if any 'noBurdens' was selected.
             * $anyVoluntary: true is any no-description needs to be given
             * anyConsent: array with two elements regarding the consent question: 0: consent question was answered with 'no', 1: true if any assent question was answered with 'no', otherwise false in both cases.
             * $isAnyCompensationVoluntary: true if any compensationVoluntary question was answered with yes, false otherwise.
            */
            [$allAddressees, $isAnyTranslated, $isAnySupplement, $isAnyOtherSources, $isAnyBurdensRisks, $isAnyBurdensNo, $anyVoluntary, $anyConsent, $isAnyCompensationVoluntary] = [[self::addresseeParticipants => false, self::addresseeChildren => false, self::addresseeWards => false], [self::informationNode => false, self::post => false], array_fill_keys($supplementTypes,false), false, [self::burdensNode => false, self::risksNode => false, self::burdensRisksContributorsNode => false], false, false, [false, false], false];
            /* The following values are true if either for third parties or participants at least one of the pre information questions was answered in the respective way:
             * $isAnyPre: yes
             * $isAnyNotPre: no
             * $isNotAnyPreYet: not yet
             * Further variables:
             * $isAnyCompletePost: true if any pre information is partial or deceit. May only be true is $isAnyPre is true
             * $isAnyPost: true if any post information of the third parties was answered with yes
             * $isAnyNoPost: true if any post information of the third parties was answered with no # TODO: Kommentar anpassen, wenn Antragsvarianten umgesetzt
             */
            [$isAnyPre, $isAnyNotPre, $isNotAnyPreYet, $isAnyCompletePost, $isAnyPost, $isAnyNoPost] = [false, false, false, false, false, false];
            $preTrans = self::pre.'Trans';
            $postTrans = self::post.'Trans';
            $preNo = self::pre.'No';
            $preNotYet = self::pre.'NotYet';
            $completePost = 'completePost';
            $postNo = self::post.'No';
            $burdensNo = self::burdensNode.'No';
            $consentNo = self::consentNode.'No';
            $allTrue = [self::addresseeParticipants => false, $preTrans => false, $postTrans => false, self::measuresNode.'PDF' => false, self::interventionsNode.'PDF' => false, self::otherSourcesNode.'PDF' => false, self::addresseeChildren => false, self::addresseeWards => false, self::pre => false, $preNo => false, $preNotYet => false, $completePost => false, self::post => false, $postNo => false, self::otherSourcesNode => false, self::burdensNode => false, $burdensNo => false, self::risksNode => false, self::burdensRisksContributorsNode => false, self::voluntaryNode => false, self::consent => false, $consentNo => false, self::compensationNode => false, self::compensationVoluntaryNode => false]; // Each entry gets true if the respective value in one of the preceding variables gets true
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
                        $isTranslation = array_key_exists(self::documentTranslationPDF,$tempArray[self::documentTranslationNode] ?? []);
                        if ($isPre || $isPreParticipants) {
                            [$isAnyPre, $allTrue[self::pre]] = [true, true];
                            if ($isPre) {
                                if (in_array($additionalChosen, self::preContentIncomplete) || $isPreParticipants && in_array($tempArrayParticipants[self::informationAddNode][self::chosen] ?? '', self::preContentIncomplete)) {
                                    [$isAnyCompletePost, $allTrue[$completePost]] = [true, true];
                                }
                                if ($isTranslation) {
                                    [$isAnyTranslated[self::informationNode],$allTrue[$preTrans]] = [true, true];
                                    $isAnySupplement[self::informationNode] = true;
                                }
                            }
                        }
                        if ($isNotPre || $chosenParticipants==='1') {
                            [$isAnyNotPre, $allTrue[$preNo]] = [true, true];
                            if ($isTranslation) { // if true, post information must be given for third parties
                                [$isAnyTranslated[self::post],$allTrue[$postTrans]] = [true, true];
                                $isAnySupplement[self::post] = true;
                            }
                        }
                        if ($chosen==='' || $tempArrayParticipants!=='' && $chosenParticipants==='') {
                            [$isNotAnyPreYet, $allTrue[$preNotYet]] = [true, true];
                        }
                        $isPost = $isNotPre && $additionalChosen==='0';
                        if ($isPost) {
                            [$isAnyPost, $allTrue[self::post]] = [true, true];
                        }
                        elseif ($isNotPre && $additionalChosen==='1') { // TODO: anpassen, wenn Antragsvarianten umgesetzt
                            [$isAnyNoPost, $allTrue[$postNo]] = [true, true];
                        }
                        // addressee
                        $groupsArray = $measureTimePoint[self::groupsNode];
                        if ($isPre || $isPost) {
                            $curAddressee = $this->getAddressee($groupsArray);
                            [$allAddressees[$curAddressee], $allTrue[$curAddressee]] = [true, true];
                        }
                        // supplementary materials
                        $measuresArray = $measureTimePoint[self::measuresNode];
                        foreach ([self::measuresNode,self::interventionsNode,self::otherSourcesNode] as $type) {
                            $tempVal = $type.'PDF';
                            if (array_key_exists($tempVal,$measuresArray[$type])) {
                                [$isAnySupplement[$type],$allTrue[$tempVal]] = [true, true];
                            }
                        }
                        // other sources
                        if ($measuresArray[self::otherSourcesNode][self::chosen]==='0') {
                            [$isAnyOtherSources,$allTrue[self::otherSourcesNode]] = [true, true];
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
                        if (in_array(self::voluntaryConsentNo, [$voluntaryArray[self::chosen], $voluntaryArray[self::chosen2Node] ?? ''])) {
                            [$anyVoluntary, $allTrue[self::voluntaryNode]] = [true, true];
                        }
                        // consent
                        $consentArray = $consentArray[self::consent];
                        if ($consentArray[self::chosen]==='no') {
                            [$anyConsent[0], $allTrue[self::consentNode]] = [true, true];
                        }
                        if (($consentArray[self::chosen2Node] ?? '')==='no') {
                            [$anyConsent[1], $allTrue[$consentNo]] = [true, true];
                        }
                        // compensation voluntary
                        if (($measureTimePoint[self::compensationNode][self::compensationVoluntaryNode] ?? '')==='0') {
                            [$isAnyCompensationVoluntary, $allTrue[self::compensationVoluntaryNode]] = [true, true];
                        }
                        if (!in_array(false, $allTrue)) {
                            break(3);
                        }
                    }
                }
            }
            $isAnyInformation = $isAnyPre || $isAnyPost;
            $isParticipants = $this->getStringFromBool($allAddressees[self::addresseeParticipants]); // 'true' if any addressee is participants
            $isChildren = $this->getStringFromBool($allAddressees[self::addresseeChildren]); // 'true' if any addressee is children
            $childrenWardsParams = [self::addresseeChildren => $isChildren, self::addresseeWards => $this->getStringFromBool($allAddressees[self::addresseeWards])];
            $isChildrenWards = in_array('true', $childrenWardsParams);
            $this->documentHint = in_array(true, $allAddressees) ? trim($this->translateStringPDF('documentHint', array_merge($childrenWardsParams, [self::addresseeParticipants => $isParticipants, 'isParticipantsChildren' => $this->getStringFromBool(in_array('true', [$isParticipants, $isChildren]))]))) : '';
            $docsParam = ['anyDocs' => $this->getStringFromBool($isAnyInformation), 'anyNoDocs' => $this->getStringFromBool($isAnyNoPost)];
            $informationHintParam = array_merge($docsParam,['informationHint' => $this->translateStringPDF('informationHint',$docsParam)]);

            // application data
            self::$isPageLink = true;
            // core data
            self::$linkedPage = self::coreDataNode;
            $pagePrefix = self::coreDataNode.'.';
            $appDataArray = $this->xmlToArray($appNode->{self::appDataNodeName});
            $pageArray = $appDataArray[self::coreDataNode];
            // application type with review process and eventually guidelines
            $applicationType = $pageArray[self::applicationType][self::chosen];
            $isExtendedResubmission = in_array($applicationType, self::appExtendedResubmission);
            $tempVal = $applicationType!=='' ? $this->translateStringPDF($pagePrefix.self::applicationType.'.'.$applicationType).' - ' : '';
            $applicationProcess = $pageArray[self::applicationProcessNode][self::chosen];
            $tempVal .= $applicationProcess!=='' ? $this->translateString($pagePrefix.self::applicationProcessNode.'.types.'.$applicationProcess) : '';
            $this->addBox($pagePrefix.self::applicationType,$tempVal,$pageArray[self::guidelinesNode][self::descriptionNode] ?? '',paragraph: self::applicationType);
            // project title
            $tempArray = $pageArray[self::projectTitleParticipation];
            $chosen = $tempArray[self::chosen];
            $this->addBox($pagePrefix.self::projectTitle, $pageArray[self::projectTitle], $chosen!=='' ? ($chosen===self::projectTitleDifferent ? $tempArray[self::descriptionNode] : $this->translateString($pagePrefix.self::projectTitleParticipation.'.types.'.$chosen)) : '',fragment: self::projectTitle);
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
                    $infos[$key] = array_key_exists($curInfo, self::positionsTypes) ? $this->translateString(self::positionsTypes[$curInfo]).($isApplicant && $tempVal && in_array($curInfo, [self::positionsStudent, self::positionsPhd]) ? $this->translateStringPDF($pagePrefix.self::qualification) : '') : $curInfo;
                }
                $name = $this->translateString($translationPrefix.$type);
                if ($isApplicant) {
                    $name = $this->addHeadingLink($name,fragment: self::applicant);
                }
                $applicantSupervisor[$type] = [self::nameNode => $name, self::infosNode => $infos];
            }
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
            $this->addBox($pagePrefix.self::funding, trim($tempVal),fragment: self::funding);
            // conflict
            $tempArray = $pageArray[self::conflictNode];
            $tempVal = $tempArray[self::chosen];
            $this->addBox($pagePrefix.self::conflictNode, $this->translateBinaryAnswer($tempVal), ($tempArray[self::descriptionNode] ?? '').($tempVal==='0' && ($isAnyInformation) ? "\n\n".$this->translateStringPDF($pagePrefix.self::conflictNode) : ''), parameters: ['conflictChosen' => $tempVal],fragment: self::conflictNode);
            // support
            self::$linkedPage = self::coreDataNode;
            $tempArray = $pageArray[self::supportNode];
            $tempVal = '';
            $supportPrefix = $pagePrefix.self::supportNode;
            $tempPrefix = $supportPrefix.'.type.';
            if ($tempArray!=='') {
                foreach ($tempArray as $support => $description) {
                    $tempVal .= "\n".($support!==self::noSupport ? '- ' : '').$this->translateString($tempPrefix.$support).($support!==self::noSupport ? ': '.$description : '');
                }
            }
            $this->addBox($supportPrefix, trim($tempVal),fragment: self::supportNode);

            // votes and medicine
            $isOtherVote = false; // gets true if the other vote question is answered with yes
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
                                $isOtherVote = true;
                                $content .= $this->translateStringPDF($tempPrefix.'committee').' '.$tempArray[self::descriptionNode];
                                $content .= $this->translateStringPDF($tempPrefix.'result', ['result' => $tempArray[self::otherVoteResult]]).$tempArray[self::otherVoteResultDescription];
                                break;
                            case self::instVote:
                                if ($isExtendedResubmission) {
                                    $content = $this->translateBinaryAnswer('0');
                                }
                                if ($tempVal) {
                                    $content .= $this->translateStringPDF($tempPrefix.self::instReference).' '.$tempArray[self::instReference];
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

            // summary
            self::$linkedPage = self::summary;
            $this->addBox(self::summary, $appDataArray[self::summary][self::descriptionNode]);

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
            $hasFurtherContributors = $contributorsInfos!=='';
            $tempPageLink = self::$isPageLink;
            self::$isPageLink = $hasFurtherContributors;
            $contributorsInfos = array_merge(['heading' => $this->addHeadingLink('boxHeadings.contributors'), self::content => $hasFurtherContributors ? $contributorsInfos : $this->noBoxTrans],!$hasFurtherContributors ? [self::inputPage => $this->getInputPageHint('contributors',1,'true','contributors')] : []);
            self::$isPageLink = $tempPageLink;

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
            $groupsPrefix = $projecdetailsPrefix.self::groupsNode.'.';
            $examinedPrefix = $groupsPrefix.'examined.';
            $burdensRisksPrefix = $projecdetailsPrefix.self::burdensRisksNode.'.';
            $isMultiple = $this->getMultiStudyGroupMeasure($appNode);
            // translations
            $projectdetailsHeadingPrefix = 'boxHeadings.'.$projecdetailsPrefix;
            $this->levelTrans[self::studyNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::studyNode);
            $studyAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'studyAll');
            $this->levelTrans[self::groupNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::groupNode);
            $groupAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'groupAll');
            $this->levelTrans[self::measureTimePointNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::measureTimePointNode);
            $measureAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'measureTimePointAll');
            $multipleStudies = count($studyArray)>1;
            self::$isPageLink = !$isMultiple;
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
                    $numIndices[$studyID][$groupID] = count($measureTimePointArray);
                    $multipleMeasures = count($measureTimePointArray)>1;
                    $measureIndices = [];
                    foreach ($measureTimePointArray as $measureID => $measureTimePoint) {
                        $this->measureID = $measureID;
                        $measureIndices[$measureID] = $this->getStudyGroupName('', self::measureTimePointNode, $measureID, $multipleMeasures);
                        $groupsArray = $measureTimePoint[self::groupsNode];
                        // get current addressee(s) and create variables that are needed in several questions
                        $addressee = $this->getAddressee($groupsArray);
                        $addresseeParam = [self::addressee => $addressee];
                        $isNotParticipants = $addressee!==self::addresseeParticipants; // true if two addressees
                        // examined (groups)
                        self::$linkedPage = self::groupsNode;
                        $tempArray = $this->getSelectedCheckboxes($groupsArray[self::examinedPeopleNode], $examinedPrefix.'types.', false);
                        $minAge = $groupsArray[self::minAge];
                        $maxAge = $groupsArray[self::maxAge];
                        $this->addBoxContent(self::examinedPeopleNode, implode(', ', $tempArray).$this->translateStringPDF($examinedPrefix.'title', ['number' => count($tempArray), 'limits' => $maxAge==='-1' ? 'noUpperLimit' : ($minAge===$maxAge ? 'sameLimit' : 'other'), self::minAge => $minAge, self::maxAge => $maxAge]), $groupsArray[self::peopleDescription] ?? '', paragraph: self::examinedPeopleNode,fragment: $this->addDiv(self::examinedPeopleNode));
                        // closed (groups)
                        $tempArray = $groupsArray[self::closedNode];
                        $closedTypes = $tempArray[self::closedTypesNode] ?? [];
                        $this->addBoxContent(self::closedNode, $this->translateBinaryAnswer($tempArray[self::chosen],addHyphenYes: true).str_replace('{description}', $closedTypes[self::closedOther] ?? '', $this->getSelectedCheckboxes($closedTypes, $groupsPrefix.self::closedNode.'.')), $tempArray[self::closedNode.self::descriptionCap] ?? '');
                        // sample size (groups)
                        $tempArray = $groupsArray[self::sampleSizeNode];
                        $this->addBoxContent(self::sampleSizeNode, $tempArray[self::sampleSizeTotalNode], $tempArray[self::sampleSizeFurtherNode],fragment: self::sampleSizeTotalNode);
                        $this->addBoxContent(self::sampleSizePlanNode, $tempArray[self::sampleSizePlanNode]);
                        // recruitment (groups)
                        $recruitmentArray = $groupsArray[self::recruitment];
                        $tempArray = $recruitmentArray[self::recruitmentTypesNode];
                        $recruitmentSelection = [];
                        if ($tempArray!=='') {
                            $tempPrefix = $projecdetailsPrefix.self::groupsNode.'.'.self::recruitment.'.';
                            foreach ($tempArray as $selection => $description) {
                                $recruitmentSelection[] = $this->translateStringPDF($tempPrefix.$selection).($description!=='' ? ' ('.$description.')' : '');
                            }
                        }
                        $this->addBoxContent(self::recruitment,implode(', ',$recruitmentSelection),$recruitmentArray[self::descriptionNode] ?? '', fragment: self::recruitmentTypesNode);
                        $measuresPrefix = $projecdetailsPrefix.self::measuresNode.'.';
                        $measuresArray = $measureTimePoint[self::measuresNode];

                        // translated addressees
                        $tempPrefix = $projecdetailsPrefix.self::addressee.'.';
                        $addresseeTrans = $this->translateString($tempPrefix.'thirdParties.'.$addressee);
                        $participantsTrans = $this->translateString($tempPrefix.'participants.'.$addressee);
                        // prefix is answers are given for both addressees
                        $addresseeHeading = $this->translateStringPDF('addresseePrefix', array_merge($addresseeParam, ['isParticipants' => 'false']));
                        $participantsHeading = $this->translateStringPDF('addresseePrefix', array_merge($addresseeParam, ['isParticipants' => 'true']));
                        $participantsHeadingShort = substr($participantsHeading, 3); // without the ' | '
                        // pre information (information/II)
                        self::$linkedPage = self::informationNode;
                        $informationArray = $measureTimePoint[self::informationNode];
                        $informationIIArray = $measureTimePoint[self::informationIINode];
                        $informationIIArray = $informationIIArray ?: [];
                        $informationPrefix = $projecdetailsPrefix.self::informationNode.'.';
                        $preTranslation = $informationPrefix.'type';
                        $tempVal = $informationArray[self::documentTranslationNode][self::descriptionNode] ?? '';
                        $documentTranslation = $tempVal!=='' ? "\n".$this->translateStringPDF($informationPrefix.self::documentTranslationNode,[self::descriptionNode => $tempVal]) : '';
                        $pre = $informationArray[self::chosen];
                        $preParticipants = $isNotParticipants ? ($informationIIArray[self::chosen] ?? '') : '';
                        // is pre information
                        $isPre = $pre==='0';
                        $isPreParticipants = $preParticipants==='0';
                        $tempVal = $addresseeHeading.$this->translateStringPDF($preTranslation, [self::chosen => $pre, self::descriptionNode => $informationArray[self::descriptionNode] ?? '']).($isNotParticipants ? $participantsHeading.$this->translateStringPDF($preTranslation, [self::chosen => $preParticipants, self::descriptionNode => $informationIIArray[self::descriptionNode] ?? '']) : '').($isPre ? $documentTranslation : '');
                        $this->addBoxContent(self::informationNode, $tempVal,subHeading: $isAnyPost ? $this->documentHint : '',paragraph: self::informationNode,fragment: $informationIIArray===[] ? self::pre : self::dummyString);

                        // pre content (information/II)
                        $content = $isAnyPre ? self::dummyBox : self::noBox;
                        self::$linkedPage = !$isPre && $isPreParticipants ? self::informationIINode : self::informationNode; // only needed if !$isMultiple
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
                        $this->addBoxContent(self::preContent, $content, fragment: !($isPre && $isPreParticipants) ? self::preContent : self::dummyString);

                        // pre complete (information/II)
                        $content = $isAnyCompletePost ? self::dummyBox : self::noBox;
                        $subContent = '';
                        self::$linkedPage = !$isIncomplete && $isIncompleteParticipants ? self::informationIINode : self::informationNode;
                        if ($isIncomplete || $isIncompleteParticipants) {
                            $completePost = $informationAddArray[self::complete] ?? '';
                            $completePostParticipants = $informationAddArrayParticipants[self::complete] ?? '';
                            $isCompletePost = $completePost==='0';
                            $isCompletePostParticipants = $completePostParticipants==='0';
                            $content = ($isIncomplete ? $addresseeHeading.$this->translateBinaryAnswer($completePost, true,true).$additionalDescription : '').($isIncompleteParticipants ? ($isIncomplete ? $participantsHeading : $participantsHeadingShort).$this->translateBinaryAnswer($completePostParticipants, true, true).$additionalDescriptionParticipants : '');
                            if ($isCompletePost || $isCompletePostParticipants) {
                                $subContent = ($isCompletePost ? $addresseeHeading.$this->translateStringPDF($preTranslation, [self::chosen => '', self::descriptionNode => $informationAddArray[self::preCompleteType]]) : '').($isCompletePostParticipants ? ($isCompletePost ? $participantsHeading : $participantsHeadingShort).$this->translateStringPDF($preTranslation, [self::chosen => '', self::descriptionNode => $informationAddArrayParticipants[self::preCompleteType]]) : '');
                            }
                        }
                        $this->addBoxContent(self::preComplete, $content, $subContent, fragment: !($isIncomplete && $isIncompleteParticipants) ? self::preComplete : self::dummyString);

                        // post information (information/II)
                        $content = $isAnyNotPre ? self::dummyBox : self::noBox;
                        $isNotPre = $pre==='1';
                        $isNotPreParticipants = $preParticipants==='1';
                        self::$linkedPage = !$isNotPre && $isNotPreParticipants ? self::informationIINode : self::informationNode;
                        if ($isNotPre || $isNotPreParticipants) {
                            $content = ($isNotPre ? $addresseeHeading.$this->translateStringPDF($preTranslation, [self::chosen => $additionalChosen, self::descriptionNode => $additionalDescription]) : '').($isNotPreParticipants ? ($isNotPre ? $participantsHeading : $participantsHeadingShort).$this->translateStringPDF($preTranslation, [self::chosen => $additionalChosenParticipants, self::descriptionNode => $additionalDescriptionParticipants]) : '').($isNotPre && $additionalChosen==='0' ? $documentTranslation : '');
                        }
                        $this->addBoxContent(self::post, $content, fragment: !($isNotPre && $isNotPreParticipants) ? $preNo : self::dummyString);

                        // attendance (information)
                        self::$linkedPage = self::informationNode;
                        $this->addBoxContent(self::attendanceNode, $isChildrenWards ? (array_key_exists(self::attendanceNode, $informationArray) ? $this->translateBinaryAnswer($informationArray[self::attendanceNode]) : self::dummyBox) : self::noBox);

                        // voluntary and consent (consent)
                        self::$linkedPage = self::consentNode;
                        $addresseeString = $this->getAddresseeString($addressee);
                        $participantsString = $this->getAddresseeString($addressee, false, true, $addressee===self::addresseeParticipants);
                        $addresseeStringParams = [self::addressee => $addresseeString, self::participant => $participantsString];
                        $participantsStringParams = [self::addressee => $this->translateString($projecdetailsPrefix.'addressee.participants.'.$addressee), self::participant => $this->getAddresseeString($addressee, false, true, true)];
                        $consentArray = $measureTimePoint[self::consentNode];
                        $consentPrefix = $projecdetailsPrefix.self::consentNode.'.';
                        $tempPrefix = $consentPrefix.'voluntaryDescription.';
                        foreach ([self::voluntaryNode, self::consentNode] as $type) {
                            $isVoluntary = $type===self::voluntaryNode;
                            $tempArray = $consentArray[$type];
                            $tempVal = $consentPrefix.$type;
                            $chosen = $tempArray[self::chosen];
                            $chosen2 = $tempArray[self::chosen2Node] ?? '';
                            $subContent = '';
                            if ($isVoluntary && array_key_exists(self::voluntaryYesDescription,$tempArray)) { // description for 'yes' if dependent
                                $subContent = $this->translateStringPDF($tempPrefix.'yes').$tempArray[self::voluntaryYesDescription];
                            }
                            if (array_key_exists(self::descriptionNode,$tempArray)) { // description for 'no'
                                $subContent .= "\n".($isVoluntary ? $this->translateStringPDF($tempPrefix.'no') : '').$tempArray[self::descriptionNode];
                            }
                            $this->addBoxContent($type, $addresseeHeading.($chosen!=='' ? $this->translateStringPDF($tempVal, array_merge($addresseeStringParams, [self::chosen => $chosen, 'otherDescription' => $tempArray[self::consentOtherDescription] ?? '', self::addresseeType => self::addresseeParticipants])) : '').($isNotParticipants ? $participantsHeading.($chosen2!=='' ? $this->translateStringPDF($tempVal, array_merge($participantsStringParams, [self::chosen => $chosen2, 'otherDescription' => $tempArray[self::consentOtherDescription.'Participants'] ?? '', self::addresseeType => $addressee])) : '') : ''), trim($subContent),paragraph: $isVoluntary ? self::voluntaryNode : '');
                        }
                        // terminate without cons (consent)
                        $terminateConsArray = $consentArray[self::terminateConsNode];
                        $this->addBoxContent(self::terminateConsNode, $this->translateBinaryAnswer($terminateConsArray[self::chosen]), $terminateConsArray[self::descriptionNode] ?? '');
                        // terminate by experimenter (consent)
                        $this->addBoxContent(self::terminateCriteriaNode, $consentArray[self::terminateCriteriaNode], subHeading: $this->translateStringPDF($projecdetailsPrefix.self::terminateCriteriaNode));

                        // measures and interventions (measures)
                        self::$linkedPage = self::measuresNode;
                        foreach ([self::measuresNode, self::interventionsNode] as $type) {
                            $tempArray = $measuresArray[$type];
                            $typeKey = $type.'Type';
                            $content = '';
                            $selections = $tempArray[$typeKey];
                            if ($selections!=='' ){
                                $tempPrefix = $measuresPrefix.$type.'Types.';
                                foreach ($selections as $selection => $description) {
                                    $content .= "\n- ".$this->translateStringPDF($tempPrefix.$selection).($description!=='' ? ': '.$description : '');
                                }
                            }
                            $tempVal = $type===self::measuresNode;
                            $this->addBoxContent($type, trim($content), $tempArray[self::descriptionNode] ?? '',paragraph: $tempVal ? self::measuresNode : '',fragment: $typeKey,paragraphSub: $tempVal);
                        }
                        // other sources (measures)
                        $tempArray = $measuresArray[self::otherSourcesNode];
                        $this->addBoxContent(self::otherSourcesNode, $this->translateBinaryAnswer($tempArray[self::chosen],true).($tempArray[self::otherSourcesNode.self::descriptionCap] ?? ''), subHeading: $isAnyOtherSources ? $this->documentHint : '');
                        // presence (measures)
                        $this->addBoxContent(self::presenceNode, $this->translateBinaryAnswer($measuresArray[self::presenceNode]));

                        // burdens and risks (burdens/risks)
                        self::$linkedPage = self::burdensRisksNode;
                        $burdensRisksArray = $measureTimePoint[self::burdensRisksNode];
                        foreach ([self::burdensNode, self::risksNode, self::burdensRisksContributorsNode] as $type) {
                            $typeArray = $burdensRisksArray[$type];
                            $typeKey = $type.'Type';
                            $this->addBoxContent($type, $type!==self::burdensRisksContributorsNode ? $this->getSelectedCheckboxes($typeArray[$type.'Type'], $burdensRisksPrefix.$type.'.') : $this->translateBinaryAnswer($typeArray[self::chosen]), $typeArray[self::descriptionNode] ?? '', paragraph: $type===self::burdensNode ? 'burdens' : '',fragment: $typeKey);
                            $tempArray = $typeArray[self::burdensRisksCompensationNode] ?? '';
                            $compensation = $type.'Compensation';
                            $isCompensation = $isAnyBurdensRisks[$type];
                            $this->addBoxContent($compensation, $isCompensation ? ($this->getBurdensOrRisks($burdensRisksArray, $type)[0] ? $this->translateBinaryAnswer($tempArray[self::chosen],true,true).$tempArray[self::descriptionNode] : self::dummyBox) : self::noBox,fragment: $isCompensation ? $this->addDiv($compensation) : $typeKey);
                        }
                        // finding (burdens/risks)
                        $tempArray = $burdensRisksArray[self::findingNode];
                        $tempVal = $tempArray[self::chosen];
                        $this->addBoxContent(self::findingNode, $this->translateBinaryAnswer($tempVal,addHyphenYes: true).($tempArray[self::descriptionNode] ?? ''), $tempVal==='0' ? $this->translateStringPDF($burdensRisksPrefix.self::findingNode, array_merge($addresseeParam, [self::chosen => $tempArray[self::informingNode]])) : '');
                        // feedback (burdens/risks)
                        $tempArray = $burdensRisksArray[self::feedbackNode];
                        $this->addBoxContent(self::feedbackNode, $this->translateBinaryAnswer($tempArray[self::chosen]), $tempArray[self::descriptionNode] ?? '');

                        // voluntariness threatened by amount or type of compensation (compensation)
                        self::$linkedPage = self::compensationNode;
                        $pageArray = $measureTimePoint[self::compensationNode];
                        $this->addBoxContent(self::compensationVoluntaryNode,array_key_exists(self::terminateNode,$pageArray) ? $this->translateBinaryAnswer($pageArray[self::compensationVoluntaryNode]) : '',$pageArray[self::compensationTextNode] ?? '');

                        // data privacy
                        self::$linkedPage = self::privacyNode;
                        // processing (data privacy)
                        $this->addBoxContent(self::processingNode,$measureTimePoint[self::privacyNode][self::processingNode],paragraph: self::processingNode,fragment: $this->addDiv(self::processingNode));

                        // data reuse (data reuse)
                        self::$linkedPage = self::dataReuseNode;
                        $pageArray = $measureTimePoint[self::dataReuseNode];
                        $dataReuse = $pageArray[self::dataReuseNode] ?? '';
                        $dataReuseHowArray = $pageArray[self::dataReuseHowNode] ?? [];
                        $dataReuseHow = $dataReuseHowArray[self::chosen] ?? '';
                        $dataReuseSelf = $pageArray[self::dataReuseSelfNode] ?? '';
                        $isDataReuse = in_array($dataReuse,self::dataReuseTypesYes);
                        $isDataReuseSelf = $dataReuseSelf==='0';
                        $tempPrefix = $projecdetailsPrefix.self::dataReuseNode.'.';
                        $content = '';
                        if (array_diff([$dataReuse,$dataReuseHow,$dataReuseSelf],[''])!==[]) { // any reuse question is answered
                            if (in_array($this->getInformationString($informationArray), [self::pre, self::post])) { // refer to participation document if any reuse
                                $content = $this->translateStringPDF((in_array(true,[$isDataReuse,$dataReuseHowArray!==[],$isDataReuseSelf]) ? $tempPrefix.'yes' : $this->translateBinaryAnswer('1')));
                            }
                            else {
                                $content = $this->translateStringPDF($tempPrefix.($isDataReuse ? self::dataReuseNode : ($isDataReuseSelf ? self::dataReuseSelfNode: 'no')),['selection' => $dataReuse, 'reuseType' => !$isDataReuseSelf ? $dataReuseHow : 'own', self::descriptionNode => $dataReuseHowArray[self::descriptionNode] ?? '']);
                            }
                        }
                        $this->addBoxContent(self::dataReuseNode,$content,fragment: ' ');
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
                self::$routeIDs = $this->createRouteIDs([self::studyNode => 1, self::groupNode => 1, self::measureTimePointNode => 1]);
            }
            $parameters = array_merge($childrenWardsParams, $informationHintParam, [self::burdensNode => $this->getStringFromBool($isAnyBurdensRisks[self::burdensNode]), 'noBurdens' => $this->getStringFromBool($isAnyBurdensNo), self::risksNode => $this->getStringFromBool($isAnyBurdensRisks[self::risksNode]), 'isPre' => $this->getStringFromBool($isAnyPre || $isNotAnyPreYet), 'isVoluntaryNo' => $this->getStringFromBool($anyVoluntary), 'isAssent' => $this->getStringFromBool($isChildrenWards), 'anyConsentNo' => $this->getStringFromBool($anyConsent[0]), 'anyAssentNo' => $this->getStringFromBool($anyConsent[1]), 'isCompensationVoluntary' => $this->getStringFromBool($isAnyCompensationVoluntary)]); // all parameters for all translations of headings and subContent headings
            // box with names of levels
            self::$linkedPage = self::landing;
            self::$isPageLink = true;
            $levelHeading = $this->addHeadingLink($projectdetailsHeadingPrefix.'overview'); // needed in template to identify the box
            $this->addBox($projecdetailsPrefix.'overview', $isMultiple);
            self::$isPageLink = !$isMultiple;
            $informationContent = [self::examinedPeopleNode,self::burdensNode,self::risksNode,self::processingNode]; // keys for which content is added once if any information
            $additionalContent = array_merge($supplementTypes,$informationContent,[self::compensationVoluntaryNode]); // keys for which content is added once
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
                $inputPage = $curContent[self::inputPage] ?? '';
                self::$linkedPage = $inputPage;
                $main = trim($curContent[self::main]);
                $fragment = $curContent[self::fragment];
                $tempPageLink = self::$isPageLink;
                if ($main===$this->noBoxTrans || $fragment===self::dummyString) { // if box is not active, show hint instead of linking to question
                    self::$isPageLink = false;
                }
                $sub = trim($curContent[self::sub]);
                if (in_array($title,$additionalContent)) {
                    $isCompensation = $title===self::compensationVoluntaryNode;
                    if (($isAnySupplement[$title] ?? false) || $isAnyInformation && in_array($title,$informationContent) || $isCompensation || $title===self::processingNode) {
                        $tempVal = $this->translateStringPDF($projecdetailsPrefix.'pdf.'.$title,$informationHintParam);
                        $isMain = $isCompensation || $sub==='';
                        $content = $isMain ? $main : $sub;
                        $content = ($isCompensation ? $tempVal.($content!=='' ? "\n\n".$this->translateStringPDF($projecdetailsPrefix.self::compensationVoluntaryNode)."\n" : '') : '').$content.(!$isCompensation ? "\n\n".$tempVal : '');
                        if ($isMain) {
                            $main = $content;
                        }
                        else {
                            $sub = $content;
                        }
                    }
                }
                $this->addBox($projecdetailsPrefix.$title, $main, $sub, $curContent[self::subHeading] ?? '', $parameters, $curContent[self::paragraph] ?? '', !self::$isPageLink ? $inputPage : '', $fragment,paragraphHint: $curContent[self::paragraph.'Sub'] ?? false);
                self::$isPageLink = $tempPageLink;
            } // foreach $boxContent
            $html = $this->renderView('PDF/_application.html.twig', array_merge($committeeParam,
                ['heading' => $this->translateStringPDF('heading', $committeeParam),
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
            $htmlWithVotes = $html;
            if (self::$savePDF && self::$isCompleteForm && $isOtherVote) {
                $htmlWithVotes .= $this->renderView('PDF/_intermediateDocument.html.twig', array_merge($committeeParam,['savePDF' => self::$savePDF, self::content => $this->translateStringPDF($projecdetailsPrefix.'custom.'.self::voteNode)]));
            }
            $session->set(self::pdfApplication, $html);
            $this->forward('App\Controller\PDF\ParticipationController::createPDF', ['routeIDs' => $routeIDs]);
            if (self::$savePDF) { // single documents or complete form
                $this->generatePDF($session, $html, 'application');
                self::$pdf->removeTemporaryFiles();
                if (!self::$isCompleteForm) {
                    return $this->getDownloadResponse($session, false);
                }
                else {
                    $this->generatePDF($session,$htmlWithVotes,'applicationCompleteForm');
                    self::$pdf->removeTemporaryFiles();
                    $this->forward('App\Controller\PDF\ParticipationController::createPDF', ['routeIDs' => $routeIDs, 'markInput' => true]);
                }
            }
            return new Response($html.(!self::$savePDF ? $session->get(self::pdfParticipation.'Marked') : ''));
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
     * @param string $fragment id of element to be linked to if only one study/group/time point exists. If empty, $key will be used.
     * @param bool $paragraphSub if true, a key 'paragraphSub' will be added. May only be true if $paragraph is not empty
     * @return void
     */
    private function addBoxContent(string $key, string $content, string $subContent = '', string $subHeading = '', string $paragraph = '', string $fragment = '', bool $paragraphSub = false): void {
        $content = preg_replace('/ +/',' ',$content); // remove multiple whitespaces between words
        $this->boxContent[$key][self::main][$content][$this->studyID][$this->groupID][] = $this->measureID;
        if ($subContent!=='') {
            $this->boxContent[$key][self::sub][$subContent][$this->studyID][$this->groupID][] = $this->measureID;
        }
        $this->boxContent[$key][self::inputPage] = self::$linkedPage;
        $this->boxContent[$key][self::fragment] = $fragment==='' ? $key : $fragment;
        if ($subHeading!=='') {
            $this->boxContent[$key][self::subHeading] = $subHeading;
        }
        if ($paragraph!=='') {
            $this->boxContent[$key][self::paragraph] = $paragraph;
            if ($paragraphSub) {
                $this->boxContent[$key][self::paragraph.'Sub'] = true;
            }
        }
    }

    /** Adds an element to the $content array with the keys 'heading' and 'boxContent' and eventually 'subHeading' and 'paragraph'.
     * @param string $headingKey translation key for the 'heading' element
     * @param string $boxContent string for the 'boxContent' element
     * @param string $boxContentSub string for the 'subContent' element. If not empty, a heading will be added which must be $headingKey.'Sub'
     * @param string $subHeadingKey if not empty, translation key for the 'subHeading' element or 'documentHint' if the hint should be added
     * @param array $parameters parameters for the translations
     * @param string $paragraph if not empty, translation key for a paragraph heading will be added before the box
     * @param string $inputPage if not empty, translation key for the page that will be displayed above the heading if hovered over the heading
     * @param string $fragment fragment for the heading link
     * @param string $inputPrefix if not empty, page prefix for the page that will be displayed above the heading
     * @param bool $paragraphHint if true, a hint will be placed below the paragraph heading. May only be used if $paragraph is not empty
     */
    private function addBox(string $headingKey, string $boxContent, string $boxContentSub = '', string $subHeadingKey = '', array $parameters = [], string $paragraph='', string $inputPage = '', string $fragment = '', string $inputPrefix = 'projectdetails', bool $paragraphHint = false): void {
        $translation = 'boxHeadings.'.$headingKey;
        $isParagraph = $paragraph!=='';
        $paragraphTrans = 'paragraphHeadings.'.$paragraph;
        $this->content = array_merge($this->content,
            [array_merge(['heading' => $this->addHeadingLink($translation,$parameters,$fragment),'content' => $boxContent],
                $boxContentSub!=='' ? ['subContent' => "\n".$this->translateStringPDF($translation.'Sub',$parameters)."\n".$boxContentSub] : [],
                $subHeadingKey!=='' ? [self::subHeading => $subHeadingKey===$this->documentHint ? $this->documentHint : $this->translateStringPDF('boxSubHeadings.'.$subHeadingKey,$parameters)] : [],
                $isParagraph ? [self::paragraph => $this->translateStringPDF($paragraphTrans)] : [],
                $isParagraph && $paragraphHint ? [self::paragraph.'Sub' => $this->translateStringPDF($paragraphTrans.'Sub',$parameters)] : [],
                $inputPage!=='' ? [self::inputPage => $this->getInputPageHint($inputPage,$inputPage===self::informationIINode || $inputPage===self::informationNode && $this->isAnyTwoAddressees && !str_contains($headingKey,self::attendanceNode) ? 2 : 1,$this->getStringFromBool($boxContent===$this->noBoxTrans),$inputPrefix)] : [])]);
    }

    /** Creates the hint to be shown above the boxes.
     * @param string $inputPage translation key for the page that will be displayed above the heading if hovered over the heading
     * @param int $numPages number of pages that the hint contains
     * @param string $noBox string representation whether the box has no content
     * @param string $pagePrefix page prefix to be used for the input page
     * @return string hint to be shown above the boxes
     */
    private function getInputPageHint(string $inputPage, int $numPages, string $noBox, string $pagePrefix = 'projectdetails'): string {
        return $this->translateStringPDF('boxHeadings.projectdetails.hint',[self::inputPage => $this->translateString('pages.'.$pagePrefix.'.'.$inputPage), 'numPages' => $numPages, 'noBox' => $noBox]);
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