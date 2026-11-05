<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les erreurs réseau / API remontent en Symfony\Contracts\HttpClient\Exception\ExceptionInterface :
 * les contrôleurs les interceptent pour afficher un message plutôt qu'une erreur 500.
 */
class SpotifyService
{
    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $cache,
        #[Autowire(env: 'SPOTIFY_CLIENT_ID')] private string $clientId,
        #[Autowire(env: 'SPOTIFY_CLIENT_SECRET')] private string $clientSecret,
    ) {
    }

    /**
     * Récupère un token d'authentification temporaire auprès de Spotify (mis en cache jusqu'à son expiration)
     */
    private function getAccessToken(): string
    {
        return $this->cache->get('spotify_access_token', function (ItemInterface $item): string {
            $data = $this->requestAccessToken();
            $item->expiresAfter(max(60, ($data['expires_in'] ?? 3600) - 60));

            return $data['access_token'];
        });
    }

    /** @return array{access_token: string, expires_in?: int} */
    private function requestAccessToken(): array
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

        return $response->toArray();
    }

    /**
     * Cherche des albums sur Spotify via une chaîne de caractères
     *
     * @return list<array<string, mixed>>
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
     *
     * @return array<string, mixed>|null
     */
    public function getAlbum(string $id): ?array
    {
        $token = $this->getAccessToken();
        $response = $this->client->request('GET', 'https://api.spotify.com/v1/albums/' . rawurlencode($id), [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return $response->getStatusCode() === 200 ? $response->toArray() : null;
    }
}