<?php

namespace App\Controller\Main;

use App\Abstract\ControllerAbstract;
use App\Form\Main\QuitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuitController extends ControllerAbstract
{
    #[Route('/quit', name: 'app_quit')]
    public function showQuit(Request $request): Response {
        $session = $request->getSession();
        if ($session->get(self::docName)===null) { // page was opened before a proposal was created/loaded
            return $this->redirectToRoute('app_main');
        }

        $quit = $this->createForm(QuitType::class)->handleRequest($request);
        if ($quit->isSubmitted()) {
            $data = $request->request->all()['quit'];
            if (array_key_exists('dummy',$data)) { // save file before quit
                return $this->getDownloadResponse($session);
            }
            else { // quit software
                if (array_key_exists('quit',$data)) { // quit after saving file
                    $session->clear();
                }
                return $this->redirectToRoute('app_main');
            }
        }
        return $this->render('Main/quit.html.twig', [self::pageTitle => 'quit', self::content => $quit]);
    }
}