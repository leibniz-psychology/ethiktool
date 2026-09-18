<?php

namespace App\Traits\Contributors;

use App\Traits\PageTrait;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;

/** Contains all constants that are needed for the Projektbeteiligte page, mainly the names for the form widgets and nodes. */
trait ContributorsTrait
{
    use PageTrait;

    // node names
    protected const eMailNode = 'eMail';
    protected const phoneNode = 'phone';
    protected const tasksNodes = ['leader', 'research', 'experiment', 'contact', 'data', 'supervision', 'other'];
    protected const taskLeader = 'leader'; // must equal one value in $taskNodes
    protected const taskData = 'data'; // must equal one value in $taskNodes
    protected const taskSupervision = 'supervision'; // must equal one value in $taskNodes
    // other variables
    protected const otherDescription = 'otherDescription';
    protected const otherTask = 'other'; // must be the same value as the key in "tasks"
    protected const tasksTypes = ['leader' => 'contributors.tasks.leader', 'research' => 'contributors.tasks.research', 'experiment' => 'contributors.tasks.experiment', 'contact' => 'contributors.tasks.contact', 'data' => 'contributors.tasks.data', 'supervision' => 'contributors.tasks.supervision', 'other' => 'contributors.tasks.other']; // must be the same keys as in $tasksNode
    protected const tasksMandatory = ['leader','experiment','contact','data','supervision']; // mandatory tasks. Must equal the keys from $tasksTypes and in the translation file (contributors->tasks)

    // methods

    /** Updates the contributor in projectdetails.
     * @param SimpleXMLElement $appNode root node of the application
     * @param int|string $id id of contributor to be edited or removed; empty string if new contributor is added
     * @param array $tasks array containing the tasks of the removed or edited contributor
     * @param boolean $isRemoved true if a contributor or task was removed, false otherwise
     */
    protected function updateProjectdetailsContributor(Request $request, SimpleXMLElement $appNode, int|string $id, array $tasks, bool $isRemoved): void
    {
        $projectdetailsNode = $appNode->{self::projectdetailsNodeName};
        $isMulti = $this->getMultiStudyGroupMeasure($appNode);
        if ($isMulti) {
            foreach ($this->addZeroIndex($this->xmlToArray($projectdetailsNode)[self::studyNode]) as $studyID => $study) {
                foreach ($this->addZeroIndex($study[self::groupNode]) as $groupID => $group) {
                    foreach ($this->addZeroIndex($group[self::measureTimePointNode]) as $measureID => $measure) {
                        $contributorProjectdetailsArray = $measure[self::contributorNode] ?? '';
                        if ($contributorProjectdetailsArray!=='') {
                            $newIndices = [];
                            foreach (self::tasksNodes as $task) {
                                $indices = explode(',', $contributorProjectdetailsArray[$task]);
                                if ($indices[0]!='') { // at least one contributor was selected for the current task
                                    foreach ($indices as $curIndex => $contributorIndex) {
                                        if ($contributorIndex==$id && ($isRemoved || !array_key_exists($task, $tasks))) { // contributor or tasks was removed
                                            unset($indices[$curIndex]);
                                        } elseif ($isRemoved && $contributorIndex>$id) { // decrease index as a contributor with a smaller index was removed
                                            --$indices[$curIndex];
                                        }
                                    }
                                }
                                $newIndices[$task] = implode(',', $indices);
                            }
                            $this->arrayToXml($newIndices, $projectdetailsNode->{self::studyNode}[$studyID]->{self::groupNode}[$groupID]->{self::measureTimePointNode}[$measureID]->{self::contributorNode});
                        }
                    }
                }
            }
        } else { // one study with one group with one measure time point -> select all tasks
            $this->setProjectdetailsContributor($request, $appNode);
        }
    }

    /** Creates a contributor node for each element in $contributors.
     * @param SimpleXMLElement $appNode root node of the application
     * @param array $contributorArray keys: indices of the contributors, values: infos and tasks of the contributor
     * @param string $committeeType current committee
     */
    protected function addAllContributorsNodes(SimpleXMLElement $appNode, array $contributorArray, string $committeeType): void
    {
        $contributorsNode = $appNode->{self::contributorsNodeName};
        $this->removeAllChildNodes($contributorsNode);
        $infosMandatory = $this->getApplicantContributorsInfos($committeeType,false);
        foreach ($contributorArray as $contributor) {
            $this->addContributor($contributorsNode, $contributor,$infosMandatory);
        }
    }

    /** Creates a new contributor node and adds content to it.
     * @param SimpleXMLElement $element node where the new contributor node gets appended
     * @param array<string, mixed> $contributor array containing two sub-arrays, one for the infos and one for the tasks
     * @param array $infosMandatory mandatory infos
     */
    protected function addContributor(SimpleXMLElement $element, array $contributor, array $infosMandatory): void
    {
        $node = $element->addChild(self::contributorNode);
        $infosNode = $node->addChild(self::infosNode);
        $tempArray = $contributor[self::infosNode];
        foreach ($infosMandatory as $value) {
            $infosNode->addChild($value,htmlspecialchars($tempArray[$value]));
        }
        $infosNode->addChild(self::position,$tempArray[self::position]);
        if (array_key_exists(self::phoneNode,$tempArray)) {
            $infosNode->addChild(self::phoneNode,$tempArray[self::phoneNode]);
        }
        $tasksNode = $node->addChild(self::taskNode);
        foreach (($contributor[self::taskNode] ?: []) as $task => $value) {
            $tasksNode->addChild($task, $task===self::otherTask ? htmlspecialchars((string) $value) : '');
        }
    }
}