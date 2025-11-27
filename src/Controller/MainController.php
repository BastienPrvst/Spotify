<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


final class MainController extends AbstractController
{

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    )
    {
    }

    #[Route('/', name: 'app_home')]
    public function home(Request $request) : Response
    {
        $session = $request->getSession();
        $accessToken = $session->get('spotify_access_token', '');
        $invalidateTime = $session->get('time_of_refresh', '');

        //Création du token
        if (!$accessToken) {
            return $this->redirectToRoute('app_login');
        }

        //Refresh du token si expiré
        if (new \DateTime() > $invalidateTime) {
            return $this->redirectToRoute('app_refresh');
        }

        try {

            $tracksTime = $request->query->get('tracksTime', 'medium_term');
            $artistTime = $request->query->get('artistTime', 'medium_term');
            //infos utilisateur
            $userProfile = $this->httpClient->request(
                'GET',
                'https://api.spotify.com/v1/me',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ]
                ]
            )->toArray();

            //top 10 artiste année

            $top10Artist = $this->httpClient->request(
                'GET',
                'https://api.spotify.com/v1/me/top/artists?time_range=' . $artistTime . '&limit=10',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],

                ]
            )->toArray();

            //top 10 tracks année

            $top10Tracks = $this->httpClient->request(
                'GET',
                'https://api.spotify.com/v1/me/top/tracks?time_range='. $tracksTime . '&limit=10',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                ]
            )->toArray();

            return $this->render('main/home.html.twig', [
                'userProfile' => $userProfile,
                'top10Tracks' => $top10Tracks,
                'top10Artist' => $top10Artist,
                'tracksTime' => $tracksTime,
                'artistTime' => $artistTime,
            ]);

        }catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|DecodingExceptionInterface|TransportExceptionInterface $e) {
            return new Response('Erreur : ' . $e->getMessage());
        }

    }

    #[Route('/login', name: 'app_login')]
    public function login(): RedirectResponse
    {
        $redirectUri = 'http://127.0.0.1:8000/authenticate';

        $urlData = [
            'response_type' => 'code',
            'client_id' => $_ENV['SPOTIFY_CLIENT_ID'],
            'redirect_uri' => $redirectUri,
            'scope' => 'user-read-private user-read-email user-top-read user-read-playback-state',
        ];

        return $this->redirect("https://accounts.spotify.com/authorize?" . http_build_query($urlData));
    }


    #[Route('/authenticate', name: 'app_authenticate')]
    public function authenticate(Request $request): Response
    {
        if ($request->query->has('error')) {
            return new Response('Erreur OAuth : ' . $request->query->get('error'));
        }

        $code = $request->query->get('code');
        if (!$code) {
            return new Response('Code manquant', 400);
        }

        $redirectUri = 'http://127.0.0.1:8000/authenticate';
        $credentials = base64_encode($_ENV['SPOTIFY_CLIENT_ID'] . ':' . $_ENV['SPOTIFY_CLIENT_SECRET']);

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://accounts.spotify.com/api/token',
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Authorization' => 'Basic ' . $credentials,
                    ],
                    'body' => http_build_query([
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $redirectUri,
                    ]),
                ]
            );

            $data = $response->toArray();
            $accessToken = $data['access_token'];
            $request->getSession()->set('spotify_access_info', $data);
            $request->getSession()->set('spotify_access_token', $accessToken);
            $request->getSession()->set('spotify_refresh_token', $data['refresh_token']);
            $invalidateTime = new \DateTime();
            $invalidateTime->modify('+1 hour');
            $request->getSession()->set('time_of_refresh', $invalidateTime);

            return $this->redirectToRoute('app_home');

        } catch (\Exception $e) {
            return new Response('Erreur : ' . $e->getMessage());
        }
    }

    #[Route('/refresh', name: 'app_refresh')]
    public function refreshToken(Request $request): Response
    {

        $credentials = base64_encode($_ENV['SPOTIFY_CLIENT_ID'] . ':' . $_ENV['SPOTIFY_CLIENT_SECRET']);

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://accounts.spotify.com/api/token',
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Authorization' => 'Basic ' . $credentials,
                    ],
                    'body' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $request->getSession()->get('spotify_refresh_token'),
                    ]
                ]
            );

            $data = $response->toArray();
            $accessToken = $data['access_token'];
            $request->getSession()->set('spotify_access_info', $data);
            $request->getSession()->set('spotify_access_token', $accessToken);
            $invalidateTime = new \DateTime();
            $invalidateTime->modify('+1 hour');
            $request->getSession()->set('time_of_refresh', $invalidateTime);

            return $this->redirectToRoute('app_home');

        }catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|DecodingExceptionInterface|TransportExceptionInterface $e) {
            return new Response('Erreur : ' . $e->getMessage());
        }
    }



}
