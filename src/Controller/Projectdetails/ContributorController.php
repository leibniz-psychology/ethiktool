<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\ContributorType;
use App\Traits\Contributors\ContributorsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContributorController extends ControllerAbstract
{
    use ContributorsTrait;

    #[Route(self::routePrefix.self::contributorNode,self::contributorNode)]
    public function showContributor(Request $request): Response
    {
        $allTasks = $this->getTasks($request);
        $tasks = array_fill_keys($allTasks[0],[]);
        $session = $request->getSession();
        if (!$session->has(self::docName) || !$this->getMultiStudyGroupMeasure($this->getXMLfromSession($session))) {
            return $this->redirectToRoute('app_main');
        }
        foreach ($this->getContributors($session) as $index => $contributor) {
            foreach ($contributor[self::taskNode] as $curTask => $value) {
                $name = $contributor[self::infosNode][self::nameNode];
                $name = $name==='' ? $this->translateString('projectdetails.pages.contributor.noName') : $name;
                $tasks[$curTask][$curTask.$index] = $name.($curTask===self::otherTask ? (' ('.$value.')') : '');
            }
        }


        return $this->createFormAndHandleSubmit(ContributorType::class,$request,[self::contributorNode],
            [self::taskNode => $tasks,
             'tasksMandatory' => $allTasks[1]],
            [self::dummyParams => [self::taskNode => $tasks]]);
    }
}