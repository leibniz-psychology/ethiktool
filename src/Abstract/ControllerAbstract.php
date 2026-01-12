<?php

namespace App\Abstract;

use App\Classes\CheckDocClass;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Main\CompleteFormTrait;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use DateTime;
use DOMDocument;
use Exception;
use GravityMedia\Ghostscript\Ghostscript;
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
    private const routeOrder = ['app_coreData','app_votes','app_medicine','app_summary','app_contributors','app_landing','app_groups','app_information','app_informationII','app_consent','app_measures','app_burdensRisks','app_compensation','app_texts','app_informationIII','app_legal','app_dataPrivacy','app_dataReuse','app_contributor']; // must equal the route names
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
    protected const customPDForder = [self::privacyNode, 'begun', self::informationNode, self::informationIINode, self::measuresNode, self::interventionsNode, self::otherSourcesNode]; // order of custom PDFs
    public const loadInput = 'loadInput'; // form element that holds the loaded xml-file; needed in TypeAbstract, therefore public
    protected const subPages = 'subPages';
    protected const label = 'label';
    protected const route = 'route';
    protected const routeIDs = 'routeIDs';
    protected const error = 'error';
    protected const tempFolder = 'tmpFiles'; // name of folder where PDFs will be temporarily saved if the complete proposal is created. Must be equal to the name in knp_snappy.yaml
    protected const newForm = 'newForm'; // name of session variable indicating that a new proposal was created successfully
    protected const pdfLoad = 'pdfLoadFailure'; // name of session variable indicating that a custom pdf could not be added
    protected const xmlLoad = 'xmlFailure'; // name of session variable indicating that the xml could not be loaded
    protected const loadSuccess = 'loadSuccess'; // name of session variable indicating the xml was successfully loaded
    protected const errorModal = 'errorMessage'; // name of session variable indicating that an error occurred and the user was redirected to the main page
    protected const preview = 'preview'; // name of the session variable indicating the position of the preview
    protected const quit = 'quit'; // name of session variable indicating that the proposal should be saved before quitting the tool
    protected const pageErrors = 'pageErrors'; // name of twig variable containing the errors on a single page
    protected const dummyString = 'dummyString'; // dummy string used as a placeholder if multiple elements are listed separated by a comma except for the last two elements
    private const answerYes = 'yes'; // answer for brief report (same for following three variables)
    private const answerNo = 'no';
    private const answerUnclear = 'unclear';
    private const answerRestricted = 'restricted';
    private Fpdi $fpdi; // used for merging PDFs
    private string $failureName;
    private PdfCollection $pdfParticipation;
    protected static bool $markInput; // true if user input should be marked in pdf

    public function __construct(TranslatorInterface $translator)
    {
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
    protected function createFormAndHandleSubmit(string $type, Request $request, array $subNodeNames, array $parameters = [], array $options = []): Response
    {
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
    protected function createFormAndHandleRequest(string $type, ?array $data, Request $request, array $options = []): FormInterface
    {
        $session = $request->getSession();
        $committeeParams = $session->get(self::committeeParams) ?? [];
        $options = array_merge($options,$committeeParams,[self::committeeParams => $committeeParams]);
        TypeAbstract::setReviewProcess($session->get(self::reviewProcess) ?? '');
        TypeAbstract::setPage(lcfirst(str_replace('Type','',substr($type,strrpos($type,"\\")+1))));

        return $this->createForm($type,$data,$options)->handleRequest($request);
    }

    /** Sets parameters that are needed on most pages for a view that gets rendered.
     * @param Request $request current Request
     * @param FormInterface $form form to be rendered
     * @param array $parameters array where the parameters are added. Keys that already exist in this array are not overwritten
     * @param string $pageTitle if not an empty string, 'pageTitle' and 'preview' will be added
     * @param bool $addProjectdetails if true, getProjectdetailsParameters() will be called
     * @param bool $addErrors if true, 'pageErrors' will be added. May only be true if $pageTitle is not an empty string
     * @return array parameters  for a view
     */
    protected function setRenderParameters(Request $request, FormInterface $form, array $parameters = [], string $pageTitle = '', bool $addProjectdetails = false, bool $addErrors = true): array
    {
        $session = $request->getSession();
        $committeeParams = $session->get(self::committeeParams) ?? [self::committeeType => 'noCommittee', self::toolVersionAttr => self::toolVersion];
        try { // set times for maintenance messages
            $date = $this->getCurrentTime();
            $timeString = strtotime($date->format('H:i:s'));
            $date = $date->format('l')==='Monday' ? ($timeString>=strtotime('7:30:00') ? ($timeString<strtotime('8:00:00') ? 'before' : ($timeString<strtotime('8:30:00') ? 'during' : '')) : '') : '';
        } catch (\Throwable) {
            return [];
        }
        $returnArray = array_merge($committeeParams,
            [self::content => $form,
             'isUpdateTime' => $date,
             self::committeeParams => $committeeParams]);
        if ($pageTitle!=='') {
            $returnArray[self::pageTitle] = $pageTitle;
            $returnArray[self::preview] = (int) ($session->get(self::preview) ?? 0);
            $session->set(self::preview,0); // if the page is reloaded, go to the top of the preview
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
    private function getProjectdetailsParameters(Request $request): array
    {
        $appNode = $this->getXMLfromSession($request->getSession());
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
        $returnArray[self::reviewProcess] = $this->getCurrentReviewProcess($appNode);
        return $returnArray;
    }

    /** Saves the document if other form elements than the language are submitted and redirects to the same page or to another page.
     * @param Request $request
     * @param SimpleXMLElement|bool $appNode xml-document that will be saved or false if no xml-document exists (i.e., if the language is changed on the main page before an application was opened)
     * @param SimpleXMLElement|null $appNodeNew if not null, the document to be saved in the 'documentRecent' session key
     * @return Response
     */
    protected function saveDocumentAndRedirect(Request $request, SimpleXMLElement|bool $appNode, ?SimpleXMLElement $appNodeNew = null): Response
    {
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
                if ($submitDummy==='quitModalButton') { // save file before quit
                    $session->set(self::quit,'download');
                }
                return $this->redirectToRoute('app_quit');
            } else {
                $loadInput = $request->files->all()[$nodeName][self::loadInput] ?? [];
                $isDownload = str_contains($submitDummy, 'download');
                $oldLanguage = $request->getLocale();
                if ($loadInput!==[]) { // form was loaded
                    try {
                        $xmlString = file_get_contents($loadInput->getRealPath());
                        $xml = simplexml_load_string($xmlString);
                        $attributes = $xml->attributes();
                        $toolVersion = (string) ($attributes[self::toolVersionAttr] ?? '');
                        if ($xml->getName()!=='Application' || // root node must be 'Application'
                            count($attributes)!==1 || // root node must have exactly one attribute 'toolVersion'
                            $toolVersion==='' || !preg_match("/^([0-9]+).([0-9]+).([0-9]+)$/", $toolVersion) || // tool version must be 'X.Y.Z'
                            str_contains($xmlString,'<script') || str_contains($xmlString,'&lt;script')) { // xml must not contain a starting 'script' tag
                            $session->set(self::xmlLoad,'');
                            return $this->redirectToRoute('app_main');
                        } else {
                            $xmlArray = $this->xmlToArray($xml);
                            unset($xmlArray['@attributes']); // attribute is checked separately
                            foreach ($xmlArray as $key => $value) {
                                $xmlArray[$key] = $this->replaceOpeningTag($value);
                            }
                            $this->arrayToXml($xmlArray,$xml);
                            // set contributors and projectdetails nodes to avoid numbers as tags in case there are multiple nodes with the same name
                            $contributorsNode = $xml->{self::contributorsNodeName};
                            $this->removeAllChildNodes($contributorsNode);
                            foreach ($this->getContributorsArray($xmlArray) as $contributor) {
                                $this->arrayToXml($contributor,$contributorsNode->addChild(self::contributorNode));
                            }
                            $projectdetailsNode = $xml->{self::projectdetailsNodeName};
                            $this->removeAllChildNodes($projectdetailsNode);
                            foreach ($this->addZeroIndex($xmlArray[self::projectdetailsNodeName][self::studyNode]) as $study) {
                                $studyNode = $projectdetailsNode->addChild(self::studyNode);
                                $studyNode->addChild(self::nameNode,$study[self::nameNode]);
                                foreach ($this->addZeroIndex($study[self::groupNode]) as $group) {
                                    $groupNode = $studyNode->addChild(self::groupNode);
                                    $groupNode->addChild(self::nameNode,$group[self::nameNode]);
                                    foreach ($this->addZeroIndex($group[self::measureTimePointNode]) as $measure) {
                                        $this->arrayToXml($measure,$groupNode->addChild(self::measureTimePointNode));
                                    }
                                }
                            }
                        }
                        $loadedVersion = $this->getToolVersion($xml);
                        $this->updateXML($request,$xml);
                        $xmlArray = $this->xmlToArray($xml);
                        $isBefore2 = str_starts_with($loadedVersion,'2');
                        if (!$isBefore2) {
                            $session->set('updateProcess',true); // used in core data to check if first visit after update
                        }
                        $session->set(self::reviewProcess,$isBefore2 ? $this->getCurrentReviewProcess($xmlArray) : self::reviewFullDocs); // if loaded file is before version 2.0.0, set fullDocs to keep all inputs. Needs to be set before getErrors() is called
                        $this->setCommittee($session, $xmlArray[self::committee], $oldLanguage);
                        $session->set(self::fileName, str_replace('.xml', '', $loadInput->getClientOriginalName()));
                        $session->set(self::docName, [$xml->asXML()]);
                        $session->set(self::contributorsSessionName, [0 => $this->getContributorsArray($xmlArray)]);
                        $session->set(self::loadSuccess,['isMain' => $this->getStringFromBool($curRoute==='app_main'), 'isMajor' => str_starts_with($loadedVersion,'1')]);
                        if ($this->getErrors($request,element: $xml)==='') { // if the file is invalid, an empty string is returned
                            $session->set(self::xmlLoad,'');
                        }
                    } catch (\Throwable) { // xml-file could not be loaded
                        $session->set(self::xmlLoad, '');
                    }
                    return $this->redirectToRoute('app_main');
                } elseif ($isDownload || $submitDummy==='finish') { // xml-file or complete proposal should be downloaded
                    return $this->getDownloadResponse($session, $isDownload, $request);
                } else {
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
                            // set committee params and first inclusion criterion
                            if ($appNode) {
                                $this->setCommittee($session, $session->get(self::committeeParams)[self::committeeType] ?? '', $language);
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
                                $this->saveDocumentInSession($session,self::docName,$appNode); // if language has changed, page will be reloaded, i.e., internal documents will be reset
                                if ($hasAppNodeNew) {
                                    $this->saveDocumentInSession($session, self::docNameRecent, $appNodeNew);
                                }
                            }
                        }
                        return $this->redirectToRoute($curRoute,array_merge($routeParams,['_locale' => $language]));
                    }
                    if (!(str_contains($submitDummy, 'undo') || str_contains($submitDummy, 'documents'))) { // page contains form elements other than the language
                        if ($appNode) {
                            $this->saveDocumentInSession($session,self::docName,$appNode);
                            if ($hasAppNodeNew) {
                                $this->saveDocumentInSession($session, self::docNameRecent, $appNodeNew);
                            }
                        }
                    }
                    $isNext = str_contains($submitDummy, 'nextPage');
                    $isPrevious = str_contains($submitDummy, 'previousPage');
                    if ($isNext && $isPrevious) { // if both buttons are clicked immediately after one another, only keep 'previous page' in case it happened on the overview page of a measure time point
                        $isNext = false;
                    }
                    if (str_contains($submitDummy, 'backToMain') || str_contains($submitDummy,'header')) { // 'back to Main menu' or the link in the header was clicked. In case of 'backToMain': must equal the name of the button in twig
                        if ($appNode) { // if the link in the header was clicked, $appNode may be false
                            $this->resetDocContributors($session, $isCoreDataContributors);
                        }
                        return $this->redirectToRoute('app_main');
                    } elseif ($isNext || $isPrevious) { // 'next page' or 'previous page' was clicked
                        $this->resetDocContributors($session, $isCoreDataContributors);
                        if ($curRoute==='app_landing') {
                            $landingArray = $session->get(self::landing);
                            if (($landingArray['page'] ?? '')===self::appDataNodeName) { // app data overview, only 'next page' is enabled
                                $nextRoute = 'app_coreData';
                            } else { // one of the projectdetails overviews
                                $isPagesOverview = array_key_exists(self::measureID,$landingArray); // true if overview of pages of one measure time point
                                $isMeasureTimePointOverview = !$isPagesOverview && array_key_exists(self::groupID,$landingArray); // true if overview of measure time points
                                $isGroupOverview = !$isMeasureTimePointOverview && array_key_exists(self::studyID,$landingArray); // true if overview of groups
                                if ($isNext) {
                                    if ($isPagesOverview) {
                                        $nextRoute = 'app_groups';
                                        unset($landingArray['page']);
                                    } else {
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
                                } else { // previous page
                                    if (array_key_exists(self::studyID,$landingArray)) { // overview of groups, measure time points or one measure time point
                                        $nextRoute = 'app_landing';
                                        unset($landingArray[$isPagesOverview ? self::measureID : ($isMeasureTimePointOverview ? self::groupID : ($isGroupOverview ? self::studyID : ''))]); // redirect to previous level
                                        $session->set(self::landing, $landingArray);
                                    } else { // overview of studies
                                        $nextRoute = 'app_contributors';
                                    }
                                }
                            }
                        } elseif ($isCoreData && $isPrevious) {
                            $nextRoute = 'app_landing';
                            $session->set(self::landing, ['page' => self::appDataNodeName]);
                        } else { // neither landing nor core data and previous
                            $addVal = $isNext ? 1 : -1;
                            $curRouteIndex = array_search($curRoute, self::routeOrder); // can not be the index of app_landing at this point
                            $nextRoute = self::routeOrder[$curRouteIndex+$addVal] ?? '';
                            $isContributor = $curRoute==='app_contributor';
                            $isLastPage = $isContributor;
                            $isGroupsPrevious = $curRoute==='app_groups' && $isPrevious;
                            $hasNotMultiple = !$this->getMultiStudyGroupMeasure($appNode);
                            if (!$isGroupsPrevious && !$isContributor && $curRouteIndex>array_search('app_landing', self::routeOrder)) { // current route is a projectdetails page
                                $measureArray = $this->xmlToArray($this->getMeasureTimePointNode($appNode, $routeParams)); // time point of the currently active page
                                while ($nextRoute!=='' && $measureArray[substr($nextRoute, 4)]==='') { // next page is not active
                                    $curRouteIndex += $addVal;
                                    $nextRoute = self::routeOrder[$curRouteIndex] ?? '';
                                }
                                if ($nextRoute==='app_contributor' && $hasNotMultiple) { // if only one time point exists, contributor page has content, but is not active
                                    $nextRoute = '';
                                }
                                $isLastPage = $nextRoute===''; // true if current route is last active page on current measure time point
                            }
                            $isLastPageNext = $isNext && $isLastPage;
                            if ($isLastPageNext && $hasNotMultiple) { // last active page and only one measure time point
                                $nextRoute = 'app_checkDoc';
                            } elseif ($isNext && $isContributors) { // contributors
                                $nextRoute = 'app_landing';
                                $session->set(self::landing, ['page' => self::projectdetailsNodeName]);
                            } elseif ($isLastPageNext || $isGroupsPrevious) { // groups or last active page of current measure time point
                                $hasNextPrevious = false;
                                $newID = $routeParams[self::measureID]+$addVal;
                                $newRouteParams = array_merge($routeParams,[self::measureID => $newID]);
                                if ($newID>0 && $this->getMeasureTimePointNode($appNode, $newRouteParams)!==null) { // next/previous measure time point exists
                                    $hasNextPrevious = true;
                                } else {
                                    $studies = $this->addZeroIndex($this->xmlToArray($appNode)[self::projectdetailsNodeName][self::studyNode]);
                                    $newID = $routeParams[self::groupID]+$addVal;
                                    $newRouteParams = array_merge($routeParams,[self::groupID => $newID, self::measureID => 1]); // first measure time point of next/previous group
                                    if ($newID>0 && $this->getMeasureTimePointNode($appNode, $newRouteParams)!==null) { // a group exists before/after the current group
                                        $hasNextPrevious = true;
                                        if ($isPrevious) {
                                            $newRouteParams = array_merge($newRouteParams,[self::measureID => count($this->addZeroIndex($this->addZeroIndex($studies[$routeParams[self::studyID]-1][self::groupNode])[$newID-1][self::measureTimePointNode]))]); // last measure time point of previous group
                                        }
                                    } else {
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
                                if ($isNext) {
                                    $nextRoute = $hasNextPrevious ? 'app_groups' : 'app_checkDoc';
                                } elseif ($hasNextPrevious) { // groups and previous study / group / measure time point exists
                                    $measureTimePointArray = $this->xmlToArray($this->getMeasureTimePointNode($appNode,$newRouteParams)); // time point where the next page wil be opened
                                    $newIndex = count(self::routeOrder)-1;
                                    while ($measureTimePointArray[substr(self::routeOrder[$newIndex],4)]==='') { // page is not active
                                        --$newIndex;
                                    }
                                    $nextRoute = self::routeOrder[$newIndex];
                                } else {
                                    $nextRoute = 'app_landing';
                                }
                                $routeParams = $hasNextPrevious ? $newRouteParams : ($isPrevious ? [self::studyID => 1, self::groupID => 1, self::measureID => 1] : []);
                                if ($nextRoute==='app_landing') {
                                    $session->set(self::landing, array_merge($routeParams, ['page' => self::projectdetailsNodeName]));
                                }
                            } else { // $curRoute is a projectdetails subpage unlike groups (and 'previous' was clicked) and unlike the last page of the current measure time point
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
                    } else { // 'save', a link or 'undo' was clicked, the language was changed, or the complete proposal should be created
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
                                    $session->set(self::reviewProcess,$this->getCurrentReviewProcess($this->getXMLfromSession($session,getRecent: true)));
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
                        } elseif ($saveUndoDoc==='documents') { // pdf should be created
                            self::$savePDF = true;
                            return $this->forward('App\Controller\PDF\ApplicationController::createPDF');
                        } else {
                            if ($route!=='' && $route!==$curRoute) { // go to another page
                                $this->resetDocContributors($session, $isCoreDataContributors);
                            } elseif (!$hasAppNodeNew) { // on some pages, creation of appNodeNew depends also on the review process, i.e., it may be set, but not updated; therefore, remove 'docNameRecent' to always get the 'docName' appNode while still on the page
                                $session->remove(self::docNameRecent);
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
                                    } elseif (!array_key_exists($curKey, $routeParams)) {
                                        $routeParams[$curKey] = $curValue;
                                    }
                                    if (str_contains($curKey, 'page')) { // If a link is double-clicked, the route parameters may exist twice (or three times, if immediately after entering text in a text field), therefore, when adding the parameters, only add them once, as the first ones added are the actual ones. As soon as the first key does not contain 'ID', all relevant IDs were added
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
        } catch (\Throwable) {
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
     * @return array keys: label for the pages, values: array: names of the routes and route IDs, if applicable, and a bool indicating if any inconsistencies are on the page(s)
     * @throws Exception is any error is thrown in getDocumentCheck
     */
    protected function setSubMenu(string $page, ?Request $request = null, ?int $studyID = null, ?int $groupID = null, ?int $measureID = null, bool $cutName = true): array
    {
        if ($page===self::appDataNodeName) {
            $tempVal = 'pages.appData.';
            $tempArray = [];
            foreach ([self::coreDataNode,self::voteNode,self::medicine,self::summary] as $page) {
                $tempArray[] = [self::label => $this->translateString($tempVal.$page),self::route => 'app_'.$page,self::error => CheckDocClass::getDocumentCheck($request,$page)];
            }
            $returnArray = [self::label => $this->translateString($tempVal.'title'),self::route => 'app_landing',self::subPages => $tempArray, self::error => CheckDocClass::getDocumentCheck($request,self::appDataNodeName)];
        } else {
            $session = $request->getSession();
            $appNode = $this->getXMLfromSession($session);
            $studies = $this->addZeroIndex($this->xmlToArray($appNode->{self::projectdetailsNodeName})[self::studyNode]);
            if ($studyID===null) { // overview of studies
                $returnArray = $this->setOverview($request,$studies,self::studyNode,$cutName,[]);
            } else {
                $groups = $this->addZeroIndex($studies[$studyID][self::groupNode]);
                if ($groupID===null) { // overview of groups
                    $returnArray = $this->setOverview($request,$groups,self::groupNode,$cutName,[self::studyID => $studyID+1]);
                } else {
                    $measureTimePoints = $this->addZeroIndex($groups[$groupID][self::measureTimePointNode]);
                    if ($measureID===null) { // overview of measure time points
                        $returnArray = $this->setOverview($request,$measureTimePoints,self::measureTimePointNode,$cutName,[self::studyID => $studyID+1, self::groupID => $groupID+1]);
                    } else { // overview of one measure time point
                        $reviewProcess = $session->get(self::reviewProcess);
                        $hasDocs = in_array($reviewProcess,self::reviewDocs);
                        $prefix = 'pages.projectdetails.';
                        $measure = $measureTimePoints[$measureID];
                        $routeIDs = [self::studyID => $studyID+1, self::groupID => $groupID+1, self::measureID => $measureID+1];
                        $information = $this->getInformation($appNode,[self::studyID => $studyID+1, self::groupID => $groupID+1, self::measureID => $measureID+1]);
                        $isPre = $information===self::pre;
                        $sidebarSuffix = $cutName ? 'Sidebar' : ''; // use abbreviation for information pages only in sidebar
                        $returnArray = [];
                        foreach ([self::groupsNode,self::informationNode,self::informationIINode,self::consentNode,self::measuresNode,self::burdensRisksNode,self::compensationNode,self::textsNode,self::informationIIINode,self::legalNode,self::privacyNode,self::dataReuseNode,self::contributorNode] as $page) {
                            $route = match ($page) {
                                self::informationIINode => $this->getAddressee($measure[self::groupsNode])!==self::addresseeParticipants ? 'app_informationII' : '',
                                self::textsNode => $hasDocs && ($isPre || $information===self::post) ? 'app_texts' : '',
                                self::informationIIINode => $hasDocs && $this->getInformationIII($measure[self::informationNode]) ? 'app_informationIII' : '',
                                self::legalNode => $hasDocs && ($isPre && ($this->getAnyConsent($measure[self::consentNode]) || $this->getTemplateChoice($this->getLoanReceipt($measure[self::measuresNode][self::loanNode] ?? [])))) ? 'app_legal' : '',
                                self::privacyNode => in_array($reviewProcess,self::reviewTypePages[self::privacyNode]) ? 'app_dataPrivacy' : '',
                                self::dataReuseNode => in_array($reviewProcess,self::reviewTypePages[self::dataReuseNode]) ? 'app_dataReuse' : '',
                                self::contributorNode => $hasDocs && (count($studies)>1 || count($groups)>1 || count($measureTimePoints)>1) ? 'app_contributor' : '',
                                default => 'app_'.$page};
                            $returnArray[] =
                                [self::label => $this->translateString($prefix.$page.(str_contains($page,self::informationNode) ? $sidebarSuffix : '')),
                                 self::route => $route,
                                 self::routeIDs => $routeIDs,
                                 self::error => $route!=='' && CheckDocClass::getDocumentCheck($request,$page,routeIDs: $routeIDs)];
                        }
                    }
                }
            }
        }
        return $returnArray;
    }

    /** Sets the overview of studies, groups, or measure time points.
     * @param Request $request request
     * @param array $array array containing the subpages
     * @param string $nodeName type of subpages overview to create. Must equal 'study', 'group', or 'measureTimePoint'
     * @param bool $cutName if $nodeName equals 'study' or 'group' and if true, only the first 5 characters of the name are shown
     * @param array $routeIDs routeIDs
     * @return array keys: label for the pages, values: names of the routes
     * @throws Exception if an error occurs in getDocumentCheck
     */
    protected function setOverview(Request $request, array $array, string $nodeName, bool $cutName, array $routeIDs): array
    {
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
            $curRouteIDs = array_merge($routeIDs,[$loopID => $index+1]);
            $returnArray[] =
                [self::label => $this->translateString('projectdetails.headings.'.$nodeName).($multiple ? ' '.($index+1) : '').($name!=='' ? ($multiple ? ' (' : ' ').$name.($multiple ? ')' : '') : ''),
                 self::route => 'app_landing',
                 self::routeIDs => $curRouteIDs,
                 self::error => CheckDocClass::getDocumentCheck($request,self::projectdetailsNodeName,routeIDs: $curRouteIDs)];
        }
        return $returnArray;
    }

    /** Checks if any compensation is awarded by a certain type.
     * @param array|string $compensation array containing the compensation
     * @param string $type type of awarding to be checked. Defaults to 'code'
     * @return bool true if any compensation is awarded later by a certain type, false otherwise
     */
    protected function checkCompensationAwarding(array|string $compensation, string $type = 'code'): bool
    {
        $compensationTypes = $compensation[self::compensationTypeNode] ?? '';
        $isCompensationAwarding = false;
        $isLater = in_array($type,['code','name']);
        $deliverTypes = ['eMail','mail','phone'];
        if ($compensationTypes!=='' && !array_key_exists(self::compensationNo,$compensationTypes)) { // at least one type except 'no compensation' was selected
            foreach ($compensationTypes as $name => $value) {
                $awardingKey = $name.self::awardingNode;
                if ($name!==self::compensationOther && array_key_exists($awardingKey,$compensation)) {
                    $awarding = $compensation[$awardingKey];
                    $chosen = $awarding[self::chosen];
                    if ($isLater) {
                        $isCompensationAwarding = array_key_exists(self::laterTypesName,$awarding) && $awarding[self::laterTypesName]===$type;
                    } elseif (in_array($type,$deliverTypes)) {
                        $isCompensationAwarding = $chosen===self::awardingDeliver && $awarding[self::descriptionNode]===$type || ($awarding[self::lotteryStart] ?? '')===$type;
                    } else {
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
    protected function getLegalInput(array $inputArray, array $measureArray, bool $addApparatus = true): string
    {
        $isInput = false;
        $legalParams = array_fill_keys(self::legalTypes,'false'); // contains more keys than necessary
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
        if ($isInput) {
            $this->addInputPage('pages.projectdetails.',self::legalNode,$inputArray,$legalParams);
            if ($legalParams['hints']>1) {
                $lastIndex = count($inputArray[self::pageInputs])-1;
                $inputArray[self::pageInputs][$lastIndex] = $this->replaceString($inputArray[self::pageInputs][$lastIndex]);
            }
        }
        return $this->setInputHint($inputArray);
    }

    /** Checks for every key in \$inputs if the corresponding key in \$nodeArray is neither empty nor equals the value of the key in $inputs.
     * @param array|string $nodeArray Array where inputs are checked
     * @param array $inputs keys: keys in $nodeArray to be checked. values: values to be checked against
     * @return bool true if any of the checked elements is neither empty nor equals the respective $inputs value, false otherwise
     */
    protected function checkInput(array|string $nodeArray, array $inputs): bool
    {
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
    protected function setInputArray(): array
    {
        return [self::pageNames => [], self::pageInputs => []];
    }

    /** Sets the hint saying that inputs on other pages are deleted.
     * @param array $pages array with keys 'pageNames' and 'pageInputs'
     * @return string hint with inputs that are deleted
     */
    protected function setInputHint(array $pages): string
    {
        $count = count($pages[self::pageNames]);
        if ($count>0) {
            return $this->translateString('multiple.inputs.hint',['pages' => $count, 'page' => $this->replaceDummyString($pages[self::pageNames],useAnd: false), 'inputs' => str_replace(self::dummyString,', ',$this->replaceDummyString($pages[self::pageInputs],useAnd: false))]); // replace last 'dummyString' by the 'lastRespectively' value and the other ones by a comma
        }
        return '';
    }

    /** Replaces all occurrences of 'dummyString' with ', ' except for the last occurrence which will be replaced by calling replaceString(). If the input is an array, it will first be imploded with 'dummyString' as the separator.
     * @param string|array $input string where 'dummyString' will be replaced
     * @param string $replaceString string to replace the other occurrences. Defaults to ', '
     * @param string $replace translation key for the string to replace the last occurrence. Will use the 'pdf' domain
     * @param bool $useAnd if $replace is empty, the last occurrence will be replaced with 'and' if true, otherwise with 'respectively'
     * @return string input string with replaced strings
     */
    protected function replaceDummyString(string|array $input, string $replaceString = ', ', string $replace = '', bool $useAnd = true): string
    {
        if (is_array($input)) {
            $input = implode(self::dummyString,$input);
        }
        return str_replace(self::dummyString,$replaceString,$this->replaceString($input,self::dummyString,$replace!=='' ? $this->translateStringPDF($replace) : '',$useAnd));
    }

    /** Replaces either the first or last occurrence of a string in a string.
     * @param string $input string where the occurrence gets replaced
     * @param string $search string to be replaced. Defaults to a comma
     * @param string $replace string that should replace the occurrence
     * @param bool $useAnd if $replace is empty, the occurrence will be replaced with 'and' if true, otherwise with 'respectively'
     * @return string \$input with the last occurrence of \$search replaced or \$input if it does not contain \$search
     */
    protected function replaceString(string $input, string $search = ',', string $replace = '', bool $useAnd = true): string
    {
        return str_contains($input,$search) ? substr_replace($input,$replace==='' ? $this->translateString('multiple.inputs.'.($useAnd ? 'lastAnd' : 'lastRespectively')) : $replace, strrpos($input,$search),strlen($search)) : $input;
    }

    /** Checks whether a pre or post information is chosen by calling getInformationString. If $element is the request, the most recent document will be used.
     * @param Request|SimpleXMLElement $element Either the request or the root node of the xml-document.
     * @param array $routeParams if $element is a SimpleXMLElement, the route parameters
     * @return string 'pre' if pre information is chosen, 'post' if pre information is answered with no and post information is answered with yes, 'noPre' if pre information is answered with no and no post information is chosen, 'noPost' if pre and post information are answered with no, empty string otherwise (i.e., no pre information is chosen)
     */
    protected function getInformation(Request|SimpleXMLElement $element, array $routeParams = []): string
    {
        if ($element instanceof Request) {
            $routeParams = $element->get('_route_params');
            if ($this->getMeasureTimePointNode($element,$routeParams)===null) { // a page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
                return '';
            }
        }
        return $this->getInformationString($this->xmlToArray($this->getMeasureTimePointNode($element,[self::studyID => $routeParams[self::studyID], self::groupID => $routeParams[self::groupID], self::measureID => $routeParams[self::measureID]]))[self::informationNode]);
    }

    /** Creates the string for the con template.
     * @param array $measureArray array containing the information about the current measure time point
     * @param bool $addDescription if true, the entered text, if applicable, is added
     * @param bool $addNoTemplate if true, the sentence that no template could be created, if applicable, is added
     * @param array $routeParams if $addNoTemplate is true, the route parameters of the current measure time point
     * @param bool $addTemplate if true, the template sentence will be added regardless of the choice in texts
     * @param bool $markInput if true, custom text will be surrounded by a span-tag
     * @return string con template text
     */
    protected function getConTemplateText(array $measureArray, bool $addDescription = true, bool $addNoTemplate = false, array $routeParams = [], bool $addTemplate = false, bool $markInput = false): string
    {
        $information = $this->getInformationString($measureArray[self::informationNode]);
        $returnString = '';
        if (in_array($information,self::prePostArray)) { // information is given
            $conArray = $measureArray[self::textsNode][self::conNode] ?? '';
            if ($conArray!=='') {
                $isTemplate = $conArray[self::conTemplate]==='1';
                $burdensRisksArray = $measureArray[self::burdensRisksNode];
                $isBurdensRisks = $this->getBurdensRisks($burdensRisksArray);
                if ($isTemplate || $addTemplate) {
                    $translationPrefix = 'projectdetails.pages.texts.con.template.';
                    if (!$isBurdensRisks) { // add no burdens/risks
                        $returnString .= ' '.$this->translateString($translationPrefix.self::risksNode,[self::informationNode => $information, 'isFinding' => $this->getStringFromBool($burdensRisksArray[self::findingNode][self::chosen]==='0')]);
                    }
                    if ($addNoTemplate && $returnString==='') { // add sentence that no template could be created
                        $returnString = $this->translateString($translationPrefix.'noTemplate',['routeIDs' => '{&quot;'.self::studyID.'&quot;:'.'&quot;'.$routeParams[self::studyID].'&quot;, &quot;'.self::groupID.'&quot;:&quot;'.$routeParams[self::groupID].'&quot;, &quot;'.self::measureID.'&quot;:&quot;'.$routeParams[self::measureID].'&quot;}']);
                    }
                }
                if ($addDescription && (!$isTemplate || $isBurdensRisks)) { // add description
                    $returnString .= ' '.(array_key_exists(self::descriptionNode,$conArray) ? $this->addMarkInput($conArray[self::descriptionNode],$markInput) : '');
                }
            }
        }
        return trim($returnString);
    }

    /** Checks if burdens or risks except 'noBurdens' and 'noRisks' are selected.
     * @param array $burdensRisksArray array containing the burdens and risks information
     * @return bool true if burdens or risks are selected, false otherwise
     */
    protected function getBurdensRisks(array $burdensRisksArray): bool
    {
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
    protected function getBurdensOrRisks(array $burdensRisksArray, string $type): array
    {
        $tempArray = $burdensRisksArray[$type];
        if ($type!==self::burdensRisksContributorsNode) {
            $tempArray = $tempArray[$type.'Type'];
            if ($tempArray==='' || $tempArray==[]) { // depending on where the function is called, the 'Type' key can either be an empty string or an empty array if nothing was chosen yet
                return [false,false];
            }
            $isNo = array_key_exists($type===self::burdensNode ? self::noBurdens : self::noRisks,$tempArray);
            return [!$isNo,$isNo];
        } else { // burdens/risks for contributors
            $chosen = $tempArray[self::chosen];
            return [$chosen==='0',$chosen==='1'];
        }
    }

    /** Sets the positions for the applicant with and without qualification. Additionally, the positions for the supervisor are set and all positions are translated.
     * @param Session $session current session
     * @return array 0: positions without qualification, 1: positions with qualification, 2: positions for supervisor, 3: all positions translated
     */
    protected function setPositions(Session $session): array
    {
        $isNotStudent = !in_array($this->getCommitteeType($session),self::committeeStudent);
        $phdOption = [self::positionsPhd => ''];
        $studentOption = [self::positionsStudent => ''];
        $positionsTranslated = self::positionsTypes;
        foreach ($positionsTranslated as $position => $translation) {
            $positionsTranslated[$position] = $this->translateString($translation);
        }
        $positionsApplicant = array_diff_key(self::positionsTypes,$isNotStudent ? $studentOption : []);
        $positionsQualification = array_intersect_key($positionsApplicant,array_merge($phdOption,!$isNotStudent ? $studentOption : []));
        $positionsSupervisor = array_diff_key(self::positionsTypes,array_merge($studentOption,$this->xmlToArray($this->getXMLfromSession($session))[self::appDataNodeName][self::coreDataNode][self::applicant][self::position]===self::positionsPhd ? $phdOption : []));
        return [$positionsApplicant,$positionsQualification,$positionsSupervisor,$positionsTranslated];
    }

    /** Checks if the qualification question was answered with yes.
     * @param array $coreDataArray array containing the core data
     * @return bool true if qualification questions exists and was answered with yes, false otherwise
     */
    protected function getQualification(array $coreDataArray): bool
    {
        return ($coreDataArray[self::qualification] ?? '')=='0';
    }

    /** Creates a response for downloading a file.
     * @param Session $session current session
     * @param bool $isXML if true, the xml-file should be downloaded, a zip file containing the xml-file and pdf-files otherwise
     * @param Request|null $request if the complete proposal should be downloaded, the request
     * @param bool $getSecondLast if true, the second most recent xml-file will be downloaded, i.e., if the review process changes and the xml-file with the information before the change should be downloaded. Therefore, may only be true if $isXML is true
     * @return Response Response containing the download
     */
    protected function getDownloadResponse(Session $session, bool $isXML = true, Request $request = null, bool $getSecondLast = false): Response
    {
        $filename = $session->get(self::fileName);
        $filenameExt = $filename.'.xml';
        $filename .= '_';
        $xml = $this->createDOM();
        if ($isXML && $getSecondLast) {
            $tempArray = $session->get(self::docNameRecent); // must exist at this point
            $xmlToSave = simplexml_load_string($tempArray[count($tempArray)-2]);
        } else {
            $xmlToSave = $this->getXMLfromSession($session,getRecent: true);
        }
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
            $singleDocsName = $this->translateStringPDF('filenames.singleDocs').$curDate;
            $zip = new ZipArchive();
            $folderName = $filename.(self::$isCompleteForm ? $this->translateStringPDF('filenames.completeForm').$curDate : $singleDocsName);
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
                $applicationPrefix = $tempFolder.'application';
                if (self::$isCompleteForm) {
                    $applicationFilename = $applicationPrefix.'CompleteForm'.$sessionIDExt; // if other vote is answered with no, file is identical to 'application'
                    (new PdfCollection())->addPdf($applicationFilename,'1-'.($this->addPDF($applicationFilename)-1));
                    $files = $request->files->all()['complete_form'];
                    $this->addCustomPDF(self::voteNode,$files);
                    $this->addParticipationPDFs($session,true,$files);
                    // set meta data
                    $versionParam = [self::toolVersionAttr => self::toolVersion];
                    $this->fpdi->SetTitle($this->translateStringPDF('metadata.title'));
                    $this->fpdi->SetAuthor($this->translateStringPDF('metadata.author'));
                    $this->fpdi->SetCreator($this->translateStringPDF('metadata.version',$versionParam));
                    $this->fpdi->SetKeywords($this->translateStringPDF('metadata.keywords',array_merge($versionParam,['createTime' => $this->convertDate($currentDate,false)])));
                    // add to zip
                    $folderNameWithFilename = $folderName.$filename;
                    $zip->addFromString($folderNameWithFilename.$this->translateStringPDF('filenames.completeForm').$pdfExt, $this->fpdi->Output( $folderNameWithFilename.$this->translateStringPDF('filenames.completeForm').$pdfExt,PdfMerger::MODE_STRING));
                    $zip->addEmptyDir($singleDocsFolder);
                }
                $singleDocsFolder .= $filename;
                $this->fpdi = new Fpdi();
                $applicationFilename = $applicationPrefix.(!self::$isCompleteForm ? '' : 'SingleDocs').$sessionIDExt;
                $zip->addFromString($singleDocsFolder.$this->translateStringPDF('filenames.application').$pdfExt,(new PdfMerger(new Fpdi()))->merge((new PdfCollection())->addPdf($applicationFilename,'1-'.($this->addPDF($applicationFilename)-1)) ,PdfMerger::MODE_STRING));
                $this->addParticipationPDFs($session);
                $zip->addFromString($singleDocsFolder.$this->translateStringPDF('filenames.participation').$pdfExt,(new PdfMerger(new Fpdi()))->merge($this->pdfParticipation,PdfMerger::MODE_STRING)); // if single documents, with time, otherwise without
                $this->removeTempFiles($session);
            } catch (\Throwable) {
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
        } elseif ($session->has(self::quit)) { // prevent downloading again if on page 'quit' and page is reloaded
            $session->set(self::quit,'');
        }
        return $returnResponse;
    }

    /** Returns the current date with Berlin timezone.
     * @return DateTime current date
     */
    protected function getCurrentDate(): DateTime
    {
        try {
            return new DateTime('today',$this->getTimezone());
        } catch (\Throwable) {
            return new DateTime(); // without timezone
        }
    }

    /** Returns the current time with Berlin timezone.
     * @return DateTime current time
     */
    protected function getCurrentTime(): DateTime
    {
        try {
            return new DateTime('now',$this->getTimezone());
        } catch (\Throwable) {
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
    private function addParticipationPDFs(Session $session, bool $isCompleteForm = false, array $files = []): void
    { // placed here because is called by getDownloadedResponse() and calls addPDF()
        $sessionIDExt = $session->getId().'.pdf';
        $tempFolder = self::tempFolder.'/';
        $customPDFs = $session->get(self::pdfParticipationCustom);
        foreach ($session->get(self::pdfParticipationArray) as $ids) {
            $idSuffix = $this->concatIDs($ids);
            // custom PDFs, if any
            $folderPrefix = $tempFolder.'participation';
            $filename = $folderPrefix.($isCompleteForm ? 'Marked' : '').$idSuffix.$sessionIDExt;
            if ($isCompleteForm) {
                $this->addPDF($filename); // only date
                $curCustomPDFs = $customPDFs[$ids[0]][$ids[1]][$ids[2]] ?? [];
                foreach (self::customPDForder as $custom) {
                    if (in_array($custom,$curCustomPDFs)) {
                        $this->addPDF($folderPrefix.$idSuffix.$custom.$sessionIDExt);
                    }
                    $this->addCustomPDF($custom.$idSuffix,$files);
                }
            } else {
                $numPages = $this->fpdi->setSourceFile($filename);
                // if no participation was created, the pdf has only two pages (hint that no participation was created and empty page). If 'pages' is passed to 'addPdf' with a '-', end page must be greater than start page.
                $this->pdfParticipation->addPdf($filename,'1'.($numPages>2 ? '-'.($numPages-1) : ''));
            }
        }
    }

    /** Adds a custom pdf if it exists.
     * @param string $name key in $files to be checked for existence
     * @param array $files array with custom PDFs
     * @return void
     * @throws CrossReferenceException
     * @throws FilterException
     * @throws PdfTypeException
     * @throws PdfParserException
     * @throws PdfReaderException
     */
    private function addCustomPDF(string $name, array $files): void
    {
        if (array_key_exists($name,$files) && $files[$name]!==null) {
            $file = $files[$name];
            $this->failureName = $file->getClientOriginalName();
            $pathname = $file->getPathname();
            $fileContents = file_get_contents($pathname);
            if (preg_match("/^%PDF-1./", $fileContents)) {
                $version = intval(substr($fileContents,strpos($fileContents,'%PDF-1.')+7,1));
                if ($version>4) { // convert pdf to 1.4
                    $ghostscript = new Ghostscript(['quiet' => false]);
                    $device = $ghostscript->createPdfDevice($pathname.'.pdf');
                    $device->setCompatibilityLevel(1.4);
                    $device->createProcess($pathname)->run();
                    $pathname .= '.pdf';
                }
            }
            // add pdf
            $this->addPDF($pathname,false);
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
    private function addPDF(string $filename, bool $removeLastPage = true): int
    {
        $numPages = $this->fpdi->setSourceFile($filename);
        for ($curPage = 1; $curPage<$numPages+($removeLastPage ? 0 : 1); $curPage++) {
            $importedPage = $this->fpdi->importPage($curPage, PageBoundaries::CROP_BOX, true, true);
            $size = $this->fpdi->getTemplateSize($importedPage);
            $this->fpdi->AddPage($size['orientation'],[$size['width'],$size['height']]);
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
    protected function setCommittee(Session $session, string $committeeType, string $locale, bool $setSession = true): array
    {
        $tempArray = ['committeeType' => $committeeType, self::toolVersionAttr => self::toolVersion, self::isCommitteeBeta => in_array($committeeType,self::committeeTypesBeta)];
        foreach (['committeeNom','committeeGen','committeeDat','committeeAcc','committeeLocation','committeeLocationGen'] as $type) {
            $tempArray[$type] = self::$translator->trans('committee.'.$type,['committee' => $committeeType],'messages',$locale);
        }
        if ($setSession) {
            $session->set(self::committeeParams,$tempArray); // separate elements
        }
        return $tempArray;
    }

    /** Sets the 'errorMessage' key in the session and redirects to the main page.
     * @param Session $session current session
     * @return RedirectResponse Response redirecting to the main page
     */
    protected function setErrorAndRedirect(Session $session): RedirectResponse
    {
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
    protected function getErrors(Request $request, string $page = '', bool $returnCheck = false, SimpleXMLElement|bool $element = null ): string|bool
    {
        try {
            return CheckDocClass::getDocumentCheck($request, $page, $returnCheck, $element, false);
        } catch (\Throwable) {
            return ''; // no need to return false if $returnCheck is true because an empty string is considered false
        }
    }

    /** Converts a string to a link
     * @param string $text string to be converted
     * @param string $link route to be linked to
     * @param string $routeIDs if provided, route IDs
     * @param string $fragment fragment to be added to the url
     * @return string converted string
     */
    protected function convertStringToLink(string $text, string $link, string $routeIDs = '', string $fragment = ''): string
    {
        return '<a class="linkInternal" href="'.$link.'" data-action="base#setDummySubmit" data-base-url-param="app_'.$link.($fragment!='' ? '#'.$fragment : '').'"'.($link===self::landing ? 'data-base-page-param="Projectdetails"': '').($routeIDs!=='' ? 'data-base-route-i-ds-param="'.$routeIDs.'"' : '').'>'.$text.'</a>';
    }

    /** Eventually adds a span-tag to the string
     * @param string $text text
     * @param bool $addSpan true if span-tag should be added, false otherwise
     * @return string $text with eventually a span-tag
     */
    protected function addMarkInput(string $text, bool $addSpan): string
    {
        return $text!=='' ? ($addSpan ? '<span class="markInput">' : '').$text.($addSpan ? '</span>' : '') : '';
    }

    /** Creates the brief report.
     * @param Session $session current session
     * @param bool $getReviewError if true, return whether any information in the brief report makes a full review process necessary
     * @return array|bool if $getReviewError is true, true if the review process that results from the inputs is unequal to the selected review process, otherwise an array: keys: translated lines of the brief report (i.e., first column), values: array of two elements: 0: translated answers (i.e., second column), 1: whether the answer should be colored
     */
    protected function getBriefReport(Session $session, bool $getReviewError = true): array|bool
    {
        $appNode = $this->getXMLfromSession($session,getRecent: true);
        $reviewProcess = $session->get(self::reviewProcess);
        $isReviewFull = str_contains($reviewProcess,self::reviewProcessFull);
        $isFullDocs = $reviewProcess===self::reviewFullDocs;
        $isReviewFullParam = ['isFull' => $this->getStringFromBool($isReviewFull)];
        $parameters = array_merge($isReviewFullParam,['reviewProcess' => $reviewProcess]);
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
        $coreDataArray = $appDataArray[self::coreDataNode];
        $tempVal = $coreDataArray[self::conflictNode][self::chosen];
        $allShort = $tempVal==='1'; // gets false if any question is either not yet answered or answered such that a full proposal is required
        $briefReport = [$this->getBriefReportHeading(self::conflictNode) => $this->getBriefReportAnswer(self::conflictNode,$tempVal==='0' ? self::answerYes : self::answerNo,$parameters,$getReviewError)];
        $isMedicinePhysician = false;
        foreach ([self::medicine,self::physicianNode] as $type) {
            $tempVal = $medicineArray[$type][self::chosen];
            $allShort = $allShort && $tempVal==='1';
            $tempVal = $tempVal==='0';
            $isMedicinePhysician = $isMedicinePhysician || $tempVal;
            $parameters['is'.ucfirst($type)] = $this->getStringFromBool($tempVal);
        }
        $briefReport[$this->getBriefReportHeading(self::medicine)] = $this->getBriefReportAnswer(self::medicine,$isMedicinePhysician ? self::answerYes : self::answerNo,$parameters,$getReviewError);
        $conflictMedicine = $briefReport;
        $briefReport = [];
        $tempArray = array_values($conflictMedicine);
        $anyFull = $getReviewError && (($tempArray[0][0] || $tempArray[1][0])); // (gets) true if any information makes a full review process necessary
        $anyUnclear = false; // gets true if any information is 'unclear'
        if (!($getReviewError && $anyFull)) { // check projectdetails only if conflict and medicine are 'no'
            foreach ($studyArray as $studyID => $study) {
                $heading = $multipleStudies ? [self::studyNode => $headingTrans[self::studyNode].($studyID + 1)] : [];
                $groupArray = $this->addZeroIndex($study[self::groupNode]);
                $multipleGroups = count($groupArray)>1;
                foreach ($groupArray as $groupID => $group) {
                    if ($multipleGroups) {
                        $heading[self::groupNode] = $headingTrans[self::groupNode].($groupID + 1);
                    } else {
                        unset($heading[self::groupNode]);
                    }
                    $measureArray = $this->addZeroIndex($group[self::measureTimePointNode]);
                    $multipleMeasureTimePoints = count($measureArray)>1;
                    foreach ($measureArray as $measureID => $measureTimePoint) {
                        if ($multipleMeasureTimePoints) {
                            $heading[self::measureTimePointNode] = $headingTrans[self::measureTimePointNode].($measureID + 1);
                        } else {
                            unset($heading[self::measureTimePointNode]);
                        }
                        $informationArray = $measureTimePoint[self::informationNode];
                        $information = $this->getInformationString($informationArray);
                        $parameters[self::informationNode] = $information;
                        $informationIIArray = $measureTimePoint[self::informationIINode];
                        $isInformationII = $informationIIArray!=='';
                        $informationII = $isInformationII ? $this->getInformationString($informationIIArray) : '';
                        $allInformation = [$information, $informationII];
                        // information -> no need to check for informationII because 'examined' will falsify $allShort anyway if informationII is active
                        $preContentChosen = $informationArray[self::preContent] ?? '';
                        $allShort = $allShort && $preContentChosen===self::complete;
                        $preContent = [$preContentChosen, $isInformationII ? ($informationIIArray[self::preContent] ?? '') : ''];
                        $isNotPre = array_diff($allInformation, ['', self::pre])!==[];
                        $parameters['isNotPre'] = $this->getStringFromBool($isNotPre);
                        $isIncomplete = array_intersect($preContent, self::preContentIncomplete)!==[];
                        $parameters['isIncomplete'] = $this->getStringFromBool($isIncomplete);
                        $briefReport[$this->getBriefReportHeading(self::informationNode)] = $this->getBriefReportAnswer(self::informationNode, $isNotPre || $isIncomplete ? self::answerNo : self::answerYes, $parameters, $getReviewError, $noUnclear);
                        // voluntary -> no need to check for chosen2 because if it exists, 'examined' will falsify $allShort anyway
                        $consentArray = $measureTimePoint[self::consentNode];
                        $tempArray = $consentArray[self::consentNode];
                        $consentChosen = $tempArray[self::chosen];
                        $isConsentOther = in_array(self::consentOther, [$consentChosen, $tempArray[self::chosen2Node] ?? '']);
                        $tempArray = $consentArray[self::voluntaryNode];
                        $voluntary = [$tempArray[self::chosen], $tempArray[self::chosen2Node] ?? 'yes'];
                        $isVoluntary = in_array(self::voluntaryNotApplicable, $voluntary) || array_key_exists(self::voluntaryYesDescription, $tempArray);
                        $compensationArray = $measureTimePoint[self::compensationNode];
                        $allShort = $allShort && $compensationArray[self::compensationTypeNode]!=='';
                        $parameters['isVoluntary'] = $this->getStringFromBool($isVoluntary);
                        $parameters['isConsent'] = $this->getStringFromBool($isConsentOther);
                        $compensationVoluntary = $compensationArray[self::compensationVoluntaryNode][self::chosen] ?? '';
                        $allShort = $allShort && array_diff($voluntary, ['yes'])===[] && (!array_key_exists(self::compensationVoluntaryNode, $compensationArray) || $compensationVoluntary==='1') && in_array($consentChosen, self::consentTypesAny);
                        $briefReport[$this->getBriefReportHeading(self::voluntaryNode)] = $this->getBriefReportAnswer(self::voluntaryNode, in_array(self::voluntaryConsentNo, $voluntary)
                            ? self::answerNo
                            : ($compensationVoluntary==='0' // compensation may compromise voluntariness
                                ? self::answerRestricted
                                : ($isVoluntary || // voluntariness not applicable, closed group or dependent
                                $isConsentOther // consent is 'other'
                                    ? self::answerUnclear : self::answerYes)), $parameters, $getReviewError, [self::answerUnclear, self::answerRestricted, self::answerNo]);
                        // terminate cons
                        $measuresArray = $measureTimePoint[self::measuresNode];
                        $isLonger30 = $this->getDuration($measuresArray[self::durationNode])>30;
                        $compensationTerminate = $compensationArray[self::terminateNode][self::chosen] ?? '';
                        $isNoCompensationTerminate = $compensationTerminate===self::terminateNothing;
                        $terminateCons = $consentArray[self::terminateConsNode][self::chosen];
                        $isTerminateCons = $terminateCons==='1';
                        $isShorter30Terminate = !$isLonger30 && $isNoCompensationTerminate;
                        $parameters['isTerminateCons'] = $this->getStringFromBool($isTerminateCons);
                        $parameters['isDuration'] = $this->getStringFromBool($isShorter30Terminate);
                        $isInformation = in_array($information,self::prePostArray);
                        $parameters['isFullInformation'] = $this->getStringFromBool($isFullDocs && $isInformation);
                        $parameters['isDocHint'] = $this->getStringFromBool(!($isTerminateCons && !$isShorter30Terminate && (!$isInformation || !$isFullDocs)));
                        $allShort = $allShort && $terminateCons==='0' && (!array_key_exists(self::terminateNode, $compensationArray) || in_array($compensationTerminate, self::terminateTypes));
                        $briefReport[$this->getBriefReportHeading(self::terminateConsNode)] = $this->getBriefReportAnswer(self::terminateConsNode, $isTerminateCons || $isLonger30 && $isNoCompensationTerminate // cons if withdrawal or duration>30min and no compensation if withdrawal
                            ? self::answerNo
                            : ($compensationTerminate===self::terminateOther || $isShorter30Terminate // other compensation if withdrawal or duration at most 30 minutes and no compensation if withdrawal
                                ? self::answerUnclear : self::answerYes), $parameters, $getReviewError, $noUnclear);
                        // examined
                        $groupsArray = $measureTimePoint[self::groupsNode];
                        $examinedArray = $groupsArray[self::examinedPeopleNode];
                        $examinedArray = is_array($examinedArray) ? $examinedArray : []; // if not an array, then is must be an empty string, i.e., nothing chosen yet
                        $minAge = $groupsArray[self::minAge];
                        $isMinAge = $minAge!=='';
                        $isUnder18 = $isMinAge && $minAge<18;
                        $isOnlyHealthyDependentOther = array_diff_key($examinedArray, [self::healthyExaminedNode => '', self::dependentExaminedNode => '', 'otherPeople' => ''])===[];
                        $isOther = array_key_exists('otherPeople', $examinedArray);
                        $allShort = $allShort && $isMinAge && !$isUnder18 && $isOnlyHealthyDependentOther && (!$isOther || count($examinedArray)>1);
                        $briefReport[$this->getBriefReportHeading(self::examinedPeopleNode)] = $this->getBriefReportAnswer(self::examinedPeopleNode, !$isOnlyHealthyDependentOther // people other than healthy, dependent, and other are examined
                            ? self::answerYes
                            : ($isOther || // only other is selected
                            $isUnder18 // underage
                                ? self::answerUnclear : self::answerNo), $parameters, $getReviewError);
                        // wards -> no need to set $allShort because checks for wards are implicitly included in checks for examined
                        $briefReport[$this->getBriefReportHeading(self::wardsExaminedNode)] = $this->getBriefReportAnswer(self::wardsExaminedNode, array_key_exists(self::wardsExaminedNode, $examinedArray) ? self::answerNo : ($isUnder18 ? self::answerUnclear : self::answerYes), $parameters, $getReviewError, $noUnclear);
                        // pre content -> no need to set $allShort because checks for pre content are implicitly included in checked for information
                        $briefReport[$this->getBriefReportHeading(self::preContent)] = $this->getBriefReportAnswer(self::preContent, in_array(self::deceit, $preContent) // deceit was chosen
                            ? self::answerYes
                            : (array_intersect($allInformation, [self::post, 'noPost'])!==[] // no information is given
                                ? self::answerUnclear : self::answerNo), $parameters, $getReviewError);
                        // burdens and risks
                        $burdensRisksArray = $measureTimePoint[self::burdensRisksNode];
                        $tempVal = $burdensRisksArray[self::burdensRisksContributorsNode][self::chosen];
                        $isBurdensRisksContributors = $tempVal==='0';
                        $allShort = $allShort && $tempVal==='1';
                        $parameters['isBurdensRisksContributors'] = $this->getStringFromBool($isBurdensRisksContributors);
                        foreach ([self::burdensNode, self::risksNode] as $type) {
                            $tempArray = $burdensRisksArray[$type][$type.'Type'];
                            $isChosen = is_array($tempArray);
                            $isCurrent = $isChosen && array_diff_key($tempArray, [($type===self::burdensNode ? self::noBurdens : self::noRisks) => ''])!==[];
                            $allShort = $allShort && $isChosen && !$isCurrent;
                            $parameters['is'.ucfirst($type)] = $this->getStringFromBool($isCurrent);
                            $briefReport[$this->getBriefReportHeading($type)] = $this->getBriefReportAnswer($type, $isBurdensRisksContributors || $isCurrent ? self::answerYes : self::answerNo, $parameters, $getReviewError);
                        }
                        // finding
                        $tempVal = $burdensRisksArray[self::findingNode][self::chosen];
                        $allShort = $allShort && $tempVal==='1';
                        $briefReport[$this->getBriefReportHeading(self::findingNode)] = $this->getBriefReportAnswer(self::findingNode, $tempVal==='0' ? self::answerYes : self::answerNo, $parameters, $getReviewError);
                        // data privacy -> no set of $allShort because data privacy is only relevant for full proposals
                        if ($isReviewFull) {
                            $dataPrivacyArray = $measureTimePoint[self::privacyNode];
                            if (array_key_exists(self::createNode, $dataPrivacyArray)) {
                                $create = $dataPrivacyArray[self::createNode][self::chosen];
                                $isTool = $create===self::createTool;
                                $responsibility = $dataPrivacyArray[self::responsibilityNode] ?? '';
                                $marking = $dataPrivacyArray[self::markingNode][self::chosen] ?? '';
                                $isMarkingOther = $marking===self::markingOther; // marking can not be created by the tool
                                $answer = $create===self::createSeparate || // personal data are collected, but document should not be created by the tool
                                          $isTool && (!$isMarkingOther && $responsibility!==self::privacyNotApplicable || // if responsibility does not equal 'not applicable', personal data are collected
                                          in_array($dataPrivacyArray[self::dataPersonalNode] ?? '',self::dataPersonal)) // research data are personal
                                         ? self::answerYes
                                         : (in_array($create,[self::createSeparateLater,self::privacyNotApplicable]) || // data privacy is checked later or no information is given
                                            $isTool && $isMarkingOther // marking can not be created by the tool
                                                ? self::answerUnclear : self::answerNo);
                            } else { // data collection has already begun or funding is requested
                                $answer = self::answerUnclear;
                            }
                            if (!$getReviewError) { // add only if brief report gets created to avoid using the answer for checking if the correct application process is chosen
                                $briefReport[$this->getBriefReportHeading(self::privacyNode)] = $this->getBriefReportAnswer(self::privacyNode, $answer, $parameters, $getReviewError);
                            }
                        }
                        // other sources
                        $tempVal = $measuresArray[self::otherSourcesNode][self::chosen];
                        $allShort = $allShort && $tempVal==='1';
                        $briefReport[$this->getBriefReportHeading(self::otherSourcesNode)] = $this->getBriefReportAnswer(self::otherSourcesNode, $tempVal==='0' ? self::answerYes : self::answerNo, $parameters, $getReviewError);
                        // add question to time point
                        $allBriefReports[] = ['heading' => implode(', ', $heading), 'content' => array_merge($briefReport, $conflictMedicine)];
                        if ($getReviewError) {
                            foreach ($briefReport as $values) {
                                $anyFull = $anyFull || $values[0];
                                $anyUnclear = $anyUnclear || $values[1];
                            }
                        }
                        if ($getReviewError && $anyFull) { // at least one information makes a full review process necessary
                            break(3);
                        }
                    }
                }
            } // foreach study
        } // if !(getReviewError && anyFull)
        return $getReviewError ? ($coreDataArray[self::applicationProcessNode][self::chosen]!=='' && str_contains($reviewProcess,self::reviewProcessFull) && !$anyFull && !$anyUnclear && $allShort || str_contains($reviewProcess,self::reviewProcessShort) && $anyFull) : $allBriefReports;
    }

    /** Creates a heading for the brief report (i.e., first columns)
     * @param string $key translation key
     * @return string translated heading
     */
    private function getBriefReportHeading(string $key): string
    {
        $tempPrefix = 'completeForm.briefReport.';
        return '<b>'.$this->translateStringPDF($tempPrefix.'headings.'.$key).":</b>\n".$this->translateStringPDF($tempPrefix.$key);
    }

    /** Get an answer for a brief report.
     * @param string $key key to be used for the translation
     * @param string $answer answer to the question
     * @param array $parameters parameters for the translation
     * @param bool $getFull if true, return whether the answer is in $coloredAnswers but unequal to 'unclear'
     * @param array $coloredAnswers if $answer equals any of these answers, the answer will be displayed in red
     * @return array if $getFull is false: 0: whether the answer is in coloredAnswers but unequal to 'unclear', 1: whether the answer is 'unclear'. If getColored is true: array of two elements: 0: translated answer (i.e., second column), 1: whether the answer is in $coloredAnswers
     */
    private function getBriefReportAnswer(string $key, string $answer, array $parameters, bool $getFull, array $coloredAnswers = [self::answerYes,self::answerUnclear]): array
    {
        $tempPrefix = 'completeForm.briefReport.';
        $isColored = in_array($answer,$coloredAnswers);
        if ($getFull) {
            $isUnclear = $answer===self::answerUnclear;
            return [$isColored && !$isUnclear,$isUnclear];
        } else {
            return [$this->translateStringPDF($tempPrefix.'types.'.$answer).$this->translateStringPDF($tempPrefix.'linking.'.$key,(array_merge($parameters,['answer' => $answer]))),$isColored];
        }
    }

    /** Creates a string indicating the type of information.
     * @param array $information
     * @return string 'pre' if pre information is chosen, 'post' if pre information is answered with no and post information is answered with yes, 'noPre' if pre information is answered with no and no post information is chosen, 'noPost' if pre and post information are answered with no, empty string otherwise (i.e., no pre information is chosen)
     */
    protected function getInformationString(array $information): string
    {
        $pre = $information[self::pre];
        $post = $pre==='1' ? $information[self::post][self::chosen] : '';
        return $pre==='0' ? self::pre : ($pre==='1' ? ($post==='0' ? self::post : ($post==='1' ? 'noPost' : 'noPre')) : '');
    }

    // functions involving xml

    /** Replaces all '<' in the values of the input with '&lt;'.
     * @param string|array $element element to be checked
     * @return array|string $element with opening tags replaced
     */
    private function replaceOpeningTag(Array|string $element): array|string
    {
        if (is_array($element)) {
            foreach ($element as $key => $value) {
                if (is_array($value)) {
                    $element[$key] = $this->replaceOpeningTag($value);
                } else {
                    $element[$key] = str_replace('<','&lt;',$value); // replace all opening tags
                }
            }
        } else {
            $element = str_replace('<','&lt;',$element);
        }
        return $element;
    }

    /** Checks if there are multiple studies, groups, or measure time points.
     * @param SimpleXMLElement $appNode root node of the xml-document
     * @return bool true if there are multiple studies, groups, or measure time points, false otherwise
     */
    protected function getMultiStudyGroupMeasure(SimpleXMLElement $appNode): bool
    {
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
    protected function getClonedMeasureTimePoint(SimpleXMLElement $appNode, array $routeParams): array
    {
        $appNodeNew = $this->cloneNode($appNode);
        return [$appNodeNew,$this->getMeasureTimePointNode($appNodeNew,$routeParams)];
    }

    /** Clones an xml-element.
     * @param SimpleXMLElement $element element to be cloned
     * @return SimpleXMLElement the cloned element
     */
    protected function cloneNode(SimpleXMLElement $element): SimpleXMLElement
    {
        return simplexml_import_dom(dom_import_simplexml($element)->cloneNode(true));
    }

    /** Gets the project title for the study information.
     * @param Session $session current session
     * @param bool $getDifferent if true, an array will be returned with the second element indicating whether the project title for participants differs
     * @return string|array project title for the study information and eventually whether it differs for participants
     */
    protected function getProjectTitleParticipants(Session $session, bool $getDifferent = false): string|array
    {
        $coreDataArray = $this->xmlToArray($this->getXMLfromSession($session,getRecent: true)->{self::appDataNodeName}->{self::coreDataNode});
        $tempArray = $coreDataArray[self::projectTitleParticipation] ?? [];
        $projectTitle = $tempArray[self::descriptionNode] ?? $coreDataArray[self::projectTitle];
        return !$getDifferent ? $projectTitle : [$projectTitle,array_key_exists(self::descriptionNode,$tempArray)];
    }

    /** Gets the current measure time point node.
     * @param Request|SimpleXMLElement|bool $appNode Either the request or the root node of the xml-document.
     * @param array $params array containing the IDs for the different levels
     * @return SimpleXMLElement|null measure time point node or null if either $appNode is false or a node cannot be found
     */
    protected function getMeasureTimePointNode(Request|SimpleXMLElement|bool $appNode, array $params): ?SimpleXMLElement
    {
        if ($appNode instanceof Request) {
            $appNode = $this->getXMLfromSession($appNode->getSession());
        }
        return !$appNode ? null : $appNode->{self::projectdetailsNodeName}->{self::studyNode}[$params[self::studyID]-1]->{self::groupNode}[$params[self::groupID]-1]->{self::measureTimePointNode}[$params[self::measureID]-1] ?? null;
    }

    /** Creates an empty DomDocument.
     * @return DOMDocument empty DomDocument
     */
    protected function createDOM(): DOMDocument
    {
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
    protected function getXMLfromSession(Session $session, bool $getFirst = false, bool $getRecent = false, bool $setRecent = false): SimpleXMLElement|bool
    {
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
    protected function getDataAndConvert(FormInterface $form, SimpleXMLElement $element): mixed
    {
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
    protected function getLeavePage(FormInterface $form, Session $session, string $route): bool
    {
        $submitDummy = $form->get(self::submitDummy)->getData();
        if (str_contains($submitDummy,self::language)) { // one of the language forms was clicked
            return false;
        }
        $nextRoute = explode("\n",$submitDummy);
        $nextRoute = trim($nextRoute[str_contains($nextRoute[0],self::preview) ? 1 : 0]); // first entry (and maybe the second, too) may be the position of the preview scrollbar
        $isLeave = substr($nextRoute,4)!==$route && ($submitDummy==='' || !in_array($nextRoute,['undo','download','documents',''])); // route in submitDummy starts with 'app_' (first check); check submitDummy in case the preview line exists twice
        if ($isLeave) {
            $session->remove(self::docNameRecent);
        }
        return $isLeave;
    }

    /** Checks if the array has a key '0' and if not, creates a new array with key '0' and the input array as the value.
     * @param array $array array whose keys are checked
     * @return array array with '0' as the first key
     */
    protected function addZeroIndex(array $array): array
    {
        return array_key_exists('0',$array) ? $array : [0 => $array];
    }

    /** Creates a string with the route IDs that can be passed to the setDummySubmit() method in base_controller.
     * @param array $routeIDs route IDs. Each value will be decreased by 1
     * @return string string with route IDs
     */
    protected function createRouteIDs(array $routeIDs): string
    {
        return '{&quot;studyID&quot;:&quot;'.($routeIDs[self::studyNode]).'&quot;,&quot;groupID&quot;:&quot;'.($routeIDs[self::groupNode]).'&quot;,&quot;measureID&quot;:&quot;'.($routeIDs[self::measureTimePointNode]).'&quot;}';
    }

    // methods

    /** Adds a page to the array indicating that inputs were made on that page.
     * @param string $translationPrefix prefix for the string that is added
     * @param string $inputPage node name of the page for which a string is added
     * @param array $pages array with keys 'pageNames' and 'pageInputs'
     * @param array $parameters parameters for the translation
     */
    protected function addInputPage(string $translationPrefix, string $inputPage, array &$pages, array $parameters = []): void
    {
        $count = count($pages[self::pageNames]);
        $inputsPrefix = 'multiple.inputs.';
        $quotesPrefix = $inputsPrefix.'quotes.';
        $pages[self::pageNames][$count] = $this->translateString($quotesPrefix.'start').$this->translateString($translationPrefix.$inputPage,$parameters).$this->translateString($quotesPrefix.'end');
        $pages[self::pageInputs][$count] = $this->translateString($inputsPrefix.$inputPage,$parameters);
    }

    /** Gets all tasks from all contributors and sets them as selected in the projectdetails contributor nodes.
     * @param Request $request
     * @param SimpleXMLElement $appNode top node of the application file
     * @return void
     */
    protected function setProjectdetailsContributor(Request $request, SimpleXMLElement $appNode): void
    {
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

    /** Get either the most or the least recent contributors array from the session.
     * @param Session $session session where the arrays are stored
     * @param bool $getFirst if true, the oldest contributors array is returned, i.e., the state when the page was entered, otherwise the most recent one
     * @return array most recent contributors array
     */
    protected function getContributors(Session $session, bool $getFirst = false): array
    { // added here because it is called by the function above
        $contributors = $session->get(self::contributorsSessionName);
        return $contributors[!$getFirst ? count($contributors)-1 : 0];
    }

    /** Resets the document and eventually the contributors arrays in the session such that only the most recent one remains.
     * @param Session $session current session
     * @param bool $resetContributors true if the contributors arrays should also be reset, false otherwise
     * @return void
     */
    protected function resetDocContributors(Session $session, bool $resetContributors = false): void
    {
        foreach (array_merge([self::docName],$resetContributors ? [self::contributorsSessionName] : []) as $type) {
            $allElements = $session->get($type);
            $session->set($type,[$allElements[count($allElements)-1]]);
        }
        $session->remove(self::docNameRecent);
    }

    /** Removes the temporary files.
     * @param Session $session current session
     * @return void
     */
    private function removeTempFiles(Session $session): void
    {
        $sessionID = $session->getId();
        $prefix = self::tempFolder.'/';
        $suffix = $sessionID.'.pdf';
        if (self::$isCompleteForm) {
            unlink($prefix.'complete'.$suffix);
            unlink($prefix.'applicationCompleteForm'.$suffix);
            unlink($prefix.'applicationSingleDocs'.$suffix);
        }
        unlink($prefix.'application'.$suffix);
        $prefix .= 'participation';
        $customPDFs = $session->get(self::pdfParticipationCustom);
        foreach ($session->get(self::pdfParticipationArray) as $ids) {
            unlink($this->concatIDs($ids,$prefix,$suffix));
            if (self::$isCompleteForm && self::$savePDF) {
                unlink($this->concatIDs($ids,$prefix.'Marked',$suffix));
                foreach ($customPDFs[$ids[0]][$ids[1]][$ids[2]] ?? [] as $curCustomPDF) {
                    unlink($this->concatIDs($ids,$prefix,$curCustomPDF.$suffix));
                }
            }
        }

    }

    // methods involving xml

    /** Updates the xml-file.
     * @param Request $request
     * @param SimpleXMLElement $xml loaded xml-file
     * @return void
     */
    private function updateXML(Request $request, SimpleXMLElement $xml): void
    {
        $loadedVersion = explode('.',$this->getToolVersion($xml));
        $major = $loadedVersion[0];
        $minor = $loadedVersion[1];
        $patch = $loadedVersion[2];
        $isMajor1 = $major==='1';
        $isMajor2 = $major==='2';
        $isMinorSmaller3 = $minor<'3';
        $is200 = $isMajor2 && $minor==='0' && $patch==='0';
        $isSmallerCurrent = $isMajor1 || $isMajor2 && $minor<'3';
        $isSmaller221 = $isMajor1 || $isMajor2 && $minor<='2' && $patch<'1';
        $coreDataNode = $xml->{self::appDataNodeName}->{self::coreDataNode};
        $isConflict = false;
        $conflictDescription = '';
        $committeeType = (string)$xml->{self::committee};
        if ($isMajor1) { // updates for version before 2.0.0
            $this->setToolVersion($xml); // update attribute
            $conflictNode = $coreDataNode->{self::conflictNode};
            $isConflict = (string)$conflictNode->{self::chosen}==='0';
            if ($isConflict) { // remove participant description because it is moved to texts
                $conflictDescription = (string)$conflictNode->{'participantDescription'};
                $this->removeElement('participantDescription', $conflictNode);
            }
            $appTypeNode = $coreDataNode->{self::applicationType};
            if (((string)$appTypeNode->{self::chosen})===self::appNew && in_array($committeeType, [self::committeeTUC, 'testCommittee'])) { // TUC or test committee -> remove old application type
                $this->removeElement(self::descriptionNode, $appTypeNode); // remove node containing the application type
            }
            $this->insertElementBefore(self::applicationProcessNode, $coreDataNode->{$this->checkElement(self::qualification, $coreDataNode) ? self::qualification : self::applicant}, [self::chosen]);
        }
        if ($isSmallerCurrent) {
            $reviewProcess = $this->getCurrentReviewProcess($xml);
            $isShortNoDocs = $reviewProcess===self::reviewShortNoDocs;
            foreach ($xml->{self::projectdetailsNodeName}->{self::studyNode} as $studyNode) {
                foreach ($studyNode->{self::groupNode} as $groupNode) {
                    foreach ($groupNode->{self::measureTimePointNode} as $measureTimePointNode) {
                        $informationNode = $measureTimePointNode->{self::informationNode};
                        $measuresNode = $measureTimePointNode->{self::measuresNode};
                        $privacyNode = $measureTimePointNode->{self::privacyNode};
                        if ($isMajor1) {
                            // updates for version before 2.0.0
                            // groups (update of nodes)
                            $groupsNode = $measureTimePointNode->{self::groupsNode};
                            $groupsNodeDom = dom_import_simplexml($groupsNode);
                            $criteriaNode = $groupsNode->{self::criteriaNode};
                            $criteriaNodeDom = dom_import_simplexml($criteriaNode);
                            foreach ([self::criteriaIncludeNode, self::criteriaExcludeNode] as $type) {
                                $groupsNodeDom->insertBefore(dom_import_simplexml($criteriaNode->{$type}), $criteriaNodeDom);
                            }
                            $this->removeElement(self::criteriaNode, $groupsNode);
                            $recruitmentNode = $groupsNode->{self::recruitment};
                            $recruitmentArray = $this->xmlToArray($recruitmentNode);
                            $this->removeAllChildNodes($recruitmentNode);
                            $tempArray = $recruitmentArray['recruitmentTypes'];
                            if ($tempArray!=='') {
                                foreach ($tempArray as $selection => $description) {
                                    $recruitmentNode->addChild($selection, $description);
                                }
                                $groupsNode->addChild(self::recruitmentFurther, $recruitmentArray[self::descriptionNode]);
                            }
                            // information(II) (update of nodes)
                            foreach ([self::informationNode, self::informationIINode] as $page) {
                                $curInformationNode = $measureTimePointNode->{$page};
                                $informationArray = $this->xmlToArray($curInformationNode);
                                if ($informationArray!==[]) {
                                    $this->removeAllChildNodes($curInformationNode);
                                    $pre = $informationArray[self::chosen];
                                    $curInformationNode->addChild(self::pre, $pre);
                                    $description = $informationArray[self::descriptionNode] ?? '';
                                    $additional = $informationArray['additional'] ?? [];
                                    $additionalChosen = $additional[self::chosen] ?? '';
                                    $additionalDescription = $additional[self::descriptionNode] ?? '';
                                    $isPrePost = false; // gets true if either pre or post information
                                    $informationType = ''; // type of pre/post information
                                    if ($pre==='0') { // pre information
                                        $isPrePost = true;
                                        $informationType = $description;
                                        $curInformationNode->addChild(self::preType, $description);
                                        $curInformationNode->addChild(self::preContent, $additionalChosen);
                                        if (in_array($additionalChosen, self::preContentIncomplete)) { // partial or deceit
                                            $preCompleteNode = $curInformationNode->addChild(self::preComplete); // pre complete
                                            $complete = $additional[self::complete] ?? '';
                                            $preCompleteNode->addChild(self::chosen, $complete);
                                            if ($complete==='0') {
                                                $preCompleteNode->addChild(self::preCompleteType, $additional[self::preCompleteType]); // type of pre complete
                                            }
                                            $preCompleteNode->addChild(self::descriptionNode, $additionalDescription);
                                        }
                                    } elseif ($pre==='1') {
                                        $isPrePost = true;
                                        $curInformationNode->addChild(self::preText, $description);
                                        $postNode = $this->addChosenNode($curInformationNode,self::post);
                                        $postNode->{self::chosen} = $additionalChosen; // post information
                                        if ($additionalChosen!=='') {
                                            $postNode->addChild(self::descriptionNode, $additionalDescription); // type of post information or description
                                            if ($additional==='0') {
                                                $informationType = $additionalDescription;
                                            }
                                        }
                                    }
                                    if (array_key_exists(self::attendanceNode, $informationArray)) {
                                        $curInformationNode->addChild(self::attendanceNode, $informationArray[self::attendanceNode]);
                                    }
                                    if (array_key_exists(self::documentTranslationNode, $informationArray) && $informationType!==self::informationOral && $isPrePost) { // add only if information is not oral
                                        $tempArray = $informationArray[self::documentTranslationNode];
                                        $documentTranslationNode = $this->addChosenNode($curInformationNode, self::documentTranslationNode);
                                        $documentTranslationNode->{self::chosen} = $tempArray[self::chosen];
                                        if (array_key_exists(self::descriptionNode, $tempArray)) {
                                            $documentTranslationNode->addChild(self::descriptionNode, $tempArray[self::descriptionNode]);
                                        }
                                        if (array_key_exists(self::documentTranslationPDF, $tempArray)) { // pdf should be added
                                            $curInformationNode->addChild(self::documentTranslationPDF);
                                        }
                                    }
                                }
                            }
                            // consent (update of nodes)
                            $consentNode = $measureTimePointNode->{self::consentNode};
                            $terminateConsNode = $consentNode->{self::terminateConsNode}; // terminate cons
                            if ($this->checkElement(self::terminateConsParticipationNode, $terminateConsNode)) {
                                $this->insertElementBefore(self::terminateConsParticipationNode, $consentNode->{self::terminateParticipantsNode});
                                $consentNode->{self::terminateConsParticipationNode} = (string)$terminateConsNode->{self::terminateConsParticipationNode};
                                $this->removeElement(self::terminateConsParticipationNode, $terminateConsNode);
                            }
                            // measures (update of nodes)
                            foreach ([self::measuresNode, self::interventionsNode] as $type) {
                                $curNode = $measuresNode->{$type};
                                $followingNode = $measuresNode->{$type===self::measuresNode ? self::interventionsNode : self::otherSourcesNode};
                                $tempVal = $type.'PDF';
                                foreach ([self::descriptionNode => $type.self::descriptionCap, $tempVal => $tempVal] as $node => $newNode) {
                                    if ($this->checkElement($node, $curNode)) {
                                        $this->insertElementBefore($newNode, $followingNode); // move node behind 'type' node
                                        $measuresNode->{$newNode} = (string)$curNode->{$node};
                                    }
                                }
                                $typeArray = $this->xmlToArray($curNode->{$type.'Type'}[0]);
                                $this->removeAllChildNodes($curNode);
                                if ($typeArray!==[]) {
                                    foreach ($typeArray as $selection => $value) { // set 'typeType' children as children of 'type'
                                        $curNode->addChild($selection);
                                    }
                                }

                            }
                            // burdensRisks (update of nodes)
                            $burdensRisksNode = $measureTimePointNode->{self::burdensRisksNode};
                            $burdensNode = $burdensRisksNode->{self::burdensNode};
                            if ($burdensNode->{self::burdensTypesNode}->{self::noBurdens}->getName()!=='') { // 'no burdens' is selected
                                $this->insertElementBefore(self::burdensNoDescription, $burdensRisksNode->{self::risksNode});
                                $burdensRisksNode->{self::burdensNoDescription} = (string)$burdensNode->{self::descriptionNode};
                                $this->removeElement(self::descriptionNode, $burdensNode);
                            }
                            // compensation (update of nodes)
                            $compensationNode = $measureTimePointNode->{self::compensationNode}[0];
                            $compensationArray = $this->xmlToArray($compensationNode);
                            $selections = $compensationArray[self::compensationTypeNode];
                            if ($selections!=='' && !array_key_exists(self::compensationNo, $selections)) { // at least one type except 'no compensation' was selected
                                $tempArray = [self::compensationTypeNode => []];
                                foreach ($selections as $selection => $infos) {
                                    $tempArray[self::compensationTypeNode] = array_merge($tempArray[self::compensationTypeNode], [$selection => '']); // 'type' key only contains the selections
                                    $tempVal = $selection.self::descriptionCap;
                                    $tempArray[$tempVal] = $infos; // separate array for further information about selection
                                    if ($selection===self::compensationMoney) {
                                        $tempArray[self::moneyFurther] = $infos[self::moneyFurther];
                                    }
                                    unset($tempArray[$tempVal][self::awardingNode]);
                                    $tempArray[$selection.self::awardingNode] = $infos[self::awardingNode]; // separate array for awarding of selection
                                }
                                $tempArray[self::terminateNode] = $compensationArray[self::terminateNode];
                                $compensationVoluntary = $compensationArray[self::compensationVoluntaryNode] ?? ''; // may not exist depending on the loaded version
                                $tempArray[self::compensationVoluntaryNode] = array_merge([self::chosen => $compensationVoluntary], $compensationVoluntary==='0' ? [self::descriptionNode => ''] : []);
                                $tempArray[self::compensationTextNode] = $compensationArray[self::compensationTextNode];
                                $this->arrayToXml($tempArray, $compensationNode);
                            }
                            // data privacy (update of nodes)
                            $this->removeElement(self::dataReuseNode, $privacyNode); // was mistakenly created in addMeasurement
                            if ($this->checkElement(self::dataOnlineNode,$privacyNode)) {
                                $dataOnlineNode = $privacyNode->{self::dataOnlineNode};
                                $tempVal = (string) $dataOnlineNode;
                                $hasProcessing = $this->checkElement(self::dataOnlineProcessingNode,$privacyNode);
                                $description = $hasProcessing ? ((string) $privacyNode->{self::dataOnlineProcessingNode}) : '';
                                $this->removeAllChildNodes($dataOnlineNode);
                                $dataOnlineNode->addChild(self::chosen,$tempVal);
                                if ($hasProcessing) {
                                    $dataOnlineNode->addChild(self::descriptionNode,$description);
                                }
                            }
                            // in version 1.2.1, compensationVoluntary was added. As the structure of the compensation nodes has changed in version 2.0.0, the compensationVoluntary node was already added there and therefore does not need to be added here (i.e., no need for a check of $minor<'2')
                            if ($isMinorSmaller3) { // updates for versions before 1.3.0
                                // information -> must be updated after updating the nodes
                                $pre = (string)$informationNode->{self::pre};
                                $isPre = $pre==='0';
                                if ($isPre || $pre==='1' && ((string)$informationNode->{self::post})==='0') { // either pre or post information -> add question for document translation
                                    $this->addChosenNode($informationNode, self::documentTranslationNode);
                                }
                                // consent
                                if (!$isPre) { // no pre information -> remove description for participants
                                    $this->removeElement(self::terminateConsParticipationNode, $consentNode);
                                }
                            }
                            // groups
                            $groupsArray = $this->xmlToArray($groupsNode);
                            $examined = $groupsArray[self::examinedPeopleNode];
                            $minAge = $groupsArray[self::minAge];
                            if (($minAge==='' || intval($minAge)>17) && ($examined==='' || count($examined)===1 && array_key_exists(self::healthyExaminedNode, $examined))) { // remove people description node
                                $this->removeElement(self::peopleDescription, $groupsNode);
                            }
                            // measures
                            $textsNode = $measureTimePointNode->{self::textsNode};
                            $hasTexts = count($textsNode->children())>0;
                            // move procedure node from texts to measures
                            $this->insertElementBefore(self::procedureNode, $measuresNode->{self::measuresNode});
                            if ($hasTexts) {
                                $measuresNode->{self::procedureNode} = (string)$textsNode->{self::procedureNode};
                            }
                            $this->removeElement(self::procedureNode, $textsNode);
                            // total duration at most 30 minutes -> remove terminate description
                            $terminateNode = $compensationNode->{self::terminateNode};
                            if ($terminateNode->getName()!=='' && $this->xmlToArray($terminateNode)[self::chosen]===self::terminateNothing && $this->getDuration($this->xmlToArray($measuresNode->{self::durationNode}))<=30) {
                                $this->removeElement(self::descriptionNode, $terminateNode);
                            }
                            // texts
                            if ($hasTexts && $isConflict) { // add conflict description
                                $textsNode->addChild(self::conflictTextNode, $conflictDescription);
                            }
                            $this->updateNodesByReviewProcess($request, $measureTimePointNode, self::reviewFullDocs); // set full docs to avoid removing a lot of inputs
                        } elseif ($is200) { // updates for version 2.0.0
                            $hasPost = $this->checkElement(self::post,$informationNode);
                            $postNode = $hasPost ? $informationNode->{self::post} : null;
                            // remove document translation if information is oral
                            if ($this->checkElement(self::documentTranslationNode,$informationNode) && ($this->checkElement(self::preType,$informationNode) && ((string) $informationNode->{self::preType})===self::informationOral || $hasPost && ((string) $postNode->{self::chosen})==='0' && ((string) $postNode->{self::descriptionNode})===self::informationOral)) {
                                $this->removeElement(self::documentTranslationNode, $informationNode);
                            }
                            // add question for presence of contributors for shortNoDocs
                            if ($isShortNoDocs) {
                                $this->insertElementBefore(self::presenceNode,$measuresNode->{self::durationNode});
                            }
                        } // is200
                        // updates for version before 2.2.1
                        if ($isSmaller221 && $this->checkElement(self::createNode,$privacyNode)) {
                            $createNode = $privacyNode->{self::createNode};
                            if (((string) $createNode->{self::chosen})===self::createSeparate) { // verification is separate node and not asked for all review types
                                if (in_array($reviewProcess,self::reviewQuestions[self::privacyNode][self::createVerificationNode])) {
                                    $verification = (string) $createNode->{self::descriptionNode};
                                    if ($this->checkElement(self::createVerificationNode,$privacyNode)) { // loaded version was before 2.0.0 -> node was already added
                                        $privacyNode->{self::createVerificationNode} = $verification;
                                    }
                                    else {
                                        $privacyNode->addChild(self::createVerificationNode,$verification);
                                    }
                                }
                                $this->removeElement(self::descriptionNode,$createNode);
                            }
                        }
                        // updates for version before 2.3.0
                        if ($committeeType===self::committeeEUB && in_array((string) $coreDataNode->{self::applicant}->{self::position}, self::positionsStudentPhd) && !$this->checkElement(self::supervisor,$coreDataNode)) { // supervisor is obligatory for student/phd independent of qualification
                            $this->insertElementBefore(self::supervisor,$coreDataNode->{self::conflictNode},self::applicantContributorsInfosTypes);
                            $contributors = $this->getContributorsArray($this->xmlToArray($xml));
                            $this->updateContributor($contributors,[self::supervisor => []],self::supervisor);
                            $request->getSession()->set(self::contributorsSessionName,[0 => $contributors]); // (temporarily) set contributors in session because updateProjectdetailsContributor may need it
                            $this->updateProjectdetailsContributor($request,$xml,'',[],false,true); // update contributors in projectdetails
                            $this->addAllContributorsNodes($xml,$contributors); // update contributors in Contributors
                        }
                    } // foreach measure time point
                } // foreach group
            } // foreach study
        } // if isSmaller221
    }

    /** Gets the array containing all contributors.
     * @param array $xmlArray array containing the information about the entire application
     * @return array array containing all contributors
     */
    protected function getContributorsArray(array $xmlArray): array
    {
        return $this->addZeroIndex($xmlArray[self::contributorsNodeName][self::contributorNode]);
    }

    /** Creates a string indicating the duration or an int indicating the total time.
     * @param array $durations array containing the durations
     * @param bool $returnTotal if true, the total time is returned
     * @param bool $isMultiple true if multiple measure time points exist. May only be provided if $returnTotal is false
     * @return string|int duration string if $returnTotal ist false, the total time otherwise
     */
    protected function getDuration(array $durations, bool $returnTotal = true, bool $isMultiple = true): string|int
    {
        $measureTime = $this->getIntFromString($durations[self::durationMeasureTime],0);
        $breaks = $this->getIntFromString($durations[self::durationBreaks],0);
        $total = $breaks+$measureTime;
        return $returnTotal ? $total : $this->translateStringPDF('participation.durationContent',['multiple' => $this->getStringFromBool($isMultiple), 'total' => $total===0 ? 'X' : $total, self::durationMeasureTime => $measureTime>0 ? $measureTime : 'X', self::durationBreaks => $breaks]);
    }

    /** Translates a string using the 'pdf' domain.
     * @param string $string String to be translated. Must be a valid key in the translation file
     * @return string the translated string
     */
    protected function translateStringPDF(string $string, array $parameters = []): string
    {
        return self::$translator->trans($string,$parameters,'pdf');
    }

    /** Gets the tool version from the xml-file.
     * @param SimpleXMLElement $xml root node of the xml-file
     * @return string tool version
     */
    protected function getToolVersion(SimpleXMLElement $xml): string
    {
        return (string)($xml->attributes()->{self::toolVersionAttr});
    }

    /** Checks the inputs of data privacy to determine the parameters for data reuse.
     * @param array $privacyArray array containing the data privacy information
     * @return array array with following parameters: bool isAnonymized: whether personal research data are anonymized, bool isPurposeReuse: whether personal research data are kept for reuse, bool dataReuse: whether the privacy document should is/can be created by the tool ('tool') or not ('noTool'), string personal: how the data is processed
     */
    protected function getPrivacyReuse(array $privacyArray): array
    { // added here because it is used by updateNodesByReviewProcess
        $returnParams = ['personal' => 'noTool', 'isAnonymized' => false, 'isPurposeReuse' => false, self::dataReuseNode => 'noTool'];
        if (array_key_exists(self::createNode,$privacyArray)) {
            $create = $privacyArray[self::createNode][self::chosen];
            $isAnonymous = $create==='anonymous';
            $isTool = $create===self::createTool && in_array($privacyArray[self::responsibilityNode] ?? '', ['', self::responsibilityOnlyOwn, self::privacyNotApplicable]) && in_array($privacyArray[self::transferOutsideNode] ?? '', ['', 'no', self::privacyNotApplicable]) && ($privacyArray[self::markingNode][self::chosen] ?? '')!==self::markingOther;
            $returnParams[self::dataReuseNode] = $isTool || $isAnonymous ? 'tool' : 'noTool';
            $isPurposeReuse = false;
            if ($isTool) {
                if (in_array($privacyArray[self::dataPersonalNode] ?? '', self::dataPersonal)) { // research data are/may be personal
                    if (array_key_exists(self::personalKeepNode, $privacyArray)) {
                        $personalKeep = $privacyArray[self::personalKeepNode];
                        $isPurposeReuse = $personalKeep!=='' && array_key_exists(self::personalKeepReuse, $personalKeep);
                        $returnParams['isPurposeReuse'] = $isPurposeReuse;
                    }
                    $anonymizationArray = $privacyArray[self::anonymizationNode];
                    if ($anonymizationArray!=='' && !array_key_exists(self::anonymizationNo, $anonymizationArray)) { // personal research data are anonymized (also true if not yet answered
                        $returnParams['isAnonymized'] = true;
                        $isStorageDelete = ($privacyArray[self::storageNode][self::chosen] ?? '')===self::storageDelete;
                        if ($isStorageDelete) { // original data are deleted immediately
                            $personal = 'immediately';
                        } else { // original data are kept
                            $personal = $isPurposeReuse ? 'purpose' : 'keep';
                        }
                    } else { // no anonymization of personal research data
                        $personal = $isPurposeReuse ? 'purpose' : 'personal';
                    }
                } else { // research data are anonymous
                    $isMarkingPersonal = false;
                    foreach ([self::markingNode, self::markingNode.self::markingSuffix] as $marking) {
                        $tempArray = $privacyArray[$marking] ?? [];
                        $isMarkingPersonal = $isMarkingPersonal || ($tempArray[self::chosen] ?? '')===self::markingName || in_array($tempArray[self::codePersonal] ?? '', self::markingDataResearchTypes);
                    }
                    $personal = $isMarkingPersonal ? 'marking' : 'anonymous';
                }
                $returnParams['personal'] = $personal;
            } elseif ($isAnonymous) {
                $returnParams['personal'] = 'anonymous';
            }
        }
        return $returnParams;
    }

    /** Updates the applicant and the supervisor.
     * @param array $contributors array containing all contributors
     * @param array $data array containing the submitted data
     * @param string $type must equal 'applicant' or 'supervisor'
     */
    protected function updateContributor(array &$contributors, array $data, string $type): void
    {
        $dataType = $data[$type];
        $tempArray = [];
        foreach (self::applicantContributorsInfosTypes as $info) {
            $tempArray[$info] = $dataType[$info] ?? '';
        }
        if ($type===self::applicant) {
            $contributors[0][self::infosNode] = $tempArray;
        } else { // supervisor
            $tasks = $contributors[1][self::taskNode] ?? '';
            if ($tasks!=='' && array_key_exists(self::supervisorNode,$tasks)) { // supervisor already exists
                $contributors[1][self::infosNode] = $tempArray;
            } else { // supervisor does not exist -> add as second contributor
                $this->addSupervisor($contributors,$tempArray);
            }
        }
    }

    /** Sets the string for the first inclusion criterion.
     * @param SimpleXMLElement $groups groups node
     * @param string $locale locale to be used
     * @return void
     */
    protected function setFirstInclusion(SimpleXMLElement $groups, string $locale): void
    {
        $groupsArray = $this->xmlToArray($groups);
        if (array_key_exists(self::criteriaIncludeNode,$groupsArray)) {
            $minAge = $groupsArray[self::minAge];
            $minAge = (int) ($minAge!=='' ? $minAge : -2);
            $maxAge = (int)($groupsArray[self::maxAge]); // will be 0 if empty
            $maxAge = $maxAge===0 ? -2 : $maxAge;
            $limit = $maxAge===-1 ? 'noUpperLimit' : ($minAge===$maxAge || in_array(-2,[$minAge,$maxAge]) ? 'sameLimit' : 'limits');
            $firstInclusion = $this->getFirstInclusion($this->getAddressee($groupsArray),$limit,$minAge,$maxAge,$locale);
            if ($minAge===-2) {
                $firstInclusion = substr_replace($firstInclusion,$maxAge>0 ? $maxAge : 'X' ,strpos($firstInclusion,'-2'),2);
            }
            $groups->{self::criteriaIncludeNode}->{self::criteriaNode}->{self::criteriaIncludeNode.'0'} = $firstInclusion;
        }
    }

    /** Translates the first inclusion criterion.
     * @param string $addressee addressee
     * @param string $limit age limits
     * @param string $minAge min age
     * @param string $maxAge max age
     * @param string $locale locale to be used for translation
     * @return string translated first inclusion criterion
     */
    protected function getFirstInclusion(string $addressee, string $limit, string $minAge, string $maxAge, string $locale): string
    {
        return self::$translator->trans('projectdetails.pages.groups.criteria.include.addressee',[self::addressee => $addressee, 'limits' => $limit, 'minAge' => $minAge, 'maxAge' => $maxAge],'messages',$locale);
    }

    /** Adds the legal nodes to the xml-document. Which nodes are added depends on the consent, the location and the loan question.
     * @param SimpleXMLElement $legalNode node where the legal nodes get added
     * @param array $measureArray array containing the current measure time point
     * @return void
     */
    protected function addLegalNodes(SimpleXMLElement $legalNode, array $measureArray): void
    {
        if ($measureArray[self::informationNode][self::pre]==='0') { // information is pre
            $measuresArray = $measureArray[self::measuresNode];
            $loanArray = $measuresArray[self::loanNode];
            $isConsent = $this->getAnyConsent($measureArray[self::consentNode][self::consentNode][self::chosen]);
            $isLocationPresence = $isConsent && !in_array($measuresArray[self::locationNode][self::chosen],[self::locationOnline,'']);
            $legalNodes = [];
            if (!$this->checkElement(self::liabilityNode,$legalNode) && $isConsent) { // consent is given
                $legalNodes = [self::liabilityNode,'insurance'];
            }
            if (!$this->checkElement(self::apparatusNode,$legalNode) && ($isLocationPresence || $isConsent && $loanArray[self::chosen]==='0' || $this->getTemplateChoice($this->getLoanReceipt($loanArray)))) { // in presence or loan with consent or with receipt
                $legalNodes[] = self::apparatusNode;
            }
            if (!$this->checkElement(self::insuranceWayNode,$legalNode) && $isLocationPresence) { // consent is given and in presence
                $legalNodes[] = self::insuranceWayNode;
            }
            foreach ($legalNodes as $type) {
                $this->addChosenNode($legalNode,$type);
            }
        }
    }

    /** Checks if the consent is either written, digital, or oral.
     * @param array|string $consent either the array containing all elements of the consent page or the type of consent
     * @return bool true if consent is either written, digital, or oral, false otherwise
     */
    protected function getAnyConsent(array|string $consent): bool
    {
        return in_array(is_array($consent) ? $consent[self::consentNode][self::chosen] : $consent,self::consentTypesAny);
    }

    /** Gets the choice of the loan receipt.
     * @param array $loanArray array containing the loan nodes
     * @return string loan receipt choice or empty string if no choice was made or the array key does not exist
     */
    protected function getLoanReceipt(array $loanArray): string
    {
        return $loanArray[self::loanReceipt][self::chosen] ?? '';
    }

    /** Adds an array to an xml-document. First, all children of the element are removed. Then, for each key in $array, a child with the name of the key is added if the key is not equal to 'language'. If the value itself is an array, the method is called recursively with the value as the new array. Otherwise, the content of the node is set to the value.
     * @param array $array array that is added to the xml-document
     * @param SimpleXMLElement $element node where the children get appended
     * @return void
     */
    protected function arrayToXml(array $array, SimpleXMLElement $element): void
    {
        $this->removeAllChildNodes($element);
        foreach ($array as $key => $value) {
            if ($key!==self::language) {
                $element->addChild($key);
                if (is_array($value)) {
                    $this->arrayToXml($value,$element->$key);
                } else {
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
    protected function insertElementBefore(string $name, SimpleXMLElement $element, array $children = []): void
    {
        try {
            $dom = dom_import_simplexml($element);
            $ownerDoc = $dom->ownerDocument;
            $newNode = $dom->parentNode->insertBefore($ownerDoc->createElement($name), $dom);
            foreach ($children as $child) {
                $newNode->appendChild($ownerDoc->createElement($child));
            }
        } catch (\DOMException) {}
    }

    /** Checks if an xml-element exists and if so, removes it.
     * @param string $name name of the element to be removed
     * @param SimpleXMLElement $element the parent element of the element to be removed
     * @return void
     */
    protected function removeElement(string $name, SimpleXMLElement $element): void
    {
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
    protected function checkElement(string $name, SimpleXMLElement $element): bool
    {
        return $element->{$name}->getName()!=='';
    }

    /** For each value in \$nodes, a child of \$element with the same name is created.
     * @param SimpleXMLElement $element node where the children get appended
     * @param array $nodeNames names of the children
     * @return void
     */
    protected function addChildNodes(SimpleXMLElement $element, array $nodeNames): void
    {
        foreach ($nodeNames as $name) {
            $element->addChild($name);
        }
    }

    /** Removes all child nodes from the element.
     * @param SimpleXMLElement $element Node whose children are removed
     * @return void
     */
    protected function removeAllChildNodes(SimpleXMLElement $element): void
    {
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
    protected function saveDocumentInSession(Session $session, string $key, SimpleXMLElement $element): void
    {
        $session->set($key,array_merge($session->get($key) ?? [],[$element->asXML()]));
    }

    // functions that are called by methods in the above section

    /** Converts all children of $node to array elements where the node name is the key and the node value is the value.
     * @param SimpleXMLElement $element node whose children are converted to an array
     * @return array of the node
     */
    protected function xmlToArray(SimpleXMLElement $element): array
    { // added here because it is used by updateNodesByReviewProcess
        return $this->convertEmptyArray(json_decode(json_encode($element),true));
    }

    /** Each element in the array whose value is an empty array is converted to an empty string.
     * @param $array array array whose elements are checked
     * @return array the input array where empty array values are empty strings
     */
    private function convertEmptyArray(array $array): array
    { // called by preceding function
        foreach ($array as $key => $value) {
            if ($value==[]) {
                $array[$key] = '';
            } elseif (is_array($value)) {
                $array[$key] =  $this->convertEmptyArray($value);
            }
        }
        return $array;
    }

    /** Adds or sets the attribute to the root node containing the tool version.
     * @param SimpleXMLElement $xml xml-file
     * @return void
     */
    protected function setToolVersion(SimpleXMLElement $xml): void
    {
        if (!isset($xml->attributes()[self::toolVersionAttr])) {
            $xml->addAttribute(self::toolVersionAttr,self::toolVersion);
        } else {
            dom_import_simplexml($xml)->setAttribute(self::toolVersionAttr,self::toolVersion);
        }
    }
}