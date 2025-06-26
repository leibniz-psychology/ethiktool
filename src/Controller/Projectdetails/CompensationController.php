<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\CompensationType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompensationController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'compensation', name: 'app_compensation')]
    public function showCompensation(Request $request): Response {
        $routeParams = $request->get('_route_params');
        $session = $request->getSession();
        $appNode = $this->getXMLfromSession($session);
        $measure = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measure===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $addressee = $this->getAddresseeFromRequest($request);
        $compensationNode = $measure->{self::compensationNode}[0];
        $isCodeCompensationLoad = $this->checkCompensationAwarding($this->xmlToArray($this->getMeasureTimePointNode($this->getXMLfromSession($session,true),$routeParams)->{self::compensationNode}));
        // check if inputs were made for the code compensation question on data privacy
        $inputArray = $this->setInputArray();
        if ($this->checkInput($this->xmlToArray($measure)[self::privacyNode][self::codeCompensationNode] ?? '',[self::chosen => ''])) {
            $this->addInputPage('pages.projectdetails.',self::privacyNode,$inputArray);
        }
        $textInput = $this->setInputHint($inputArray);

        $compensation = $this->createFormAndHandleRequest(CompensationType::class, $this->xmlToArray($compensationNode),$request,
            [self::addresseeTrans => $this->translateString('projectdetails.addressee'.($addressee===self::addresseeParticipants ? '.participants.' : '.both.').$addressee),
             self::dummyParams => [self::addressee => $addressee]]);
        if ($compensation->isSubmitted()) {
            $data = $this->getDataAndConvert($compensation,$compensationNode);
            [$appNodeNew,$measureNodeNew] = $this->getClonedMeasureTimePoint($appNode,$routeParams);
            $isCodeCompensation = $this->checkCompensationAwarding($data);
            $privacyNode = $measureNodeNew->{self::privacyNode};
            if (!$isCodeCompensationLoad && $isCodeCompensation) { // eventually add nodes
                $privacyArray = $this->xmlToArray($privacyNode);
                $tempArray = $privacyArray[self::purposeResearchNode] ?? '';
                if (array_key_exists(self::dataPersonalNode,$privacyArray) && ($tempArray==='' || !array_key_exists(self::purposeCompensation,$tempArray))) { // add nodes
                    $this->addChosenNode($privacyNode,self::codeCompensationNode);
                }
            }
            elseif ($isCodeCompensationLoad && !$isCodeCompensation) { // remove nodes
                $this->removeElement(self::codeCompensationNode,$privacyNode);
            }
            $isNotLeave = !$this->getLeavePage($compensation,$session,self::compensationNode);
            return $this->saveDocumentAndRedirect($request,$isNotLeave ? $appNode : $appNodeNew, $isNotLeave ? $appNodeNew : null);
        }
        return $this->render('Projectdetails/compensation.html.twig',
            $this->setParameters($request,$appNode,
                [self::content => $compensation,
                 self::pageTitle => 'projectdetails.compensation',
                 'types' => self::compensationTypes,
                 'textInput' => $textInput]));

//        return $this->createFormAndHandleSubmit(CompensationType::class,$request,[self::compensationNode],
//            [self::pageTitle => 'projectdetails.compensation',
//             'types' => self::compensationTypes],
//            [self::addresseeTrans => $this->translateString('projectdetails.addressee'.($addressee===self::addresseeParticipants ? '.participants.' : '.both.').$addressee),
//             self::dummyParams => [self::addressee => $addressee]]);
    }
}