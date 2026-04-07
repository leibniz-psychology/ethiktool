<?php

namespace App\Controller;

use App\Abstract\ControllerAbstract;
use App\Form\LandingType;
use App\Traits\LandingTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingController extends ControllerAbstract
{
    use LandingTrait;

    private const copy = 'copy';
    private const remove = 'remove';
    private const edit = 'edit';

    #[Route(self::landing,self::landing)]
    public function showLanding(Request $request): Response
    {
        $session = $request->getSession();
        $landingArray = $session->get(self::landing);
        $title = $landingArray['page'] ?? ''; // if a link is double-clicked, the key does not exist
        $isProjectdetails = $title===self::projectdetailsNodeName;
        $appNode = $this->getXMLfromSession($session);
        if (!$appNode || $landingArray===null) { // page was opened before a proposal was created/loaded or landing was opened by the URL instead of clicking a link
            return $this->redirectToRoute('app_main');
        }
        $projectdetailsNode = $appNode->{self::projectdetailsNodeName};
        $hasStudyID = array_key_exists(self::studyID,$landingArray);
        $isPageOverview = $isProjectdetails && $hasStudyID; // true if overview of one measure time point
        $isProjectdetailsOverview = $isProjectdetails && !$hasStudyID;
        $IDs = [null,null,null];
        $allStudies = $this->addZeroIndex($this->xmlToArray($projectdetailsNode)[self::studyNode]); // $projectdetailsNode converted to array, but every sub-element has a numerical index (and a key 'name' which is not used) and the measure time point level has an array which contains the name (not used) and arrays with information about the pages
        $projectdetailsPrefix = self::landing.'.projectdetails.';
        $nameTrans = []; // name of elements where the given name may be appended
        $newTrans = []; // label for button for creating a new element
        $copyTrans = []; // hint above text field for copying an element
        $removeTrans = []; // heading and text for the remove modal
        $pageHeading = (!$isProjectdetailsOverview ? $this->translateString('pages.'.self::landing) : ''); // heading on page
        $removePrefix = $projectdetailsPrefix.'removeModal.';
        $currentElement = $allStudies;
        foreach ([self::studyNode,self::groupNode,self::measureTimePointNode] as $index => $type) {
            $isNotStudy = $type!==self::studyNode;
            $typeParam = ['type' => $type];
            $typeTrans = ucfirst($this->translateString('projectdetails.headings.'.$type)).' ';
            $nameTrans[$type] = $typeTrans;
            $newTrans[$type] = $this->translateString($projectdetailsPrefix.'buttons.new.'.$type);
            $copyTrans[$type] = $this->translateString($projectdetailsPrefix.'copyHint',$typeParam);
            $removeTrans[$type] = ['title' => $this->translateString($removePrefix.'title',$typeParam), 'text' => $this->translateString($removePrefix.'content',$typeParam)];
            if ($isPageOverview) {
                $curID = $landingArray[$type!==self::measureTimePointNode ? $type.'ID' : self::measureID]-1;
                $currentElement = $isNotStudy ? $this->addZeroIndex($currentElement[$type])[$curID] : $currentElement[$curID];
                $IDs[$index] = $curID;
                $curName = $currentElement[self::nameNode];
                $pageHeading .= ($isNotStudy ? ' / ' : '').$typeTrans.($curID+1).($curName!=='' ? ' ('.$curName.')' : '');
            }
        }
        $tempVal = $pageHeading.($isPageOverview ? '' : $this->translateString('pages.'.lcfirst($title).'.title'));
        $tabName = $this->translateString('pages.tabName');
        if ($isPageOverview && !$this->getMultiStudyGroupMeasure($appNode)) {
            $studyDetailsTrans = $this->translateString('projectdetails.sidebar');
            $pageHeading = $studyDetailsTrans;
            $tabName .= $studyDetailsTrans;
        } else {
            $tabName .= $tempVal;
        }
        $pageHeading = !$isPageOverview ? $tempVal : $pageHeading;

        $pages = [];
        // names of the elements, split by levels. E.g.: names has several values: one index for each study and 'names' containing the names of all studies. Each of these elements has the same structure (one index for each group and one element with the names of all groups of this study). Each of these elements has an array as the value containing the names of the measure time points of this group
        $names = []; // needed for overview of projectdetails structure
        if ($isProjectdetailsOverview) { // overview of projectdetails structure
            $studyNames = [];
            foreach ($allStudies as $studyIndex => $curStudy) {
                $studyNames[] = $curStudy[self::nameNode];
                $groupNames = [];
                $allGroups = $this->addZeroIndex($curStudy[self::groupNode]);
                foreach ($allGroups as $groupIndex => $group) {
                    $groupNames[] = $group[self::nameNode];
                    $measureNames = [];
                    $measures = $this->addZeroIndex($group[self::measureTimePointNode]);
                    foreach ($measures as $measure) {
                        $measureNames[] = $measure[self::nameNode];
                    }
                    $names[$studyIndex][$groupIndex] = $measureNames;
                    $allGroups[$groupIndex][self::measureTimePointNode] = $measures;
                }
                $names[$studyIndex][self::nameNode] = $groupNames;
                $allStudies[$studyIndex][self::groupNode] = $allGroups;
            }
            $names[self::nameNode] = $studyNames;
        } else { // overview of application data pages or measure time point pages
            try {
                $pages = $this->setSubMenu($title, $request, $IDs[0], $IDs[1], $IDs[2], false);
                if (!$isProjectdetails) {
                    $pages = $pages[self::subPages];
                }
            } catch (\Throwable) {
                return $this->setErrorAndRedirect($session);
            }
        }

        $landing = $this->createFormAndHandleRequest(LandingType::class,[self::language => $session->get(self::language)],$request,
            [self::dummyParams => [self::isProjectdetails => $isProjectdetails, self::isMeasure => array_key_exists(self::measureID,$landingArray),'allStudies' => $allStudies]]);
        if ($landing->isSubmitted()) { // language has changed or a link was clicked
            $data = $landing->getData();
            $submitDummy = $data[self::submitDummy]; // submitDummy as string
            if (!str_contains($submitDummy,'loadedXML:')) { // if a proposal is loaded, the submit dummy contains the entire xml
                $isNew = str_contains($submitDummy,'new') && !str_contains($submitDummy,'app_newForm');
                $isCopy = str_contains($submitDummy,self::copy);
                $isNewCopy = $isNew || $isCopy;
                $isRemove = str_contains($submitDummy, self::remove);
                $isEditRemove = $isRemove || str_contains($submitDummy, self::edit);
                $name = ''; // name of new element
                if ($isNewCopy || $isEditRemove) {
                    // logic: $submitDummy has the form 'key:value\r\nkey:value'. Cut everything before the 'remove' such that the string starts with 'remove:index\r\n' (substr call). The split the string by "\r" such that the first element contains "remove:..." (first explode). Then split again by the colon such that the second element contains the indices (second explode). Then split again by the underscore to get the individual indices (third explode). Same for 'edit'.
                    $indicesString = explode(':', explode("\r", substr($submitDummy, strpos($submitDummy, $isNewCopy ? ($isNew ? self::newElement : self::copy) : ($isRemove ? self::remove : self::edit))))[0])[1];
                    $indices = explode('_', $indicesString);
                    $name = !$isRemove ? $data[($isNewCopy ? self::newElement : self::editName).'_'.(!$isCopy ? $indicesString : implode('_',array_slice($indices,0,count($indices)-1)))] : '';
                }
                if ($isNewCopy) {
                    $newIndices = [0,0,0];
                    $studies = $this->addZeroIndex($this->xmlToArray($projectdetailsNode->{self::studyNode}));
                    $appendNode = $projectdetailsNode; // node where the new element is added
                    $studyIndex = $indices[0];
                    $copyIndex = '';
                    $appendNodeName = self::studyNode; // type of element to be added
                    if ($isNew) {
                        if ($studyIndex!=='') { // group or measure time point is created
                            $studyIndex = intval($studyIndex);
                            $newIndices[0] = $studyIndex; // values are used -> if $isNew is true, $isCopy will be false, i.e., $newIndices will be accessed
                            $groups = $this->addZeroIndex($studies[$studyIndex][self::groupNode]);
                            $appendNodeName = self::groupNode;
                            $appendNode = $appendNode->{self::studyNode}[$studyIndex];
                            if (array_key_exists(1, $indices)) { // new measure time point
                                $groupIndex = intval($indices[1]);
                                $newIndices[1] = $groupIndex;
                                $newIndices[2] = count($this->addZeroIndex($groups[$groupIndex][self::measureTimePointNode]));
                                $appendNodeName = self::measureTimePointNode;
                                $appendNode = $appendNode->{self::groupNode}[$groupIndex];
                            } else {
                                $newIndices[1] = count($groups);
                            }
                        } else { // new study is created
                            $newIndices[0] = count($studies);
                        }
                    } else { // element is copied
                        $copyIndex = intval($studyIndex); // index of element that is copied
                        if (array_key_exists(1,$indices)) { // group or measure time point is copied
                            $appendNodeName = self::groupNode;
                            $appendNode = $appendNode->{self::studyNode}[1]!==null ? $appendNode->{self::studyNode}[$copyIndex] : $appendNode->{self::studyNode};
                            $copyIndex = intval($indices[1]);
                            if (array_key_exists(2,$indices)) { // measure time point is copied
                                $appendNodeName = self::measureTimePointNode;
                                $appendNode = $appendNode->{self::groupNode}[1]!==null ? $appendNode->{self::groupNode}[$copyIndex] : $appendNode->{self::groupNode};
                                $copyIndex = intval($indices[2]);
                            }
                        }
                    }
                    $isCopy = $copyIndex!=='';
                    $this->addMeasurement($appendNode,$appendNodeName,$name,$isCopy ? $copyIndex : null);
                    if (!$isCopy) {
                        $this->updateNodesByReviewProcess($request,$projectdetailsNode->{self::studyNode}[$newIndices[0]]->{self::groupNode}[$newIndices[1]]->{self::measureTimePointNode}[$newIndices[2]],$this->getCurrentReviewProcess($appNode));
                    }
                } elseif ($isEditRemove) {
                    // logic: $submitDummy has the form 'key:value\r\nkey:value'. Cut everything before the 'remove' such that the string starts with 'remove:index\r\n' (substr call). The split the string by "\r" such that the first element contains "remove:..." (first explode). Then split again by the colon such that the second element contains the indices (second explode). Then split again by the underscore to get the individual indices (third explode). Same for 'edit'.
                    $editRemoveNode = $projectdetailsNode->{self::studyNode}[intval($indices[0])];
                    $isNotStudy = array_key_exists(1,$indices);
                    try {
                        if ($isNotStudy) {
                            $editRemoveNode = $editRemoveNode->{self::groupNode}[intval($indices[1])];
                            if (array_key_exists(2, $indices)) {
                                $editRemoveNode = $editRemoveNode->{self::measureTimePointNode}[intval($indices[2])];
                            }
                        }
                    } catch (\Throwable) {} // if remove button is double-clicked, it may already be removed
                    if (!$isRemove) {
                        $editRemoveNode->{self::nameNode} = $name;// $data[self::editName.'_'.$indicesString];
                    } else { // element is removed
                        if ($editRemoveNode!==null) {
                            $dom = dom_import_simplexml($editRemoveNode);
                            $childNodes = $dom->parentNode->childNodes;
                            $index = 0;
                            while ($childNodes->length>$index) { // remove '#text' nodes
                                $child = $childNodes->item($index);
                                if ($child->nodeName==='#text') {
                                    $child->remove();
                                } else {
                                    ++$index;
                                }
                            }
                            if ($childNodes->count()>($isNotStudy ? 2 : 1) && in_array($childNodes->item(1)->nodeName, [self::studyNode, self::groupNode, self::measureTimePointNode])) { // if the remove button is double-clicked and an element after the one to be removed exists, it would also be removed
                                $dom->parentNode->removeChild($dom);
                                if (!$this->getMultiStudyGroupMeasure($appNode)) { // only one study with one group with one measure time point remaining
                                    $this->setProjectdetailsContributor($request, $appNode);
                                }
                            }
                        }
                    }
                }
            }
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
        return $this->render('landing.html.twig', $this->setRenderParameters($request,$landing,
            ['menu' => $pages,
             'tabName' => $tabName,
             'pageHeading' => $pageHeading,
             'page' => lcfirst($title),
             'IDs' => $IDs,
             'allStudies' => $allStudies,
             'nameTrans' => $nameTrans,
             'newTrans' => $newTrans,
             'copyTrans' => $copyTrans,
             'removeTrans' => $removeTrans,
             'allNames' => $names,
             'isPageOverview' => $isPageOverview,
             self::pageErrors => $this->getErrors($request,$title)],self::landing));
    }
}