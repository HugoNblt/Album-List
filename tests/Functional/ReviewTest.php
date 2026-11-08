<?php

namespace App\Tests\Functional;

use App\Entity\Album;
use App\Entity\Review;
use App\Tests\Mock\SpotifyMockResponseFactory;

class ReviewTest extends AppWebTestCase
{
    public function testWriteFormRequiresLogin(): void
    {
        $this->client->request('GET', '/modal/write/' . SpotifyMockResponseFactory::ALBUM_ID);
        self::assertResponseStatusCodeSame(403);
    }

    public function testPublishReviewOnNewSpotifyAlbum(): void
    {
        $this->client->loginUser($this->createUser());

        $this->postReview(SpotifyMockResponseFactory::ALBUM_ID, 9, "  Super album.\r\n\r\n\r\nVraiment.  ");

        self::assertResponseIsSuccessful();
        self::assertSame('success', $this->json()['status']);

        $album = $this->em()->getRepository(Album::class)->findOneBy(['spotifyId' => SpotifyMockResponseFactory::ALBUM_ID]);
        self::assertNotNull($album, "l'album est créé à partir des données Spotify");
        self::assertSame('Album de test', $album->getTitle());

        $review = $this->em()->getRepository(Review::class)->findOneBy(['album' => $album]);
        self::assertSame(9, $review->getRating());
        self::assertSame("Super album.\n\nVraiment.", $review->getContent());
    }

    public function testInvalidReviewReturnsFormWithErrors(): void
    {
        $this->client->loginUser($this->createUser());
        $album = $this->createAlbum();

        $this->postReview($album->getSpotifyId(), null, str_repeat('a', 3001));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Choisis une note');
        self::assertSelectorTextContains('body', 'ne doit pas dépasser 3000 caractères');
        self::assertCount(0, $this->em()->getRepository(Review::class)->findAll());
    }

    public function testPublishingIsRateLimited(): void
    {
        $this->client->loginUser($this->createUser());
        $album = $this->createAlbum();

        $this->postReview($album->getSpotifyId(), 7, 'Première critique');
        self::assertSame('success', $this->json()['status']);

        $this->postReview($album->getSpotifyId(), 8, 'Révision trop rapide');
        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('15 secondes', $this->json()['message']);
        self::assertCount(1, $this->em()->getRepository(Review::class)->findAll());
    }

    public function testUnknownSpotifyAlbumReturns404(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/modal/write/' . SpotifyMockResponseFactory::MISSING_ALBUM_ID);
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidSpotifyIdFormatIsRejected(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/modal/write/abc..def');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAuthorCanEditOwnReview(): void
    {
        $user = $this->createUser();
        $review = $this->createReview($user, $this->createAlbum());
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/modal/edit/' . $review->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/modal/edit/' . $review->getId(), ['review' => [
            'rating' => 3,
            'content' => 'Avis revu à la baisse.',
            '_token' => $crawler->filter('input[name="review[_token]"]')->attr('value'),
        ]]);

        self::assertSame('success', $this->json()['status']);
        self::assertSame(3, $this->reload($review)->getRating());
    }

    public function testCannotEditOrDeleteSomeoneElsesReview(): void
    {
        $review = $this->createReview($this->createUser('auteur'), $this->createAlbum());
        $this->client->loginUser($this->createUser('intrus'));

        $this->client->request('GET', '/modal/edit/' . $review->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/post/delete/' . $review->getId());
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->reload($review));
    }

    public function testAuthorCanDeleteOwnReview(): void
    {
        $user = $this->createUser();
        $review = $this->createReview($user, $this->createAlbum());
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/user/alice');
        $this->client->submit($crawler->filter('.disc-row form')->form());

        self::assertResponseRedirects();
        self::assertNull($this->reload($review));
    }

    public function testHistoryListsAllRevisions(): void
    {
        $user = $this->createUser();
        $album = $this->createAlbum();
        $this->createReview($user, $album, 6, 'Première écoute');
        $latest = $this->createReview($user, $album, 9, 'Après plusieurs écoutes');

        $this->client->request('GET', '/modal/history/' . $latest->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.history-item');
        self::assertSelectorTextContains('.history-meta', '2 révisions');
    }
}
