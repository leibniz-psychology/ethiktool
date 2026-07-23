<?php

namespace App\Controller\Contributors;

use App\Abstract\ControllerAbstract;
use App\Form\Contributors\ContributorsType;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Contributors\ContributorsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContributorsController extends ControllerAbstract
{
    use ContributorsTrait;
    use AppDataTrait; // for updating the applicant in coreData

    #[Route('contributors','contributors')]
    public function showContributors(Request $request): Response
    {
        $session = $request->getSession();
        $allContributorsArrays = $session->get(self::contributorsSessionName); // all contributors arrays
        if ($allContributorsArrays===null) { // page was opened before a proposal was created/loaded
            return $this->redirectToRoute('app_main');
        }
        $contributorsArray = $allContributorsArrays[count($allContributorsArrays)-1]; // most recent contributors array
        $appNode = $this->getXMLfromSession($session);
        $committeeType = $this->getCommitteeType($session);
        $coreDataNode = $appNode->{self::appDataNodeName}->{self::coreDataNode};
        $applicantNode = $coreDataNode->{self::applicant};
        $positionOld = (string) $applicantNode->{self::position};
        $isSupervisorOld = $this->checkSupervisor($committeeType,$positionOld);
        $tasks = $this->getTasks($request);
        $possibleTasks = $tasks[0];

        $contributors = $this->createFormAndHandleRequest(ContributorsType::class,null,$request,[self::dummyParams => [self::taskNode => $possibleTasks]]);
        if ($contributors->isSubmitted()) {
            $dataContributors = $request->request->all()['contributors'];
            $submitDummy = $dataContributors[self::submitDummy];
            if (str_contains((string) $submitDummy,'modalSubmitButton')) { // contributor was added, edited, or removed. Must equal the name of the button in formModal.html.twig
                $submitType = explode(':',(string) $submitDummy)[1];
                $dataContributors[self::submitDummy] = '';
                $request->request->set('contributors',$dataContributors); // reset submit dummy to redirect to the same page
                $id = preg_replace('/\D/','',$submitType); // id of contributor to be edited or removed; empty string if new contributor is added
                $isRemoved = str_contains($submitType,'remove');
                $tasks = [];
                if (!$isRemoved) { // contributor was added or edited
                    $tempArray = [];
                    // infos
                    foreach (self::infosMandatory as $info) {
                        $tempArray[$info] = $dataContributors[$info];
                    }
                    foreach (self::institutionPosition as $info) {
                        $other = $info===self::institutionInfo ? self::institutionOther : self::positionOther;
                        $curInfo = $dataContributors[$info];
                        $tempArray[$info] = $curInfo===$other ? $dataContributors[$this->appendText($other)] : $curInfo;
                    }
                    $phone = $dataContributors[self::phoneNode];
                    if ($phone!=='') {
                        $tempArray[self::phoneNode] = $phone;
                    }
                    $newData[self::infosNode] = $tempArray;
                    //tasks
                    foreach (self::tasksNodes as $value) {
                        if (array_key_exists($value, $dataContributors)) {
                            $tasks[$value] = $value===self::otherTask ? $dataContributors[self::otherDescription] : '';
                        }
                    }
                    $newData[self::taskNode] = $tasks;
                    if (str_contains($submitType,'add')) { // new contributor -> str_contains because route will also be in this string
                        $contributorsArray = array_merge($contributorsArray, [count($contributorsArray) => $newData]);
                    } else { // contributor was edited
                        $contributorsArray[$id] = $newData;
                    }
                    // update applicant in coreData
                    if ($id==='0') {
                        $infos = &$contributorsArray[$id][self::infosNode];
                        if (!array_key_exists(self::phoneNode,$infos)) {
                            $infos[self::phoneNode] = '';
                        }
                        foreach (self::applicantContributorsInfosTypes as $info) { // update infos in core data
                            $applicantNode->{$info} = $infos[$info];
                        }
                        $position = $dataContributors[self::position];
                        if ($positionOld===self::positionsStudent && $position===self::positionsPhd && $committeeType===self::committeeEUB) { // position changed from student to phd -> remove position from other contributors that are supervisor
                            $this->removeContributorIndices($appNode,$this->removePhd($contributorsArray));
                        } elseif ($isSupervisorOld && !$this->checkSupervisor($committeeType,$position)) { // position changed such that no supervisor is needed anymore -> remove task 'supervision' from all contributors
                            $this->removeContributorIndices($appNode,$this->removeSupervision($contributorsArray),false);
                        }
                    }
                } else { // contributor was removed
                    unset($contributorsArray[$id]);
                    $contributorsArray = array_values($contributorsArray); // re-indexing
                }
                $session->set(self::contributorsSessionName, array_merge($allContributorsArrays,[$contributorsArray]));
                // update xml
                $this->updateProjectdetailsContributor($request,$appNode,$id,$tasks,$isRemoved); // update contributor in projectdetails
                $this->addAllContributorsNodes($appNode,$contributorsArray); // update contributor in contributors
            }
            return $this->saveDocumentAndRedirect($request,$appNode);
        } // if ($contributors->isSubmitted())
        [,,$positionsTranslated] = $this->setPositions($session);
        $phone = 'multiple.infos.'.self::phoneNode;
        $isQualification = $this->getQualification($this->xmlToArray($coreDataNode));
        return $this->render('Contributors/contributors.html.twig', $this->setRenderParameters($request,$contributors,
            ['isQualification' => $isQualification,
             'infos' => self::applicantContributorsInfosTypes,
             'tasks' => $possibleTasks,
             'tasksMandatory' => $tasks[1],
             'addSupervisionIcon' => !$isQualification && $isSupervisorOld,
             'contributorsArray' => $contributorsArray,
             'phoneLabel' => [$this->translateString($phone), $this->translateString($phone.'Optional')],
             'committeeStudent' => self::committeeStudent,
             'institutionTypes' => array_keys(self::institutionTypes),
             'positions' => $positionsTranslated],
            'contributors.contributors'));
    }
}