<?php

namespace App\Abstract;

use App\Classes\CheckDocClass;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Main\CompleteFormTrait;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use DateTime;
use DOMDocument;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\Filter\FilterException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;
use setasign\Fpdi\PdfReader\PageBoundaries;
use setasign\Fpdi\PdfReader\PdfReaderException;
use SimpleXMLElement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tomsgu\PdfMerger\Exception\FileNotFoundException;
use Tomsgu\PdfMerger\Exception\InvalidArgumentException;
use Tomsgu\PdfMerger\PdfCollection;
use Tomsgu\PdfMerger\PdfMerger;
use ZipArchive;

/** Contains all variables, functions and methods that are used in several controller classes. Therefore, it extends AbstractController. All controller classes inherit this class. */
abstract class ControllerAbstract extends AbstractController
{
    use AppDataTrait, ProjectdetailsTrait, CompleteFormTrait;

    protected const pageTitle = 'pageTitle'; // variable name for the twig variable for the title of the page
    public const submitDummy = 'submitDummy'; // name of the form element that hold the route to redirect to; needed in TypeAbstract, therefore public
    protected const landing = 'landing'; // name of the session variable
    protected const content = 'content'; // name of the variable that is passed to the template
    private const routeOrder = ['app_coreData','app_votes','app_medicine','app_summary','app_contributors','app_landing','app_groups','app_information','app_informationII','app_informationIII','app_measures','app_burdensRisks','app_consent','app_compensation','app_texts','app_legal','app_dataPrivacy','app_dataReuse','app_contributor']; // must equal the route names
    // constant variables for node names
    protected const docName = 'document'; // array of documents. Each time an input is made, a copy is added. If the page is left, all copies except the most recent one are removed.
    protected const docNameRecent = 'documentRecent'; // Same as 'document' but including changes on other pages. Will be used if the xml-file or the single documents are downloaded before the page is left, but only on pages whose inputs may affect other pages. Therefore, the key only exists on such pages.
    protected const contributorsSessionName = 'contributors'; // name of the key in the session where the most recent contributors are stored
    public const fileName = 'fileName'; // needed in NewFormType, therefore public
    protected const saveNodeName = 'saveDate';
    protected const pdfNodeName = 'pdfDate';
    protected const appDataNodeName = 'AppData';
    protected const contributorsNodeName = 'Contributors';
    protected const projectdetailsNodeName = 'Projectdetails';
    protected const completeFormNodeName = 'CompleteForm';
    protected const language = 'language'; // value needs to be the same as in PageTrait
    protected static bool $savePDF = false; // indicates if the pdf should be saved
    protected static bool $isCompleteForm = false; // indicates if the complete proposal should be created
    public const loadInput = 'loadInput'; // form element that holds the loaded xml-file; needed in TypeAbstract, therefore public
    protected const subPages = 'subPages';
    protected const label = 'label';
    protected const route = 'route';
    protected const routeIDs = 'routeIDs';
    protected const tempFolder = 'tmpFiles'; // name of folder where PDFs will be temporarily saved if the complete proposal is created. Must be equal to the name in knp_snappy.yaml
    protected const newForm = 'newForm'; // name of session variable indicating that a new proposal was created successfully
    protected const pdfLoad = 'pdfLoadFailure'; // name of session variable indicating that a custom pdf could not be added
    protected const xmlLoad = 'xmlFailure'; // name of session variable indicating that the xml could not be loaded
    protected const loadSuccess = 'loadSuccess'; // name of session variable indicating the xml was successfully loaded
    protected const errorModal = 'errorMessage'; // name of session variable indicating that an error occurred and the user was redirected to the main page
    protected const preview = 'preview'; // name of the session variable indicating the position of the preview
    protected const pageErrors = 'pageErrors'; // name of twig variable containing the errors on a single page
    protected const isUpdateTime = 'isUpdateTime'; // name of parameter for twig
    private Fpdi $fpdi; // used for merging PDFs
    private string $failureName;
    private PdfCollection $pdfParticipation;

    public function __construct(TranslatorInterface $translator) {
        self::$translator = $translator;
    }

    // functions

    /** Creates the form and handles the submission of the form. If the data should be saved, it is saved in the session and on disk. Then page is reloaded or redirected. This function can only be invoked for pages whose submitted data is converted to xml as it is, i.e., no additional transformation or manipulation needs to be done.
     * @param string $type Type class
     * @param Request $request
     * @param array $subNodeNames names of the sub nodes starting from the root node to the top node of the page
     * @param array $parameters parameters for the view that gets rendered if form is not submitted. Passed keys will not be overwritten
     * @param array $options parameters that are passed to the FormBuilder
     * @return Response
     */
    protected function createFormAndHandleSubmit(string $type, Request $request, array $subNodeNames, array $parameters = [], array $options = []): Response {
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session);
        $isProjectdetails = !in_array(self::appDataNodeName,$subNodeNames); // currently only AppData- and Projectdetails-pages call this function
        if (!$appNode || $isProjectdetails && $this->getMeasureTimePointNode($request,$request->get('_route_params'))===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        if ($isProjectdetails) {
            $parameters = array_merge($this->getProjectdetailsParameters($request),$parameters);
            $subNodeNames = array_merge([self::projectdetailsNodeName,self::studyNode,self::groupNode,self::measureTimePointNode],$subNodeNames);
            foreach ([self::addresseeString,self::participantsString,self::addresseeType,self::informationNode] as $curParam) {
                $options[$curParam] = $parameters[$curParam];
            }
        }
        $pageNode = $appNode;
        foreach ($subNodeNames as $subName) { // get node for current page
            $pageNode = $pageNode->{$subName}[$subName===self::studyNode ? $parameters[self::studyID]-1 : ($subName===self::groupNode ? $parameters[self::groupID]-1 : ($subName===self::measureTimePointNode ? $parameters[self::measureID]-1 : 0))];
        }
        $pageArray = $this->xmlToArray($pageNode);
        if ($pageArray===[]) { // page was opened, but is not active
            return $this->redirectToRoute('app_main');
        }

        $form = $this->createFormAndHandleRequest($type,$pageArray,$request,$options);
        if ($form->isSubmitted()) { // a button was clicked or the language was changed
            $this->getDataAndConvert($form,$pageNode);
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
        // Get the controller name from the request and concatenate every sub-route with '/' until the string 'Controller' is found. E.g.: App\Controller\Projectdetails\CompensationController::showCompensation -> Projectdetails/compensation
        // -> each route must have the function showPageName and the twig file pageName.html.twig
        $controller = $request->get('_controller');
        $controller = substr($controller,strpos($controller,'Controller')+strlen('Controller')+1);
        $controllerName = '';
        foreach (explode('\\',$controller) as $curString) { // get every sub-route until the string contains 'Controller'
            $controllerName .= !str_contains($curString,'Controller::') ? ucfirst($curString).'/' : lcfirst(explode('Controller::show',$curString)[1]);
        }
        return $this->render(ucfirst($controllerName).'.html.twig', $this->setRenderParameters($request,$form,$parameters,($isProjectdetails ? 'projectdetails.' : 'appData.').$pageNode->getName())); // if $isProjectdetails is true, parameters for projectdetails are already set
    }

    /** Adds the language key to $data, sets the languageChanged key in the session to false, creates a form and handles the request.
     * @param string $type Type class
     * @param array|null $data data that is rendered
     * @param Request $request
     * @param array $options options passed to the FormBuilder
     * @return FormInterface
     */
    protected function createFormAndHandleRequest(string $type, ?array $data, Request $request, array $options = []): FormInterface {
        $session = $request->getSession();
        $committeeParams = $session->get(self::committeeParams) ?? [];
        $options = array_merge($options,$committeeParams,[self::committeeParams => $committeeParams]);

        return $this->createForm($type,$data,$options)->handleRequest($request);
    }

    /** Sets parameters that are needed on most pages for a view that gets rendered.
     * @param Request $request current Request
     * @param FormInterface $form form to be rendered
     * @param array $parameters array where the parameters are added. Keys that already exist in this array are not overwritten
     * @param string $pageTitle if not an empty string, 'pageTitle' and 'preview' will be added
     * @param bool $addProjectdetails if true, setParameters() will be called
     * @param bool $addErrors if true, 'pageErrors' will be added. May only be true if $pageTitle is not an empty string
     * @return array parameters  for a view
     */
    protected function setRenderParameters(Request $request, FormInterface $form, array $parameters = [], string $pageTitle = '', bool $addProjectdetails = false, bool $addErrors = true): array {
        $session = $request->getSession();
        $committeeParams = $session->get(self::committeeParams) ?? [self::committeeType => 'noCommittee', self::toolVersionAttr => self::toolVersion];
        try { // set times for maintenance messages
            $date = $this->getCurrentTime();
            $timeString = strtotime($date->format('H:i:s'));
            $startTime = strtotime('8:00:00');
            $date = $date->format('l')==='Monday' ? ($timeString>=strtotime('7:30:00') && $timeString<$startTime ? 'before' : ($timeString>=$startTime && $timeString<strtotime('8:30:00') ? 'during' : '')) : '';
        }
        catch (\Throwable $throwable) {
            return [];
        }
        $returnArray = array_merge($committeeParams,
            [self::content => $form,
             self::isUpdateTime => $date,
             self::committeeParams => $committeeParams]);
        if ($pageTitle!=='') {
            $returnArray[self::pageTitle] = $pageTitle;
            $returnArray[self::preview] = $this->getPreviewScroll($session);
            if ($addErrors) {
                $returnArray[self::pageErrors] = $this->getErrors($request,substr($pageTitle,strrpos($pageTitle,'.')+1));
            }
        }
        if ($addProjectdetails) {
            $returnArray = array_merge($returnArray,$this->getProjectdetailsParameters($request));
        }
        return array_merge($returnArray,$parameters);
    }

    /** Gets the parameters for projectdetails pages.
     * @param Request $request current request
     * @return array array with projectdetails parameters
     */
    private function getProjectdetailsParameters(Request $request): array {
        $appNode = $this->getXMLfromSession($request->getSession());
        $appTypeArray = $this->xmlToArray($appNode->{self::appDataNodeName}->{self::coreDataNode})[self::applicationType];
        $returnArray = ['isNotMain' => $appTypeArray[self::chosen]===self::appNew && ($appTypeArray[self::descriptionNode] ?? '')===self::appTypeShort];
        $routeParams = $request->get('_route_params');
        $studyID = $routeParams[self::studyID];
        $groupID = $routeParams[self::groupID];
        $measureID = $routeParams[self::measureID];
        $returnArray['choiceTextHint'] = self::choiceTextHint;
        $returnArray[self::studyID] = $studyID;
        $returnArray[self::groupID] = $groupID;
        $returnArray[self::measureID] = $measureID;
        $returnArray['routeIDsParam'] = ['routeIDs' => $this->createRouteIDs([self::studyNode => $studyID, self::groupNode => $groupID, self::measureTimePointNode => $measureID])];
        $allNodes = $appNode->{self::projectdetailsNodeName}->{self::studyNode};
        $studyNode = $allNodes[$studyID-1];
        $returnArray[self::studyName] = (string) $studyNode->{self::nameNode};
        $returnArray[self::groupName] = (string) $studyNode->{self::groupNode}[$groupID-1]->{self::nameNode};
        $isMultiple = [count($allNodes)>1, false, false]; // indicates for each level if multiple elements exist
        $allNodes = $allNodes[$routeParams[self::studyID]-1]->{self::groupNode};
        $isMultiple[1] = count($allNodes)>1;
        $isMultiple[2] = count($allNodes[$routeParams[self::groupID]-1]->{self::measureTimePointNode})>1;
        $returnArray['multipleStudyGroupMeasure'] = $isMultiple; // name for the twig variable indicating if there are multiple studies, groups, or measure points in time
        $addressee = $this->getAddresseeFromRequest($request);
        $returnArray[self::addresseeType] = $addressee;
        $returnArray[self::participantsString] = $this->getAddresseeString($addressee,false,true,$addressee===self::addresseeParticipants);
        $returnArray[self::addresseeString] = $this->getAddresseeString($addressee);
        $returnArray[self::informationNode] = $this->getInformation($request);
        return $returnArray;
    }

    /** Saves the document if other form elements than the language are submitted and redirects to the same page or to another page.
     * @param Request $request
     * @param SimpleXMLElement|bool $appNode xml-document that will be saved or false if no xml-document exists (i.e., if the language is changed on the main page before an application was opened)
     * @param SimpleXMLElement|null $appNodeNew if not null, the document to be saved in the 'documentRecent' session key
     * @return Response
     */
    protected function saveDocumentAndRedirect(Request $request, SimpleXMLElement|bool $appNode, ?SimpleXMLElement $appNodeNew = null): Response {
        $session = $request->getSession();
        try {
            $response = $request->request->all();
            $curRoute = $request->get('_route'); // current route
            if ($curRoute==='app_completeForm' && $response===[]) { // response should only empty if current route is complete form
                $session->set(self::pdfLoad,'sizeExceed');
                return $this->redirectToRoute($curRoute);
            }
            $curRouteWoApp = substr($curRoute, 4); // current route without '_app'
            $nodeName = !str_contains($curRoute, self::informationNode) ? strtolower(preg_replace('/[A-Z]/', '_$0', $curRouteWoApp)) : ($curRouteWoApp===self::informationIIINode ? 'information_iii' : self::informationNode); // current route with camel case converted to snake case
            $nodeName = !in_array($nodeName, ['main', 'check_doc']) ? $nodeName : 'dummy';
            $formContent = $response[$nodeName] ?? $response['dummy'];
            $submitDummy = $formContent[self::submitDummy];
            if (str_starts_with($submitDummy, self::preview)) {
                $submitDummy = explode("\n", $submitDummy);
                $session->set(self::preview, substr(trim($submitDummy[0]), strlen(self::preview.':')));
                foreach ($submitDummy as $key => $value) { // if a link is double-clicked, the 'preview:X' value may exist twice
                    if (str_contains($value, self::preview)) {
                        unset($submitDummy[$key]);
                    }
                }
                $submitDummy = implode("\n", $submitDummy);
            }
            if (str_contains($submitDummy, 'quit')) { // quit program
                if ($submitDummy!=='quitModalButton') { // quit without saving or before a proposal was opened
                    $session->clear();
                    return $this->redirectToRoute('app_main');
                }
                return $this->redirectToRoute('app_quit');
            }
            else {
                $loadInput = $request->files->all()[$nodeName][self::loadInput] ?? [];
                $isDownload = str_contains($submitDummy, 'download');
                $oldLanguage = $request->getLocale();

                if ($loadInput!==[]) { // form was loaded
                    try {
                        $xml = simplexml_load_string(file_get_contents($loadInput->getRealPath()));
                        $xmlArray = $this->xmlToArray($xml);
                        $this->getErrors($request,element: $xml); // if the file is invalid, an exception is thrown
                        $this->setCommittee($session, $xmlArray[self::committee], $oldLanguage);
                        $session->set(self::fileName, str_replace('.xml', '', $loadInput->getClientOriginalName()));
                        $xml = $xml->asXML();
                        $session->set(self::docName, [$xml]);
                        $session->set(self::contributorsSessionName, [0 => $this->addZeroIndex($xmlArray[self::contributorsNodeName][self::contributorNode])]);
                        $session->set(self::loadSuccess,['isMain' => $this->getStringFromBool($curRoute==='app_main')]);
                    } catch (\Throwable $throwable) { // xml file could not be loaded
                        $session->set(self::xmlLoad, '');
                    }
                    return $this->redirectToRoute('app_main');
                }
                elseif ($isDownload || $submitDummy==='finish') { // xml-file or complete proposal should be downloaded
                    return $this->getDownloadResponse($session, $isDownload, $request);
                }
                else {
                    $isCoreData = $curRoute==='app_coreData';
                    $isContributors = $curRoute==='app_contributors';
                    $isCoreDataContributors = $isCoreData || $isContributors;
                    $routeParams = $request->get('_route_params');
                    $language = $oldLanguage;
                    $hasAppNodeNew = $appNodeNew!==null;
                    if (str_starts_with($submitDummy,self::language)) { // one of the language elements was clicked
                        $submitDummy = explode("\n", $submitDummy);
                        $language = substr(trim($submitDummy[0]), strlen(self::language.':'));
                        if ($language!==$oldLanguage) { // language has changed
                            $session->set(self::language, $language);
                            $this->setCommittee($session, $session->get(self::committeeParams)[self::committeeType] ?? '', $language);
                            // set first inclusion criterion
                            if ($appNode) {
                                foreach ($this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName}->{self::studyNode})) as $studyID => $study) {
                                    foreach ($this->addZeroIndex($study[self::groupNode]) as $groupID => $group) {
                                        foreach ($this->addZeroIndex($group[self::measureTimePointNode]) as $measureID => $measure) {
                                            $curRouteParams = [self::studyID => $studyID + 1, self::groupID => $groupID + 1, self::measureID => $measureID + 1];
                                            $this->setFirstInclusion($this->getMeasureTimePointNode($appNode, $curRouteParams)->{self::groupsNode}, $language);
                                            if ($hasAppNodeNew) {
                                                $this->setFirstInclusion($this->getMeasureTimePointNode($appNodeNew,$curRouteParams)->{self::groupsNode}, $language);
                                            }
                                        }
                                    }
                                }
                                $this->saveDocument($session, $appNode); // if language has changed, page will be reloaded, i.e., internal documents will be reset
                                if ($hasAppNodeNew) {
                                    $this->saveDocumentInSession($session, self::docNameRecent, $appNodeNew);
                                }
                            }
                        }
                        return $this->redirectToRoute($curRoute,array_merge($routeParams,['_locale' => $language]));
                    }
                    if ((!(str_contains($submitDummy, 'undo') || str_contains($submitDummy, 'documents')))) { // page contains form elements other than the language
                        $this->saveDocument($session, $appNode);
                        if ($hasAppNodeNew) {
                            $this->saveDocumentInSession($session, self::docNameRecent, $appNodeNew);
                        }
                    }
                    $isNext = str_contains($submitDummy, 'nextPage');
                    $isPrevious = str_contains($submitDummy, 'previousPage');
                    if ($isNext && $isPrevious) { // if both buttons are clicked immediately after one another, only keep 'previous page' in case it happened on the overview page of a measure time point
                        $isNext = false;
                    }
                    if (str_contains($submitDummy, 'backToMain')) { // 'back to Main menu' was clicked. Must equal the name of the button in twig
                        $this->resetDocContributors($session, $isCoreDataContributors);
                        return $this->redirectToRoute('app_main');
                    }
                    elseif ($isNext || $isPrevious) { // 'next page' or 'previous page' was clicked
                        $this->resetDocContributors($session, $isCoreDataContributors);
                        if ($curRoute==='app_landing') {
                            $landingArray = $session->get(self::landing);
                            if (($landingArray['page'] ?? '')===self::appDataNodeName) { // app data overview, only 'next page' is enabled
                                $nextRoute = 'app_coreData';
                            }
                            else { // one of the projectdetails overviews
                                $isPagesOverview = array_key_exists(self::measureID,$landingArray); // true if overview of pages of one measure time point
                                $isMeasureTimePointOverview = !$isPagesOverview && array_key_exists(self::groupID,$landingArray); // true if overview of measure time points
                                $isGroupOverview = !$isMeasureTimePointOverview && array_key_exists(self::studyID,$landingArray); // true if overview of groups
                                if ($isNext) {
                                    if ($isPagesOverview) {
                                        $nextRoute = 'app_groups';
                                        unset($landingArray['page']);
                                    }
                                    else {
                                        $nextRoute = 'app_landing';;
                                        $landingArray[$isMeasureTimePointOverview ? self::measureID : ($isGroupOverview ? self::groupID : self::studyID)] = 1; // redirect to first element of next level
                                    }
                                    $tempArray = []; // if 'next page' is clicked immediately after a link was clicked, landingArray is empty
                                    foreach (explode("\n", $submitDummy) as $line) {
                                        $line = explode(':', $line);
                                        $key = $line[0];
                                        if (str_contains($key, 'ID')) {
                                            $tempArray[$key] = trim($line[1]);
                                        }
                                    }
                                    $routeParams = array_merge($routeParams, $tempArray, $landingArray); // add IDs
                                    if (!$isPagesOverview) {
                                        $session->set(self::landing, $landingArray);
                                    }
                                }
                                else { // previous page
                                    if (array_key_exists(self::studyID,$landingArray)) { // overview of groups, measure time points or one measure time point
                                        $nextRoute = 'app_landing';
                                        unset($landingArray[$isPagesOverview ? self::measureID : ($isMeasureTimePointOverview ? self::groupID : ($isGroupOverview ? self::studyID : ''))]); // redirect to previous level
                                        $session->set(self::landing, $landingArray);
                                    }
                                    else { // overview of studies
                                        $nextRoute = 'app_contributors';
                                    }
                                }
                            }
                        }
                        elseif ($isCoreData && $isPrevious) {
                            $nextRoute = 'app_landing';
                            $session->set(self::landing, ['page' => self::appDataNodeName]);
                        }
                        else { // page unlike landing and core data
                            $isReuseOnly = $curRoute==='app_dataReuse' && !$this->getMultiStudyGroupMeasure($appNode);

                            $nextVal = $isNext && ($isContributors || $isReuseOnly);
                            $addVal = $isNext ? 1 : -1;
                            if ($nextVal || $isNext && $curRoute==='app_contributor' || $isPrevious && $curRoute==='app_groups') { // groups or last page of current measure time point or contributors
                                if ($isNext && $isReuseOnly) { // $curRoute is data reuse and contributor page is not active
                                    $nextRoute = 'app_checkDoc';
                                }
                                elseif ($isNext && $isContributors) { // $curRoute is contributors -> next page is projectdetails overview
                                    $nextRoute = 'app_landing';
                                    $session->set(self::landing, ['page' => self::projectdetailsNodeName]);
                                }
                                else { // groups or last page of current measure time point
                                    $hasNextPrevious = false;
                                    $newID = $routeParams[self::measureID]+$addVal;
                                    $newRouteParams = array_merge($routeParams,[self::measureID => $newID]);
                                    if ($newID>0 && $this->getMeasureTimePointNode($appNode, $newRouteParams)!==null) { // next/previous measure time point exists
                                        $hasNextPrevious = true;
                                    }
                                    else {
                                        $studies = $this->addZeroIndex($this->xmlToArray($appNode)[self::projectdetailsNodeName][self::studyNode]);
                                        $newID = $routeParams[self::groupID]+$addVal;
                                        $newRouteParams = array_merge($routeParams,[self::groupID => $newID, self::measureID => 1]); // first measure time point of next/previous group
                                        if ($newID>0 && $this->getMeasureTimePointNode($appNode, $newRouteParams)!==null) { // a group exists before/after the current group
                                            $hasNextPrevious = true;
                                            if ($isPrevious) {
                                                $newRouteParams = array_merge($newRouteParams,[self::measureID => count($this->addZeroIndex($this->addZeroIndex($studies[$routeParams[self::studyID]-1][self::groupNode])[$newID-1][self::measureTimePointNode]))]); // last measure time point of previous group
                                            }
                                        }
                                        else {
                                            $newID = $routeParams[self::studyID]+$addVal;
                                            $newRouteParams = array_merge($routeParams,[self::studyID => $newID, self::groupID => 1, self::measureID => 1]); // first measure time point of first group of next/previous study
                                            if ($newID>0 && $this->getMeasureTimePointNode($appNode, $newRouteParams)!==null) { // a study exists before/after the current study
                                                $hasNextPrevious = true;
                                                if ($isPrevious) {
                                                    $groupArray = $this->addZeroIndex($studies[$newID][self::groupNode]);
                                                    $groupID = count($groupArray);
                                                    $newRouteParams = array_merge($newRouteParams,[self::groupID => $groupID, self::measureID => count($this->addZeroIndex($groupArray[$groupID-1][self::measureTimePointNode]))]); // last measure time point of last group of previous study
                                                }
                                            }
                                        }
                                    }
                                    $nextRoute = $isNext ? ($hasNextPrevious ? 'app_groups' : 'app_checkDoc') : ($hasNextPrevious ? 'app_contributor' : 'app_landing');
                                    $routeParams = $hasNextPrevious ? $newRouteParams : ($isPrevious ? [self::studyID => 1, self::groupID => 1, self::measureID => 1] : []);
                                    if ($nextRoute==='app_landing') {
                                        $session->set(self::landing, array_merge($routeParams, ['page' => self::projectdetailsNodeName]));
                                    }
                                }
                            }
                            else { // $curRoute is a projectdetails subpage unlike groups and unlike the last page of the current measure time point
                                $curRouteIndex = array_search($curRoute, self::routeOrder); // can not be the index of app_landing at this point
                                $nextRoute = self::routeOrder[$curRouteIndex+$addVal] ?? '';
                                if ($curRouteIndex>array_search('app_landing', self::routeOrder)) {
                                    $measureArray = $this->xmlToArray($this->getMeasureTimePointNode($appNode, $routeParams));
                                    while ($measureArray[substr($nextRoute, 4)]==='') { // next page is not active
                                        $curRouteIndex += $addVal;
                                        $nextRoute = self::routeOrder[$curRouteIndex];
                                    }
                                }
                            }
                        }
                        return $this->redirectToRoute($nextRoute, $routeParams);
                    }
                    else { // 'save', a link or 'undo' was clicked, the language was changed, or the complete proposal should be created
                        $submitDummy = explode("\n", $submitDummy);
                        $route = trim($submitDummy[0]);
                        $fragment = '';
                        if (str_contains($route,'#')) {
                            [$route,$fragment] = explode('#',$route);
                        }
                        $saveUndoDoc = in_array($route, ['undo', 'save', 'documents']) ? $route : '';
                        if ($saveUndoDoc!=='') {
                            $route = '';
                            $submitDummy = array_slice($submitDummy, 1); // first line contains either 'undo', 'save', or 'documents'
                        }
                        $routeParams = array_merge($routeParams, ['_locale' => $language]);
                        if ($saveUndoDoc==='undo') { // undo to state of last input
                            // remove most recent documents -> must be invoked after saveDocuments()
                            foreach ([self::docName, self::docNameRecent] as $docType) {
                                $docs = $session->get($docType); // all documents
                                if ($docs!==null && count($docs)>1) {
                                    $session->set($docType, array_slice($docs, 0, count($docs) - 1));
                                }
                            }
                            if ($isCoreData || $isContributors) { // remove most recent contributors array
                                $allContributorsArrays = $session->get(self::contributorsSessionName);
                                $numArrays = count($allContributorsArrays);
                                if ($numArrays>1) {
                                    unset($allContributorsArrays[$numArrays - 1]);
                                    if ($isCoreData && count($allContributorsArrays)>1) { // if core data, a copy will be saved before calling this function, but only if 'undo' is not double-clicked
                                        unset($allContributorsArrays[$numArrays - 2]);
                                    }
                                    $session->set(self::contributorsSessionName, $allContributorsArrays);
                                }
                            }
                        }
                        elseif ($saveUndoDoc==='documents') { // pdf should be created
                            self::$savePDF = true;
                            return $this->forward('App\Controller\PDF\ApplicationController::createPDF');
                        }
                        else {
                            if ($route!=='' && $route!==$curRoute) { // go to another page
                                $this->resetDocContributors($session, $isCoreDataContributors);
                            }
                            $routeParams = ['_locale' => $routeParams['_locale']]; // if current route is a projectdetails page and next is a non-projectdetails page, remove IDs
                            if (count($submitDummy)>1) { // a link was clicked and additional parameters are passed
                                $landingParams = [];
                                $isLanding = $route==='app_landing';
                                foreach (array_slice($submitDummy, 1) as $id) { // first line contains the route, so exclude it.
                                    $curID = explode(':', trim($id)); // every parameter must have the form name:value
                                    $curKey = $curID[0];
                                    $curValue = $curID[1];
                                    if ($isLanding) {
                                        if (!array_key_exists($curKey, $landingParams)) {
                                            $landingParams[$curKey] = $curValue;
                                        }
                                    }
                                    elseif (!array_key_exists($curKey, $routeParams)) {
                                        $routeParams[$curKey] = $curValue;
                                    }
                                    if (str_contains($curKey, 'page')) { // If a link is double-clicked, the route parameters may exist twice (or three times, if immediately after entering text in a text field, therefore, when adding the parameters, only add them once, as the first ones added are the actual ones. As soon as the first key does not contain 'ID', all relevant IDs were added
                                        break;
                                    }
                                }
                                $session->set(self::landing, $landingParams);
                            }
                            if ($route==='app_newForm') {
                                $session->clear();
                            }
                        }
                        return $this->redirectToRoute($route ?: $request->get('_route'), array_merge($routeParams,['_fragment' => $fragment]));
                    }
                } // else after load and download
            } // else after quit check
        }
        catch (\Throwable $throwable) { // catches exceptions and error
            return $this->setErrorAndRedirect($session);
        }
    }

    /** Sets the names and paths for a submenu.
     * @param string $page name of the main heading of the submenu. Must equal 'AppData' or 'Projectdetails'
     * @param Request|null $request if page does not equal 'AppData', the request
     * @param int|null $studyID if $page does not equal 'AppData', the id of the study
     * @param int|null $groupID if $page does not equal 'AppData', the id of the group or null if an overview of the study pages should be created
     * @param int|null $measureID if $page does not equal 'AppData', the id of the measure point in time or null if an overview of the group pages should be created
     * @param bool $cutName if $page equals 'Projectdetails' and an overview of studies or groups should be created, true if only the first 5 characters of the name should be shown, false otherwise
     * @return array keys: label for the pages, values: array: names of the routes and route IDs, if applicable
     */
    protected function setSubMenu(string $page, ?Request $request = null, ?int $studyID = null, ?int $groupID = null, ?int $measureID = null, bool $cutName = true): array {
        if ($page===self::appDataNodeName) {
            $tempVal = 'pages.appData.';
            $tempArray = [];
            foreach ([self::coreDataNode,self::voteNode,self::medicine,self::summary] as $page) {
                $tempArray[] = [self::label => $this->translateString($tempVal.$page),self::route => 'app_'.$page];
            }
            $returnArray = [self::label => $this->translateString($tempVal.'title'),self::route => 'app_landing',self::subPages => $tempArray];
        }
        else {
            $appNode = $this->getXMLfromSession($request->getSession());
            $studies = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
            if ($studyID===null) { // overview of studies
                $returnArray = $this->setOverview($studies,self::studyNode,$cutName,[]);
            }
            else {
                $groups = $this->addZeroIndex($studies[$studyID][self::groupNode]);
                if ($groupID===null) { // overview of groups
                    $returnArray = $this->setOverview($groups,self::groupNode,$cutName,[self::studyID => $studyID+1]);
                }
                else {
                    $measureTimePoints = $this->addZeroIndex($groups[$groupID][self::measureTimePointNode]);
                    if ($measureID===null) { // overview of measure time points
                        $returnArray = $this->setOverview($measureTimePoints,self::measureTimePointNode,$cutName,[self::studyID => $studyID+1, self::groupID => $groupID+1]);
                    }
                    else { // overview of one measure time point
                        $prefix = 'pages.projectdetails.';
                        $measure = $measureTimePoints[$measureID];
                        $routeIDs = [self::studyID => $studyID+1, self::groupID => $groupID+1, self::measureID => $measureID+1];
                        $information = $this->getInformation($appNode,[self::studyID => $studyID+1, self::groupID => $groupID+1, self::measureID => $measureID+1]);
                        $isPre = $information===self::pre;
                        $sidebarSuffix = $cutName ? 'Sidebar' : ''; // use abbreviation for information pages only in sidebar
                        $returnArray = [
                            [self::label => $this->translateString($prefix.self::groupsNode), self::route => 'app_groups', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::informationNode.$sidebarSuffix), self::route => 'app_information', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::informationIINode.$sidebarSuffix), self::route => $this->getAddressee($measure[self::groupsNode])!==self::addresseeParticipants ? 'app_informationII' : '', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::informationIIINode.$sidebarSuffix), self::route => $this->getInformationIII($measure[self::informationNode]) ?  'app_informationIII' : '', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::measuresNode), self::route => 'app_measures', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::burdensRisksNode), self::route => 'app_burdensRisks', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::consentNode), self::route => 'app_consent', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::compensationNode), self::route => 'app_compensation', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::textsNode), self::route => ($isPre || $information===self::post) ? 'app_texts' : '', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::legalNode), self::route => ($isPre && ($this->getAnyConsent($measure[self::consentNode]) || $this->getTemplateChoice($this->getLoanReceipt($measure[self::measuresNode][self::loanNode])))) ? 'app_legal' : '', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::privacyNode), self::route => 'app_dataPrivacy', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::dataReuseNode), self::route => 'app_dataReuse', self::routeIDs => $routeIDs],
                            [self::label => $this->translateString($prefix.self::contributorNode), self::route => (count($studies)>1 || count($groups)>1 || count($measureTimePoints)>1) ? 'app_contributor' : '', self::routeIDs => $routeIDs]
                        ];
                    }
                }
            }
        }
        return $returnArray;
    }

    /** Sets the overview of studies, groups, or measure time points.
     * @param array $array array containing the subpages
     * @param string $nodeName type of subpages overview to create. Must equal 'study', 'group', or 'measureTimePoint'
     * @param bool $cutName if $nodeName equals 'study' or 'group' and if true, only the first 5 characters of the name are shown
     * @param array $routeIDs routeIDs
     * @return array keys: label for the pages, values: names of the routes
     */
    protected function setOverview(array $array, string $nodeName, bool $cutName, array $routeIDs): array {
        $returnArray = [];
        $multiple = count($array)>1;
        $showName = $nodeName!==self::measureTimePointNode;
        $loopID = $nodeName===self::studyNode ? self::studyID : ($nodeName===self::groupNode ? self::groupID : self::measureID);
        foreach ($array as $index => $page) {
            $name = '';
            if ($showName) {
                $name = $page[self::nameNode];
                if ($cutName && strlen($name)>8) {
                    $name = substr($name,0,5).'...';
                }
            }
            $returnArray[] = [self::label => $this->translateString('projectdetails.headings.'.$nodeName).($multiple ? ' '.($index+1) : '').($name!=='' ? ($multiple ? ' (' : ' ').$name.($multiple ? ')' : '') : ''), self::route => 'app_landing', self::routeIDs => array_merge($routeIDs,[$loopID => $index+1])];
        }
        return $returnArray;
    }

    /** Checks if any compensation is awarded by a certain type
     * @param array|string $compensation array containing the compensation
     * @param string $type type of awarding to be checked. Defaults to 'code'
     * @return bool true if any compensation is awarded later by a certain type, false otherwise
     */
    protected function checkCompensationAwarding(array|string $compensation, string $type = 'code'): bool {
        $compensation = $compensation[self::compensationTypeNode] ?? '';
        $isCompensationAwarding = false;
        $isLater = in_array($type,['code','name']);
        $deliverTypes = ['eMail','mail','phone'];
        if ($compensation!=='' && !array_key_exists(self::compensationNo,$compensation)) { // at least one type except 'no compensation' was selected
            foreach ($compensation as $name => $data) {
                if ($name!==self::compensationOther) {
                    $awarding = $data[self::awardingNode];
                    $chosen = $awarding[self::chosen];
                    if ($isLater) {
                        $isCompensationAwarding = array_key_exists(self::laterTypesName,$awarding) && $awarding[self::laterTypesName]===$type;
                    }
                    elseif (in_array($type,$deliverTypes)) {
                        $isCompensationAwarding = $chosen===self::awardingDeliver && $awarding[self::descriptionNode]===$type || ($awarding[self::lotteryStart] ?? '')===$type;
                    }
                    else {
                        $isCompensationAwarding = $chosen===$type;
                    }
                }
                if ($isCompensationAwarding) {
                    break;
                }
            }
        }
        return $isCompensationAwarding;
    }

    /** Checks if inputs on the legal page were made.
     * @param array $inputArray array with keys 'pageNames' and 'pageInputs'
     * @param array $measureArray array containing the current measure time point
     * @param bool $addApparatus if true, the apparatus is added if existent
     * @return string hint saying that inputs the legal page are deleted. May also contain other inputs and pages if the values of the $inputArray keys are not empty
     */
    protected function getLegalInput(array $inputArray, array $measureArray, bool $addApparatus = true): string {
        $isInput = false;
        $legalParams = array_combine(self::legalTypes,array_fill(0,count(self::legalTypes),'false')); // contains more keys than necessary
        $legalParams['hints'] = -1;
        $legalArray = $measureArray[self::legalNode];
        if ($legalArray!=='') {
            foreach (array_keys($legalArray) as $type) {
                if ($type!==self::apparatusNode || $addApparatus) {
                    ++$legalParams['hints'];
                    $legalParams[$type] = 'true';
                    if ($this->checkInput($legalArray[$type],[self::chosen => ''])) {
                        $isInput = true;
                    }
                }
            }
        }
        $multipleHints = $legalParams['hints']>1;
        if ($isInput) {
            $this->addInputPage('pages.projectdetails.',self::legalNode,$inputArray,$legalParams);
            if ($multipleHints) {
                $lastIndex = count($inputArray[self::pageInputs])-1;
                $inputArray[self::pageInputs][$lastIndex] = $this->replaceString($inputArray[self::pageInputs][$lastIndex]);
            }
        }
        return $this->setInputHint($inputArray,$multipleHints);
    }

    /** Checks for every key in \$inputs if the corresponding key in \$nodeArray is neither empty nor equals the value of the key in $inputs.
     * @param array|string $nodeArray Array where inputs are checked
     * @param array $inputs keys: keys in $nodeArray to be checked. values: values to be checked against
     * @return bool true if any of the checked elements is neither empty nor equals the respective $inputs value, false otherwise
     */
    protected function checkInput(array|string $nodeArray, array $inputs): bool {
        if ($nodeArray==='' || $nodeArray===[]) {
            return false;
        }
        foreach ($inputs as $key => $value) {
            $curValue = $nodeArray[$key] ?? ''; // as the state of the document when entering the page is checked, the array key may not exist
            if ($curValue!=='' && $curValue!==$value) {
                return true;
            }
        }
        return false;
    }

    /** Initializes the input array.
     * @return array keys: 'pageNames', 'pageInputs' (values: empty arrays)
     */
    protected function setInputArray(): array {
        return [self::pageNames => [], self::pageInputs => []];
    }

    /** Sets the hint saying that inputs on other pages are deleted.
     * @param array $pages array with keys 'pageNames' and 'pageInputs'
     * @param bool $replaceFirst if true, the first comma of the inputs gets replaced, otherwise the last one
     * @return string hint with inputs that are deleted
     */
    protected function setInputHint(array $pages, bool $replaceFirst = false): string {
        $count = count($pages[self::pageNames]);
        if ($count>0) {
            $translationPrefix = 'multiple.inputs.';
            return $this->translateString($translationPrefix.'hint',['pages' => $count, 'page' => $this->replaceString(implode(',',$pages[self::pageNames]),useAnd: false), 'inputs' => $this->replaceString(implode(', ',$pages[self::pageInputs]),replaceFirst: $replaceFirst,useAnd: false)]);
        }
        return '';
    }

    /** Replaces either the first or last occurrence of a string in a string.
     * @param string $input string where the occurrence gets replaced
     * @param string $search string to be replaced. Defaults to a comma
     * @param string $replace string that should replace the occurrence
     * @param bool $replaceFirst if true, the first occurrence will be replaced, otherwise the last one
     * @param bool $useAnd if $replace is empty, the occurrence will be replaced with 'and' if true, otherwise with 'respectively'
     * @return string \$input with the last occurrence of \$search replaced or \$input if it does not contain \$search
     */
    protected function replaceString(string $input, string $search = ',', string $replace = '', bool $replaceFirst = false, bool $useAnd = true): string {
        return str_contains($input,$search) ? substr_replace($input,$replace==='' ? $this->translateString('multiple.inputs.'.($useAnd ? 'lastAnd' : 'lastRespectively')) : $replace, $replaceFirst ? strpos($input,$search) : strrpos($input,$search),1) : $input;
    }

    /** Checks whether a pre or post information is chosen by calling getInformationString. If $element is the request, the most recent document will be used.
     * @param Request|SimpleXMLElement $element Either the request or the root node of the xml-document.
     * @param array $routeParams if $element is a SimpleXMLElement, the route parameters
     * @return string 'pre' if pre information is chosen, 'post' if pre information is answered with no and post information is answered with yes, 'noPre' if pre information is answered with no and no post information is chosen, 'noPost' if pre and post information are answered with no, empty string otherwise (i.e., no pre information is chosen)
     */
    protected function getInformation(Request|SimpleXMLElement $element, array $routeParams = []): string {
        if ($element instanceof Request) {
            $routeParams = $element->get('_route_params');
            if ($this->getMeasureTimePointNode($element,$routeParams)===null) { // a page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
                return '';
            }
        }
        return $this->getInformationString($this->xmlToArray($this->getMeasureTimePointNode($element,[self::studyID => $routeParams[self::studyID], self::groupID => $routeParams[self::groupID], self::measureID => $routeParams[self::measureID]]))[self::informationNode]);
    }

    /** Creates a string indicating the type of information.
     * @param array $information
     * @return string 'pre' if pre information is chosen, 'post' if pre information is answered with no and post information is answered with yes, 'noPre' if pre information is answered with no and no post information is chosen, 'noPost' if pre and post information are answered with no, empty string otherwise (i.e., no pre information is chosen)
     */
    protected function getInformationString(array $information): string {
        $pre = $information[self::chosen];
        $post = $pre==='1' ? $information[self::informationAddNode][self::chosen] : '';
        return $pre==='0' ? self::pre : ($pre==='1' ? ($post==='0' ? self::post : ($post==='1' ? 'noPost' : 'noPre')) : '');
    }

    /** Translates the first inclusion criterion.
     * @param string $addressee addressee
     * @param string $limit age limits
     * @param string $minAge min age
     * @param string $maxAge max age
     * @param string $locale locale to be used for translation
     * @return string translated first inclusion criterion
     */
    protected function getFirstInclusion(string $addressee, string $limit, string $minAge, string $maxAge, string $locale): string {
        return self::$translator->trans('projectdetails.pages.groups.criteria.include.addressee',[self::addressee => $addressee, 'limits' => $limit, 'minAge' => $minAge, 'maxAge' => $maxAge],'messages',$locale);
    }

    /** Checks if the consent is either written, digital, or oral.
     * @param array|string $consent either the array containing all elements of the consent page or the type of consent
     * @return bool true if consent is either written, digital, or oral, false otherwise
     */
    protected function getAnyConsent(array|string $consent): bool {
        return in_array(is_array($consent) ? $consent[self::consentNode][self::chosen] : $consent,self::consentTypesAny);
    }

    /** Gets the choice of the loan receipt.
     * @param array $loanArray array containing the loan nodes
     * @return string loan receipt choice or empty string if no choice was made or the array key does not exist
     */
    protected function getLoanReceipt(array $loanArray): string {
        return $loanArray[self::loanReceipt][self::chosen] ?? '';
    }

    /** Creates the string for the con template.
     * @param array $measureArray array containing the information about the current measure time point
     * @param bool $addDescription if true, the entered text, if applicable, is added
     * @param bool $addNoTemplate if true, the sentence that no template could be created, if applicable, is added
     * @param array $routeParams if $addNoTemplate is true, the route parameters of the current measure time point
     * @param bool $addTemplate if true, the template sentence will be added regardless of the choice in texts
     * @return string con template text
     */
    protected function getConTemplateText(array $measureArray, bool $addDescription = true, bool $addNoTemplate = false, array $routeParams = [], bool $addTemplate = false): string {
        $information = $this->getInformationString($measureArray[self::informationNode]);
        $returnString = '';
        if (in_array($information,[self::pre,self::post])) { // information is given
            $conArray = $measureArray[self::textsNode][self::conNode] ?? '';
            if ($conArray!=='') {
                $isTemplate = $conArray[self::conTemplate]==='1';
                $isBurdensRisks = $this->getBurdensRisks($measureArray[self::burdensRisksNode]);
                if ($isTemplate || $addTemplate) {
                    $translationPrefix = 'projectdetails.pages.texts.con.template.';
                    if (!$isBurdensRisks) { // add no burdens/risks
                        $returnString .= ' '.$this->translateString($translationPrefix.self::risksNode,[self::informationNode => $information]);
                    }
                    if ($addNoTemplate && $returnString==='') { // add sentence that no template could be created
                        $returnString = $this->translateString($translationPrefix.'noTemplate',['routeIDs' => '{&quot;'.self::studyID.'&quot;:'.'&quot;'.$routeParams[self::studyID].'&quot;, &quot;'.self::groupID.'&quot;:&quot;'.$routeParams[self::groupID].'&quot;, &quot;'.self::measureID.'&quot;:&quot;'.$routeParams[self::measureID].'&quot;}']);
                    }
                }
                if ($addDescription && (!$isTemplate || $isBurdensRisks)) { // add description
                    $returnString .= ' '.($conArray[self::descriptionNode] ?? '');
                }
            }
        }
        return trim($returnString);
    }

    /** Creates a string indicating the duration or an int indicating the total time.
     * @param array $durations array containing the durations
     * @param bool $isMultiple true if multiple measure time points exist
     * @param bool $returnTotal if true, the total time is returned
     * @return string|int duration string if $returnTotal ist false, the total time otherwise
     */
    protected function getDuration(array $durations, bool $isMultiple, bool $returnTotal = false): string|int {
        $measureTime = $this->getIntFromString($durations[self::durationMeasureTime],0);
        $breaks = $this->getIntFromString($durations[self::durationBreaks],0);
        $total = $breaks+$measureTime;
        return $returnTotal ? $total : $this->translateStringPDF('projectdetails.measures.duration',['multiple' => $this->getStringFromBool($isMultiple), 'total' => $total===0 ? 'X' : $total, self::durationMeasureTime => $measureTime>0 ? $measureTime : 'X', self::durationBreaks => $breaks]);
    }

    /** Checks if burdens or risks except 'noBurdens' and 'noRisks' are selected.
     * @param array $burdensRisksArray array containing the burdens and risks information
     * @return bool true if burdens or risks are selected, false otherwise
     */
    protected function getBurdensRisks(array $burdensRisksArray): bool {
        foreach ([self::burdensNode,self::risksNode] as $type) {
            if ($this->getBurdensOrRisks($burdensRisksArray, $type)[0]) {
                return true;
            }
        }
        return false;
    }

    /** Checks if either burdens, risks, or burdens/risks for contributors are selected.
     * @param array $burdensRisksArray array containing the burdens and risks information
     * @param string $type must equal 'burdens','risks', or 'burdensRisksContributors
     * @return array 0: true if any option except 'no' is selected (burdens/risks for contributors: if 'yes' is selected), 1: true if 'no' is selected; otherwise false in both cases
     */
    protected function getBurdensOrRisks(array $burdensRisksArray, string $type): array {
        $tempArray = $burdensRisksArray[$type];
        if ($type!==self::burdensRisksContributorsNode) {
            $tempArray = $tempArray[$type.'Type'];
            if ($tempArray==='' || $tempArray==[]) { // depending on where the function is called, the 'Type' key can either be an empty string or an empty array if nothing was chosen yet
                return [false,false];
            }
            $isNo = array_key_exists($type===self::burdensNode ? self::noBurdens : self::noRisks,$tempArray);
            return [!$isNo,$isNo];
        }
        else { // burdens/risks for contributors
            $chosen = $tempArray[self::chosen];
            return [$chosen==='0',$chosen==='1'];
        }
    }

    /** Sets the positions for the applicant with and without qualification. Additionally, the positions for the supervisor are set and all positions are translated.
     * @param Session $session current session
     * @return array 0: positions without qualification, 1: positions with qualification, 2: positions for supervisor, 3: all positions translated
     */
    protected function setPositions(Session $session): array {
        $isNotEUB = $this->getCommitteeType($session)!==self::committeeEUB;
        $phdOption = [self::positionsPhd => ''];
        $studentOption = [self::positionsStudent => ''];
        $positionsTranslated = self::positionsTypes;
        foreach ($positionsTranslated as $position => $translation) {
            $positionsTranslated[$position] = $this->translateString($translation);
        }
        $positionsApplicant = array_diff_key(self::positionsTypes,$isNotEUB ? $studentOption : []);
        $positionsQualification = array_intersect_key($positionsApplicant,array_merge($phdOption,!$isNotEUB ? $studentOption : []));
        $positionsSupervisor = array_diff_key(self::positionsTypes,array_merge($studentOption,$this->xmlToArray($this->getXMLfromSession($session))[self::appDataNodeName][self::coreDataNode][self::applicant][self::position]===self::positionsPhd ? $phdOption : []));
        return [$positionsApplicant,$positionsQualification,$positionsSupervisor,$positionsTranslated];
    }

    /** Creates an array where the keys are the values from \$array and the values are all \$string.
     * @param array $array array whose keys are used for the keys of the array to be returned
     * @param string $string translation key for a string that is set as the value for each element
     * @param array $params if provided, parameters for the translation
     * @return array keys: keys from \$keys, value: \$string
     */
    protected function createStringArray(array $array, string $string, array $params = []): array {
        return array_combine(array_values($array),array_fill(0,count($array),$this->translateString($string,$params)));
    }

    /** Get either the most or the least recent contributors array from the session.
     * @param Session $session session where the arrays are stored
     * @param bool $getFirst if true, the oldest contributors array is returned, i.e., the state when the page was entered, otherwise the most recent one
     * @return array most recent contributors array
     */
    protected function getContributors(Session $session, bool $getFirst = false): array {
        $contributors = $session->get(self::contributorsSessionName);
        return $contributors[!$getFirst ? count($contributors)-1 : 0];
    }

    /** Saves the xml-document either on disk or in the session.
     * @param Session $session current session
     * @param SimpleXMLElement $element xml-document that will be saved
     * @param bool $saveOnDisk true if the document should be saved on disk, false otherwise
     * @return Response|null Response for downloading or null if the document was saved in session
     */
    protected function saveDocument(Session $session, SimpleXMLElement $element, bool $saveOnDisk = false): ?Response {
        if ($saveOnDisk) { // // if saved on disk, the 'download' button was clicked, i.e., no changes were made
            return $this->getDownloadResponse($session);
        }
        else {
            $this->saveDocumentInSession($session,self::docName,$element);
            return null;
        }
    }

    /** Creates a response for downloading a file.
     * @param Session $session current session
     * @param bool $isXML if true, the xml-file should be downloaded, a zip file containing the xml-file and pdf-files otherwise
     * @param Request|null $request if the complete proposal should be downloaded, the request
     * @return Response Response containing the download
     */
    protected function getDownloadResponse(Session $session, bool $isXML = true, Request $request = null): Response {
        $filename = $session->get(self::fileName);
        $filenameExt = $filename.'.xml';
        $filename .= '_';
        $xml = $this->createDOM();
        $xmlToSave = $this->getXMLfromSession($session,getRecent: true);
        // add date to xml
        $currentDate = $this->getCurrentTime();
        $curDate = $currentDate->format('Y-m-d H:i:s');
        $xmlToSave->{self::saveNodeName} = $curDate;
        if (!$isXML) {
            $xmlToSave->{self::pdfNodeName} = $curDate;
        }
        $this->setToolVersion($xmlToSave);
        $xml->loadXML($xmlToSave->asXML()); // formatted xml
        $xml = $xml->saveXML(); // formatted xml
        if (!$isXML) {
            $curDate = '_'.$currentDate->format('Ymd');
            $pdfExt = $curDate.'.pdf';
            $singleDocsName = $this->translateString('filenames.singleDocs',[],'pdf').$curDate;
            $zip = new ZipArchive();
            $folderName = $filename.(self::$isCompleteForm ? $this->translateString('filenames.completeForm',[],'pdf').$curDate : $singleDocsName);
            $zipName = sys_get_temp_dir().'/'.$folderName.'.zip';
            $zip->open($zipName,ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addEmptyDir($folderName);
            $folderName .= '/';
            $singleDocsFolder = $folderName.(self::$isCompleteForm ? $filename.$singleDocsName.'/' : '');
            try {
                $this->fpdi = new Fpdi();
                $this->pdfParticipation = new PdfCollection(); // for single documents
                $this->failureName = '';
                $tempFolder = self::tempFolder.'/';
                $sessionIDExt = $session->getId().'.pdf';
                if (self::$isCompleteForm) {
                    $this->addPDF($tempFolder.'complete'.$sessionIDExt);
                }
                $applicationFilename = $tempFolder.'application'.$sessionIDExt;
                $pdfApplication = new PdfCollection(); // for single documents
                $pdfApplication->addPdf($applicationFilename,'1-'.($this->addPDF($applicationFilename)-1));
                if (self::$isCompleteForm) {
                    $files = $request->files->all()['complete_form'];
                    if (array_key_exists(self::voteNode,$files) && $files[self::voteNode]!==null) {
                        $file = $files[self::voteNode];
                        $this->failureName = $file->getClientOriginalName();
                        $this->addPDF($file->getPathname(),false);
                    }
                    $this->addParticipationPDFs($session,true,$files);
                    // set meta data
                    $versionParam = [self::toolVersionAttr => self::toolVersion];
                    $this->fpdi->SetTitle($this->translateStringPDF('metadata.title'));
                    $this->fpdi->SetAuthor($this->translateStringPDF('metadata.author'));
                    $this->fpdi->SetCreator($this->translateStringPDF('metadata.version',$versionParam));
                    $this->fpdi->SetKeywords($this->translateStringPDF('metadata.keywords',array_merge($versionParam,['createTime' => $this->convertDate($currentDate,false)])));
                    // add to zip
                    $folderNameWithFilename = $folderName.$filename;
                    $zip->addFromString($folderNameWithFilename.$this->translateString('filenames.completeForm',[],'pdf').$pdfExt, $this->fpdi->Output( $folderNameWithFilename.$this->translateString('filenames.completeForm',[],'pdf').$pdfExt,PdfMerger::MODE_STRING));
                    $zip->addEmptyDir($singleDocsFolder);
                }
                $singleDocsFolder .= $filename;
                $zip->addFromString($singleDocsFolder.$this->translateString('filenames.application',[],'pdf').$pdfExt,(new PdfMerger(new Fpdi()))->merge($pdfApplication ,PdfMerger::MODE_STRING));
                $this->addParticipationPDFs($session);
                $zip->addFromString($singleDocsFolder.$this->translateString('filenames.participation',[],'pdf').$pdfExt,(new PdfMerger(new Fpdi()))->merge($this->pdfParticipation,PdfMerger::MODE_STRING)); // if single documents, with time, otherwise without
                $this->removeTempFiles($session);
            }
            catch (\Throwable $throwable) {
                $session->set(self::pdfLoad,$this->failureName);
                $this->removeTempFiles($session);
                $zip->close();
                return $this->redirectToRoute('app_completeForm');
            }
            $zip->addFromString($folderName.$filenameExt,$xml);
            $zip->close();
            $filenameExt = basename($zipName);
        }
        $returnResponse = new Response($isXML ? $xml : file_get_contents($zipName), Response::HTTP_OK,
            array_merge([
                'Content-Type' => $isXML ? 'text/xml' : 'application/zip',
                'Content-Disposition' => 'attachment;filename="'.$filenameExt.'"'],
                !$isXML ? ['Content-Length' => filesize($zipName)] : []));
        if (!$isXML) {
            unlink($zipName);
        }
        return $returnResponse;
    }

    /** Returns the current time with Berlin timezone.
     * @return DateTime current time
     */
    protected function getCurrentTime(): DateTime {
        try {
            return new DateTime('now',$this->getTimezone());
        }
        catch (\Throwable $throwable) {
            return new DateTime(); // without timezone
        }
    }

    /** Adds the PDFs for participation.
     * @param Session $session current session
     * @param bool $isCompleteForm if true, custom PDFs from measures will be added, if any. If false, documents will be added to $this->pdfParticipation
     * @param array $files if $isCompleteForm is true, array containing the files from measures that may be added
     * @throws FileNotFoundException
     * @throws PdfTypeException
     * @throws CrossReferenceException
     * @throws PdfReaderException
     * @throws InvalidArgumentException
     * @throws PdfParserException
     * @throws FilterException
     */
    private function addParticipationPDFs(Session $session, bool $isCompleteForm = false, array $files = []): void { // placed here because is called by getDownloadedResponse() and calls addPDF()
        $sessionIDExt = $session->getId().'.pdf';
        $tempFolder = self::tempFolder.'/';
        foreach ($session->get(self::pdfParticipationArray) as $ids) {
            $idSuffix = $this->concatIDs($ids);
            // custom PDFs, if any
            $filename = $tempFolder.'participation'.$idSuffix.$sessionIDExt;
            if ($isCompleteForm) {
                $this->addPDF($filename); // only date
                foreach ([self::informationIINode.$idSuffix, self::measuresNode.$idSuffix, self::interventionsNode.$idSuffix, self::privacyNode.$idSuffix] as $custom) {
                    if (array_key_exists($custom, $files)) {
                        $file = $files[$custom];
                        $this->failureName = $file->getClientOriginalName();
                        $this->addPDF($file->getPathname(), false);
                    }
                }
            }
            else {
                $numPages = $this->fpdi->setSourceFile($filename);
                // if no participation was created, the pdf has only two pages (hint that no participation was created and empty page). If 'pages' is passed to 'addPdf' with a '-', end page must be greater than start page.
                $this->pdfParticipation->addPdf($filename,'1'.($numPages>2 ? '-'.($numPages-1) : ''));
            }
        }
    }

    /** Add a pdf.
     * @param string $filename complete path to the pdf
     * @param bool $removeLastPage if true, the last page of the pdf is removed
     * @return int number of pages
     * @throws CrossReferenceException
     * @throws FilterException
     * @throws PdfParserException
     * @throws PdfTypeException
     * @throws PdfReaderException
     */
    private function addPDF(string $filename, bool $removeLastPage = true): int {
        $numPages = $this->fpdi->setSourceFile($filename);
        for ($curPage = 1; $curPage<$numPages+($removeLastPage ? 0 : 1); $curPage++) {
            $importedPage = $this->fpdi->importPage($curPage, PageBoundaries::CROP_BOX, true, true);
            $this->fpdi->AddPage();
            $this->fpdi->useTemplate($importedPage);
        }
        return $numPages;
    }

    /** Sets the session variables for the committee and the tool version.
     * @param Session $session current session
     * @param string $committeeType committee type
     * @param string $locale locale to be used for translations of the committee
     * @param bool $setSession if true, the variables are saved in the session
     * @return array session variables
     */
    protected function setCommittee(Session $session, string $committeeType, string $locale, bool $setSession = true): array {
        $tempArray = ['committeeType' => $committeeType, self::toolVersionAttr => self::toolVersion, self::isCommitteeBeta => in_array($committeeType,self::committeeTypesBeta)];
        foreach (['committeeNom','committeeGen','committeeDat','committeeAcc','committeeLocation','committeeLocationGen'] as $type) {
            $tempArray[$type] = self::$translator->trans('committee.'.$type,['committee' => $committeeType],'messages',$locale);
        }
        if ($setSession) {
            $session->set(self::committeeParams,$tempArray); // separate elements
        }
        return $tempArray;
    }

    /** Gets the position of the preview scrollbar.
     * @param Session $session current session
     * @return int position of the preview scrollbar or 0 if the session variable cannot be foun
     */
    protected function getPreviewScroll(Session $session): int {
        $scrollPoss = (int) ($session->get(self::preview) ?? 0);
        $session->set(self::preview,0); // if the page is reloaded, go to the top of the preview
        return $scrollPoss;
    }

    /** Sets the 'errorMessage' key in the session and redirects to the main page.
     * @param Session $session current session
     * @return RedirectResponse Response redirecting to the main page
     */
    protected function setErrorAndRedirect(Session $session): RedirectResponse {
        $session->set(self::errorModal,'');
        return $this->redirectToRoute('app_main');
    }

    /** Calls getDocumentCheck().
     * @param Request $request
     * @param string $page if not an empty string, only the errors on a single page are checked
     * @param bool $returnCheck if true and $page is an empty string, a boolean is returned whether no errors were found
     * @param SimpleXMLElement|bool|null $element if not null, the xml document to be checked
     * @return string|bool if \$page is an empty string and \$returnCheck is true: true is no errors were found, false otherwise; otherwise: string with errors or message that no errors were found
     */
    protected function getErrors(Request $request, string $page = '', bool $returnCheck = false, SimpleXMLElement|bool $element = null ): string|bool {
        try {
            return CheckDocClass::getDocumentCheck($request, $page, $returnCheck, $element);
        }
        catch (\Throwable $throwable) {
            return '';
        }
    }

    /** Converts a string to a link
     * @param string $text string to be converted
     * @param string $link route to be linked to
     * @param string $routeIDs if provided, route IDs
     * @param string $fragment fragment to be added to the url
     * @param bool $noColorLink if true, the link will be displayed in normal color
     * @return string converted string
     */
    protected function convertStringToLink(string $text, string $link, string $routeIDs = '', string $fragment = '', bool $noColorLink = false): string {
        return '<a href="'.$link.'" data-action="base#setDummySubmit" data-base-url-param="app_'.$link.($fragment!='' ? '#'.$fragment : '').'"'.($link===self::landing ? 'data-base-page-param="Projectdetails"': '').($routeIDs!=='' ? 'data-base-route-i-ds-param="'.$routeIDs.'"' : '').($noColorLink ? ' style="color: inherit"' : '').'>'.$text.'</a>';
    }

    // functions involving xml

    /** Checks the inputs of data privacy to determine the parameters for data reuse.
     * @param array $privacyArray array containing the data privacy information
     * @return array array with following parameters: bool isAnonymized: whether personal research data are anonymized, bool isPurposeReuse: whether personal research data are kept for reuse, bool dataReuse: whether the privacy document should is/can be created by the tool ('tool') or not ('noTool'), string personal: how the data is processed
     */
    protected function getPrivacyReuse(array $privacyArray): array {
        $create = $privacyArray[self::createNode][self::chosen];
        $isAnonymous = $create==='anonymous';
        $isTool = $create===self::createTool && in_array($privacyArray[self::responsibilityNode] ?? '',['',self::responsibilityOnlyOwn,self::privacyNotApplicable]) && in_array($privacyArray[self::transferOutsideNode] ?? '',['','no',self::privacyNotApplicable]) && ($privacyArray[self::markingNode][self::chosen] ?? '')!==self::markingOther;
        $personal = $isAnonymous ? 'anonymous' : 'noTool';
        $isAnonymized = false;
        $isPurposeReuse = false;
        if ($isTool) {
            if (in_array($privacyArray[self::dataPersonalNode] ?? '',self::dataPersonal)) { // research data are/may be personal
                $anonymizationArray = $privacyArray[self::anonymizationNode];
                $isAnonymized = $anonymizationArray!=='' && !array_key_exists(self::anonymizationNo,$anonymizationArray); // also true if not yet answered
                if (array_key_exists(self::personalKeepNode,$privacyArray)) {
                    $personalKeep = $privacyArray[self::personalKeepNode];
                    $isPurposeReuse = $personalKeep!=='' && array_key_exists(self::personalKeepReuse,$personalKeep);
                }
                if ($isAnonymized) { // personal research data are anonymized
                    $isStorageDelete = ($privacyArray[self::storageNode][self::chosen] ?? '')===self::storageDelete;
                    if ($isStorageDelete) { // original data are deleted immediately
                        $personal = 'immediately';
                    }
                    else { // original data are kept
                        $personal = $isPurposeReuse ? 'purpose' : 'keep';
                    }
                }
                else  { // no anonymization of personal research data
                    $personal = $isPurposeReuse ? 'purpose' : 'personal';
                }
            }
            else { // research data are anonymous
                $isMarkingPersonal = false;
                foreach ([self::markingNode,self::markingNode.self::markingSuffix] as $marking) {
                    $tempArray = $privacyArray[$marking] ?? [];
                    $isMarkingPersonal = $isMarkingPersonal || ($tempArray[self::chosen] ?? '')===self::markingName || in_array($tempArray[self::codePersonal] ?? '',self::markingDataResearchTypes);
                }
                $personal = $isMarkingPersonal ? 'marking' : 'anonymous';
            }
        }
        return ['isAnonymized' => $isAnonymized, 'isPurposeReuse' => $isPurposeReuse, self::dataReuseNode => $isTool || $isAnonymous ? 'tool' : 'noTool', 'personal' => $personal ];
    }

    /** Checks if there are multiple studies, groups, or measure time points.
     * @param SimpleXMLElement $appNode root node of the xml-document
     * @return bool true if there are multiple studies, groups, or measure time points, false otherwise
     */
    protected function getMultiStudyGroupMeasure(SimpleXMLElement $appNode): bool {
        $studyArray = $this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode];
        $groupArray = $studyArray[self::groupNode] ?? []; // 'group' node does only exist if there is only one study
        if (array_key_exists(self::nameNode,$studyArray) && array_key_exists(self::nameNode,$groupArray) && array_key_exists(self::groupsNode,$groupArray[self::measureTimePointNode])) {
            return false;
        }
        return true;
    }

    /** Clones an xml-document and gets a measure time point node.
     * @param SimpleXMLElement $appNode document to be cloned
     * @param array $routeParams route parameter
     * @return array 0: cloned document, 1: cloned measure time point
     */
    protected function getClonedMeasureTimePoint(SimpleXMLElement $appNode, array $routeParams): array {
        $appNodeNew = $this->cloneNode($appNode);
        return [$appNodeNew,$this->getMeasureTimePointNode($appNodeNew,$routeParams)];
    }

    /** Clones an xml-element.
     * @param SimpleXMLElement $element element to be cloned
     * @return SimpleXMLElement the cloned element
     */
    protected function cloneNode(SimpleXMLElement $element): SimpleXMLElement {
        return simplexml_import_dom(dom_import_simplexml($element)->cloneNode(true));
    }

    /** Gets the project title for the study information.
     * @param Session $session current session
     * @return string project title for the study information
     */
    protected function getProjectTitleParticipants(Session $session): string {
        $coreDataArray = $this->xmlToArray($this->getXMLfromSession($session,getRecent: true)->{self::appDataNodeName}->{self::coreDataNode});
        return $coreDataArray[self::projectTitleParticipation][self::descriptionNode] ?? $coreDataArray[self::projectTitle];
    }

    /** Converts all children of $node to array elements where the node name is the key and the node value is the value.
     * @param SimpleXMLElement $element node whose children are converted to an array
     * @return array of the node
     */
    protected function xmlToArray(SimpleXMLElement $element): array {
        return $this->convertEmptyArray(json_decode(json_encode($element),true));
    }

    /** Gets the current measure time point node.
     * @param Request|SimpleXMLElement|bool $appNode Either the request or the root node of the xml-document.
     * @param array $params array containing the IDs for the different levels
     * @return SimpleXMLElement|null measure time point node or null if either $appNode is false or a node cannot be found
     */
    protected function getMeasureTimePointNode(Request|SimpleXMLElement|bool $appNode, array $params): ?SimpleXMLElement {
        if ($appNode instanceof Request) {
            $appNode = $this->getXMLfromSession($appNode->getSession());
        }
        return !$appNode ? null : $appNode->{self::projectdetailsNodeName}->{self::studyNode}[$params[self::studyID]-1]->{self::groupNode}[$params[self::groupID]-1]->{self::measureTimePointNode}[$params[self::measureID]-1] ?? null;
    }

    /** Creates an empty DomDocument.
     * @return DOMDocument empty DomDocument
     */
    protected function createDOM(): DOMDocument {
        $doc = new DOMDocument('1.0', 'utf-8'); // create new xml-file to keep formatting
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        return $doc;
    }

    /** Get either the most or least recent xml-document from the session.
     * @param Session $session session where the document is stored
     * @param bool $getFirst if true, the oldest document is returned, i.e., the state when the page was entered, otherwise the most recent one
     * @param bool $getRecent if true, the most recent document including changes on other pages is returned, if it exists
     * @param bool $setRecent if true and no 'documentRecent' key exists in the session, it will be created with the same value as the 'document' key
     * @return SimpleXMLElement|bool xml-document or false if no xml-document exists (i.e., if the language is changed on the main page before an application was opened)
     */
    protected function getXMLfromSession(Session $session, bool $getFirst = false, bool $getRecent = false, bool $setRecent = false): SimpleXMLElement|bool {
        $docs = null;
        $docsRecent = $session->get(self::docNameRecent);
        if ($getRecent) {
            $docs = $docsRecent;
        }
        if ($docs===null) {
            $docs = $session->get(self::docName);
        }
        if ($setRecent && $docsRecent===null) { // needed to have the same number of documents in docName and docNameRecent
            $session->set(self::docNameRecent,$docs);
        }
        return $docs===null ? false : simplexml_load_string($docs[!$getFirst ? count($docs)-1 : 0]);
    }

    /** Gets the data from a form and adds it to an xml-document.
     * @param FormInterface $form submitted form
     * @param SimpleXMLElement $element node where the form data is added
     * @return mixed data from the form
     */
    protected function getDataAndConvert(FormInterface $form, SimpleXMLElement $element): mixed {
        $data = $form->getData();
        $this->arrayToXml($data, $element);
        return $data;
    }

    /** Checks if the page should be left after submission. The following route must be the first row in the 'submitDummy' form element. If the page is left, the 'documentRecent' key in the current session is removes
     * @param FormInterface $form submitted form
     * @param Session $session current session
     * @param string $route current page
     * @return bool true if the page should be left, false otherwise
     */
    protected function getLeavePage(FormInterface $form, Session $session, string $route): bool {
        $submitDummy = $form->get(self::submitDummy)->getData();
        if (str_contains($submitDummy,self::language)) { // one of the language forms was clicked
            return false;
        }
        $nextRoute = explode("\n",$submitDummy);
        $nextRoute = trim($nextRoute[str_contains($nextRoute[0],self::preview) ? 1 : 0]); // first entry may be the position of the preview scrollbar
        $isLeave = substr($nextRoute,4)!==$route && ($submitDummy==='' || !in_array($nextRoute,['undo','download','documents',''])); // route in submitDummy starts with 'app_'
        if ($isLeave) {
            $session->remove(self::docNameRecent);
        }
        return $isLeave;
    }

    /** Each element in the array whose value is an empty array is converted to an empty string.
     * @param $array array array whose elements are checked
     * @return array the input array where empty array values are empty strings
     */
    private function convertEmptyArray(array $array): array {
        foreach ($array as $key => $value) {
            if ($value==[]) {
                $array[$key] = '';
            }
            elseif (is_array($value)) {
                $array[$key] =  $this->convertEmptyArray($value);
            }
        }
        return $array;
    }

    /** Checks if the array has a key '0' and if not, creates a new array with key '0' and the input array as the value.
     * @param array $array array whose keys are checked
     * @return array array with '0' as the first key
     */
    protected function addZeroIndex(array $array): array {
        return array_key_exists('0',$array) ? $array : [0 => $array];
    }

    /** Increases all values of the passed array by 1.
     * @param array $array array whose values are increased
     * @return array $array with all values increased by 1
     */
    protected function increaseArrayValues(array $array): array {
        return array_map(function ($value) {return ++$value;},$array);
    }

    /** Creates a string with the route IDs that can be passed to the setDummySubmit() method in base_controller.
     * @param array $routeIDs route IDs. Each value will be decreased by 1
     * @return string string with route IDs
     */
    protected function createRouteIDs(array $routeIDs): string {
        return '{&quot;studyID&quot;:&quot;'.($routeIDs[self::studyNode]).'&quot;,&quot;groupID&quot;:&quot;'.($routeIDs[self::groupNode]).'&quot;,&quot;measureID&quot;:&quot;'.($routeIDs[self::measureTimePointNode]).'&quot;}';
    }

    /** Translates a string using the 'pdf' domain.
     * @param string $string String to be translated. Must be a valid key in the translation file
     * @return string the translated string
     */
    protected function translateStringPDF(string $string, array $parameters = []): string {
        return self::$translator->trans($string,$parameters,'pdf');
    }

    // methods

    /** Adds a page to the array indicating that inputs were made on that page.
     * @param string $translationPrefix prefix for the string that is added
     * @param string $inputPage node name of the page for which a string is added
     * @param array $pages array with keys 'pageNames' and 'pageInputs'
     * @param array $parameters parameters for the translation
     */
    protected function addInputPage(string $translationPrefix, string $inputPage, array &$pages, array $parameters = []): void {
        $count = count($pages[self::pageNames]);
        $pages[self::pageNames][$count] = '„'.$this->translateString($translationPrefix.$inputPage,$parameters).'“';
        $pages[self::pageInputs][$count] = $this->translateString('multiple.inputs.'.$inputPage,$parameters);
    }

    /** Gets all tasks from all contributors and sets them as selected in the projectdetails contributor nodes.
     * @param Request $request
     * @param SimpleXMLElement $appNode top node of the application file
     * @return void
     */
    protected function setProjectdetailsContributor(Request $request, SimpleXMLElement $appNode): void {
        $contributorsArray = $this->getContributors($request->getSession());
        $taskIndices = [];
        foreach (self::tasksNodes as $task) {
            $indices = [];
            foreach ($contributorsArray as $index => $contributor) {
                if (array_key_exists($task,$contributor[self::taskNode])) {
                    $indices = array_merge($indices,[$index]);
                }
            }
            $taskIndices[$task] = implode(',',$indices);
        }
        $this->arrayToXml($taskIndices,$appNode->{self::projectdetailsNodeName}->{self::studyNode}->{self::groupNode}->{self::measureTimePointNode}->{self::contributorNode});
    }

    /** Resets the document and eventually the contributors arrays in the session such that only the most recent one remains.
     * @param Session $session current session
     * @param bool $resetContributors true if the contributors arrays should also be reset, false otherwise
     * @return void
     */
    protected function resetDocContributors(Session $session, bool $resetContributors = false): void {
        foreach (array_merge([self::docName],$resetContributors ? [self::contributorsSessionName] : []) as $type) {
            $allElements = $session->get($type);
            $session->set($type,[$allElements[count($allElements)-1]]);
        }
    }

    /** Removes the temporary files.
     * @param Session $session current session
     * @return void
     */
    private function removeTempFiles(Session $session): void {
        $sessionID = $session->getId();
        $prefix = self::tempFolder.'/';
        $suffix = $sessionID.'.pdf';
        if (self::$isCompleteForm) {
            unlink($prefix.'complete'.$suffix);
        }
        unlink($prefix.'application'.$suffix);
        $prefix .= 'participation';
        foreach ($session->get(self::pdfParticipationArray) as $ids) {
            unlink($this->concatIDs($ids,$prefix,$suffix));
        }

    }

    // methods involving xml

    /** Sets the string for the first inclusion criterion.
     * @param SimpleXMLElement $groups groups node
     * @param string $locale locale to be used
     * @return void
     */
    protected function setFirstInclusion(SimpleXMLElement $groups, string $locale): void {
        $groupsArray = $this->xmlToArray($groups);
        $minAge = $groupsArray[self::minAge];
        $minAge = (int) ($minAge!=='' ? $minAge : -2);
        $maxAge = (int)($groupsArray[self::maxAge]); // will be 0 if empty
        $maxAge = $maxAge===0 ? -2 : $maxAge;
        $limit = $maxAge===-1 ? 'noUpperLimit' : ($minAge===$maxAge || in_array(-2,[$minAge,$maxAge]) ? 'sameLimit' : 'limits');
        $firstInclusion = $this->getFirstInclusion($this->getAddressee($groupsArray),$limit,$minAge,$maxAge,$locale);
        if ($minAge===-2) {
            $firstInclusion = substr_replace($firstInclusion,$maxAge>0 ? $maxAge : 'X' ,strpos($firstInclusion,'-2'),2);
        }
        $groups->{self::criteriaNode}->{self::criteriaIncludeNode}->{self::criteriaNode}->{self::criteriaIncludeNode.'0'} = $firstInclusion;
    }

    /** Adds the legal nodes to the xml-document. Which nodes are added depends on the consent, the location and the loan question.
     * @param SimpleXMLElement $legalNode node where the legal nodes get added
     * @param array $measureArray array containing the current measure time point
     * @return void
     */
    protected function addLegalNodes(SimpleXMLElement $legalNode, array $measureArray): void {
        if ($measureArray[self::informationNode][self::chosen]==='0') { // information is pre
            $measuresArray = $measureArray[self::measuresNode];
            $loanArray = $measuresArray[self::loanNode];
            $isConsent = $this->getAnyConsent($measureArray[self::consentNode][self::consentNode][self::chosen]);
            $isLocationPresence = $isConsent && !in_array($measuresArray[self::locationNode][self::chosen],[self::locationOnline,'']);
            $legalNodes = [];
            if ($legalNode->{self::liabilityNode}->getName()==='' && $isConsent) { // consent is given
                $legalNodes = [self::liabilityNode,self::insuranceNode];
            }
            if ($legalNode->{self::apparatusNode}->getName()==='' && ($isLocationPresence || $isConsent && $loanArray[self::chosen]==='0' || $this->getTemplateChoice($this->getLoanReceipt($loanArray)))) { // in presence or loan with consent or with receipt
                $legalNodes[] = self::apparatusNode;
            }
            if ($legalNode->{self::insuranceWayNode}->getName()==='' && $isLocationPresence) { // consent is given and in presence
                $legalNodes[] = self::insuranceWayNode;
            }
            foreach ($legalNodes as $type) {
                $this->addChosenNode($legalNode,$type);
            }
        }
    }

    /** Adds or sets the attribute to the root node containing the tool version.
     * @param SimpleXMLElement $xml xml-file
     * @return void
     */
    protected function setToolVersion(SimpleXMLElement $xml): void {
        if (!isset($xml->attributes()[self::toolVersionAttr])) {
            $xml->addAttribute(self::toolVersionAttr,self::toolVersion);
        }
        else {
            dom_import_simplexml($xml)->setAttribute(self::toolVersionAttr,self::toolVersion);
        }
    }

    /** Adds an array to an xml-document. First, all children of the element are removed. Then, for each key in $array, a child with the name of the key is added if the key is not equal to 'language'. If the value itself is an array, the method is called recursively with the value as the new array. Otherwise, the content of the node is set to the value.
     * @param array $array array that is added to the xml-document
     * @param SimpleXMLElement $element node where the children get appended
     * @return void
     */
    protected function arrayToXml(array $array, SimpleXMLElement $element): void {
        $this->removeAllChildNodes($element);
        foreach ($array as $key => $value) {
            if ($key!==self::language) {
                $element->addChild($key);
                if (is_array($value)) {
                    $this->arrayToXml($value,$element->$key);
                }
                else {
                    $element->$key = $value;
                }
            }
        }
    }

    /** Creates an xml-element with the name \$name, inserts it before \$element and optionally adds children to the newly created element.
     * @param string $name name of the new element
     * @param SimpleXMLElement $element element where the new element gets inserted before
     * @param array $children children to be added to the new element
     * @return void
     */
    protected function insertElementBefore(string $name, SimpleXMLElement $element, array $children = []): void {
        try {
            $dom = dom_import_simplexml($element);
            $ownerDoc = $dom->ownerDocument;
            $newNode = $dom->parentNode->insertBefore($ownerDoc->createElement($name), $dom);
            foreach ($children as $child) {
                $newNode->appendChild($ownerDoc->createElement($child));
            }
        } catch (\DOMException $e) {
        }
    }

    /** Checks if an xml-element exists and if so, removes it.
     * @param string $name name of the element to be removed
     * @param SimpleXMLElement $element the parent element of the element to be removed
     * @return void
     */
    protected function removeElement(string $name, SimpleXMLElement $element): void {
        $dom = dom_import_simplexml($element);
        if ($this->checkElement($name,$element)) {
            $dom->removeChild(dom_import_simplexml($element->{$name}));
        }
    }

    /** Checks if an xml-element exists.
     * @param string $name name of the element
     * @param SimpleXMLElement $element parent element
     * @return bool true if element has a child called name, false otherwise
     */
    protected function checkElement(string $name, SimpleXMLElement $element): bool {
        return $element->{$name}->getName()!=='';
    }

    /** For each value in \$nodes, a child of \$element with the same name is created.
     * @param SimpleXMLElement $element node where the children get appended
     * @param array $nodeNames names of the children
     * @return void
     */
    protected function addChildNodes(SimpleXMLElement $element, array $nodeNames): void {
        foreach ($nodeNames as $name) {
            $element->addChild($name);
        }
    }

    /** Removes all child nodes from the element.
     * @param SimpleXMLElement $element Node whose children are removed
     * @return void
     */
    protected function removeAllChildNodes(SimpleXMLElement $element): void {
        $domNode = dom_import_simplexml($element);
        while ($domNode->hasChildNodes()) {
            $domNode->removeChild($domNode->firstChild);
        }
    }

    /** Saves an xml-document in the session.
     * @param Session $session current session
     * @param string $key xml-document that will be saved
     * @param SimpleXMLElement $element session key where the document will be saved
     * @return void
     */
    protected function saveDocumentInSession(Session $session, string $key, SimpleXMLElement $element): void {
        $session->set($key,array_merge($session->get($key) ?? [],[$element->asXML()]));
    }
}