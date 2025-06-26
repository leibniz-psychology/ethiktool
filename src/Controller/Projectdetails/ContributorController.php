<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\ContributorType;
use App\Traits\Contributors\ContributorsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContributorController extends ControllerAbstract
{
    use ContributorsTrait;

    #[Route(self::routePrefix.'contributor', name: 'app_contributor')]
    public function showContributor(Request $request): Response {
        $tasks = array_combine(self::tasksNodes,array_fill(0,count(self::tasksNodes),[]));
        $session = $request->getSession();
        if (!$session->has(self::docName) || !$this->getMultiStudyGroupMeasure($this->getXMLfromSession($session))) {
            return $this->redirectToRoute('app_main');
        }
        foreach ($this->getContributors($session) as $index => $contributor) {
            foreach ($contributor[self::taskNode] as $curTask => $value) {
                if ($curTask!==self::applicationNode && $curTask!==self::supervisorNode) {
                    $name = $contributor[self::infosNode][self::nameNode];
                    $name = $name==='' ? $this->translateString('projectdetails.pages.contributor.noName') : $name;
                    $tasks[$curTask][$curTask.$index] = $name.($curTask===self::otherTask ? (' ('.$value.')') : '');
                }
            }
        }

        return $this->createFormAndHandleSubmit(ContributorType::class,$request,[self::contributorNode],
            [self::pageTitle => 'projectdetails.contributor',
             self::taskNode => $tasks,
             'tasksMandatory' => self::tasksMandatory],
            [self::dummyParams => [self::taskNode => $tasks]]);
    }
}