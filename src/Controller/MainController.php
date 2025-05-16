<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


final class MainController extends AbstractController
{

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    )
    {
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/login', name: 'app_login')]
    public function login(): \Symfony\Component\HttpFoundation\RedirectResponse
    {

        $redirectUri = 'http://127.0.0.1:8000/home';
        $urlData = [
            'response_type' => 'code',
            'client_id' => $_ENV['SPOTIFY_CLIENT_ID'],
            'redirect_uri' => $redirectUri,
            'scope' => 'user-read-private user-read-email',
        ];

        return $this->redirect("https://accounts.spotify.com/authorize" . "?" . http_build_query($urlData));

    }

    #[Route('/home', name: 'app_home')]
    public function home(Request $request)
    {
        // 1. Récupération du code renvoyé par Spotify
        $code = $request->query->get('code');
        dd($code);
    }


    /**
     * @throws TransportExceptionInterface
     */
    private function getToken(): string
    {
        try {
            $request = $this->httpClient->request(
                'POST',
                'https://accounts.spotify.com/api/token',
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => http_build_query([
                        'grant_type' => 'client_credentials',
                        'client_id' => $_ENV['SPOTIFY_CLIENT_ID'],
                        'client_secret' => $_ENV['SPOTIFY_CLIENT_SECRET'],
                    ]),
                ]
            );
        } catch (\Exception $e) {
            return $e->getMessage();
        }


        $content = $request->getContent();
        $data = json_decode($content, true);
        return $data['access_token'];
    }
}
