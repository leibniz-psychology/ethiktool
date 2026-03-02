<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuitController extends ControllerAbstract
{
    #[Route(self::quit,self::quit)]
    public function showQuit(Request $request): Response
    {
        $request->getSession()->clear();
        return $this->render('Main/quit.html.twig',[self::committeeParams => [self::committeeType => 'noCommittee'], self::toolVersionAttr => self::toolVersion, 'isUpdateTime' => $this->getUpdateTimeString()]);
    }
}