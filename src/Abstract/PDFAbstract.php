<?php

namespace App\Abstract;

use App\Traits\Projectdetails\ProjectdetailsTrait;
use Knp\Snappy\Pdf;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

class PDFAbstract extends ControllerAbstract
{
    use ProjectdetailsTrait;

    // session variables
    protected static Pdf $pdf;
    protected static string $linkedPage; // page where the heading is linked to
    protected static bool $isPageLink; // type of page where the heading is linked to, e.g., application data
    protected static string $routeIDs = '';

    public function __construct(TranslatorInterface $translator, Pdf $pdf) {
        parent::__construct($translator);
        self::$pdf = $pdf;
    }

    // functions

    /** Creates a string containing the information about the location.
     * @param array $measureTimePoint array containing the information about the current measure time point.
     * @param array $committeeParam array containing the committee
     * @param bool $markInput if true, the description gets surrounded with span-tags
     * @return string string with location or an empty string if no location is chosen
     */
    protected function getLocation(array $measureTimePoint, array $committeeParam, bool $markInput = false): string {
        $locationArray = $measureTimePoint[self::measuresNode][self::locationNode];
        $chosen = $locationArray[self::chosen];
        $translationPrefix = 'projectdetails.measures.location.';
        return $chosen!=='' ? $this->translateStringPDF($translationPrefix.'start',array_merge($committeeParam,[self::informationNode => $this->getInformationString($measureTimePoint[self::informationNode]), self::locationNode => $chosen])).$this->addMarkInput($locationArray[self::descriptionNode],$markInput).($chosen===self::locationOnline ? $this->translateStringPDF($translationPrefix.'end') : '') : '';
    }

    /** Creates a string for the inclusion and exclusion criteria.
     * @param array $measureTimePoint array containing the information about the current time point
     * @param string $addressee addressee
     * @param bool $addSubHeadings if true, the subheadings are added before the criteria
     * @param bool $markInput if true, custom text will be surrounded by a span-tag
     * @return array the inclusion criteria and eventually the exclusion criteria
     */
    protected function getCriteria(array $measureTimePoint, string $addressee, bool $addSubHeadings = true, bool $markInput = false): array {
        $criteriaArray = $measureTimePoint[self::groupsNode][self::criteriaNode];
        $tempPrefix = 'projectdetails.pages.groups.criteria.';
        $returnArray = [];
        foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
            $tempArray = $criteriaArray[$type][self::criteriaNode];
            if ($tempArray!=='') { // at least one criterion
                $isInclude = $type===self::criteriaIncludeNode;
                $criteria = $isInclude ? '• '.$tempArray['include0']."\n" : '';
                if ($isInclude) {
                    if (($measureTimePoint[self::burdensRisksNode][self::findingNode][self::informingNode] ?? '')===self::informingAlways) {
                        $criteria .= "• ".$this->translateStringPDF('participation.criteria.finding')."\n";
                    }
                    $tempArray = array_slice($tempArray, 1);
                }
                $returnArray[] = ($addSubHeadings ? $this->translateString($tempPrefix.$type.'.start',[self::addressee => $addressee])."\n" : '').$criteria.($tempArray!==[] ? "• ".$this->addMarkInput(implode("\n• ",$tempArray),$markInput) : '');
            }
        }
        return $returnArray;
    }

    /** Creates the compensation string.
     * @param array $compensationArray array containing the compensation nodes
     * @param array $addresseeParam array containing the addressee
     * @param string $information information
     * @param Session $session current session
     * @param bool $addEnd if true, an additional sentence for money and hours (if existent) as well as the sentences for awarding and a sentence about further compensation are added
     * @param bool $markInput if true, custom text will be surrounded by a span-tag
     * @return string compensation array
     */
    protected function getCompensation(array $compensationArray, array $addresseeParam, string $information, Session $session, bool $addEnd = false, bool $markInput = false): string {
        $compensationTypes = $compensationArray['type'];
        $returnString = '';
        $numCompensation = 0;
        $compensationPrefix = 'projectdetails.pages.compensation.';
        $compensationPrefixPDF = 'projectdetails.'.self::compensationNode.'.';
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
            foreach (array_keys($compensationTypes) as $index => $type) {
                $isCompensation = $type!==self::compensationNo;
                $description = $compensationTypes[$type];
                $description = $isCompensation ? $description : [self::descriptionNode => $description];
                $additional = $description[self::moneyHourAdditionalNode] ?? '';
                $isMoneyCompensation = $type===self::compensationMoney;
                $isHoursCompensation = $type===self::compensationHours;
                $isReal = $additional==='real';
                $isMoney = $isMoney || $isMoneyCompensation && $isReal;
                $isHours = $isHours || $isHoursCompensation && $isReal;
                $value = $description[self::descriptionNode];
                if ($isMoneyCompensation) {
                    $tempArray = $description[self::moneyFurther];
                    if (array_key_exists(self::descriptionNode,$tempArray)) {
                        $moneyFurther = $this->translateString($compensationPrefix.self::compensationMoney.'.textHint').$this->addMarkInput($tempArray[self::descriptionNode],$markInput);
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
                $returnString .= ($index===($numCompensation-1) ? $lastOr : ',').' '.$this->translateStringPDF($tempPrefix.'start',[self::descriptionNode => $value, self::moneyHourAdditionalNode => $description[self::moneyHourAdditionalNode] ?? '', 'hoursValue' => $hoursValue, 'amount' => $hoursValue!='' ? (int)($hoursValue) : 0]).(!$isMoneyCompensation ? ($isHoursCompensation ? ' ' : '').$this->addMarkInput($value,$markInput) : '').$this->translateStringPDF($tempPrefix.'end');
                // awarding
                if ($addEnd && $isCompensation) {
                    $tempPrefix = $compensationPrefix.self::awardingNode.'.'.$type.'.';
                    $awardingArray = $compensationTypes[$type][self::awardingNode];
                    if ($type===self::compensationLottery) { // announcement
                        $lotteryPrefix = $tempPrefix.'result.';
                        $lotteryStart = $awardingArray[self::lotteryStart];
                        $awardingString .= ' '.$this->translateString($lotteryPrefix.'start').$this->addMarkInput($awardingArray[self::lotteryStart.self::descriptionCap],$markInput).' '.(!in_array($lotteryStart,['',self::lotteryResultOther]) ? $this->translateString($lotteryPrefix.'types.'.$lotteryStart) : $this->addMarkInput($awardingArray[self::lotteryStartOtherDescription] ?? '',$markInput)).$this->translateString($lotteryPrefix.'end');
                    }
                    $chosen = $awardingArray[self::chosen];
                    $isLater = $chosen===self::awardingLater;
                    $awardingString .= ' '.($type!==self::compensationOther ? $this->translateString($tempPrefix.'title').(!in_array($chosen,['','other']) ? $this->translateString($tempPrefix.$chosen) : '') : $this->addMarkInput($chosen,$markInput));
                    $description = $awardingArray[self::descriptionNode] ?? '';
                    if ($isLater || $chosen==='external') {
                        $awardingString .= $namelyString;
                    }
                    if ($description!=='') { // a description or further choice is needed and was made
                        $awardingString .= $chosen===self::awardingDeliver ? $this->translateString($tempPrefix.'deliverTypes.'.$description) : $this->addMarkInput($description,$markInput);
                    }
                    if ($chosen===self::awardingLater) { // information for later
                        $laterChosen = $awardingArray[self::laterTypesName];
                        $awardingString .= ' '.$this->translateStringPDF($laterPrefix.'title').(array_key_exists(self::laterOtherDescription,$awardingArray) ? $this->addMarkInput($awardingArray[self::laterOtherDescription],$markInput) : ($laterChosen!=='' ? $this->translateStringPDF($laterPrefix.$laterChosen,$addresseeParam) : ''));
                    }
                }
            }
            $returnString = substr($returnString,$multipleCompensation ? 2 : 0).'.';
            if ($addEnd) {
                $returnString .= ' '.$this->translateStringPDF('participation.'.self::compensationNode.'.start',['isMoneyHours' => $this->getStringFromBool($isMoney || $isHours), 'isMoney' => $this->getStringFromBool($isMoney), 'isHours' => $this->getStringFromBool($isHours)]).$moneyFurther."\n".$awardingString;
            }
        }
        return $this->translateStringPDF($compensationPrefixPDF.'start',array_merge($addresseeParam,['number' => $numCompensation, self::informationNode => $information])).trim($returnString);
    }

    /** Creates the string for compensation if the experiment is terminated.
     * @param array $compensationArray array containing the compensation nodes
     * @param array $parameter array containing the parameters (addressee and terminate cons)
     * @param bool $markInput if true, custom text will be surrounded by a span-tag
     * @return string compensation for termination or an empty string if no compensation is given at all
     */
    protected function getCompensationTerminate(array $compensationArray, array $parameter, bool $markInput = false): string {
        $tempArray = $compensationArray[self::terminateNode] ?? [];
        return $tempArray!==[] ? $this->translateStringPDF('participation.'.self::compensationNode.'.'.self::terminateNode,array_merge($parameter,[self::compensationNode => $tempArray[self::chosen]])).$this->addMarkInput($tempArray[self::descriptionNode] ?? '',$markInput).'.' : '';
    }

    /** Creates the marking sentences.
     * @param array $privacyArray array containing the data privacy nodes
     * @param array $addresseeParam array containing the addressee params for translations
     * @param bool $addCodeCompensation if true, the sentences for code compensation are also added
     * @return array 0: marking sentences 1: code compensation sentences
     */
    protected function getMarkingSentences(array $privacyArray, array $addresseeParam, bool $addCodeCompensation = true): array {
        $markingSentences = '';
        $codeCompensationSentences = '';
        $markingSecondString = self::markingNode.self::markingSuffix;
        $marking = $privacyArray[self::markingNode] ?? '';
        $markingSecond = $privacyArray[$markingSecondString] ?? '';
        $codeCompensation = $addCodeCompensation ? ($privacyArray[self::codeCompensationNode] ?? '') : '';
        $translationPrefix = 'participation.'.self::privacyNode.'.';
        $dataPersonal = $privacyArray[self::dataPersonalNode] ?? '';
        $isPurposeCompensation = false;
        foreach ([self::purposeNode,self::purposeFurtherNode] as $type) {
            $tempArray = $privacyArray[$type] ?? '';
            $isPurposeCompensation = $isPurposeCompensation || $tempArray!=='' && array_key_exists(($type===self::purposeFurtherNode ? self::purposeFurtherNode : '').self::purposeCompensation,$tempArray);
        }
        $purposeCompensationParam = ['purposeCompensation' => $this->getStringFromBool($isPurposeCompensation)];
        // the following variables get true if any marking is of that type
        $isExternal = false;
        $isInternal = false;
        $codePersonal = ['isName' => false, 'isList' => false, 'isGeneration' => false, 'isNameList' => false];
        foreach (array_merge($marking!=='' ? [self::markingNode] : [], $markingSecond!=='' ? [$markingSecondString] : [], $codeCompensation!=='' ? [self::codeCompensationNode] : []) as $type) {
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
                $isExternal = $isExternal || $chosenWoPrefix===self::markingExternal;
                $isInternal = $isInternal || $isCurInternal;
                $codePersonal['isName'] = $chosenWoPrefix===self::markingName;
                $codePersonal['isList'] = $curCodePersonal===self::listNode;
                $codePersonal['isGeneration'] = $curCodePersonal===self::generation;
            }
            else {
                $codeCompensationSentences .= $curSentences;
            }
        } // foreach
        $markingSentences = trim($markingSentences); // if no marking is chosen yet, $curSentences may be only one space
        if ($markingSentences!=='' && $dataPersonal!=='') {
            $isDataPersonal = $dataPersonal==='personal';
            $isExternalInternal = $isExternal || $isInternal;
            $isCodePersonal = in_array(true,$codePersonal);
            $tempPrefix = $translationPrefix.'markingPersonal.';
            if ($isDataPersonal && ($isExternalInternal || $isCodePersonal)) {
                $tempVal = $isCodePersonal ? 'codePersonal' : 'anonymous';
            }
            elseif ($dataPersonal===self::dataPersonalMaybe) {
                $tempVal = $isCodePersonal ? 'codePersonal' : ($isExternalInternal ? 'anonymous' : self::markingNo);
            }
            else { // research data are anonymous
                $tempVal = $isCodePersonal ? 'codePersonal' : ($isExternal ? self::markingExternal : ($isInternal ? self::markingInternal : self::markingNo));
            }
            if ($tempVal!=='' && !($isDataPersonal && $tempVal===self::markingNo)) { // if no marking is chosen, tempVal equals markingNo
                $codePersonal['isNameList'] = $codePersonal['isName'] || $codePersonal['isList'];
                foreach ($codePersonal as $key => $value) {
                    $codePersonal[$key] = $this->getStringFromBool($value);
                }
                $markingSentences .= "\n".$this->translateStringPDF($tempPrefix.$dataPersonal.'.'.$tempVal,array_merge($addresseeParam,[self::codePersonal => $this->translateStringPDF($tempPrefix.self::codePersonal,$codePersonal)]));
            }
        }
        return [trim($markingSentences), trim($codeCompensationSentences)];
    }

    /** Creates the string for data reuse how.
     * @param array $measureTimePoint array containing the information about the current measure time point
     * @return array 0: string for data reuse how 1: whether any of the data reuse how questions was not answered with 'own', 2: 'dataReuseHow' if class 0-3, otherwise an empty string, 4: answer to data reuse how question
     */
    protected function getDataReuseHow(array $measureTimePoint): array {
        $returnString = '';
        $dataReuseArray = $measureTimePoint[self::dataReuseNode];
        $isNotOwn = true; // overwriting of this variable and the following two is ok because of available questions and answers in this case
        $dataReuse = '';
        $dataReuseHow = '';
        $tempPrefix = 'projectdetails.pages.'.self::dataReuseNode.'.'.self::dataReuseHowNode.'.';
        $personalParam = $this->getPrivacyReuse($measureTimePoint[self::privacyNode]);
        if (array_key_exists(self::dataReuseHowNode,$dataReuseArray)) {
            foreach (['',self::personalKeepReuse] as $suffix) {
                $curKey = self::dataReuseHowNode.$suffix;
                $isFirst = $suffix==='';
                if (array_key_exists($curKey,$dataReuseArray)) {
                    $tempArray = $dataReuseArray[$curKey];
                    $dataReuseHow = $tempArray[self::chosen];
                    $isNotOwn = $isNotOwn && $dataReuseHow!=='own';
                    $dataReuse = !in_array($dataReuseHow,['','own']) ? self::dataReuseHowNode : '';
                    $returnString .= ' '.$this->translateString($tempPrefix.'start',array_merge($isFirst ? $personalParam : ['personal' => 'keep'],['isSecond' => $this->getStringFromBool(!$isFirst)])).($dataReuseHow!=='' ? $this->translateString($tempPrefix.'types.'.$dataReuseHow) : '');
                    $description = $tempArray[self::descriptionNode] ?? '';
                    if ($description!=='') {
                        $returnString .= $this->mergeContent([$this->translateString($tempPrefix.'descriptionStart'),$description,'.']);
                    }
                }
            }
        }
        return [$returnString,$isNotOwn,$dataReuse,$dataReuseHow];
    }

    /** Translates \$string. If the pdf should not be saved on disk (i.e., if preview), \$string is then converted to a link.
     * @param string $string string to be translated and eventually converted
     * @param array $parameters if $string is a translation key, parameters for the translation
     * @param string $fragment fragment to be added to the link.
     * @return string converted string
     */
    protected function addHeadingLink(string $string, array $parameters = [], string $fragment = ''): string {
        $string = $this->translateStringPDF($string,$parameters);
        if (!self::$savePDF && self::$isPageLink && $fragment!==self::dummyString) {
            $string = $this->convertStringToLink($string,self::$linkedPage,self::$routeIDs,$fragment);
        }
        return $string;
    }

    // methods

    /** Creates a pdf in the temporary folder with the session ID added to the filename.
     * @param Session $session current session
     * @param string $html html string to be converted to pdf
     * @param string $name name of the pdf file
     * @return void
     */
    protected function generatePDF(Session $session, string $html, string $name): void {
        self::$pdf->generateFromHtml($html,self::tempFolder.'/'.$name.$session->getId().'.pdf',overwrite: true); // add session ID to avoid overwriting if multiple users generate simultaneously
    }
}