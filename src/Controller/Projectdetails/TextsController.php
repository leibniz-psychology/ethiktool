<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\TextsType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TextsController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'texts', name: 'app_texts')]
    public function showTexts(Request $request): Response {
        $routeParams = $request->get('_route_params');
        $measureNode = $this->getMeasureTimePointNode($request,$routeParams);
        if ($measureNode===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $measureArray = $this->xmlToArray($measureNode);
        $burdensRisksArray = $measureArray[self::burdensRisksNode];
        $isBurdens = $this->getBurdensOrRisks($burdensRisksArray,self::burdensNode)[0];
        $isRisks = $this->getBurdensOrRisks($burdensRisksArray,self::risksNode)[0];
        $addressee = $this->getAddresseeFromRequest($request);
        $addresseeParam = [self::addressee => $addressee];
        $finding = $measureArray[self::burdensRisksNode][self::findingNode];
        $isFinding = $finding[self::chosen]==='0';
        $compensation = $measureArray[self::compensationNode][self::compensationTypeNode];
        $translationPrefix = 'projectdetails.pages.texts.';

        return $this->createFormAndHandleSubmit(TextsType::class,$request,[self::textsNode],
            [self::pageTitle => 'projectdetails.texts',
             'maxCharsIntroGoalsProcedure' => 800,
             'maxCharsProCon' => 500,
             'maxCharsFinding' => 500,
             'introTemplateText' => $this->translateString($translationPrefix.'intro.template',array_merge($addresseeParam,[self::informationNode => $this->getInformationString($measureArray[self::informationNode]), self::projectTitle => $this->getProjectTitleParticipants($request->getSession())])),
             'isBurdens' => $isBurdens,
             'isRisks' => $isRisks,
             'proTemplateEnd' => $this->translateString($translationPrefix.'pro.template.end',array_merge($addresseeParam,['compensation' => $this->getStringFromBool($compensation!=='' && count($compensation)>0 && !array_key_exists(self::compensationNo,$compensation))])),
             'conTemplateText' => $this->getConTemplateText($measureArray,false,true,$routeParams,true),
             'isConsent' => $isFinding && $finding[self::informingNode]===self::informingConsent],
            [self::addresseeTrans => $addressee, self::informationNode => $this->getInformation($request), self::dummyParams => ['isFinding' => $isFinding, 'isBurdensRisks' => $isBurdens || $isRisks]]);
    }
}