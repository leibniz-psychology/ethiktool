<?php

namespace App\Controller\Main;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class FooterController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client) {
        $this->client = $client;
    }

    #[Route('/footer', name: 'footer')]
    public function getFooterFromAssets(Request $request): Response {
        $content = null;
        try {
            $response = $this->client->request('GET', 'https://www.lifp.de/assets/collapsible-footer/index.php?framework=css&lang='.$request->getLocale());
            $statusCode = $response->getStatusCode();
            if ($statusCode===200) {
                $content = $response->getContent();
            }
        }
        catch (ExceptionInterface $exception) {
        }
        return new Response($content,200,['content-type'=>'text/html']);
    }
}