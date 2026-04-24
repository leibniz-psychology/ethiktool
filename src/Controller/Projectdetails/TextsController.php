<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\TextsType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TextsController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.self::textsNode,self::textsNode)]
    public function showTexts(Request $request): Response
    {
        $routeParams = $request->get('_route_params');
        $appNode = $this->getXMLfromSession($request->getSession());
        $measureNode = $this->getMeasureTimePointNode($appNode,$routeParams);
        if ($measureNode===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $measureArray = $this->xmlToArray($measureNode);
        $burdensRisksArray = $measureArray[self::burdensRisksNode];
        $isBurdens = $this->getBurdensOrRisks($burdensRisksArray,self::burdensNode)[0];
        $isRisks = $this->getBurdensOrRisks($burdensRisksArray,self::risksNode)[0];
        $addresseeParam = [self::addressee => $this->getAddresseeFromRequest($request)];
        $finding = $burdensRisksArray[self::findingNode];
        $isFinding = $finding[self::chosen]==='0';
        $compensation = $measureArray[self::compensationNode][self::compensationTypeNode];
        $information = $this->getInformationString($measureArray[self::informationNode]);
        $isNotInformationNoConsent = !(in_array($information,self::prePostArray) && $measureArray[self::consentNode][self::consentNode][self::chosen]===self::voluntaryConsentNo);

        return $this->createFormAndHandleSubmit(TextsType::class,$request,[self::textsNode],
            ['maxCharsIntroGoals' => 800,
             'maxCharsProCon' => 500,
             'maxCharsFinding' => 500,
             'maxCharsConflict' => 500,
             'introTemplateText' => $this->translateString('projectdetails.pages.'.self::textsNode.'.'.self::introNode.($isNotInformationNoConsent ? '.template' : '.noTemplate'),array_merge($addresseeParam,[self::informationNode => $information, self::projectTitle => $this->getProjectTitleParticipants($request->getSession()), self::routeIDs => $this->createRouteIDs([self::studyNode => $routeParams[self::studyID], self::groupNode => $routeParams[self::groupID], self::measureTimePointNode => $routeParams[self::measureID]])])),
             'isNotInformationNoConsent' => $isNotInformationNoConsent,
             'isBurdens' => $isBurdens,
             'isRisks' => $isRisks,
             'proTemplateEnd' => $this->translateString('projectdetails.pages.texts.pro.template.end',array_merge($addresseeParam,['compensation' => $this->getStringFromBool($compensation!=='' && count($compensation)>0 && !array_key_exists(self::compensationNo,$compensation))])),
             'conTemplateText' => $this->getConTemplateText($measureArray,false,true,$routeParams,true),
             'isConsent' => $isFinding && $finding[self::informingNode]===self::informingConsent,],
            [self::dummyParams => [
                'isNotInformationNoConsent' => $isNotInformationNoConsent,
                'isFinding' => $isFinding,
                'isBurdensRisks' => $isBurdens || $isRisks,
                'isConflict' => $this->xmlToArray($appNode->{self::appDataNodeName}->{self::coreDataNode})[self::conflictNode][self::chosen]==='0']]);
    }
}