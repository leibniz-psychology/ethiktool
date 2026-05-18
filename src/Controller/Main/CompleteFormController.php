<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\CompleteFormType;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Main\CompleteFormTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompleteFormController extends ControllerAbstract
{
    use CompleteFormTrait, AppDataTrait;
    #[Route('completeForm','completeForm')]
    public function showCompleteForm(Request $request): Response
    {
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session);
        if (!($appNode && $this->getErrors($request,returnCheck: true))) { // page was opened before a proposal was created/loaded or with missing/erroneous inputs
            return $this->redirectToRoute('app_main');
        }
        $pdfFilename = '';
        if ($session->has(self::pdfLoad)) {
            $pdfFilename = $session->get(self::pdfLoad);
            $session->remove(self::pdfLoad);
        }
        $completeFormNode = $appNode->{self::completeFormNodeName};
        $appDataNode = $appNode->{self::appDataNodeName};
        $coreDataArray = $this->xmlToArray($appDataNode->{self::coreDataNode});
        $parameters = $session->get(self::committeeParams);
        // check if any documents besides the form are created
        $anyDoc = 'false'; // translation parameters need to be strings and strval() converts booleans to '0' or '1'
        $pdf = $this->xmlToArray($appDataNode->{self::voteNode})[self::otherVote][self::chosen]==='0' ? [self::voteNode => ''] : []; // indicates if self-written PDFs need to be added
        [$tempArray,$names] = [[],[]]; // $names: all names of studies and groups -> studies: key 0: name, key 1: groups. Same for groups and measure time points
        $studies = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
        $isMultiple = [self::studyNode => count($studies)>1,self::groupNode => false,self::measureTimePointNode => false]; // each value gets true if multiple studies, groups, or measure time points exist.
        $reviewProcess = $session->get(self::reviewProcess);
        $isBegun = $this->getBegunDocs($reviewProcess,$session); // true if data collection has already begun and application type is either full or short and participant documents are reviewed
        $isLegitimizationPDF = !(str_contains($reviewProcess,self::reviewProcessShort) && in_array($parameters[self::committeeType],self::reviewShortChoose) || str_contains($reviewProcess,'Requested')); // neither short without review of participant documents nor requested
        $appTypeArray = $coreDataArray[self::applicationType];
        $references = array_key_exists(self::descriptionNode,$appTypeArray) ? [$appTypeArray[self::descriptionNode]] : []; // references if any re-used data was reviewed
        foreach ($studies as $studyID => $study) {
            $names[$studyID][0] = $study[self::nameNode];
            $groups = $this->addZeroIndex($study[self::groupNode]);
            $isMultiple[self::groupNode] = $isMultiple[self::groupNode] || count($groups)>1;
            foreach ($groups as $groupID => $group) {
                $names[$studyID][1][$groupID] = [$group[self::nameNode],[]];
                $measureTimePoints = $this->addZeroIndex($group[self::measureTimePointNode]);
                $isMultiple[self::measureTimePointNode] = $isMultiple[self::measureTimePointNode] || count($measureTimePoints)>1;
                foreach ($measureTimePoints as $measureID => $measureTimePoint) {
                    $names[$studyID][1][$groupID][1][$measureID] = [];
                    $pdfArray = [];
                    $dataSourceArray = $measureTimePoint[self::dataSourceNode];
                    if (array_key_exists(self::dataSetPDF, $dataSourceArray)) {
                        $pdfArray[self::dataSetNode] = [];
                    }
                    $legitimizationArray = $dataSourceArray[self::legitimizationNode] ?? '';
                    if ($isLegitimizationPDF && $legitimizationArray!=='' && array_key_exists(self::legitimizationConsentNew,$legitimizationArray)) {
                        $pdfArray[self::legitimizationNode] = [];
                    }
                    $dataSourceCommitteeArray = $dataSourceArray[self::dataSourceVotesNode][self::dataSourceCommitteeNode] ?? [];
                    $tempVal = $dataSourceCommitteeArray[self::descriptionNode] ?? '';
                    if (($dataSourceCommitteeArray[self::chosen] ?? '')===self::dataSourceCommitteeSame && $tempVal!=='') {
                        $references[] = $tempVal;
                    }
                    $groupsArray = $measureTimePoint[self::groupsNode];
                    if ($groupsArray!=='') {
                        $informationIIArray = $measureTimePoint[self::informationIINode];
                        $isInformationII = $this->checkInformation($informationIIArray);
                        $addresseeParam = [self::addressee => $this->getAddressee($groupsArray)];
                        if ($isBegun) {
                            $pdfArray['begun'] = $addresseeParam;
                        }
                        $informationArray = $measureTimePoint[self::informationNode];
                        $isInformation = $this->checkInformation($informationArray);
                        if ($isInformation || // information of participants or third party
                            $isInformationII) { // information of participants if third party
                            $anyDoc = 'true';
                        }
                        if (array_key_exists(self::documentTranslationPDF, $informationArray)) {
                            $pdfArray[self::informationNode] = [];
                        }
                        if ($isInformationII) {
                            $pdfArray[self::informationIINode] = array_merge($addresseeParam, [self::informationNode => $this->getInformationString($informationIIArray)]); // must be 'pre' or 'post' at this point
                        }
                        $measuresArray = $measureTimePoint[self::measuresNode];
                        foreach ([self::measuresNode, self::interventionsNode] as $type) {
                            if (array_key_exists($type.'PDF', $measuresArray)) {
                                $pdfArray[$type] = [];
                            }
                        }
                        if (array_key_exists(self::otherSourcesPDF, $measuresArray[self::otherSourcesNode])) {
                            $pdfArray[self::otherSourcesNode] = [];
                        }
                        $privacyArray = $measureTimePoint[self::privacyNode];
                        if ($privacyArray!=='' && array_key_exists(self::createNode, $privacyArray) && ($privacyArray[self::createNode][self::chosen]===self::createSeparate || ($privacyArray[self::addOwnNode] ?? '')==='0')) {
                            $pdfArray[self::privacyNode] = [];
                        }
                        foreach ($pdfArray as $key => $value) {
                            if (!in_array($reviewProcess, self::reviewTypesPDF[$key]) || $key==='begun' && !$isInformation) { // add pdf only if applicable for the current review process and in case of 'fullBegun' if any information is given
                                unset($pdfArray[$key]);
                            }
                        }
                    }
                    if ($pdfArray!==[]) {
                        $tempArray[$studyID][$groupID][$measureID] = $pdfArray;
                    }
                }
            }
        }
        if ($tempArray!==[]) {
            $pdf['projectdetails'] = $tempArray;
        }
        $references = array_unique($references);
        $completeFormArray = $this->xmlToArray($completeFormNode);
        $translationPrefix = 'completeForm.';
        $privacyPrefix = $translationPrefix.self::consentFurther.'.';
        $consentContent = $this->translateString($translationPrefix.'consent.text',array_merge($parameters,['position' => $coreDataArray[self::applicant][self::position], 'anyDoc' => $anyDoc]));
        $isExRe = in_array($appTypeArray[self::chosen],self::appExtendedResubmission);
        $numReferences = count($references);
        $consentFurtherText = $this->translateString($privacyPrefix.'text',array_merge($parameters,['isExRe' => $this->getStringFromBool($isExRe), 'reference' => str_replace('<','&lt;',$this->replaceDummyString($references)), 'numReferences' => $numReferences, 'isReUse' => $this->getStringFromBool(!$isExRe && $numReferences>0 || $isExRe && $numReferences>1)])); // prevent opening tags in user-entered text
        // $firstPage: keys: text to be shown: values: array for a checkbox: 0: whether the checkbox should be checked, 1: text next to the checkbox
        $firstPage = [$consentContent => [$completeFormArray[self::consent],$this->translateString($translationPrefix.self::consent.'.confirm')],$consentFurtherText => [$completeFormArray[self::consentFurther],$this->translateString($privacyPrefix.'consent')]]; // first page of the pdf (preview)

        $completeForm = $this->createFormAndHandleRequest(CompleteFormType::class,$this->xmlToArray($completeFormNode),$request,[self::dummyParams => $pdf]);
        if ($completeForm->isSubmitted()) {
            $this->getDataAndConvert($completeForm,$completeFormNode);
            $response = $request->request->all();
            if (count($response)===1 && str_contains($response['complete_form'][self::submitDummy],'finish')) { // complete proposal should be created
                self::$savePDF = true;
                self::$isCompleteForm = true;
                $this->forward('App\Controller\PDF\CompletePDFController::createPDF',['additional' => [$consentContent => $firstPage[$consentContent], str_replace('<a href','<a class="linkNormal" href',$consentFurtherText) => $firstPage[$consentFurtherText]]]); // remove marking of links
            }
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
        $tempPrefix = $translationPrefix.'finish.text.start';
        return $this->render('Main/completeForm.html.twig',
            $this->setRenderParameters($request,$completeForm,
                [self::pageTitle => 'completeForm.title',
                 'pdfFilename' => $pdfFilename,
                 'firstPage' => $firstPage,
                 'consentContent' => $consentContent,
                 'consentHint' => $this->translateString($translationPrefix.'consent.hint',$parameters),
                 'biasTypes' => self::biasTypes,
                 'biasTitle' => $this->translateString($translationPrefix.'bias.title',$parameters),
                 'consentFurtherText' => $consentFurtherText,
                 'pdf' => $pdf,
                 'names' => $names,
                 'isMultiple' => $isMultiple,
                 'finishText' => [$this->translateString($tempPrefix),$this->translateString($tempPrefix.'Missing'),$this->getFinishEndText($session,true)],
                  self::isCommitteeBeta => $parameters[self::isCommitteeBeta]],'completeForm',addErrors: false));
    }

    /** Checks if pre or post information is selected.
     * @param array|string $information information array
     * @return bool true if pre or post information, false otherwise
     */
    private function checkInformation(array|string $information): bool
    {
        return $information!=='' && ($information[self::pre]==='0' || ($information[self::post][self::chosen] ?? '')==='0');
    }
}