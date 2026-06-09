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
    private bool $hasBoxes = false; // gets true if current box gets created for the current time point
    private bool $isMixed = false; // gets true if time points with new data collection and time points with reanalysis exist
    private array $measureTimePoint = []; // current measure time point
    private string $originNewTrans = ''; // translation of suffix for time points with new data colletion
    private string $originExistingTrans = ''; // translation of suffix for time poiunts with reanalysis
    private const main = 'main'; // top part of the content of a box
    private const sub = 'sub'; // bottom part (e.g., further description) of the content of a box
    private const subHeading = 'subHeading'; // italic part of the heading
    private const paragraph = 'paragraph'; // smaller heading above a box
    private const paragraphSub = 'paragraphSub'; // hint below a paragraph
    private const paragraphTop = 'paragraphTop'; // main heading above a paragraph
    private const boxSub = 'boxSub'; // key for $boxContent
    private const boxNumber = 'boxNumber'; // key for $boxContent
    private const inputPage = 'inputPage'; // key for $boxContent
    private string $documentHint = ''; // hint saying the wording is identical to participation information
    private const noBox = 'noBox'; // key indicating that the box is empty
    private string $noBoxTrans; // translation of box content indicating that no inputs were necessary
    private const dummyBox = 'dummyBox'; // key indicating that the levels should not be added to the box
    private array $levelTrans; // translations of levels
    private bool $isReviewFull; // true if review process is full
    private array $boxesShort = ['coreData.appType','coreData.projectTitle','coreData.projectDates','coreData.funding','coreData.conflict','coreData.support','votes.otherVote','votes.instVote','summary','projectdetails.overview','projectdetails.examinedPeople','projectdetails.closed','projectdetails.recruitment','projectdetails.consent','projectdetails.measures','projectdetails.interventions','projectdetails.burdens','projectdetails.originSources','projectdetails.dataSourceVotes','projectdetails.dataSet','projectdetails.dataSourceProcedure','projectdetails.restriction','projectdetails.dataSourceAccess','projectdetails.legitimization','projectdetails.dataSourceIdentification','projectdetails.publication','projectdetails.dataSourceBurdensRisks','projectdetails.dataSourceBurdensRisksContributors']; // boxes that are created for short review processes

    public function createPDF(Request $request, array $routeIDs = []): ?Response
    {
        $session = $request->getSession();
        try {
            self::$markInput = false; // do not mark custom text in the application document
            $committeeParam = $session->get(self::committeeParams);
            $reviewProcess = $session->get(self::reviewProcess);
            $this->isReviewFull = str_contains($reviewProcess,self::reviewProcessFull);
            $hasDocs = in_array($reviewProcess,self::reviewDocs);
            $appNode = $this->getXMLfromSession($session, getRecent: true); // if supervisor was added while on core data page, indices of contributors have changed
            $this->noBoxTrans = $this->translateStringPDF(self::noBox);

            // projectdetails information
            $supplementTypes = [self::measuresNode,self::interventionsNode,self::otherSourcesNode,self::informationNode,self::post,self::dataSetNode,self::legitimizationNode];
            $projectdetailsNode = $appNode->{self::projectdetailsNodeName};
            $studyArray = $this->addZeroIndex($this->xmlToArray($projectdetailsNode)[self::studyNode]);
            /*
             * $allAddressees: all possible addressees with a value true if the addressee occurs, false otherwise. Only addressees where either pre or post information for the third parties (or participants if no third parties) happens are considered.
             * $isAnyWards: true if any wards is selected
             * $isAnyTranslated: true if any translated participant documents are added for pre (0) or post (1) information
             * $isAnySupplement: true if for any measures (0), interventions (1), or other sources (2) supplementary materials or translated documents for pre (3) or post (4) or the participation documents of the original data set (5) are added.
             * $isAnyOriginMissing: true if any origin question is not yet answered
             * $isAnyOriginNew: true if any data source origin is "new"
             * $isAnyOriginExisting: true if any data source origin is "existing"
             * $isAnyDataSourceVotes: true if any data source votes question is asked
             * $isAnyDataSourceFurther: true if any further question for data source is asked
             * $isAnyDataSet: true if any data set question is asked
             * $isAnyOtherSources: true if any other sources question was answered with yes.
             * $isAnyBurdensRisks: indicates if any burdens (0), risks (1), or burdens/risks for contributors (2) are selected (respectively answered with no in the last case).
             * $isAnyBurdensNo: true if any 'noBurdens' was selected.
             * $isAnyBurdensEveryday: true if any burdens everyday question was answered with yes
             * $anyVoluntary: true is any no-description needs to be given
             * anyConsent: array with two elements regarding the consent question: 0: consent question was answered with 'no', 1: true if any assent question was answered with 'no', otherwise false in both cases.
             * $isAnyCompensation: true if any compensation is given if information is pre or not yet chosen (0) of if nor pre information is given (1)
             * $isAnyCompensationVoluntary: true if any compensationVoluntary question was answered with yes, false otherwise.
            */
            [$allAddressees, $isAnyWards, $isAnyTranslated, $isAnySupplement, $isAnyOriginMissing, $isAnyDataSourceFurther, $isAnyDataSet, $isAnyOriginNew, $isAnyOriginExisting, $isAnyDataSourceVotes, $isAnyOtherSources, $isAnyBurdensRisks, $isAnyBurdensNo, $isAnyBurdensEveryday, $anyVoluntary, $anyConsent, $isAnyCompensation, $isAnyCompensationVoluntary] = [[self::addresseeParticipants => false, self::addresseeChildren => false, self::addresseeWards => false], false, [self::informationNode => false, self::post => false], array_fill_keys($supplementTypes,false), false, false, false, false, false, false, false, [self::burdensNode => false, self::risksNode => false, self::burdensRisksContributorsNode => false], false, false, false, [false, false], [false, false], false];
            /* The following values are true if either for third parties or participants at least one of the information questions was answered in the respective way:
             * $isAnyPre: yes
             * $isAnyDocInformation: true if any information may be created, i.e., if for by third parties either pre or post information is yes
             * $isAnyNotPre: no
             * $isNotAnyPreYet: not yet
             * Further variables:
             * $isAnyCompletePost: true if any pre information is partial or deceit. May only be true if $isAnyPre is true
             * $isAnyPreAbort: true if any complete post was answered with yes
             * $isAnyPreAbortOtherNo: true if any pre abort question was answered with 'abortOther' (0) or 'abortNo' (1). May only be true if $isAnyPreAbort is true
             * $isAnyPost: true if any post information of the third parties was answered with yes
             * $isAnyNoPost: true if any post information of the third parties was answered with no
             */
            [$isAnyPre, $isAnyDocInformation, $isAnyNotPre, $isNotAnyPreYet, $isAnyCompletePost, $isAnyPreAbort, $isAnyPreAbortOtherNo, $isAnyPost, $isAnyNoPost] = [false, false, false, false, false, false, [false,false], false, false];
            $preTrans = self::pre.'Trans';
            $preInformation = self::pre.self::informationNode;
            $postTrans = self::post.'Trans';
            $preNo = self::pre.'No';
            $preNotYet = self::pre.'NotYet';
            $completePost = 'completePost';
            $postNo = self::post.'No';
            $burdensNo = self::burdensNode.'No';
            $consentNo = self::consentNode.'No';
            $compensationPost = self::compensationNode.self::post;
            $originNew = self::originNode.self::originNew;
            $originMissing = self::originNode.'Missing';
            $allTrue = [self::dataSourceNode => false, self::dataSetNode => false, self::addresseeParticipants => false, $preTrans => false, $preInformation => false, $postTrans => false, self::measuresNode.'PDF' => false, self::interventionsNode.'PDF' => false, self::otherSourcesNode.'PDF' => false, self::addresseeChildren => false, self::addresseeWards => false, self::originNode => false, $originNew => false, $originMissing => false, self::dataSourceVotesNode => false, self::pre => false, $preNo => false, $preNotYet => false, $completePost => false, self::preAbort => false, self::preAbortOther => false, self::preAbortNo => false, self::post => false, $postNo => false, self::otherSourcesNode => false, self::burdensNode => false, $burdensNo => false, self::burdensEveryday => false, self::risksNode => false, self::burdensRisksContributorsNode => false, self::voluntaryNode => false, self::consent => false, $consentNo => false, self::compensationNode => false, $compensationPost => false, self::compensationVoluntaryNode => false]; // Each entry gets true if the respective value in one of the preceding variables gets true
            foreach ($studyArray as $study) {
                foreach ($this->addZeroIndex($study[self::groupNode]) as $group) {
                    foreach ($this->addZeroIndex($group[self::measureTimePointNode]) as $measureTimePoint) {
                        // data source
                        $tempArray = $measureTimePoint[self::dataSourceNode];
                        $origin = $tempArray[self::originNode][self::chosen];
                        if ($origin==='') {
                            [$isAnyOriginMissing, $allTrue[$originMissing]] = [true, true];
                        }
                        if (array_key_exists(self::dataSourceVotesNode,$tempArray)) {
                            [$isAnyDataSourceVotes, $allTrue[self::dataSourceVotesNode]] = [true, true];
                        }
                        if (array_key_exists(self::dataSourceProcedureNode,$tempArray)) {
                            [$isAnyDataSourceFurther,$allTrue[self::dataSourceNode]] = [true, true];
                        }
                        if (array_key_exists(self::dataSetNode,$tempArray)) {
                            [$isAnyDataSet,$allTrue[self::dataSetNode]] = [true, true];
                            if (array_key_exists(self::dataSetPDF,$tempArray)) {
                                $isAnySupplement[self::dataSetNode] = true;
                            }
                        }
                        $legitimizationArray = $tempArray[self::legitimizationNode] ?? '';
                        if ($legitimizationArray!=='' && array_key_exists(self::legitimizationConsentNew,$legitimizationArray)) {
                            $isAnySupplement[self::legitimizationNode] = true;
                        }
                        if ($origin===self::originExisting) {
                            [$isAnyOriginExisting, $allTrue[self::originNode]] = [true, true];
                        } elseif ($origin===self::originNew) {
                            [$isAnyOriginNew, $allTrue[$originNew]] = [true, true];
                            // information
                            // pre information
                            $tempArray = $measureTimePoint[self::informationNode];
                            $tempArrayParticipants = $measureTimePoint[self::informationIINode];
                            $chosen = $tempArray[self::pre];
                            $chosenParticipants = $tempArrayParticipants[self::pre] ?? '';
                            $isPre = $chosen==='0';
                            $isNotPre = $chosen==='1';
                            $isPreParticipants = $chosenParticipants==='0';
                            $anyPre = $isPre || $isPreParticipants;
                            $isTranslation = array_key_exists(self::documentTranslationPDF, $tempArray);
                            if ($anyPre) {
                                [$isAnyPre, $allTrue[self::pre]] = [true, true];
                                if ($isPre) {
                                    [$isAnyDocInformation, $allTrue[$preInformation]] = [true, true];
                                    if ($isTranslation) {
                                        [$isAnyTranslated[self::informationNode], $allTrue[$preTrans]] = [true, true];
                                        $isAnySupplement[self::informationNode] = true;
                                    }
                                }
                                if ($isPre && in_array($tempArray[self::preContent], self::preContentIncomplete) || $isPreParticipants && in_array($tempArrayParticipants[self::preContent] ?? '', self::preContentIncomplete)) {
                                    [$isAnyCompletePost, $allTrue[$completePost]] = [true, true];
                                    $preCompleteArray = $tempArray[self::preComplete] ?? [];
                                    $preCompleteArrayParticipants = $tempArrayParticipants[self::preComplete] ?? [];
                                    if (array_key_exists(self::preAbort, $preCompleteArray) || array_key_exists(self::preAbort, $preCompleteArrayParticipants)) {
                                        [$isAnyPreAbort, $allTrue[self::preAbort]] = [true, true];
                                        $chosen = [$preCompleteArray[self::preAbort][self::chosen] ?? '', $preCompleteArrayParticipants[self::preAbort][self::chosen] ?? ''];
                                        if (in_array(self::preAbortOther, $chosen)) {
                                            [$isAnyPreAbortOtherNo[0], $allTrue[self::preAbortOther]] = [true, true];
                                        }
                                        if (in_array(self::preAbortNo, $chosen)) {
                                            [$isAnyPreAbortOtherNo[1], $allTrue[self::preAbortNo]] = [true, true];
                                        }
                                    }
                                }
                                }
                            if ($isNotPre || $chosenParticipants==='1') {
                                [$isAnyNotPre, $allTrue[$preNo]] = [true, true];
                                if ($isTranslation) { // if true, post information must be given for third parties
                                    [$isAnyTranslated[self::post], $allTrue[$postTrans]] = [true, true];
                                    $isAnySupplement[self::post] = true;
                                }
                            }
                            if ($chosen==='' || $tempArrayParticipants!=='' && $chosenParticipants==='') {
                                [$isNotAnyPreYet, $allTrue[$preNotYet]] = [true, true];
                            }
                            $post = $tempArray[self::post][self::chosen] ?? '';
                            $isPost = $isNotPre && $post==='0';
                            if ($isPost) {
                                [$isAnyDocInformation, $allTrue[$preInformation]] = [true, true];
                                [$isAnyPost, $allTrue[self::post]] = [true, true];
                            } elseif ($isNotPre && $post==='1') {
                                [$isAnyNoPost, $allTrue[$postNo]] = [true, true];
                            }
                            // addressee
                            $curAddressee = $this->getAddressee($measureTimePoint[self::groupsNode]);
//                            if ($anyPre || $isPost || ($tempArrayParticipants[self::post][self::chosen] ?? '')==='0') {
                                [$allAddressees[$curAddressee], $allTrue[$curAddressee]] = [true, true];
//                            }
                            if ($curAddressee!==self::addresseeParticipants) {
                                $isAnyWards = true;
                            }
                            // supplementary materials
                            $measuresArray = $measureTimePoint[self::measuresNode];
                            foreach ([self::measuresNode,self::interventionsNode] as $type) {
                                $tempVal = $type.'PDF';
                                if (array_key_exists($tempVal, $measuresArray)) {
                                    [$isAnySupplement[$type], $allTrue[$tempVal]] = [true, true];
                                }
                            }
                            // other sources
                            $tempArray = $measuresArray[self::otherSourcesNode];
                            if ($tempArray[self::chosen]==='0') {
                                [$isAnyOtherSources, $allTrue[self::otherSourcesNode]] = [true, true];
                                if (array_key_exists(self::otherSourcesPDF, $tempArray)) { // supplementary material
                                    [$isAnySupplement[self::otherSourcesNode], $allTrue[self::otherSourcesNode]] = [true, true];
                                }
                            }
                            // burdens and risks
                            $burdensRisksArray = $measureTimePoint[self::burdensRisksNode];
                            foreach ([self::burdensNode, self::risksNode, self::burdensRisksContributorsNode] as $type) {
                                $isBurdens = $type===self::burdensNode;
                                $tempArray = $this->getBurdensOrRisks($burdensRisksArray, $type, false);
                                if ($tempArray[0]) { // at least one option except 'no' is selected
                                    [$isAnyBurdensRisks[$type], $allTrue[$type]] = [true, true];
                                    if ($isBurdens && $burdensRisksArray[self::burdensNode][self::burdensEveryday]==='0') {
                                        [$isAnyBurdensEveryday, $allTrue[self::burdensEveryday]] = [true, true];
                                    }
                                } elseif ($isBurdens && $tempArray[1]) {
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
                            // compensation (voluntary)
                            $compensationArray = $measureTimePoint[self::compensationNode];
                            $tempArray = $compensationArray[self::compensationTypeNode];
                            if ($tempArray!=='' && !array_key_exists(self::compensationNo, $tempArray)) {
                                if ($isNotPre) {
                                    [$isAnyCompensation[1], $allTrue[$compensationPost]] = [true, true];
                                } else {
                                    [$isAnyCompensation[0], $allTrue[self::compensationNode]] = [true, true];
                                }
                                if ($compensationArray[self::compensationVoluntaryNode]!=='') {
                                    [$isAnyCompensationVoluntary, $allTrue[self::compensationVoluntaryNode]] = [true, true];
                                }
                            }
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
            $this->documentHint = $isAnyDocInformation && $reviewProcess===self::reviewFullDocs ? trim($this->translateStringPDF('documentHint', array_merge($childrenWardsParams, [self::addresseeParticipants => $isParticipants, 'isParticipantsChildren' => $this->getStringFromBool(in_array('true', [$isParticipants, $isChildren]))]))) : ''; // currently only description of no pre information and other sources contain document hint, i.e., only if review process is "fullDocs"
            $docsParam = ['anyDocs' => $this->getStringFromBool($hasDocs && $isAnyInformation), 'anyNoDocs' => $this->getStringFromBool(!$hasDocs || $isAnyNoPost)];
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
            $this->addBox($pagePrefix.self::applicationType,$tempVal,$pageArray[self::guidelinesNode][self::descriptionNode] ?? '',paragraph: self::applicationType,fragment: self::applicationType);
            // project title
            $this->addBox($pagePrefix.self::projectTitle, $pageArray[self::projectTitle].(($pageArray[self::projectTitleParticipation][self::chosen] ?? '')===self::projectTitleDifferent ? "\n\n".$this->translateStringPDF($pagePrefix.self::projectTitle) : ''),fragment: self::projectTitle);
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
            $content = $this->translateStringPDF($tempPrefix.'start');
            if ($start!=='') {
                $content .= $start==='0' ? $this->translateStringPDF($tempPrefix.'next') : $this->convertDate($start);
            }
            $content .= ' '.$this->translateStringPDF($tempPrefix.'end').$this->convertDate($pageArray[self::projectEnd]).(array_key_exists(self::descriptionNode,$projectStart) ? $this->translateStringPDF($tempPrefix.'startedDescription').$projectStart[self::descriptionNode].(array_key_exists(self::projectStartRetrospective,$projectStart) ? $this->translateStringPDF($tempPrefix.self::projectStartRetrospective).$projectStart[self::projectStartRetrospective] : '') : '');
            $this->addBox($pagePrefix.'projectDates', $content, fragment: 'projectDates');
            // funding
            $tempArray = $pageArray[self::funding];
            $tempVal = '';
            if ($tempArray!=='') { // at least one type was selected
                $fundingPrefix = $pagePrefix.self::funding;
                $tempPrefix = $fundingPrefix.'.';
                foreach ($tempArray as $type => $source) {
                    $fundingState = $source[self::fundingStateNode] ?? '';
                    $tempVal .= "\n- ".$this->translateString($tempPrefix.$type).($type!==self::fundingQuali ? ": ".$source[self::descriptionNode].($fundingState!=='' ? "\n".$this->translateString($tempPrefix.self::fundingStateNode.'.'.$fundingState) : '') : '');
                }
                if (($pageArray[self::requestedConfirm] ?? '')==='1') { // confirmation for requested funding
                    $tempVal .= $this->translateStringPDF($fundingPrefix);
                }
            }
            $this->addBox($pagePrefix.self::funding, trim($tempVal),fragment: self::funding);
            // conflict
            if ($this->isReviewFull) {
                $tempArray = $pageArray[self::conflictNode];
                $tempVal = $tempArray[self::chosen];
                $this->addBox($pagePrefix.self::conflictNode, $this->translateBinaryAnswer($tempVal), ($tempArray[self::descriptionNode] ?? '').($tempVal==='0' && $isAnyInformation ? "\n\n".$this->translateStringPDF($pagePrefix.self::conflictNode) : ''), parameters: ['conflictChosen' => $tempVal],fragment: self::conflictNode);
            }
            // support
            self::$linkedPage = self::coreDataNode;
            $tempPrefix = $pagePrefix.self::supportNode;
            $this->addBox($tempPrefix, $this->getSelectedCheckboxes($pageArray[self::supportNode],$tempPrefix.'.',implodeLines: true),fragment: self::supportNode);

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
                    $subContent = '';
                    $translationParams = [];
                    $tempVal = $tempVal==='0';
                    if ($tempVal || $subPage===self::instVote && $isExtendedResubmission) { // if application type is changed, it may not yet be updated on votes
                        switch ($subPage) {
                            case self::otherVote:
                                $isOtherVote = true;
                                $resultParam = ['result' => $tempArray[self::otherVoteResult]];
                                $content .= $this->translateStringPDF($tempPrefix.'committee').' '.$tempArray[self::descriptionNode];
                                $content .= "\n".$this->translateStringPDF($tempPrefix.'result', $resultParam);
                                $subContent = $tempArray[self::otherVoteResultDescription]."\n\n".$this->translateStringPDF($tempPrefix.'pdf');
                                $translationParams = $resultParam;
                                break;
                            case self::instVote:
                                if ($isExtendedResubmission) {
                                    $content = $this->translateBinaryAnswer('0');
                                }
                                if ($tempVal) {
                                    $content .= $this->translateStringPDF($tempPrefix.self::instReference).' '.$tempArray[self::instReference];
                                    $subContent = $tempArray[self::instVoteText];
                                    $translationParams = ['type' => $applicationType];
                                }
                                break;
                            case self::medicine:
                                $subContent = $tempArray[self::descriptionNode];
                                break;
                            case self::physicianNode:
                                $tempArray = $tempArray[self::descriptionNode];
                                $subContent = $this->translateStringPDF($pagePrefix.self::physicianNode, array_merge($committeeParam, ['result' => $tempArray[self::chosen]])).$tempArray[self::descriptionNode];
                                break;
                            default:
                                break;
                        }
                    }
                    $this->addBox($pagePrefix.$subPage, $content, $subContent, parameters: array_merge($committeeParam,$translationParams), fragment: $subPage);
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
            // names: names of studies, groups and measure time points and indices of measure time points. key: study ID, value: array. In this array; first element: name of study, second element: array of arrays (one for each group in this study). In each of these arrays: same for groups. if any of the arrays is empty, there is at most one element in the level underneath.
            // Example: [0 =>
            //               [0 => 'study 1: studyName',
            //                1 => [0 =>
            //                          [0 => 'group 1: groupName',
            //                           1 => [0 => 'measure time point 1: measureTimePointName'
            //                                ]
            //                          ]
            //                     ]
            //               ]
            //          ]
            $names = [];
            $projecdetailsPrefix = 'projectdetails.';
            $projectdetailsPagesPrefix = $projecdetailsPrefix.'pages.';
            $groupsPrefix = $projecdetailsPrefix.self::groupsNode.'.';
            $examinedPrefix = $groupsPrefix.'examined.';
            $burdensRisksPrefix = $projecdetailsPrefix.self::burdensRisksNode.'.';
            $isMultiple = $this->getMultiStudyGroupMeasure($appNode);
            $sampleSizes = []; // contains all sample sizes
            $sampleSizesMulti = []; // contains all sample sizes which occure more than once
            // translations
            $projectdetailsHeadingPrefix = 'boxHeadings.'.$projecdetailsPrefix;
            $this->levelTrans[self::studyNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::studyNode);
            $studyAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'studyAll');
            $this->levelTrans[self::groupNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::groupNode);
            $groupAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'groupAll');
            $this->levelTrans[self::measureTimePointNode] = $this->translateStringPDF($projectdetailsHeadingPrefix.self::measureTimePointNode);
            $measureAllTrans = $this->translateStringPDF($projectdetailsHeadingPrefix.'measureTimePointAll');
            $multipleStudies = count($studyArray)>1;
            $hasCriteria = $reviewProcess!==self::reviewShortService && $this->checkElement(self::criteriaIncludeNode,$projectdetailsNode->{self::studyNode}[0]->{self::groupNode}[0]->{self::measureTimePointNode}[0]->{self::groupsNode}); // criteria nodes exist either for all time points or for none
            $this->isMixed = $isAnyOriginNew && $isAnyOriginExisting;
            $tempPrefix = $projecdetailsPrefix.'overview.'.self::originNode.'.';
            $this->originNewTrans = $this->translateStringPDF($tempPrefix.self::originNew);
            $this->originExistingTrans = $this->translateStringPDF($tempPrefix.self::originExisting);
            $reviewFullParam = ['isReviewFull' => $this->getStringFromBool($this->isReviewFull)];
            self::$isPageLink = !$isMultiple;
            foreach ($studyArray as $studyID => $study) {
                $this->studyID = $studyID;
                $names[$studyID] = $this->getLevelName($study[self::nameNode], self::studyNode, $studyID, $multipleStudies);
                $groupArray = $this->addZeroIndex($study[self::groupNode]);
                $multipleGroups = count($groupArray)>1;
                $groupIndices = [];
                foreach ($groupArray as $groupID => $group) {
                    $this->groupID = $groupID;
                    $groupIndices[$groupID] = $this->getLevelName($group[self::nameNode], self::groupNode, $groupID, $multipleGroups);
                    $measureTimePointArray = $this->addZeroIndex($group[self::measureTimePointNode]);
                    $numIndices[$studyID][$groupID] = count($measureTimePointArray);
                    $multipleMeasures = count($measureTimePointArray)>1;
                    $measureIndices = [];
                    foreach ($measureTimePointArray as $measureID => $measureTimePoint) {
                        $this->measureID = $measureID;
                        $this->measureTimePoint = $measureTimePoint;
                        $measureIndices[$measureID] = $this->getLevelName($measureTimePoint[self::nameNode], self::measureTimePointNode, $measureID, $multipleMeasures);
                        $groupsArray = $measureTimePoint[self::groupsNode];
                        if ($isAnyOriginNew) {
                            $hasGroups = $groupsArray!=='';
                            $this->hasBoxes = $hasGroups;
                            // get current addressee(s) and create variables that are needed in several questions
                            $addressee = $hasGroups ? $this->getAddressee($groupsArray) : '';
                            $addresseeParam = [self::addressee => $addressee];
                            $isNotParticipants = $addressee!==self::addresseeParticipants; // true if two addressees
                            $this->isAnyTwoAddressees |= $isNotParticipants;
                            // examined (groups)
                            self::$linkedPage = self::groupsNode;
                            $minAge = $groupsArray[self::minAge] ?? '';
                            $maxAge = $groupsArray[self::maxAge] ?? '';
                            $examinedArray = $groupsArray[self::examinedPeopleNode] ?? [];
                            $isSmaller14 = $minAge!=='' && $minAge<14;
                            if ($isSmaller14) {
                                unset($examinedArray[self::wardsExaminedNode]); // if min age is below 14, wards is selected automatically
                            }
                            $tempArray = $this->getSelectedCheckboxes($examinedArray, $examinedPrefix.'types.', false);
                            $numSelected = count($tempArray);
                            $this->addBoxContent(self::examinedPeopleNode, implode(', ', $tempArray).$this->translateStringPDF($examinedPrefix.'title', ['number' => $numSelected, 'limits' => $maxAge==='-1' ? 'noUpperLimit' : ($minAge===$maxAge || array_diff([''],[$minAge,$maxAge])===[] ? 'sameLimit' : 'other'), self::minAge => $minAge ?: ($maxAge ?: -2), self::maxAge => $maxAge ?: -2, 'isWardsOnly' => $this->getStringFromBool($isSmaller14 && $numSelected===0), 'isWardsAge' => $this->getStringFromBool($isSmaller14)]), $groupsArray[self::peopleDescription] ?? '', paragraph: self::examinedPeopleNode, fragment: $this->addDiv(self::examinedPeopleNode), paragraphTop: true);
                            // closed (groups)
                            $tempArray = $groupsArray[self::closedNode] ?? [];
                            $this->addBoxContent(self::closedNode,$this->translateBinaryAnswer($tempArray[self::chosen] ?? '',addHyphenYes: true).$this->getSelectedCheckboxes($tempArray[self::closedTypesNode] ?? '',$groupsPrefix.self::closedNode.'.'));
                            // sample size (groups)
                            $tempArray = $groupsArray[self::sampleSizeNode] ?? [];
                            $tempVal = $tempArray[self::sampleSizeTotalNode] ?? '';
                            if ($tempVal!=='') {
                                if (in_array($tempVal, $sampleSizes)) {
                                    $sampleSizesMulti[] = $tempVal;
                                }
                                $sampleSizes[] = $tempVal;
                            }
                            $this->addBoxContent(self::sampleSizeNode, $tempArray[self::sampleSizeTotalNode] ?? '', $tempArray[self::sampleSizeFurtherNode] ?? '', fragment: self::sampleSizeTotalNode);
                            $this->addBoxContent(self::sampleSizePlanNode, $tempArray[self::sampleSizePlanNode] ?? '');
                            // recruitment (groups)
                            $this->addBoxContent(self::recruitment, $this->getSelectedCheckboxes($groupsArray[self::recruitment] ?? '',$groupsPrefix.self::recruitment.'.'), $groupsArray[self::recruitmentFurther] ?? '', fragment: self::recruitment);
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
                            $informationArray = $informationArray==='' ? [] : $informationArray;
                            $informationIIArray = $measureTimePoint[self::informationIINode];
                            $informationIIArray = $informationIIArray ?: [];
                            $informationPrefix = $projecdetailsPrefix.self::informationNode.'.';
                            $preTranslation = $informationPrefix.'type';
                            $tempVal = $informationArray[self::documentTranslationNode][self::descriptionNode] ?? '';
                            $documentTranslation = $tempVal!=='' ? "\n".$this->translateStringPDF($informationPrefix.self::documentTranslationNode, [self::descriptionNode => $tempVal]) : '';
                            $pre = $informationArray[self::pre] ?? '';
                            $preParticipants = $isNotParticipants ? ($informationIIArray[self::pre] ?? '') : '';
                            // is pre information
                            $isPre = $pre==='0';
                            $isPreParticipants = $preParticipants==='0';
                            $tempVal = $addresseeHeading.$this->translateStringPDF($preTranslation, [self::chosen => $pre, self::descriptionNode => $informationArray[$isPre ? self::preType : self::preText] ?? '']).($isNotParticipants ? $participantsHeading.$this->translateStringPDF($preTranslation, [self::chosen => $preParticipants, self::descriptionNode => $informationIIArray[$isPreParticipants ? self::preType : self::preText] ?? '']) : '').($isPre ? $documentTranslation : '');
                            $this->addBoxContent(self::informationNode, $tempVal, subHeading: $isAnyPost ? $this->documentHint : '', paragraph: self::informationNode, fragment: $informationIIArray===[] ? self::pre : self::dummyString);

                            // pre content (information/II)
                            $content = $isAnyPre ? self::dummyBox : self::noBox;
                            self::$linkedPage = !$isPre && $isPreParticipants ? self::informationIINode : self::informationNode; // only needed if !$isMultiple
                            // is either partial or deceit
                            $isIncomplete = false;
                            $isIncompleteParticipants = false;
                            if ($isPre || $isPreParticipants) {
                                $tempPrefix = $informationPrefix.self::preContent;
                                $chosen = $isPre ? $informationArray[self::preContent] : '';
                                $isIncomplete = in_array($chosen, self::preContentIncomplete);
                                $isIncompleteParticipants = in_array($informationIIArray[self::preContent] ?? '', self::preContentIncomplete);
                                $content = ($isPre ? $addresseeHeading.$this->translateStringPDF($tempPrefix, [self::chosen => $chosen, self::addressee => $addresseeTrans]) : '').($isPreParticipants ? ($isPre ? $participantsHeading : $participantsHeadingShort).$this->translateStringPDF($tempPrefix, [self::chosen => $informationIIArray[self::preContent], self::addressee => $participantsTrans]) : '');
                            }
                            $this->addBoxContent(self::preContent, $content, fragment: !($isPre && $isPreParticipants) ? self::preContent : self::dummyString);

                            // pre complete (information/II)
                            $content = $isAnyCompletePost ? self::dummyBox : self::noBox;
                            $subContent = '';
                            self::$linkedPage = !$isIncomplete && $isIncompleteParticipants ? self::informationIINode : self::informationNode;
                            $preCompleteArray = $informationArray[self::preComplete] ?? [];
                            $preCompleteArrayParticipants = $informationIIArray[self::preComplete] ?? [];
                            $completePost = $preCompleteArray[self::chosen] ?? '';
                            $completePostParticipants = $preCompleteArrayParticipants[self::chosen] ?? '';
                            if ($isIncomplete || $isIncompleteParticipants) {
                                $tempVal = $isIncomplete ? $participantsHeading : $participantsHeadingShort;
                                $content = ($isIncomplete ? $addresseeHeading.$this->translateBinaryAnswer($completePost, true).($completePost==='0' ? $this->translateStringPDF($preTranslation, [self::chosen => '', self::descriptionNode => $preCompleteArray[self::preCompleteType] ?? '']) : '') : '').($isIncompleteParticipants ? $tempVal.$this->translateBinaryAnswer($completePostParticipants, true).($completePostParticipants==='0' ? $this->translateStringPDF($preTranslation, [self::chosen => '', self::descriptionNode => $preCompleteArrayParticipants[self::preCompleteType] ?? '']) : '') : '');
                                $subContent = ($isIncomplete ? $addresseeHeading.($preCompleteArray[self::descriptionNode] ?? '') : '').($isIncompleteParticipants ? $tempVal.($preCompleteArrayParticipants[self::descriptionNode] ?? '') : '');
                            }
                            $this->addBoxContent(self::preComplete, $content, $subContent, fragment: !($isIncomplete && $isIncompleteParticipants) ? self::preComplete : self::dummyString);

                            // pre abort (information/II)
                            $content = $isAnyPreAbort ? self::dummyBox : self::noBox;
                            $subContent = '';
                            $isPreAbort = $completePost==='0';
                            $isPreAbortParticipants = $completePostParticipants==='0';
                            if ($isPreAbort || $isPreAbortParticipants) {
                                $tempVal = $isPreAbort ? $participantsHeading : $participantsHeadingShort;
                                $preAbortArray = $preCompleteArray[self::preAbort] ?? [];
                                $preAbortArrayParticipants = $preCompleteArrayParticipants[self::preAbort] ?? [];
                                $preAbort = $preAbortArray[self::chosen] ?? '';
                                $preAbortParticipants = $preAbortArrayParticipants[self::chosen] ?? '';
                                $tempPrefix = $projectdetailsPagesPrefix.self::informationNode.'.'.self::preAbort.'.type.';
                                $content = ($isPreAbort ? $addresseeHeading.($preAbort!=='' ? $this->translateString($tempPrefix.$preAbort) : '') : '').($isPreAbortParticipants ? $tempVal.($preAbortParticipants!=='' ? $this->translateString($tempPrefix.$preAbortParticipants) : '') : '');
                                $subContent = (in_array($preAbort, self::preAbortDescriptions) ? $addresseeHeading.$preAbortArray[self::descriptionNode] : '').(in_array($preAbortParticipants, self::preAbortDescriptions) ? $tempVal.$preAbortArrayParticipants[self::descriptionNode] : '');
                            }
                            $this->addBoxContent(self::preAbort, $content, $subContent, fragment: !($isPreAbort && $isPreAbortParticipants) ? self::preAbort : self::dummyString);

                            // post information (information/II)
                            $content = $isAnyNotPre ? self::dummyBox : self::noBox;
                            $isNotPre = $pre==='1';
                            $isNotPreParticipants = $preParticipants==='1';
                            self::$linkedPage = !$isNotPre && $isNotPreParticipants ? self::informationIINode : self::informationNode;
                            if ($isNotPre || $isNotPreParticipants) {
                                $postArray = $informationArray[self::post] ?? [];
                                $postArrayParticipants = $informationIIArray[self::post] ?? [];
                                $post = $postArray[self::chosen] ?? '';
                                $content = ($isNotPre ? $addresseeHeading.$this->translateStringPDF($preTranslation, [self::chosen => $post, self::descriptionNode => $postArray[self::descriptionNode] ?? '']) : '').($isNotPreParticipants ? ($isNotPre ? $participantsHeading : $participantsHeadingShort).$this->translateStringPDF($preTranslation, [self::chosen => $postArrayParticipants[self::chosen] ?? '', self::descriptionNode => $postArrayParticipants[self::descriptionNode] ?? '']) : '').($isNotPre && $post==='0' ? $documentTranslation : '');
                            }
                            $this->addBoxContent(self::post, $content, fragment: !($isNotPre && $isNotPreParticipants) ? $preNo : self::dummyString);

                            // attendance (information)
                            self::$linkedPage = self::informationNode;
                            $this->addBoxContent(self::attendanceNode, $isAnyWards ? (array_key_exists(self::attendanceNode, $informationArray) ? $this->translateBinaryAnswer($informationArray[self::attendanceNode]) : self::dummyBox) : self::noBox);

                            // voluntary and consent (consent)
                            self::$linkedPage = self::consentNode;
                            $addresseeString = $this->getAddresseeString($addressee);
                            $participantsString = $this->getAddresseeString($addressee, false, true, $addressee===self::addresseeParticipants);
                            $addresseeStringParams = [self::addressee => $addresseeString, self::participant => $participantsString];
                            $participantsStringParams = [self::addressee => $this->translateString($projecdetailsPrefix.'addressee.participants.'.$addressee), self::participant => $this->getAddresseeString($addressee, false, true, true)];
                            $consentArray = $measureTimePoint[self::consentNode];
                            $consentPrefix = $projecdetailsPrefix.self::consentNode.'.';
                            $descriptionPrefix = $consentPrefix.'voluntaryDescription.';
                            $voluntaryArray = $consentArray[self::voluntaryNode] ?? [];
                            $isVoluntaryYes = array_key_exists(self::voluntaryEnsureNode, $voluntaryArray);
                            foreach ([self::voluntaryNode, self::consentNode] as $type) {
                                $isVoluntary = $type===self::voluntaryNode;
                                $tempArray = $consentArray[$type] ?? [];
                                $tempVal = $consentPrefix.$type;
                                $chosen = $tempArray[self::chosen] ??'';
                                $chosen2 = $tempArray[self::chosen2Node] ?? '';
                                $subContent = '';
                                if ($isVoluntaryYes && ($this->isReviewFull && $isVoluntary || !$this->isReviewFull && !$isVoluntary)) { // choices for 'yes' if dependent
                                    $tempPrefix = $descriptionPrefix.self::voluntaryEnsureNode.'.';
                                    $subContent = ($this->translateStringPDF($tempPrefix.'title',$reviewFullParam).$this->getSelectedCheckboxes($voluntaryArray[self::voluntaryEnsureNode],$tempPrefix.'types.',implodeLines: true));
                                }
                                if (array_key_exists(self::descriptionNode, $tempArray)) { // description for 'no'
                                    $subContent .= "\n".($isVoluntary ? $this->translateStringPDF($descriptionPrefix.'no') : '').$tempArray[self::descriptionNode];
                                }
                                $this->addBoxContent($type, $addresseeHeading.($chosen!=='' ? $this->translateStringPDF($tempVal, array_merge($addresseeStringParams, [self::chosen => $chosen, 'otherDescription' => $tempArray[self::consentOtherDescription] ?? '', self::addresseeType => self::addresseeParticipants])) : '').($isNotParticipants ? $participantsHeading.($chosen2!=='' ? $this->translateStringPDF($tempVal, array_merge($participantsStringParams, [self::chosen => $chosen2, 'otherDescription' => $tempArray[self::consentOtherDescription.'Participants'] ?? '', self::addresseeType => $addressee])) : '') : ''), trim($subContent), paragraph: $isVoluntary ? self::voluntaryNode : '');
                            }
                            // terminate without cons (consent)
                            $tempArray = $consentArray[self::terminateConsNode] ?? [];
                            $this->addBoxContent(self::terminateConsNode, $this->translateBinaryAnswer($tempArray[self::chosen] ?? ''), $tempArray[self::descriptionNode] ?? '');
                            // terminate by experimenter (consent)
                            $this->addBoxContent(self::terminateCriteriaNode, $consentArray[self::terminateCriteriaNode] ?? '', subHeading: $this->translateStringPDF($projecdetailsPrefix.self::terminateCriteriaNode));

                            // measures and interventions (measures)
                            self::$linkedPage = self::measuresNode;
                            foreach ([self::measuresNode, self::interventionsNode] as $type) {
                                $selections = $measuresArray[$type] ?? '';
                                $content = '';
                                if ($selections!=='') {
                                    $tempPrefix = $measuresPrefix.$type.'Types.';
                                    foreach ($selections as $selection => $description) {
                                        $content .= "\n- ".$this->translateStringPDF($tempPrefix.$selection).($description!=='' ? ': '.$description : '');
                                    }
                                }
                                $tempVal = $type===self::measuresNode;
                                $this->addBoxContent($type, $this->getSelectedCheckboxes($measuresArray[$type] ?? '',$measuresPrefix.$type.'Types.',implodeLines: true), $measuresArray[$type.self::descriptionCap] ?? '', paragraph: $tempVal ? self::measuresNode : '', fragment: $type.'Type', paragraphSub: $tempVal);
                            }
                            // other sources (measures)
                            $tempArray = $measuresArray[self::otherSourcesNode] ?? [];
                            $this->addBoxContent(self::otherSourcesNode, $this->translateBinaryAnswer($tempArray[self::chosen] ?? '', true).($tempArray[self::otherSourcesNode.self::descriptionCap] ?? ''), subHeading: $isAnyOtherSources ? $this->documentHint : '');
                            // presence (measures)
                            $tempArray = $measuresArray[self::presenceNode] ?? [];
                            $tempVal = $tempArray[self::chosen] ?? '';
                            $this->addBoxContent(self::presenceNode, $tempVal!=='' ? $this->translateString($projectdetailsPagesPrefix.self::measuresNode.'.'.self::presenceNode.'.types.'.$tempVal) : '', $tempArray[self::descriptionNode] ?? '');

                            // burdens and risks (burdens/risks)
                            self::$linkedPage = self::burdensRisksNode;
                            $burdensRisksArray = $measureTimePoint[self::burdensRisksNode];
                            $burdensRisksArray = $burdensRisksArray==='' ? [] : $burdensRisksArray;
                            foreach ([self::burdensNode, self::risksNode, self::burdensRisksContributorsNode] as $type) {
                                $isBurdens = $type===self::burdensNode;
                                $typeArray = $burdensRisksArray[$type] ?? [];
                                $typeKey = $type.'Type';
                                $description = $typeArray[self::descriptionNode] ?? '';
                                $tempPrefix = $burdensRisksPrefix.$type.'.';
                                $this->addBoxContent($type, $type!==self::burdensRisksContributorsNode ? $this->getSelectedCheckboxes($typeArray[$type.'Type'] ?? '', $tempPrefix) : $this->translateBinaryAnswer($typeArray[self::chosen] ?? ''), $isBurdens ? ($burdensRisksArray[self::burdensNoDescription] ?? $description.(($typeArray[self::burdensEveryday] ?? '')==='1' ? $this->translateStringPDF($tempPrefix.self::burdensEveryday) : '')) : $description, paragraph: $isBurdens ? 'burdens' : '', fragment: $typeKey, boxSub: $isBurdens && !$this->isReviewFull && !$isAnyOriginExisting);
                                $tempArray = $typeArray[self::burdensRisksCompensationNode] ?? [];
                                $compensation = $type.'Compensation';
                                $isCompensation = $isAnyBurdensRisks[$type] && (!$isBurdens || $isAnyBurdensEveryday);
                                $this->addBoxContent($compensation, $isCompensation ? ($this->getBurdensOrRisks($burdensRisksArray, $type)[0] ? $this->translateBinaryAnswer($tempArray[self::chosen] ?? '', true, true).($tempArray[self::descriptionNode] ?? '') : self::dummyBox) : self::noBox, fragment: $isCompensation ? $this->addDiv($compensation) : $typeKey);
                            }
                            // finding (burdens/risks)
                            $tempArray = $burdensRisksArray[self::findingNode] ?? [];
                            $tempVal = $tempArray[self::chosen] ?? '';
                            $this->addBoxContent(self::findingNode, $this->translateBinaryAnswer($tempVal, addHyphenYes: true).($tempArray[self::descriptionNode] ?? ''), $tempVal==='0' ? $this->translateStringPDF($burdensRisksPrefix.self::findingNode, array_merge($addresseeParam, [self::chosen => $tempArray[self::informingNode]])) : '');
                            // feedback (burdens/risks)
                            $tempArray = $burdensRisksArray[self::feedbackNode] ?? [];
                            $this->addBoxContent(self::feedbackNode, $this->translateBinaryAnswer($tempArray[self::chosen] ?? ''), $tempArray[self::descriptionNode] ?? '');

                            // voluntariness threatened by amount or type of compensation (compensation)
                            self::$linkedPage = self::compensationNode;
                            $pageArray = $measureTimePoint[self::compensationNode] ?? '';
                            $pageArray = $pageArray==='' ? [] : $pageArray;
                            $tempPrefix = $projecdetailsPrefix.self::compensationVoluntaryNode.'.';
                            $this->addBoxContent(self::compensationVoluntaryNode, array_key_exists(self::terminateNode, $pageArray) ? $this->getSelectedCheckboxes($pageArray[self::compensationVoluntaryNode],$tempPrefix.'types.',implodeLines: true) : self::dummyBox, $pageArray[self::compensationTextNode] ?? '');

                            // data privacy
                            self::$linkedPage = self::privacyNode;
                            // processing (data privacy)
                            $this->addBoxContent(self::processingNode, $measureTimePoint[self::privacyNode][self::processingNode] ?? '', paragraph: self::processingNode, fragment: $this->addDiv(self::processingNode));

                            // data reuse (data reuse)
                            self::$linkedPage = self::dataReuseNode;
                            $pageArray = $measureTimePoint[self::dataReuseNode] ?? [];
                            $dataReuse = $pageArray[self::dataReuseNode] ?? '';
                            $dataReuseHowArray = $pageArray[self::dataReuseHowNode] ?? [];
                            $dataReuseHow = $dataReuseHowArray[self::chosen] ?? '';
                            $dataReuseSelf = $pageArray[self::dataReuseSelfNode] ?? '';
                            $isDataReuse = in_array($dataReuse, self::dataReuseTypesYes);
                            $isDataReuseSelf = $dataReuseSelf==='0';
                            $tempPrefix = $projecdetailsPrefix.self::dataReuseNode.'.';
                            $content = '';
                            if (array_diff([$dataReuse, $dataReuseHow, $dataReuseSelf], [''])!==[]) { // any reuse question is answered
                                if ($hasDocs && in_array($this->getInformationString($informationArray), [self::pre, self::post])) { // refer to participation document if any reuse
                                    $content = $this->translateStringPDF((in_array(true, [$isDataReuse, $dataReuseHowArray!==[], $isDataReuseSelf]) ? $tempPrefix.'yes' : $this->translateBinaryAnswer('1')));
                                } else {
                                    $content = $this->translateStringPDF($tempPrefix.($isDataReuse ? self::dataReuseNode : ($isDataReuseSelf ? self::dataReuseSelfNode : 'no')), ['selection' => $dataReuse, 'reuseType' => !$isDataReuseSelf ? (array_key_exists(self::dataReuseHowNode, $pageArray) ? $dataReuseHow : 'other') : 'own', self::descriptionNode => $dataReuseHowArray[self::descriptionNode] ?? '']);
                                }
                            }
                            $this->addBoxContent(self::dataReuseNode, $content, fragment: ' ', boxSub: !$isAnyOriginExisting);
                        } // any origin new

                        if ($isAnyOriginExisting) {
                            self::$linkedPage = self::dataSourceNode;
                            // origin sources (data source)
                            $pageArray = $measureTimePoint[self::dataSourceNode];
                            $originArray = $pageArray[self::originNode];
                            $this->hasBoxes = $originArray[self::chosen]===self::originExisting;
                            $translationPrefix = $projecdetailsPrefix.self::dataSourceNode.'.';
                            $this->addBoxContent(self::originSourcesNode,$this->getSelectedCheckboxes($originArray[self::originSourcesNode] ?? '',$translationPrefix.self::originSourcesNode.'.',implodeLines: true),paragraph: self::originNode, paragraphTop: true);
                            // data source votes (data source)
                            $votesArray = $pageArray[self::dataSourceVotesNode] ?? [];
                            $chosen = $votesArray[self::chosen] ?? '';
                            $content = $isAnyDataSourceVotes ? ($votesArray!==[] ? $this->translateBinaryAnswer($chosen, true) : self::dummyBox) : self::noBox;
                            $subContent = '';
                            $dataSourcePrefixTool = $projectdetailsPagesPrefix.self::dataSourceNode.'.';
                            if ($chosen==='0') { // answer is yes
                                $tempArray = $votesArray[self::dataSourceCommitteeNode];
                                $tempVal = $tempArray[self::chosen];
                                if ($tempVal!=='') {
                                    // committee and case number or name of other committee
                                    $committeePrefix = $dataSourcePrefixTool.self::dataSourceVotesNode.'.'.self::dataSourceCommitteeNode.'.';
                                    $content .= $this->translateString($committeePrefix.'types.'.$tempVal, $committeeParam).($tempVal===self::dataSourceCommitteeSame ? '. '.$this->translateString($committeePrefix.'textHint.'.self::dataSourceCommitteeSame) : '').': '.$tempArray[self::descriptionNode];
                                    // result of other vote
                                    $tempArray = $votesArray[self::dataSourceResultNode];
                                    $tempVal = $tempArray[self::chosen];
                                    $isResult = $tempVal!=='';
                                    $subContent = $this->translateStringPDF($translationPrefix.self::dataSourceResultNode).($isResult ? $this->translateString($committeePrefix.self::dataSourceResultNode.'.types.'.$tempVal) : '');
                                    if ($isResult) { // further explanation
                                        $subContent .= "\n".($tempVal===self::dataSourceResultPositive
                                                ? $this->translateString($committeePrefix.self::committeeResultPositiveNode.'.title')."\n".$this->getSelectedCheckboxes($tempArray[self::committeeResultPositiveNode], $translationPrefix.self::committeeResultPositiveNode.'.', implodeLines: true)
                                                : $this->translateString($committeePrefix.self::descriptionNode.'.'.$tempVal)."\n".$tempArray[self::descriptionNode]);
                                    }
                                }
                            } elseif ($chosen==='1') { // answer is no
                                $tempArray = $votesArray[self::voteContributorsNode];
                                $tempVal = $tempArray[self::descriptionNode] ?? ''; // description may only exist if no review after start of data collection is allowed
                                $tempPrefix = $translationPrefix.self::voteContributorsNode.'.';
                                $subContent = $this->translateStringPDF($tempPrefix.'title').$this->translateBinaryAnswer($tempArray[self::chosen]).($tempVal!=='' ? $this->translateStringPDF($tempPrefix.self::descriptionNode).$tempVal : '');
                            }
                            $this->addBoxContent(self::dataSourceVotesNode, $content, $subContent);

                            // data set (data source)
                            $this->addBoxContent(self::dataSetNode, $isAnyDataSet ? ($pageArray[self::dataSetNode] ?? self::dummyBox) : self::noBox, fragment: self::dataSetNode);

                            // data source procedure (data source)
                            $this->addBoxContent(self::dataSourceProcedureNode, $isAnyDataSourceFurther ? ($pageArray[self::dataSourceProcedureNode] ?? self::dummyBox) : self::noBox);

                            // restriction (data source)
                            $tempArray = $pageArray[self::restrictionNode] ?? [];
                            $hasFurther = $tempArray!==[]; // if not empty (i.e., if key exists), keys of following boxes exist, too
                            $tempVal = $tempArray[self::chosen] ?? '';
                            $this->addBoxContent(self::restrictionNode, $isAnyDataSourceFurther ? ($hasFurther ? ($tempVal!=='' ? $this->translateString($dataSourcePrefixTool.self::restrictionNode.'.types.'.$tempVal) : '') : self::dummyBox) : self::noBox, $tempArray[self::descriptionNode] ?? '');

                            // data source access (data source)
                            $this->addBoxContent(self::dataSourceAccessNode, $isAnyDataSourceFurther ? ($hasFurther ? $this->getSelectedCheckboxes($pageArray[self::dataSourceAccessNode], $translationPrefix.self::dataSourceAccessNode.'.', implodeLines: true) : self::dummyBox) : self::noBox);

                            // legitimization (data source)
                            $this->addBoxContent(self::legitimizationNode, $isAnyDataSourceFurther ? ($hasFurther ? $this->getSelectedCheckboxes($pageArray[self::legitimizationNode], $translationPrefix.self::legitimizationNode.'.', implodeLines: true) : self::dummyBox) : self::noBox);

                            // data source identification (data source)
                            $tempVal = $pageArray[self::dataSourceIdentificationNode] ?? '';
                            $this->addBoxContent(self::dataSourceIdentificationNode, $isAnyDataSourceFurther ? ($hasFurther ? ($tempVal!=='' ? $this->translateStringPDF($translationPrefix.self::dataSourceIdentificationNode.'.'.$tempVal) : '') : self::dummyBox) : self::noBox);

                            // publication (data source)
                            $tempVal = $pageArray[self::publicationNode] ?? '';
                            $this->addBoxContent(self::publicationNode, $isAnyDataSourceFurther ? ($hasFurther ? ($tempVal!=='' ? $this->translateString($dataSourcePrefixTool.self::publicationNode.'.types.'.$tempVal) : '') : self::dummyBox) : self::noBox);

                            // data source burdens risks and burdens risks contributors
                            foreach (self::dataSourceBurdensRisksNodes as $type) {
                                $tempArray = $pageArray[$type] ?? [];
                                $this->addBoxContent($type, $isAnyDataSourceFurther ? ($hasFurther ? $this->translateBinaryAnswer($tempArray[self::chosen]) : self::dummyBox) : self::noBox, $tempArray[self::descriptionNode] ?? '');
                            }
                        } // any origin existing
                    } // for measure time point
                    $groupIndices[$groupID][1] = false;
                    $groupIndices[$groupID][2] = $measureIndices;
                } // for group
                // if the current study has only one group, but multiple measure time points, the measure time points need extra indentation
                $names[$studyID][1] = !$multipleGroups; // extra indentation if only one group in current study. If no multiple groups at all, extra indentation will also be added
                $names[$studyID][2] = $groupIndices;
            } // for study
            if ($sampleSizesMulti!==[]) { // add 'each' to sample sizes that occur more than once
                $tempArray = $this->boxContent[self::sampleSizeNode][self::main];
                $sampleSizeArray = [];
                $sampleSizeString = $this->translateStringPDF($projecdetailsPrefix.'sampleSizeMultiple');
                foreach (array_unique($sampleSizes) as $sampleSize) {
                    $sampleSizeArray[in_array($sampleSize,$sampleSizesMulti) ? str_replace('X',$sampleSize,$sampleSizeString) : $sampleSize] = $tempArray[$sampleSize];
                }
                $this->boxContent[self::sampleSizeNode][self::main] = $sampleSizeArray;
            }
            if (!$isMultiple) { // simplify $names
                $tempArray = $names[0];
                $names = $tempArray[0].' - '.$tempArray[2][0][0].' - '.$tempArray[2][0][2][0][0];
                self::$routeIDs = $this->createRouteIDs([self::studyNode => 1, self::groupNode => 1, self::measureTimePointNode => 1]);
            }
            $isPreNotYet = $isAnyPre || $isNotAnyPreYet;
            $parameters = array_merge($childrenWardsParams, $informationHintParam, $reviewFullParam, [
                self::burdensNode => $this->getStringFromBool($isAnyBurdensRisks[self::burdensNode]),
                'noBurdens' => $this->getStringFromBool($isAnyBurdensNo),
                self::risksNode => $this->getStringFromBool($isAnyBurdensRisks[self::risksNode]),
                'informationTypes' => $isPreNotYet && !$isAnyNotPre ? 'onlyPre' : (!$isPreNotYet ? 'onlyNotPre' : 'prePost'),
                'anyPreNo' => $this->getStringFromBool($isAnyNotPre),
                'isPreCompensation' => $this->getStringFromBool($isAnyCompensation[0]),
                'anyPreNoCompensation' => $this->getStringFromBool($isAnyCompensation[1]),
                'anyAbortOther' => $this->getStringFromBool($isAnyPreAbortOtherNo[0]),
                'anyAbortNo' => $this->getStringFromBool($isAnyPreAbortOtherNo[1]),
                'isVoluntaryNo' => $this->getStringFromBool($anyVoluntary),
                'isAssent' => $this->getStringFromBool($isAnyWards),
                'anyConsentNo' => $this->getStringFromBool($anyConsent[0]),
                'anyAssentNo' => $this->getStringFromBool($anyConsent[1]),
                'isCompensationVoluntary' => $this->getStringFromBool($isAnyCompensationVoluntary),
                'isOrigin' => $this->getStringFromBool(!$isAnyOriginMissing),
                'anyOriginExisting' => $this->getStringFromBool($isAnyOriginExisting)]); // all parameters for all translations of headings and subContent headings
            // box with names of levels
            self::$linkedPage = self::landing;
            self::$isPageLink = true;
            $levelHeading = $this->addHeadingLink($projectdetailsHeadingPrefix.'overview'); // needed in template to identify the box
            $this->addBox($projecdetailsPrefix.'overview', $isMultiple, parameters: $parameters, boxSub: !$isAnyOriginNew, boxNumber: !$isAnyOriginNew ? ($this->isReviewFull ? 46 : 20) : 0);
            self::$isPageLink = !$isMultiple;
            $informationContent = [self::burdensNode,self::risksNode,self::processingNode]; // keys for which content is added once if any information
            $additionalContent = array_merge($supplementTypes,$informationContent,[self::compensationVoluntaryNode,self::examinedPeopleNode]); // keys for which content is added once
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
                        } else { // no content for this box
                            $curContent[self::main] = $this->noBoxTrans;
                        }
                    } else { // paragraph, inputPage, or subHeading
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
                    if (($isAnySupplement[$title] ?? false) || $isAnyInformation && in_array($title,$informationContent) && (!in_array($title,[self::burdensNode,self::risksNode]) || $title===self::burdensNode && $isAnyBurdensEveryday || $title===self::risksNode && $isAnyBurdensRisks[self::risksNode]) || $title===self::examinedPeopleNode && ($this->isReviewFull || $hasCriteria) || $isCompensation || $title===self::processingNode) {
                        $tempVal = $this->translateStringPDF($projecdetailsPrefix.'pdf.'.$title,$informationHintParam);
                        $isMain = $isCompensation || $sub==='';
                        $content = $isMain ? $main : $sub;
                        $content = ($isCompensation ? $tempVal.(in_array(true,$isAnyCompensation) ? "\n\n".$this->translateStringPDF($projecdetailsPrefix.self::compensationVoluntaryNode.'.title',$parameters)."\n".$content : '') : '').(!$isCompensation ? $content : '').(!$isCompensation ? "\n\n".$tempVal : '');
                        if ($isMain) {
                            $main = $content;
                        } else {
                            $sub = $content;
                        }
                    }
                }
                $this->addBox($projecdetailsPrefix.$title, $main, $sub, $curContent[self::subHeading] ?? '', $parameters, $curContent[self::paragraph] ?? '',$curContent[self::paragraphTop] ?? false, !self::$isPageLink ? $inputPage : '', $fragment,paragraphHint: $curContent[self::paragraphSub] ?? false, boxSub: $curContent[self::boxSub] ?? false);
                self::$isPageLink = $tempPageLink;
            } // foreach $boxContent
            $renderParameters = array_merge($committeeParam,
                ['heading' => $this->translateStringPDF('heading', $committeeParam),
                    'singleDocsHint' => $this->getSingleDocsHint($request,'application'),
                    'applicantInfos' => $applicantSupervisor,
                    'applicantWidth' => $applicantWidth,
                    'contributorsInfos' => $contributorsInfos,
                    'contributorsTasks' => $nameTasks,
                    'contributorsHeading' => $contributorsHeading,
                    'tasks' => array_keys(self::tasksTypes),
                    'levelNames' => $names,
                    'levelHeading' => $levelHeading,
                    'boxContent' => $this->content,
                    'savePDF' => self::$savePDF,
                    'isNotParticipation' => true]);
            $html = $this->renderView('PDF/_application.html.twig', $renderParameters);
            $htmlWithVotes = $html;
            if (self::$savePDF && self::$isCompleteForm) {
                $this->generatePDF($session,$this->renderView('PDF/_application.html.twig', array_merge($renderParameters,['singleDocsHint' => $this->translateStringPDF('singleDocuments.application',['isSingleDocs' => 'false', 'isComplete' => 'true'])])),'applicationSingleDocs'); // add hint also in single docs of complete form
                if ($isOtherVote) {
                    $htmlWithVotes .= $this->renderView('PDF/_intermediateDocument.html.twig', array_merge($committeeParam,['savePDF' => self::$savePDF, self::content => $this->translateStringPDF($projecdetailsPrefix.'custom.'.self::voteNode)]));
                }
            }
            $session->set('pdfApplication', $html);
            $this->forward('App\Controller\PDF\ParticipationController::createPDF', ['routeIDs' => $routeIDs]);
            if (self::$savePDF) { // single documents or complete form
                $this->generatePDF($session, $html, 'application');
                self::$pdf->removeTemporaryFiles();
                if (!self::$isCompleteForm) {
                    return $this->getDownloadResponse($session, false);
                } else {
                    $this->generatePDF($session,$htmlWithVotes,'applicationCompleteForm');
                    self::$pdf->removeTemporaryFiles();
                    $this->forward('App\Controller\PDF\ParticipationController::createPDF', ['routeIDs' => $routeIDs, 'markInput' => true]);
                }
            }
            return new Response($html);
        } catch (\Throwable) {
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
     * @param bool $paragraphSub if true, a key 'paragraphSub' will be added
     * @param bool $paragraphTop if true, a key 'paragraphTop' will be added
     * @param bool $boxSub if true, a key 'boxSub' will be added
     * @param int $boxNumber if not zero, number for the box
     * @return void
     */
    private function addBoxContent(string $key, string $content, string $subContent = '', string $subHeading = '', string $paragraph = '', string $fragment = '', bool $paragraphSub = false, bool $paragraphTop = false, bool $boxSub = false, int $boxNumber = 0): void
    {
        $content = preg_replace('/ +/',' ',$content); // remove multiple whitespaces between words
        $this->boxContent[$key][self::main][$this->hasBoxes ? $content : self::dummyBox][$this->studyID][$this->groupID][] = $this->measureID;
        if ($this->hasBoxes) {
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
            }
            if ($paragraphSub) {
                $this->boxContent[$key][self::paragraphSub] = true;
            }
            if ($paragraphTop) {
                $this->boxContent[$key][self::paragraphTop] = true;
            }
            if ($boxSub) {
                $this->boxContent[$key][self::boxSub] = true;
            }
            if ($boxNumber>0) {
                $this->boxContent[$key][self::boxNumber] = $boxNumber;
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
     * @param bool $isParagraphTop if true, a main heading will be added before the box
     * @param string $inputPage if not empty, translation key for the page that will be displayed above the heading if hovered over the heading
     * @param string $fragment fragment for the heading link
     * @param string $inputPrefix if not empty, page prefix for the page that will be displayed above the heading
     * @param bool $paragraphHint if true, a hint will be placed below the paragraph heading
     * @param bool $boxSub if true, text will be placed below the box
     * @param int $boxNumber if not zero, the number for the box
     */
    private function addBox(string $headingKey, string $boxContent, string $boxContentSub = '', string $subHeadingKey = '', array $parameters = [], string $paragraph='', bool $isParagraphTop = false, string $inputPage = '', string $fragment = '', string $inputPrefix = 'projectdetails', bool $paragraphHint = false, bool $boxSub = false, int $boxNumber = 0): void
    {
        if ($this->isReviewFull || in_array($headingKey,$this->boxesShort)) { // for short review processes, less boxes are created
            $translation = 'boxHeadings.'.$headingKey;
            $isParagraph = $paragraph!=='' && $this->isReviewFull;
            $paragraphTrans = 'paragraphHeadings.'.$paragraph;
            $this->content = array_merge($this->content,
                [array_merge(['heading' => $this->addHeadingLink($translation, $parameters, $fragment), 'content' => $boxContent],
                    $boxContentSub!=='' ? ['subContent' => "\n".$this->translateStringPDF($translation.'Sub', $parameters)."\n".$boxContentSub] : [],
                    $subHeadingKey!=='' ? [self::subHeading => $subHeadingKey===$this->documentHint ? $this->documentHint : $this->translateStringPDF('boxSubHeadings.'.$subHeadingKey, $parameters)] : [],
                    $isParagraph ? [self::paragraph => $this->translateStringPDF($paragraphTrans)] : [],
                    $paragraphHint ? [self::paragraphSub => $this->translateStringPDF($paragraphTrans.'Sub', $parameters)] : [],
                    $isParagraphTop ? [self::paragraphTop => $this->translateStringPDF($paragraphTrans.'Top', $parameters)] : [],
                    $boxSub ? [self::boxSub => $this->translateStringPDF($translation.'BoxSub', $parameters)] : [],
                    $boxNumber>0 ? [self::boxNumber => $boxNumber] : [],
                    $inputPage!=='' ? [self::inputPage => $this->getInputPageHint($inputPage, $inputPage===self::informationIINode || $inputPage===self::informationNode && $this->isAnyTwoAddressees && !str_contains($headingKey, self::attendanceNode) ? 2 : 1, $this->getStringFromBool($boxContent===$this->noBoxTrans), $inputPrefix)] : [])]);
        }
    }

    /** Creates the hint to be shown above the boxes.
     * @param string $inputPage translation key for the page that will be displayed above the heading if hovered over the heading
     * @param int $numPages number of pages that the hint contains
     * @param string $noBox string representation whether the box has no content
     * @param string $pagePrefix page prefix to be used for the input page
     * @return string hint to be shown above the boxes
     */
    private function getInputPageHint(string $inputPage, int $numPages, string $noBox, string $pagePrefix = 'projectdetails'): string
    {
        return $this->translateStringPDF('boxHeadings.projectdetails.hint',[self::inputPage => $this->translateString('pages.'.$pagePrefix.'.'.$inputPage), 'numPages' => $numPages, 'noBox' => $noBox]);
    }

    /** If \$array is not an empty string, a string is created where all elements in \$array are translated and eventually are concatenated with a comma. The values of the elements are passed as a 'description' key to the translation
     * @param array|string $array array whose keys are translation keys
     * @param string $translationPrefix prefix for the translation keys
     * @param bool $implode if true, the values are concatenated
     * @param bool $implodeLines if true and $implode is true, the values will be concatenated by line breaks, otherwise by comma
     * @return array|string all array keys translated
     */
    private function getSelectedCheckboxes(array|string $array, string $translationPrefix, bool $implode = true, bool $implodeLines = false): array|string
    {
        $returnArray = [];
        if ($array!=='') {
            foreach ($array as $selection => $value) {
                $returnArray[] = $this->translateStringPDF($translationPrefix.$selection,[self::descriptionNode => $value]);
            }
        }
        return $implode ? ($returnArray!==[] && $implodeLines ? '- ' : '').implode($implodeLines ? "\n- " : ', ',$returnArray) : $returnArray;
    }

    /** If there are either multiple elements of the passed level or the passed name is no an empty string, prefix the passed name by the level.
     * @param string $name name to be eventually prefixed
     * @param string $level name of the level
     * @param int $levelID id of the level
     * @param bool $isMultiple true if multiple elements of the level exist, false otherwise
     * @return array $name eventually prefixed by the level name
     */
    private function getLevelName(string $name, string $level, int $levelID, bool $isMultiple): array
    {
        $origin = '';
        if ($this->isMixed && $level===self::measureTimePointNode) {
            $tempVal = $this->measureTimePoint[self::dataSourceNode][self::originNode][self::chosen];
            $origin = $tempVal!=='' ? ': '.($tempVal===self::originNew ? $this->originNewTrans : $this->originExistingTrans) : '';
        }
        if ($isMultiple) {
            return [$this->levelTrans[$level].($levelID+1).($name!=='' ? ' ('.$name.')' : '').$origin];
        } else {
            return [$this->translateStringPDF('projectdetails.overview.'.$level,['is'.ucfirst($level).'Name' => $this->getStringFromBool($name!==''), $level.'Name' => $name, self::originNode => $origin])];
        }
    }

    // further functions

    /** Translates the answer to a yes/no question and eventually adds a hyphen.
     * @param string $chosen selected answer
     * @param bool $addHyphenYes if true, the hyphen is added if the answer is ye
     * @param bool $addHyphenNo if true, the hyphen is added if the answer is no
     * @return string translated answer
     */
    private function translateBinaryAnswer(string $chosen, bool $addHyphenYes = false , bool $addHyphenNo = false): string
    {
        return $this->translateStringPDF('answer',[self::chosen => $chosen]).(($chosen==='0' && $addHyphenYes || $chosen==='1' && $addHyphenNo) ? ' - ' : '');
    }
}