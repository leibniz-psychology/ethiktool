<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\DataSourceType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DataSourceController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.self::dataSourceNode,self::dataSourceNode)]
    public function showDataSource(Request $request): Response
    {
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session,setRecent: true);
        $routeParams = $request->get('_route_params');
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measureNode===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $dataSourceNode = $measureNode->{self::dataSourceNode};
        $isLoadNew = $this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams))[self::dataSourceNode][self::originNode][self::chosen]===self::originNew;
        $modal = [];
        $projectdetailsPrefix = 'projectdetails.pages.';
        if ($isLoadNew) {
            $tempPrefix = $projectdetailsPrefix.self::dataSourceNode.'.'.self::originNode.'.modal.';
            $buttonPrefix = $tempPrefix.'buttons.';
            $modal = ['prefix' => $tempPrefix, 'modalWidth' => true, 'leftButton' => $buttonPrefix.'save', 'middleButton' => $buttonPrefix.'cancel', 'rightButton' => $buttonPrefix.'undo', 'middleCont' => false, 'modalID' => 'originModal', 'link' => 'app_dataSource', 'params' => ['isMultiple' => $this->getStringFromBool($this->getMultiStudyGroupMeasure($appNode))], 'submitParams' => ['routeIDs' => $routeParams], 'routeParams' => $routeParams];
        }
        $reviewProcess = $this->getCurrentReviewProcess($appNode);
        $hasDocs = !(str_contains($reviewProcess,self::reviewProcessShort) && in_array($this->getCommitteeType($session),self::reviewShortChoose) || str_contains($reviewProcess,'Requested'));

        $dataSource = $this->createFormAndHandleRequest(DataSourceType::class,$this->xmlToArray($dataSourceNode),$request,[self::dummyParams => ['isNotBegun' => !in_array($this->getCommitteeType($session),self::begunCommittees), 'hasDocs' => $hasDocs]]);
        if ($dataSource->isSubmitted()) {
            $submitDummy = $request->request->all()['data_source'][self::submitDummy];
            if (str_contains($submitDummy,self::preview) && str_contains($submitDummy,'app_dataSource') && !str_contains($submitDummy,'#')) { // download xml file after origin has changed from 'new' to 'existing' or go to data source page of another time point
                $isSame = true;
                foreach (explode("\n",$submitDummy) as $type) {
                    if (str_contains($type,'ID')) { // studyID, groupID, or measureID
                        $split = explode(':',$type); // e.g., 'studyID:1'
                        if ($routeParams[$split[0]]!==trim($split[1])) {
                            $isSame = false;
                        }
                    }
                }
                if ($isSame) { // download xml file
                    return $this->getDownloadResponse($session,getSecondLast: true);
                }
            }
            $originNew = $this->getDataAndConvert($dataSource,$dataSourceNode)[self::originNode][self::chosen];
            [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
            if (!$isLoadNew && $originNew===self::originNew) {
                // groups
                $groupsNode = $measureNodeNew->{self::groupsNode};
                $this->addChildNodes($groupsNode,[self::minAge,self::maxAge,self::examinedPeopleNode,self::peopleDescription]);
                $includeNode = $groupsNode->addChild(self::criteriaIncludeNode);
                $this->addChildNodes($includeNode,[self::noCriteriaNode,self::criteriaNode]);
                $includeNode->{self::criteriaNode}->addChild(self::criteriaIncludeNode.'0',str_replace('0','X',$this->translateString($projectdetailsPrefix.self::groupsNode.'.criteria.include.addressee',[self::addressee => 'other', 'limits' => 'sameLimit', 'minAge' => '0'])));
                $this->addChildNodesChosen($groupsNode,[self::closedNode]);
                $this->addChildNodes($groupsNode->addChild(self::criteriaExcludeNode),[self::noCriteriaNode,self::criteriaNode]);
                $this->addChildNodes($groupsNode->addChild(self::sampleSizeNode),[self::sampleSizeTotalNode,self::sampleSizeFurtherNode,self::sampleSizePlanNode]);
                $this->addChildNodes($groupsNode,[self::recruitment,self::recruitmentFurther]);
                // information
                $measureNodeNew->{self::informationNode}->addChild(self::pre);
                // consent
                $consentNode = $measureNodeNew->{self::consentNode};
                $this->addChildNodesChosen($consentNode,[self::voluntaryNode,self::consentNode]);
                $this->addChosenNode($consentNode,self::terminateConsNode);
                $this->addChosenNode($consentNode,self::terminateParticipantsNode);
                $consentNode->addChild(self::terminateCriteriaNode);
                // measures
                $measuresNode = $measureNodeNew->{self::measuresNode};
                $this->addChildNodes($measuresNode,[self::procedureNode,self::measuresNode,self::measuresDescription,self::interventionsNode]);
                $this->addChosenNode($measuresNode,self::otherSourcesNode);
                $this->addChosenNode($measuresNode,self::loanNode);
                $this->addChosenNode($measuresNode,self::locationNode)->addChild(self::descriptionNode);
                $this->addChosenNode($measuresNode,self::presenceNode);
                $this->addChildNodes($measuresNode->addChild(self::durationNode),self::durationTypes);
                // burdens/risks
                $burdensRisksNode = $measureNodeNew->{self::burdensRisksNode};
                $this->addChildNodes($burdensRisksNode->addChild(self::burdensNode),[self::burdensTypesNode]);
                $this->addChildNodes($burdensRisksNode->addChild(self::risksNode),[self::risksTypesNode]);
                $this->addChosenNode($burdensRisksNode,self::burdensRisksContributorsNode);
                $this->addChildNodesChosen($burdensRisksNode,[self::findingNode,self::feedbackNode]);
                // compensation
                $measureNodeNew->{self::compensationNode}->addChild(self::compensationTypeNode);
                // data privacy
                $privacyNode = $measureNodeNew->{self::privacyNode};
                $privacyNode->addChild(self::processingNode);
                $this->addChosenNode($privacyNode,self::createNode);
                // data reuse
                $measureNodeNew->{self::dataReuseNode}->addChild(self::confirmIntroNode);
                // contributor
                $contributorNode = $measureNodeNew->{self::contributorNode};
                if (count($contributorNode->children())===0) {
                    foreach (self::tasksNodes as $task) {
                        $contributorNode->addChild($task);
                    }
                }
                $this->updateNodesByReviewProcess($request,$measureNodeNew,$reviewProcess);
            } elseif ($isLoadNew && $originNew===self::originExisting) { // new data to existing data -> remove nodes
                foreach (self::projectdetailsNodes as $nodeName) {
                    $measureNodeNew->{$nodeName} = '';
                }
            }
            $isNotLeave = !$this->getLeavePage($dataSource,$session,self::dataSourceNode);
            return $this->saveDocumentAndRedirect($request,$isNotLeave ? $appNode : $appNodeNew, $isNotLeave ? $appNodeNew : null); // $appNodeNew is only defined if $originChanged is true
        }

        return $this->render('Projectdetails/dataSource.html.twig',$this->setRenderParameters($request,$dataSource,
            ['originSourcesTypes' => self::originSourcesTypes,
             'resultPositiveTypes' => self::committeeResultPositiveTypes,
             'dataSourceAccessTypes' => self::dataSourceAccessTypes,
             'legitimizationTypes' => self::legitimizationTypes,
             'hasDocs' => $hasDocs,
             'modal' => $modal],'projectdetails.dataSource',true));
    }
}