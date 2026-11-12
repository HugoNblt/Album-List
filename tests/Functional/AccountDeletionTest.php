<?php

namespace App\Tests\Functional;

use App\Entity\Album;
use App\Entity\Review;
use App\Entity\User;

class AccountDeletionTest extends AppWebTestCase
{
    public function testEditPageWarnsAboutReviewsBeingDeleted(): void
    {
        $user = $this->createUser();
        $album = $this->createAlbum();
        $this->createReview($user, $album, 6);
        $this->createReview($user, $album, 8);
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile/edit');

        self::assertSelectorTextContains('.danger-zone', 'tes 2 critiques');
        self::assertSelectorExists('.danger-zone form[data-confirm*="irréversible"]');
    }

    public function testDeletionRemovesAccountReviewsLikesAndAvatar(): void
    {
        $user = $this->createUser('partant');
        $other = $this->createUser('ami');
        $album = $this->createAlbum();
        $ownReview = $this->createReview($user, $album);
        $otherReview = $this->createReview($other, $album);

        // Like reçu (sur la critique du partant) et like donné (sur celle de l'ami)
        $ownReview->addLike($other);
        $otherReview->addLike($user);
        $avatarDir = static::getContainer()->getParameter('app.avatar_dir');
        @mkdir($avatarDir, 0775, true);
        file_put_contents($avatarDir . '/avatar-test.webp', 'x');
        $user->setAvatar('avatar-test.webp');
        $this->em()->flush();
        $userId = $user->getId();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/profile/edit');
        $this->client->submit($crawler->filter('.danger-zone form')->form(['password' => 'MotDePasse!42']));

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-message', 'supprimés');

        $this->em()->clear();
        self::assertNull($this->em()->find(User::class, $userId), 'compte supprimé');
        self::assertCount(1, $this->em()->getRepository(Review::class)->findAll(), 'seule la critique de l\'ami reste');
        self::assertCount(0, $this->em()->getRepository(Review::class)->findAll()[0]->getLikes(), 'le like donné a disparu');
        self::assertNotNull($this->em()->getRepository(Album::class)->find($album->getId()), "l'album reste");
        self::assertFileDoesNotExist($avatarDir . '/avatar-test.webp', 'photo supprimée');

        // Déconnecté : l'édition du profil renvoie vers la connexion
        $this->client->request('GET', '/profile/edit');
        self::assertResponseRedirects('/login');
    }

    public function testWrongPasswordKeepsAccount(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile/edit');
        $this->client->submit($crawler->filter('.danger-zone form')->form(['password' => 'mauvais']));

        self::assertResponseRedirects('/profile/edit');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-message--error', 'Mot de passe incorrect');
        self::assertNotNull($this->reload($user));
    }

    public function testDeletionRequiresCsrfToken(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/profile/delete', ['password' => 'MotDePasse!42']);

        self::assertResponseRedirects('/profile/edit');
        self::assertNotNull($this->reload($user));
    }

    public function testAnonymousCannotDelete(): void
    {
        $this->client->request('POST', '/profile/delete');
        self::assertResponseRedirects('/login');
    }
}
