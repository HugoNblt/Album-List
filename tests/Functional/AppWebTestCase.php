<?php

namespace App\Tests\Functional;

use App\Entity\Album;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels : client HTTP + fabriques de données.
 * Chaque test tourne dans une transaction annulée à la fin (DAMA), la base reste vide entre deux tests.
 */
abstract class AppWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Les compteurs des rate limiters ne doivent pas déborder d'un test à l'autre
        static::getContainer()->get('cache.rate_limiter')->clear();
        // Le CSRF « stateless » de Symfony vérifie l'origine de la requête, comme le ferait un navigateur
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $username = 'alice', string $password = 'MotDePasse!42'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.test');
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function createAlbum(string $spotifyId = 'album0000000000000001', string $title = 'Iowa', string $artist = 'Slipknot'): Album
    {
        $album = new Album();
        $album->setSpotifyId($spotifyId);
        $album->setTitle($title);
        $album->setArtist($artist);
        $album->setReleaseYear(2001);

        $this->em()->persist($album);
        $this->em()->flush();

        return $album;
    }

    protected function createReview(User $user, Album $album, int $rating = 8, string $content = 'Un très bon disque.'): Review
    {
        $review = new Review();
        $review->setUser($user);
        $review->setAlbum($album);
        $review->setRating($rating);
        $review->setContent($content);
        $review->setCreatedAt(new \DateTimeImmutable());
        $album->addReview($review);

        $this->em()->persist($review);
        $this->em()->flush();

        return $review;
    }

    /** Recharge une entité depuis la base (les données ont pu changer pendant la requête). */
    protected function reload(object $entity): ?object
    {
        $this->em()->clear();

        return $this->em()->find($entity::class, $entity->getId());
    }

    /** Envoie le formulaire d'écriture d'une critique (le jeton CSRF vient du formulaire affiché). */
    protected function postReview(string $spotifyId, ?int $rating, string $content): void
    {
        $crawler = $this->client->request('GET', '/modal/write/' . $spotifyId);
        $token = $crawler->filter('input[name="review[_token]"]')->attr('value');

        $this->client->request('POST', '/modal/write/' . $spotifyId, ['review' => [
            'rating' => $rating,
            'content' => $content,
            '_token' => $token,
        ]]);
    }

    /** @return array<string, mixed> */
    protected function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
