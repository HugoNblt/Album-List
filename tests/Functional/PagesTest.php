<?php

namespace App\Tests\Functional;

class PagesTest extends AppWebTestCase
{
    public function testPublicPagesAreReachable(): void
    {
        foreach (['/', '/login', '/register'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    public function testHomeListsReviews(): void
    {
        $this->createReview($this->createUser(), $this->createAlbum());

        $this->client->request('GET', '/');

        self::assertSelectorCount(1, '.review-card');
        self::assertSelectorTextContains('.review-card__title', 'Iowa');
    }

    public function testOwnerActionsOnlyShownToAuthor(): void
    {
        $author = $this->createUser('auteur');
        $this->createReview($author, $this->createAlbum());

        $this->client->loginUser($this->createUser('visiteur'));
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.owner-actions');

        $this->client->loginUser($author);
        $this->client->request('GET', '/');
        self::assertSelectorExists('.owner-actions');
    }

    public function testSecurityHeaders(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHasHeader('Referrer-Policy');
    }

    public function testNoBetaNoticeWhenRegistrationIsOpen(): void
    {
        $this->client->request('GET', '/');

        self::assertSelectorNotExists('#beta-modal');
    }

    public function testReviewFormIsProtectedAgainstAccidentalLoss(): void
    {
        $this->client->loginUser($this->createUser());
        $album = $this->createAlbum();

        $this->client->request('GET', '/modal/write/' . $album->getSpotifyId());

        // La classe review-form active la sauvegarde du brouillon et bloque la fermeture au clic extérieur (app.js)
        self::assertSelectorExists('form#modal-write-form.review-form');
    }

    public function testBioSubmitsOnEnter(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/profile/edit');

        self::assertSelectorExists('textarea#profile_form_bio[data-submit-on-enter]');
    }

    public function testKeyboardSkipLinkIsPresent(): void
    {
        $this->client->request('GET', '/');

        self::assertSelectorExists('a.skip-link[href="#main"]');
        self::assertSelectorExists('main#main');
    }
}
