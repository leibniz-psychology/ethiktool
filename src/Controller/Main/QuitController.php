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
    public function showQuit(Request $request): Response
    {
        $session = $request->getSession();
        $hasQuitSession = $session->has(self::quit); // true if file should be or was downloaded
        if (!$hasQuitSession) { // quit without saving
            $session->clear();
        }

        $quit = $this->createForm(QuitType::class,options: [self::dummyParams => [self::quit => $hasQuitSession]])->handleRequest($request);
        if ($quit->isSubmitted()) {
            $data = $request->request->all()['quit'];
            if (count($data)===1) { // save file before quit or language was changed
                if (!str_contains($data['submitDummy'],self::language)) { // save file before quit
                    return $this->getDownloadResponse($session);
                } else {
                    if ($hasQuitSession) {
                        $session->set(self::quit,'');
                    }
                    return $this->saveDocumentAndRedirect($request,$this->getXMLfromSession($session));
                }
            } elseif (array_key_exists('backToMain',$data)) {
              return $this->redirectToRoute('app_main');
            } else { // quit software
                $session->remove(self::quit);
                return $this->redirectToRoute('app_quit');
            }
        }
        return $this->render('Main/quit.html.twig', $this->setRenderParameters($request,$quit,[self::pageTitle => 'quit', self::content => $quit, self::quit => $hasQuitSession ? 'download' : self::quit, 'hasDownload' => $hasQuitSession && $session->get(self::quit)==='download']));
    }
}