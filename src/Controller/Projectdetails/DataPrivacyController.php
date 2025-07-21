<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\DataPrivacyType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DataPrivacyController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'dataPrivacy', name: 'app_dataPrivacy')]
    public function showDataPrivacy(Request $request): Response {
        $routeParams = $request->get('_route_params');
        $measure = $this->getMeasureTimePointNode($request,$routeParams);
        if ($measure===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session);
        $privacyNode = $this->getMeasureTimePointNode($appNode,$routeParams)->{self::privacyNode};
        $addresseeTypeParam = [self::addressee => $this->getAddresseeFromRequest($request)];
        $projectdetailsPrefix = 'projectdetails.pages.';
        $privacyPrefix = $projectdetailsPrefix.self::privacyNode.'.';
        // data research icons
        $dataResearchIcons = [];
        $tempPrefix = $privacyPrefix.self::dataResearchNode.'.hints.';
        foreach (['audio','photo','video','health'] as $type) {
            $dataResearchIcons[$type] = $this->translateString($tempPrefix.$type);
        }
        // icon hints for marking (sub-)questions
        $translationPrefix = $privacyPrefix.self::markingNode.'.';
        // marking question
        $tempPrefix = $translationPrefix.'hints.';
        $tempArray = [];
        foreach ([self::markingExternal,self::markingInternal,self::markingNo] as $type) { // external
            $tempArray[$type] = $this->translateString($tempPrefix.$type,$addresseeTypeParam);
        }
        $iconArray = [self::markingNode => $tempArray, self::markingExternal => ['generation' => $this->translateString($translationPrefix.self::markingExternal.'.hints.generation')]]; // one element for marking question, one sub-array for external sub-question, one sub-array for internal
        // internal
        $tempArray = [];
        $translationPrefix .= self::markingInternal.'.';
        $tempPrefix = $translationPrefix.'hints.';
        foreach (['own', 'contributors'] as $type) { // internal
            $tempArray[$type] = $this->translateString($tempPrefix.$type,$addresseeTypeParam);
        }
        $iconArray[self::markingInternal] = $tempArray;
        // internal sub-questions
        foreach (['pattern' => 'generation', 'contributors' => 'marking'] as $key => $value) {
            $iconArray[$key] = [$value => $this->translateString($translationPrefix.$key.'.hints.'.$value)];
        }
        // data research hints
        $dataResearchHints = [];
        $tempPrefix = $privacyPrefix.self::dataResearchNode.'.headingHint.';
        foreach (['code','personalCode','personalMaybeCode','personal',self::dataPersonalMaybe] as $type) {
            $dataResearchHints[$type] = $this->translateString($tempPrefix.$type);
        }
        // anonymization
        $iconIDsAnonymization = ['convert','delete','alienate','preprocess',self::anonymizationOther];
        [$iconTextsAnonymization,$iconColorsAnonymization] = [[],[]];
        $translationPrefix = $privacyPrefix.self::anonymizationNode.'.hints.';
        foreach ($iconIDsAnonymization as $type) {
            $iconTextsAnonymization[$type] = $translationPrefix.$type;
            $iconColorsAnonymization[$type] = 'black';
        }
        // purpose research and further
        $iconIDs = array_slice(self::purposeResearchTypes,1);
        $iconIDsFurther = $this->prefixArray(array_diff($iconIDs,[self::purposeRelatable]),self::purposeFurtherNode);
        [$iconTexts,$iconTextsFurther,$iconColors,$iconColorsFurther] = [[],[],[],[]];
        $purposeResearchPrefix = $privacyPrefix.self::purposeResearchNode.'.';
        $translationPrefix = $purposeResearchPrefix.'icons.';
        foreach ($iconIDs as $type) {
            $iconTexts[$type] = $translationPrefix.$type;
            $iconColors[$type] = 'black';
            $tempVal = self::purposeFurtherNode.$type;
            if (in_array($tempVal,$iconIDsFurther)) {
                $iconTextsFurther[$tempVal] = $translationPrefix.$type.($type===self::compensationNode ? 'Further' : '');
                $iconColorsFurther[$tempVal] = 'black';
            }
        }
        $tempVal = self::purposeFurtherNode.'contactResult'; // contact result only exists in purpose further
        $iconIDsFurther[] = $tempVal;
        $iconTextsFurther[$tempVal] = $translationPrefix.'contactResult';
        $iconColorsFurther[$tempVal] = 'black';
        // relatable
        $iconIDsRelatable = array_slice(self::relatableTypes,1);
        [$iconTextsRelatable,$iconColorsRelatable] = [[],[]];
        $translationPrefix = $privacyPrefix.self::relatableNode.'.icons.';
        foreach ($iconIDsRelatable as $type) {
            $iconTextsRelatable[$type] = $translationPrefix.$type;
            $iconColorsRelatable[$type] = 'black';
        }
        // purpose sub-questions
        [$purposeDataTrans,$purposeDataTransGen,$purposeDataNames,$middleNames,$accessNames,$iconArrayAccess,$orderProcessingKnownNames] = [[],[],[],[],[],[],[]];
        $translationPrefix = $privacyPrefix.self::accessNode.'.hints.';
        $accessIcons = [];
        foreach (self::accessTypes as $type) {
            $accessIcons[] = $translationPrefix.$type;
        }
        $typesShortPrefix = $purposeResearchPrefix.'typesShort.';
        $typesShortGenPrefix = $purposeResearchPrefix.'typesShortGen.';
        $iconColorsArray = array_fill(0,count(self::accessTypes),'black');
        foreach (array_merge([self::dataPersonalNode],self::allPurposeTypes) as $type) {
            if ($type!==self::dataPersonalNode) {
                $purposeDataTransGen[$type] = $this->translateString($typesShortGenPrefix.$type); // translated purposes short for headings
                $purposeDataNames[$type] = $this->prefixArray(self::purposeDataTypes,$type); // widget names
                $middleNames[$type] = $this->prefixArray(self::markingRemoveMiddleTypes,$type); // widget names
            }
            $purposeDataTrans[$type] = $this->translateString($typesShortPrefix.$type); // translated purposes short for headings
            $tempArray = $this->prefixArray(self::accessTypes,$type);
            $accessNames[$type] = $tempArray; // widget names
            $iconArrayAccess[$type] = [$tempArray,array_combine($tempArray,$accessIcons), array_combine($tempArray,$iconColorsArray)];
            foreach (self::accessOrderProcessing as $accessType) {
                $orderProcessingKnownNames[$type][$accessType] = $type.$accessType.self::orderProcessingKnownNode;
            }
        }
        // personal keep icons
        $iconArrayPersonalKeep = [];
        $tempPrefix = $privacyPrefix.self::personalKeepNode.'.hints.';
        foreach (['documentation',self::personalKeepTeaching,self::personalKeepDemonstration] as $type) {
            $iconArrayPersonalKeep[$type] = $this->translateString($tempPrefix.$type);
        }
        // create string indicating the personal data may/must be collected
        $measureTimePoint = $this->xmlToArray($this->getMeasureTimePointNode($request,$routeParams));
        $maybeString = ''; // personal data may be collected
        $sureString = ''; // personal data must be collected
        $translationPrefix = $privacyPrefix.'personal.types.';
        $routeParam = ['routeIDs' => $this->createRouteIDs([self::studyNode => $routeParams[self::studyID], self::groupNode => $routeParams[self::groupID], self::measureTimePointNode => $routeParams[self::measureID]])];
        // groups
        $pageArray = $measureTimePoint[self::groupsNode];
        if ($pageArray[self::closedNode][self::chosen]==='0') { // closed group
            $maybeString .= $this->translateString($translationPrefix.self::closedNode,$routeParam)."\n";
        }
        $tempArray = $pageArray[self::recruitment][self::recruitmentTypesNode];
        if ($tempArray!=='') { // recruitment
            $tempPrefix = $translationPrefix.self::recruitment;
            foreach (['database','external'] as $type) {
                if (array_key_exists($type,$tempArray)) {
                    $maybeString .= $this->translateString($tempPrefix.ucfirst($type),$routeParam)."\n";
                }
            }
        }
        // measures
        $pageArray = $measureTimePoint[self::measuresNode];
        $tempArray = $pageArray[self::measuresNode][self::measuresTypesNode];
        if ($tempArray!=='' && array_key_exists(self::measuresVideo,$tempArray)) { // video
            $sureString .= $this->translateString($translationPrefix.self::measuresVideo,$routeParam)."\n";
        }
        if (in_array(($pageArray[self::loanNode][self::loanReceipt][self::chosen] ?? ''),[self::template,self::templateText])) { // loan
            $maybeString .= $this->translateString($translationPrefix.self::loanNode,$routeParam)."\n";
        }
        if ($pageArray[self::locationNode][self::chosen]===self::locationOnline) { // location online
            $maybeString .= $this->translateString($translationPrefix.self::locationOnline,$routeParam)."\n";
        }
        // burdens/risks
        $pageArray = $measureTimePoint[self::burdensRisksNode];
        foreach ([self::findingNode,self::feedbackNode] as $type) { // finding and feedback
            if ($pageArray[$type][self::chosen]==='0') {
                $maybeString .= $this->translateString($translationPrefix.$type,$routeParam)."\n";
            }
        }
        // compensation
        $compensationArray = $measureTimePoint[self::compensationNode];
        $types = $compensationArray[self::compensationTypeNode];
        if ($types!=='' && !array_key_exists(self::compensationNo,$types)) {
            $translationPrefix .= self::compensationNode.'.';
            $otherTrans = $this->translateString($projectdetailsPrefix.self::compensationNode.'.'.self::awardingNode.'.laterEnd.'.self::laterEndOther,$routeParam); // other for later end and awarding type
            foreach (array_diff(self::compensationTypes,[self::compensationNo,self::compensationOther]) as $type) {
                if (array_key_exists($type,$types)) {
                    $awarding = $types[$type][self::awardingNode];
                    $chosen = $awarding[self::chosen];
                    $description = $awarding[self::descriptionNode] ?? ''; // used for lottery deliver and other compensation
                    if ($type===self::compensationMoney && $chosen==='transfer') {
                        $sureString .= $this->translateString($translationPrefix.'transfer',$routeParam)."\n";
                    }
                    elseif ($type===self::compensationHours && $chosen==='post') { // hours automatically
                        $maybeString .= $this->translateString($translationPrefix.'post',$routeParam)."\n";
                    }
                    elseif ($type===self::compensationLottery) {
                        $lotteryStart = $awarding[self::lotteryStart];
                        $resultDeliverTypes = ['eMail','mail','phone'];
                        if (in_array($lotteryStart,$resultDeliverTypes)) { // lottery result
                            $sureString .= $this->translateString($translationPrefix.'lotteryResult',array_merge($routeParam,['type' => $lotteryStart]))."\n";
                        }
                        if ($chosen===self::awardingDeliver && in_array($description,$resultDeliverTypes)) {
                            $sureString .= $this->translateString($translationPrefix.self::awardingDeliver,array_merge($routeParam,['type' => $description]))."\n";
                        }
                    }
                    $laterInformation = $awarding[self::laterTypesName] ?? '';
                    $typeParam = array_merge($routeParam,['type' => $type]);
                    if ($chosen==='immediately' && in_array($type,[self::compensationMoney,'voucher'])) { // immediately for money or voucher
                        $maybeString .= $this->translateString($translationPrefix.'immediately',$typeParam)."\n";
                    }
                    elseif ($chosen===self::awardingLater && $laterInformation!=='') { // later
                        $tempVal = $this->translateString($translationPrefix.self::awardingLater,array_merge($typeParam,[self::awardingLater => $laterInformation, self::descriptionNode => ($awarding[self::laterOtherDescription] ?? '') ?: $otherTrans]))."\n";
                        if ($laterInformation==='name') {
                            $sureString .= $tempVal;
                        }
                        else {
                            $maybeString .= $tempVal;
                        }
                    }
                    elseif ($chosen==='external') {// external for money or lottery
                        $maybeString .= $this->translateString($translationPrefix.'external',$typeParam)."\n";
                    }
                    elseif ($chosen==='other') {
                        $maybeString .= $this->translateString($translationPrefix.'other',array_merge($typeParam,[self::descriptionNode => $description ?: $otherTrans]))."\n";
                    }
                }
            }
        }
        $dataPrivacy = $this->createFormAndHandleRequest(DataPrivacyType::class,$this->xmlToArray($privacyNode),$request, [self::dummyParams => ['isCompensationCode' => $this->checkCompensationAwarding($compensationArray), 'isOnline' => $measureTimePoint[self::measuresNode][self::locationNode][self::chosen]===self::locationOnline]]);
        if ($dataPrivacy->isSubmitted()) {
            $privacyLoad = $this->getPrivacyReuse($this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams)->{self::privacyNode}));
            $privacy = $this->getPrivacyReuse($this->getDataAndConvert($dataPrivacy,$privacyNode));
            [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
            $anyDiff = false;
            foreach ($privacyLoad as $key => $value) {
                if (!array_key_exists($key,$privacy) || $privacy[$key]!==$value) {
                    $anyDiff = true;
                    break;
                }
            }
            if ($anyDiff) { // changes that affect data reuse were made
                $dataReuseNode = $measureNodeNew->{self::dataReuseNode};
                $this->removeAllChildNodes($dataReuseNode);
                $dataReuseNode->addChild(self::confirmIntroNode);
            }
            $isNotLeave = !$this->getLeavePage($dataPrivacy,$session,self::privacyNode);
            return $this->saveDocumentAndRedirect($request,$isNotLeave ? $appNode : $appNodeNew, $isNotLeave ? $appNodeNew : null);
        }
        return $this->render('Projectdetails/dataPrivacy.html.twig',
            $this->setRenderParameters($request,$dataPrivacy,
                ['privacyCheck' => ['maybe' => $maybeString!=='' ? explode("\n",trim($maybeString)) : [],'sure' => $sureString!=='' ? explode("\n",trim($sureString)) : []],
                 'internalKeys' => ['pattern','own','contributors'],
                 'iconArray' => $iconArray,
                 'listTypes' => self::listTypes,
                 'dataResearchHints' => $dataResearchHints,
                 'dataResearchTypes' => self::dataResearchTypes,
                 'dataSpecialTypes' => self::dataSpecialTypes,
                 'dataResearchTextFields' => self::dataResearchTextFields,
                 'dataSpecialTextFields' => self::dataSpecialTextFields,
                 'dataResearchIcons' => $dataResearchIcons,
                 'anonymizationTypes' => self::anonymizationTypes,
                 'iconArrayAnonymization' => [$iconIDsAnonymization,$iconTextsAnonymization,$iconColorsAnonymization],
                 'storageTypes' => self::storageTypes,
                 'personalKeepTypes' => self::personalKeepTypes,
                 'iconArrayPersonalKeep' => $iconArrayPersonalKeep,
                 'purposeResearchTypes' => array_combine(self::purposeResearchTypes,self::purposeResearchTypes),
                 'purposeFurtherTypes' => array_combine(self::purposeFurtherTypes,$this->prefixArray(self::purposeFurtherTypes,self::purposeFurtherNode)),
                 'allPurposeTypes' => self::allPurposeTypes,
                 'iconArrayPurpose' => [$iconIDs,$iconTexts,$iconColors],
                 'iconArrayPurposeFurther' => [$iconIDsFurther,$iconTextsFurther,$iconColorsFurther],
                 'relatableTypes' => self::relatableTypes,
                 'iconArrayRelatable' => [$iconIDsRelatable,$iconTextsRelatable,$iconColorsRelatable],
                 'purposeDataTypes' => self::purposeDataTypes,
                 'purposeDataNames' => $purposeDataNames,
                 'purposeDataTrans' => $purposeDataTrans,
                 'purposeDataTransGen' => $purposeDataTransGen,
                 'middleTypes' => $middleNames,
                 'accessTypes' => $accessNames,
                 'iconArrayAccess' => $iconArrayAccess,
                 'accessOrderProcessingTypes' => self::accessOrderProcessing,
                 'orderProcessingKnownTypes' => $orderProcessingKnownNames,
                 'orderProcessingKnownTexts' => self::orderProcessingKnownTexts],'projectdetails.dataPrivacy',true));
    }
}