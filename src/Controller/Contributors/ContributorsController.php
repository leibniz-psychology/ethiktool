<?php

namespace App\Controller\Contributors;

use App\Abstract\ControllerAbstract;
use App\Form\Contributors\ContributorsType;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Contributors\ContributorsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContributorsController extends ControllerAbstract
{
    use ContributorsTrait, AppDataTrait; // AppDataTrait for updating the applicant in coreData

    #[Route('/contributors', name: 'app_contributors')]
    public function showContributors(Request $request): Response {
        $session = $request->getSession();
        $allContributorsArrays = $session->get(self::contributorsSessionName); // all contributors arrays
        if ($allContributorsArrays===null) { // page was opened before a proposal was created/loaded
            return $this->redirectToRoute('app_main');
        }
        $contributorsArray = $allContributorsArrays[count($allContributorsArrays)-1]; // most recent contributors array
        $appNode = $this->getXMLfromSession($session);
        $coreDataNode = $appNode->{self::appDataNodeName}->{self::coreDataNode};
        $isQualification = ((string) $coreDataNode->{self::qualification} ?? '')==='0';
        // Check if any mandatory task is not selected for any contributor and create an error message, if so. Additionally, check if the applicant has more than one task.
        $missingTasksArray = ['',''];
        $missingFunction = 'contributors.errors.missingFunction';
        foreach ($contributorsArray as $index => $contributor) {
            $tasks = $contributor[self::taskNode];
            if ($tasks!=='' ) {
                $oneTask = count($tasks)===1;
                if ($index===0 && $oneTask) {
                    $missingTasksArray[0] = $this->translateString($missingFunction,['type' => self::applicant]);
                }
                elseif ($index===1 && array_key_exists(self::supervisorNode,$tasks) && $oneTask) {
                    $missingTasksArray[1] = $this->translateString($missingFunction,['type' => self::supervisor]);
                }
            }
        }

        $contributors = $this->createFormAndHandleRequest(ContributorsType::class,null,$request);
        if ($contributors->isSubmitted()) {
            $dataContributors = $request->request->all()['contributors'];
            $submitDummy = $dataContributors[self::submitDummy];
            if (str_contains($submitDummy,'modalSubmitButton')) { // contributor was added, edited, or removed. Must equal the name of the button in formModal.html.twig
                $submitType = explode(':',$submitDummy)[1];
                $dataContributors[self::submitDummy] = '';
                $request->request->set('contributors',$dataContributors); // reset submit dummy to redirect to the same page
                $id = preg_replace('/\D/','',$submitType); // id of contributor to be edited or removed; empty string if new contributor is added
                $isRemoved = str_contains($submitType,'remove');
                $tasks = [];
                if (!$isRemoved) { // contributor was added or edited
                    $positionOld = (string) $coreDataNode->{self::applicant}->{self::position};
                    $isStudentOld = $positionOld===self::positionsStudent || $positionOld===self::positionsPhd && $isQualification;
                    $isApplicant = $id==='0';
                    $isApplicantOrSupervisor = $isApplicant || $isStudentOld && $id==='1';
                    $tempArray = [];
                    // infos
                    foreach (self::infosMandatory as $info) {
                        $tempArray[$info] = $dataContributors[$info];
                    }
                    $position = $dataContributors[self::position] ?? self::positionsPhd; // if key does not exist, field is disabled which means that only possible position is phd
                    $tempArray[self::position] = $position===self::positionOther ? $dataContributors[$this->appendText(self::positionOther)] : $position;
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
                    }
                    else { // contributor was edited
                        if ($isApplicantOrSupervisor) { // applicant or supervisor
                            $newData[self::taskNode] = array_merge([$isApplicant ? self::applicationNode : self::supervisorNode => ''],$newData[self::taskNode]); // add applicant as task
                        }
                        $contributorsArray[$id] = $newData;
                    }
                    // update applicant or supervisor in coreData
                    if ($isApplicantOrSupervisor) {
                        $infos = &$contributorsArray[$id][self::infosNode];
                        if (!array_key_exists(self::phoneNode,$infos)) {
                            $infos[self::phoneNode] = '';
                        }
                        $node = $coreDataNode->{$isApplicant ? self::applicant : self::supervisor};
                        foreach (self::applicantContributorsInfosTypes as $info) { // update infos in core data
                            $node->{$info} = $infos[$info];
                        }
                        $isStudent = $position===self::positionsStudent || $position===self::positionsPhd && $isQualification;
                        if ($isApplicant) {
                            if (!$isStudentOld && $isStudent) { // position was changed to student or PhD
                                $this->insertElementBefore(self::supervisor,$coreDataNode->{self::projectStart},self::applicantContributorsInfosTypes);
                                $this->addSupervisor($contributorsArray);
                            }
                            elseif ($isStudentOld && !$isStudent) { // position was changed from student to something else
                                unset($contributorsArray[1][self::taskNode][self::supervisorNode]);
                                $this->removeElement(self::supervisor,$coreDataNode);
                            }
                        }
                    }
                }
                else { // contributor was removed
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
        [,,$positionsSupervisor,$positionsTranslated] = $this->setPositions($session);
        $infosPrefix = 'multiple.infos.';
        $institution = $infosPrefix.self::institutionInfo;
        $phone = $infosPrefix.self::phoneNode;
        return $this->render('Contributors/contributors.html.twig', $this->setRenderParameters($request,$contributors,
            ['positionsSupervisor' => $positionsSupervisor,
             'infos' => self::applicantContributorsInfosTypes,
             'tasks' => self::tasksNodes,
             'tasksMandatory' => self::tasksMandatory,
             'otherDescription' => self::otherDescription,
             'contributorsArray' => $contributorsArray,
             'missingTasks' => $missingTasksArray,
             'institutionLabel' => [$this->translateString($institution.'Applicant'),$this->translateString($institution)],
             'phoneLabel' => [$this->translateString($phone), $this->translateString($phone.'Optional')],
             'positions' => $positionsTranslated],
            'contributors.contributors'));
    }
}