<?php

namespace App\Controller\Main;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
readonly class FooterController
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    #[Route('footer','footer')]
    public function getFooterFromAssets(Request $request): Response
    {
        $content = null;
        try {
            $response = $this->client->request('GET', 'https://www.lifp.de/assets/collapsible-footer/index.php?framework=css&lang='.$request->getLocale());
            $statusCode = $response->getStatusCode();
            if ($statusCode===200) {
                $content = $response->getContent();
            }
        } catch (ExceptionInterface) {}
        return new Response($content, Response::HTTP_OK,['content-type'=>'text/html']);
    }
}