<?php

namespace App\Controller\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Form\Projectdetails\DataReuseType;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DataReuseController extends ControllerAbstract
{
    use ProjectdetailsTrait;

    #[Route(self::routePrefix.'dataReuse', name: 'app_dataReuse')]
    public function showDataReuse(Request $request): Response{
        $routeParams = $request->get('_route_params');
        $measure = $this->getMeasureTimePointNode($request,$routeParams);
        if ($measure===null) { // page was opened before a proposal was created/loaded or a non-existent study / group / measure time point was opened
            return $this->redirectToRoute('app_main');
        }
        $translationPrefix = 'projectdetails.pages.'.self::dataReuseNode.'.';
        $dataReuseHowPrefix = $translationPrefix.self::dataReuseHowNode.'.';
        // check data privacy
        $parameters = $this->getPrivacyReuse($this->xmlToArray($measure->{self::privacyNode}));
        $isPurposeReuse = $parameters['isPurposeReuse'];
        // data reuse how icons
        $dataReuseHowIconArray = [];
        $tempPrefix = $dataReuseHowPrefix.'hints.';
        foreach ($this->prefixArray(['0','1','2','3'],'class') as $type) {
            $dataReuseHowIconArray[self::dataReuseHowNode.$type] = $this->translateString($tempPrefix.$type);
        }

        $dataReuseTitle = $translationPrefix.self::dataReuseNode.'.title';
        $dataReuseHowTitle = $dataReuseHowPrefix.'title';
        $personalParamKeep = ['personal' => 'keep'];
        return $this->createFormAndHandleSubmit(DataReuseType::class, $request,[self::dataReuseNode],
            [self::pageTitle => 'projectdetails.dataReuse',
             'dataReuseHowHeading' => ['' => $this->translateString($dataReuseHowTitle,$parameters), self::personalKeepReuse => $this->translateString($dataReuseHowTitle,$personalParamKeep)],
             'dataReuseHint' => $this->translateString($translationPrefix.($isPurposeReuse ? self::dataReuseHowNode.'.hint' : self::dataReuseNode.'.hint.'.$parameters['personal']),['routeIDs' => $this->createRouteIDs([self::studyNode => $routeParams[self::studyID], self::groupNode => $routeParams[self::groupID], self::measureTimePointNode => $routeParams[self::measureID]])]),
             'dataReuseHowIconArray' => $dataReuseHowIconArray,
             'isPurposeReuse' => $isPurposeReuse,
             'personal' => $parameters['personal'],],
            [self::dummyParams => array_merge($parameters,['dataReuseHeading' => ['' => $this->translateString($dataReuseTitle,$parameters), self::personalKeepReuse => $this->translateString($dataReuseTitle,$personalParamKeep)]])]);
    }
}