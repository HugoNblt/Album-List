<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SpotifyService
{
    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(env: 'SPOTIFY_CLIENT_ID')] private string $clientId,
        #[Autowire(env: 'SPOTIFY_CLIENT_SECRET')] private string $clientSecret,
    ) {
    }

    /**
     * Récupère un token d'authentification temporaire auprès de Spotify
     */
    private function getAccessToken(): string
    {
        $response = $this->client->request('POST', 'https://accounts.spotify.com/api/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        return $response->toArray()['access_token'];
    }

    /**
     * Cherche des albums sur Spotify via une chaîne de caractères
     */
    public function searchAlbums(string $query): array
    {
        $token = $this->getAccessToken();

        $response = $this->client->request('GET', 'https://api.spotify.com/v1/search', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'query' => [
                'q' => $query,
                'type' => 'album',
                'limit' => 10, // On limite à 10 résultats pour ne pas surcharger la vue
            ],
        ]);

        // Retourne la liste des albums trouvés
        return $response->toArray()['albums']['items'] ?? [];
    }
    /**
     * Récupère un album spécifique via son ID Spotify
     */
    public function getAlbum(string $id): ?array
    {
        $token = $this->getAccessToken();
        $response = $this->client->request('GET', 'https://api.spotify.com/v1/albums/' . $id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return $response->getStatusCode() === 200 ? $response->toArray() : null;
    }
}