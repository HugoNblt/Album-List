<?php

namespace App\Tests\Mock;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Remplace l'API Spotify pendant les tests.
 * - recherche « panne » : simule une coupure réseau ;
 * - album « 0000000000000000000404 » : album inexistant (404).
 */
class SpotifyMockResponseFactory
{
    public const ALBUM_ID = '4aawyAB9vmqN3uQ7FjRGTy';
    public const MISSING_ALBUM_ID = '0000000000000000000404';

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($url, 'accounts.spotify.com/api/token')) {
            return $this->json(['access_token' => 'fake-token', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        if ($path === '/v1/search') {
            if (($query['q'] ?? '') === 'panne') {
                return new MockResponse('', ['error' => 'Connexion impossible (simulée)']);
            }

            return $this->json(['albums' => ['items' => [$this->album()]]]);
        }

        if (str_starts_with($path, '/v1/albums/')) {
            if (str_ends_with($path, self::MISSING_ALBUM_ID)) {
                return $this->json(['error' => ['status' => 404]], 404);
            }

            return $this->json($this->album());
        }

        return $this->json(['error' => 'Requête inattendue : ' . $url], 500);
    }

    /** @return array<string, mixed> */
    private function album(): array
    {
        return [
            'id' => self::ALBUM_ID,
            'name' => 'Album de test',
            'artists' => [['name' => 'Artiste de test']],
            'release_date' => '2024-05-17',
            'images' => [
                ['url' => 'https://i.scdn.co/image/large'],
                ['url' => 'https://i.scdn.co/image/medium'],
                ['url' => 'https://i.scdn.co/image/small'],
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
