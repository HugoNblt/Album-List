<?php

namespace App\Tests\Functional;

class SearchTest extends AppWebTestCase
{
    public function testHomeSearchFindsReviewsAndProfiles(): void
    {
        $user = $this->createUser('sleeper');
        $this->createReview($user, $this->createAlbum('album0000000000000002', 'Take Me Back to Eden', 'Sleep Token'));
        $this->createReview($user, $this->createAlbum('album0000000000000003', 'Iowa', 'Slipknot'));

        $this->client->request('GET', '/?q=sleep');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.review-card');
        self::assertSelectorTextContains('.review-card__title', 'Take Me Back to Eden');
        self::assertSelectorTextContains('.user-chips', 'sleeper');
    }

    public function testWildcardsAreSearchedLiterally(): void
    {
        $this->createReview($this->createUser(), $this->createAlbum());

        $this->client->request('GET', '/?q=%25');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.review-card');
    }

    public function testSearchTermIsTruncated(): void
    {
        $crawler = $this->client->request('GET', '/?q=' . str_repeat('x', 150));

        self::assertSame(100, strlen((string) $crawler->filter('#home-q')->attr('value')));
    }

    public function testProfileSearchOnlyCoversThatUser(): void
    {
        $alice = $this->createUser('alice');
        $album = $this->createAlbum();
        $this->createReview($alice, $album, 8, 'Batterie incroyable');
        $this->createReview($this->createUser('bob'), $album, 5, 'Batterie trop forte');

        $this->client->request('GET', '/user/alice?q=batterie');

        self::assertSelectorCount(1, '.disc-row');
    }

    public function testSpotifySearchInModal(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/modal/search-results?q=test');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.modal-result', 'Album de test');
    }

    public function testSpotifyOutageShowsFriendlyMessage(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/modal/search-results?q=panne');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-alert', 'momentanément indisponible');
    }
}
