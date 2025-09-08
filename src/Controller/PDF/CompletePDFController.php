<?php

namespace App\Controller\PDF;

use App\Abstract\PDFAbstract;
use App\Traits\Main\CompleteFormTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CompletePDFController extends PDFAbstract
{
    use CompleteFormTrait;

    private array $briefReport; // one element for each question
    private const answerYes = 'yes';
    private const answerNo = 'no';
    private const answerUnclear = 'unclear';

    public function createPDF(Request $request, array $additional): Response {
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session);
        $completeArray = $this->xmlToArray($appNode)[self::completeFormNodeName];
        $committeeParams = $session->get(self::committeeParams);
        // brief report
        $allBriefReports = []; // 0: heading, 1: array, keys: questions, values: 0: answer, 1: if true, answer will be displayed in red
        $headingTrans = [];
        foreach ([self::studyNode,self::groupNode,self::measureTimePointNode] as $type) {
            $headingTrans[$type] = $this->translateString('projectdetails.headings.'.$type).' ';
        }
        $studyArray = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
        $multipleStudies = count($studyArray)>1;
        $noUnclear = [self::answerNo,self::answerUnclear];
        $appDataArray = $this->xmlToArray($appNode->{self::appDataNodeName});
        $medicineArray = $appDataArray[self::medicine];
        $this->briefReport = [];
        $this->addBriefReportAnswer(self::conflictNode,$appDataArray[self::coreDataNode][self::conflictNode][self::chosen]==='0' ? self::answerYes : self::answerNo);
        $this->addBriefReportAnswer(self::medicine,in_array('0',[$medicineArray[self::medicine][self::chosen],$medicineArray[self::physicianNode][self::chosen]]) ? self::answerYes : self::answerNo);
        $conflictMedicine = $this->briefReport;
        $this->briefReport = [];
        foreach ($studyArray as $studyID => $study) {
            $heading = $multipleStudies ? [self::studyNode => $headingTrans[self::studyNode].($studyID+1)] : [];
            $groupArray = $this->addZeroIndex($study[self::groupNode]);
            $multipleGroups = count($groupArray)>1;
            foreach ($groupArray as $groupID => $group) {
                if ($multipleGroups) {
                    $heading[self::groupNode] = $headingTrans[self::groupNode].($groupID+1);
                }
                else {
                    unset($heading[self::groupNode]);
                }
                $measureArray = $this->addZeroIndex($group[self::measureTimePointNode]);
                $multipleMeasureTimePoints = count($measureArray)>1;
                foreach ($measureArray as $measureID => $measureTimePoint) {
                    if ($multipleMeasureTimePoints) {
                        $heading[self::measureTimePointNode] = $headingTrans[self::measureTimePointNode].($measureID+1);
                    }
                    else {
                        unset($heading[self::measureTimePointNode]);
                    }
                    $informationArray = $measureTimePoint[self::informationNode];
                    $informationIIArray = $measureTimePoint[self::informationIINode];
                    $informationII = $informationIIArray!=='' ? $this->getInformationString($informationIIArray) : '';
                    // information
                    $preContent = [$informationArray[self::informationAddNode][self::chosen],$informationII!=='' ? $informationIIArray[self::informationAddNode][self::chosen] : '']; // if no pre information, key contains answer to question about post information which is either '0' or '1'
                    $this->addBriefReportAnswer(self::informationNode,$preContent[0]===self::complete && in_array($preContent[1],['',self::complete]) ? self::answerYes : self::answerNo,$noUnclear);
                    // voluntary
                    $consentArray = $measureTimePoint[self::consentNode];
                    $tempArray = $consentArray[self::voluntaryNode];
                    $voluntary = [$tempArray[self::chosen], $tempArray[self::chosen2Node] ?? 'yes'];
                    $this->addBriefReportAnswer(self::voluntaryNode,in_array('no',$voluntary)
                        ? self::answerNo
                        : (in_array(self::voluntaryNotApplicable,$voluntary) || // no voluntariness
                        array_key_exists(self::voluntaryYesDescription,$tempArray) || // closed group or dependent
                        ($measureTimePoint[self::compensationNode][self::compensationVoluntaryNode] ?? '')==='0' // compensation may compromise voluntariness
                            ? self::answerUnclear : self::answerYes),$noUnclear);
                    // terminate cons
                    $this->addBriefReportAnswer(self::terminateConsNode,$consentArray[self::terminateConsNode][self::chosen]==='0' ? self::answerYes : self::answerNo,$noUnclear);
                    // examined
                    $groupsArray = $measureTimePoint[self::groupsNode];
                    $tempArray = $groupsArray[self::examinedPeopleNode];
                    $this->addBriefReportAnswer(self::examinedPeopleNode,array_diff_key($tempArray,[self::healthyExaminedNode => '', self::dependentExaminedNode => '', 'otherPeople' => ''])!==[] // people other than healthy, dependent, and other are examined
                        ? self::answerYes
                        : (array_key_exists('otherPeople',$tempArray) || // only other is selected
                           $groupsArray[self::minAge]<18 // underage
                            ? self::answerUnclear : self::answerNo));
                    // wards
                    $this->addBriefReportAnswer(self::wardsExaminedNode,array_key_exists(self::wardsExaminedNode,$tempArray) ? self::answerYes : self::answerNo);
                    // pre content
                    $this->addBriefReportAnswer(self::preContent,in_array(self::deceit,$preContent) // deceit was chosen
                        ? self::answerYes
                        : (array_intersect([$this->getInformationString($informationArray),$informationII],[self::post,'noPost'])!==[] // no information is given
                            ? self::answerUnclear : self::answerNo));
                    // burdens and risks
                    $burdensRisksArray = $measureTimePoint[self::burdensRisksNode];
                    $isBurdensRisksContributors = $burdensRisksArray[self::burdensRisksContributorsNode][self::chosen]==='0';
                    foreach ([self::burdensNode,self::risksNode] as $type) {
                        $this->addBriefReportAnswer($type,$isBurdensRisksContributors || array_diff_key($burdensRisksArray[$type][$type.'Type'],[($type===self::burdensNode ? self::noBurdens : self::noRisks) => ''])!==[] ? self::answerYes : self::answerNo);
                    }
                    // finding
                    $this->addBriefReportAnswer(self::findingNode,$burdensRisksArray[self::findingNode][self::chosen]==='0' ? self::answerYes : self::answerNo);
                    // data privacy
                    $dataPrivacyArray = $measureTimePoint[self::privacyNode];
                    $create = $dataPrivacyArray[self::createNode][self::chosen];
                    $isTool = $create===self::createTool;
                    $this->addBriefReportAnswer(self::privacyNode,
                        $create===self::createSeparate || // personal data are collected, but document should not be created by the tool
                        $isTool && $dataPrivacyArray[self::responsibilityNode]!==self::privacyNotApplicable  // if responsibility does not equal 'not applicable', personal data are collected
                        ? self::answerYes
                        : (in_array($create,['separateLater',self::privacyNotApplicable]) || // data privacy is checked later or no information is given
                           $isTool && $dataPrivacyArray[self::markingNode][self::chosen]===self::markingOther // marking can not be created by the tool
                            ? self::answerUnclear : self::answerNo));
                    // add question to time point
                    $allBriefReports[] = ['heading' => implode(', ',$heading), 'content' => array_merge($this->briefReport,$conflictMedicine)];
                }
            }
        }
        $completePDF = $this->renderView('PDF/_completePDF.html.twig',array_merge($committeeParams,[
            self::committeeType => $this->getCommitteeType($session),
            self::isCommitteeBeta => $committeeParams[self::isCommitteeBeta],
            self::committeeParams => $committeeParams,
            'briefReports' => $allBriefReports,
            'savePDF' => self::$savePDF,
            self::content => $additional,
            'messages' => $completeArray[self::descriptionNode],
            self::bias => $completeArray[self::bias],
            'toolVersion' => self::toolVersion]));
        $this->forward('App\Controller\PDF\ApplicationController::createPDF');

        if (self::$savePDF) {
            $this->generatePDF($session,$completePDF,'complete');
            self::$pdf->removeTemporaryFiles();
            return new Response();
        }
        return new Response($completePDF.$session->get(self::pdfApplication).$session->get(self::pdfParticipation.'Marked'));
    }

    /** Adds an element to $this->briefReport.
     * @param string $key key to be used for the translation
     * @param string $answer answer to the question
     * @param array $coloredAnswers if $answer equals any of these answers, the answer will be displayed in red
     * @return void
     */
    private function addBriefReportAnswer(string $key, string $answer, array $coloredAnswers = [self::answerYes,self::answerUnclear]): void {
        $tempPrefix = 'completeForm.briefReport.';
        $this->briefReport['<b>'.$this->translateStringPDF($tempPrefix.'headings.'.$key).":</b>\n".$this->translateStringPDF($tempPrefix.$key)] = [$this->translateStringPDF($tempPrefix.'types.'.$answer),in_array($answer,$coloredAnswers)];
    }
}