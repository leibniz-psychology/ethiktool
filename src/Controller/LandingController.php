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

    private array $pages = [];
    private array $names = [];
    private array $choices = [];
    private array $isMultiple = [false,false,false];

    #[Route('/landing', name: 'app_landing')]
    public function showLanding(Request $request): Response {
        $session = $request->getSession();
        $landingArray = $session->get(self::landing);
        $title = $landingArray['page'] ?? ''; // if a link is double-clicked, the key does not exist
        $isProjectdetails = $title===self::projectdetailsNodeName;
        $appNode = $this->getXMLfromSession($session);
        if (!$appNode || $landingArray===null) { // page was opened before a proposal was created/loaded or landing was opened by the URL instead of clicking a link
            return $this->redirectToRoute('app_main');
        }
        $projectdetailsNode = $appNode->{self::projectdetailsNodeName};
        $study = $this->addZeroIndex($this->xmlToArray($projectdetailsNode)[self::studyNode]);
        $isStudy = array_key_exists(self::studyID,$landingArray); // true if overview of one study
        $isGroup = array_key_exists(self::groupID,$landingArray); // true if overview of one group
        $isMeasure = array_key_exists(self::measureID,$landingArray); // true if overview of one measure point in time
        $IDs = [$isStudy ? $landingArray[self::studyID]-1 : null, $isGroup ? $landingArray[self::groupID]-1 : null, $isMeasure ? $landingArray[self::measureID]-1 : null];
        $this->pages = $this->setSubMenu($title,$request,$IDs[0],$IDs[1],$IDs[2],false); // menu points;
        $numGroups = $isStudy ? count($this->addZeroIndex($study[$IDs[0]][self::groupNode])) : 1;
        $numMeasures = $isGroup ? count($this->addZeroIndex($this->addZeroIndex($this->addZeroIndex($study)[$IDs[0]][self::groupNode])[$IDs[1]][self::measureTimePointNode])) : 1;
        // isMultiple: true if multiple studies (element 0) or multiple groups of the current study (element 1) or multiple measure points in time of the current group (element 2) are created. If the current level is the overview of one study or group, then the following elements are false
        $this->isMultiple = [$isProjectdetails && count($study)>1, $isProjectdetails && $isStudy && $numGroups>1, $isProjectdetails && $isGroup && $numMeasures>1];
        if ($isProjectdetails) {
            if (!$isStudy) { // overview over studies
                $this->setVariables(self::studyNode,$study);
            }
            elseif (!$isGroup) { // overview of groups
                $this->setVariables(self::groupNode,$this->addZeroIndex($study[$IDs[0]][self::groupNode]));
            }
            elseif (!$isMeasure) { // overview of measure time points
                $this->setVariables(self::measureTimePointNode);
            }
        }

        $landing = $this->createFormAndHandleRequest(LandingType::class,[self::language => $session->get(self::language)],$request,
            ['attr' => [self::isProjectdetails => $isProjectdetails, self::isStudy => $isStudy, self::isGroup => $isGroup, self::isMeasure => $isMeasure, self::numStudies => count($study), self::numGroups => $numGroups, 'numMeasures' => $numMeasures], self::dummyParams => [self::dropdownChoices => $this->choices]]);
        if ($landing->isSubmitted()) { // language has changed or a link was clicked
            $data = $landing->getData();
            $submitDummy = $data[self::submitDummy]; // submitDummy as string
            if (!str_contains($submitDummy,"loadedXML:")) { // if a proposal is loaded, the submit dummy contains the entire xml
                $isRemove = str_contains($submitDummy, self::remove);
                $isCopy = str_contains($submitDummy, 'copyClicked');
                if (str_contains($submitDummy, 'newClicked') || $isCopy) { // new study, group, or measure point in time should be created
                    $addNode = $projectdetailsNode; // node where the new one gets appended
                    $nodeName = self::studyNode;
                    if (str_contains($submitDummy, self::studyID)) { // new group oder measure point in time
                        $addNode = $addNode->{self::studyNode}[$IDs[0]];
                        $nodeName = self::groupNode;
                        if (str_contains($submitDummy, self::groupID)) { // new measure point in time
                            $addNode = $addNode->{self::groupNode}[$IDs[1]];
                            $nodeName = self::measureTimePointNode;
                        }
                    }
                    $addMeasurement = true;
                    $newName = $data[self::newStudyGroupName] ?? '';
                    if ($nodeName!==self::measureTimePointNode) { // if double-clicked, prevent creating two studies/groups with the same name
                        foreach ($this->addZeroIndex($this->xmlToArray($addNode)[$nodeName]) as $studyGroup) {
                            $curName = $studyGroup[self::nameNode];
                            if ($curName!=='' && $curName===$newName) {
                                $addMeasurement = false;
                            }
                        }
                    }
                    if ($addMeasurement) {
                        $this->addMeasurement($addNode, $nodeName, $newName, $isCopy ? $data[self::copy] : null); // if 'new' is clicked, but an existing is selected, 'copy' is not null
                    }
                } elseif ($isRemove || str_contains($submitDummy, self::edit)) { // study, group, or measure point in time should be removed or study or group name should be changed
                    // logic: $submitDummy has the form 'key:value\r\nkey:value'. Cut everything before the 'remove' such that the string starts with 'remove:index\r\n' (substr call). Then split the string by the colon such that the first element is 'remove' and the second element starts with 'index\r\n' (first explode call). Then, split again by "\r" such that the first element contains the index (second explode call). Finally, convert it to an integer ((int) call). Same for 'edit'.
                    // The only characters before the 'remove' are the page, but there must be no other 'remove' string before the one containing the index. Same for 'edit'.
                    $index = (int)(explode("\r", explode(':', substr($submitDummy, strpos($submitDummy, $isRemove ? self::remove : self::edit)))[1])[0]);
                    $editRemoveNode = $projectdetailsNode->{self::studyNode}[$isStudy ? $IDs[0] : $index];
                    if ($isStudy) { // remove group or measure time point or edit group name
                        $editRemoveNode = $editRemoveNode->{self::groupNode}[($isRemove && $isGroup) ? $IDs[1] : $index];
                    }
                    if ($isRemove) {
                        if ($isGroup) { // remove measure time point
                            $editRemoveNode = $editRemoveNode->{self::measureTimePointNode}[$index];
                        }
                        if ($editRemoveNode!==null) { // if the remove button is double-clicked, the element may already be removed
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
                            if ($childNodes->count()>1 && in_array($childNodes->item(1)->nodeName, [self::studyNode, self::groupNode, self::measureTimePointNode])) { // if the remove button is double-clicked and an element after the one to be removed exists, it would also be removed
                                $dom->parentNode->removeChild($dom);
                                $studies = $this->addZeroIndex($this->xmlToArray($projectdetailsNode->{self::studyNode}));
                                if (count($studies)===1) {
                                    $groups = $this->addZeroIndex($studies[0][self::groupNode]);
                                    if (count($groups)===1 && count($this->addZeroIndex($groups[0][self::measureTimePointNode]))===1) { // only one study with one group with one measure time point remaining
                                        $this->setProjectdetailsContributor($request, $appNode);
                                    }
                                }
                            }
                        }
                    } else { // edit study or group name
                        $editRemoveNode->{self::nameNode} = $data[self::editName.$index];
                    }
                }
            }
            return $this->saveDocumentAndRedirect($request,$appNode);
        }
        return $this->render('landing.html.twig', $this->setRenderParameters($request,$landing,
            ['menu' => $isProjectdetails ? $this->pages : $this->pages[self::subPages],
             'page' => lcfirst($title),
             'id' => $IDs,
             'isMultiple' => $this->isMultiple,
             self::studyName => $isStudy ? $study[$IDs[0]][self::nameNode] : '',
             self::groupName => $isGroup ? $this->addZeroIndex($study[$IDs[0]][self::groupNode])[$IDs[1]][self::nameNode] : '',
             'names' => $this->names,
             self::pageErrors => $this->getErrors($request,$title)],self::landing));
    }

    /** Sets the variables needed for creating the links on the landing page.
     * @param string $type level. Must equal 'study', 'group', or 'measureTimePoint'
     * @param array $subElements array with the subelements of the current level
     * @return void
     */
    private function setVariables(string $type, array $subElements = []): void {
        $isStudy = $type===self::studyNode;
        $isMeasure = $type===self::measureTimePointNode;
        foreach ($this->pages as $index => $page) {
            $name = !$isMeasure ? $subElements[$index][self::nameNode] : '';
            $this->names[$index] = $name;
            $this->choices[$name ?: $this->translateString('projectdetails.headings.'.$type).($this->isMultiple[$isMeasure ? 2 : (!$isStudy ? 1 : 0)] ? ' '.($index+1) : '')] = $index;
        }
    }
}