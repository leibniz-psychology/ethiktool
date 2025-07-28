<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\CompleteFormType;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Main\CompleteFormTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompleteFormController extends ControllerAbstract
{
    use CompleteFormTrait, AppDataTrait;
    #[Route('/completeForm', name: 'app_completeForm')]
    public function showCompleteForm(Request $request): Response {
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session);
        try {
            if (!($appNode && $this->getErrors($request,returnCheck: true))) { // page was opened before a proposal was created/loaded or with missing/erroneous inputs
                return $this->redirectToRoute('app_main');
            }
        }
        catch (\Throwable $throwable) {
            return $this->setErrorAndRedirect($session);
        }
        $pdfFilename = '';
        if ($session->has(self::pdfLoad)) {
            $pdfFilename = $session->get(self::pdfLoad);
            $session->remove(self::pdfLoad);
        }
        $completeFormNode = $appNode->{self::completeFormNodeName};
        $appDataNode = $appNode->{self::appDataNodeName};
        $coreDataArray = $this->xmlToArray($appDataNode->{self::coreDataNode});
        $appTypeArray = $coreDataArray[self::applicationType];
        $parameters = $session->get(self::committeeParams);
        // check if any documents besides the form are created
        $anyDoc = 'false'; // translation parameters need to be strings and strval() converts booleans to '0' or '1'
        $pdf = $this->xmlToArray($appDataNode->{self::voteNode})[self::otherVote][self::chosen]==='0' ? [self::voteNode => ''] : []; // indicates if self-written PDFs need to be added
        [$tempArray,$names] = [[],[]]; // $names: all names of studies and groups -> studies: key 0: name, key 1: groups. Same for groups and measure time points
        $studies = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
        $isMultiple = [self::studyNode => count($studies)>1,self::groupNode => false,self::measureTimePointNode => false]; // each value gets true if multiple studies, groups, or measure time points exist.
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
                    $informationIIArray = $measureTimePoint[self::informationIINode];
                    $isInformationII = $this->checkInformation($informationIIArray);
                    $pdfArray = [];
                    if ($this->checkInformation($measureTimePoint[self::informationNode]) || // information of participants or third party
                        $isInformationII) { // information of participants if third party
                        $anyDoc = 'true';
                    }
                    if ($isInformationII) {
                       $pdfArray[self::informationIINode] = [self::informationNode => $this->getInformationString($informationIIArray), self::addressee => $this->getAddressee($measureTimePoint[self::groupsNode])]; // must be 'pre' or 'post' at this point
                    }
                    $measuresArray = $measureTimePoint[self::measuresNode];
                    foreach ([self::measuresNode,self::interventionsNode,self::otherSourcesNode] as $type) {
                        if (array_key_exists($type.'PDF',$measuresArray[$type])) {
                            $pdfArray[$type] = [];
                        }
                    }
                    if ($this->getPrivacyNoTool($measureTimePoint[self::privacyNode])) {
                        $pdfArray[self::privacyNode] = [];
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
        $completeFormArray = $this->xmlToArray($completeFormNode);
        $translationPrefix = 'completeForm.';
        $privacyPrefix = $translationPrefix.self::consentFurther.'.';
        $consentContent = $this->translateString($translationPrefix.'consent.text',array_merge($parameters,['position' => $coreDataArray[self::applicant][self::position], 'anyDoc' => $anyDoc]));
        $consentFurtherText = $this->translateString($privacyPrefix.'text',array_merge($parameters,['isExRe' => $this->getStringFromBool(in_array($appTypeArray[self::chosen],[self::appExtended,self::appResubmission])), 'reference' => str_replace('<','&lt;',$appTypeArray[self::descriptionNode] ?? '')])); // prevent opening tags in user-entered text
        // $firstPage: keys: text to be shown: values: array for a checkbox: 0: whether the checkbox should be checked, 1: text next to the checkbox
        $firstPage = [$consentContent => [$completeFormArray[self::consent],$this->translateString($translationPrefix.self::consent.'.confirm')],$consentFurtherText => [$completeFormArray[self::consentFurther],$this->translateString($privacyPrefix.'consent')]]; // first page of the pdf (preview)

        $completeForm = $this->createFormAndHandleRequest(CompleteFormType::class,$this->xmlToArray($completeFormNode),$request,[self::dummyParams => $pdf]);
        if ($completeForm->isSubmitted()) {
            $this->getDataAndConvert($completeForm,$completeFormNode);
            $response = $request->request->all();
            if (count($response)===1 && str_contains($response['complete_form'][self::submitDummy],'finish')) { // complete proposal should be created
                self::$savePDF = true;
                self::$isCompleteForm = true;
                $this->forward('App\Controller\PDF\CompletePDFController::createPDF',['additional' => $firstPage]);
            }
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
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
                 'finishText' => $this->translateString($translationPrefix.'finish.text',array_merge($parameters,[self::fileName => $session->get(self::fileName), 'curDate' => $this->getCurrentTime()->format('Ymd')])),
                  self::isCommitteeBeta => $parameters[self::isCommitteeBeta]],'completeForm',addErrors: false));
    }

    /** Checks if pre or post information is selected.
     * @param array|string $information information array
     * @return bool true if pre or post information, false otherwise
     */
    private function checkInformation(array|string $information): bool {
        return $information!=='' && ($information[self::chosen]==='0' || $information[self::informationAddNode][self::chosen]==='0');
    }
}