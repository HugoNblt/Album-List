<?php

namespace App\DataFixtures;

use App\Entity\Album;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // 1. Création de 5 Utilisateurs
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $user = new User();
            // Assure-toi que les setters correspondent à ton entité User
            $user->setEmail("user$i@test.com");
            $user->setPassword($this->hasher->hashPassword($user, 'password123'));
            $manager->persist($user);
            $users[] = $user;
        }

        // 2. Création de 10 Albums avec de fausses métadonnées "Spotify"
        $albums = [];
        for ($i = 0; $i < 10; $i++) {
            $album = new Album();
            $album->setSpotifyId($faker->uuid()); // Simule un ID unique
            $album->setTitle($faker->words(3, true));
            $album->setArtist($faker->name());
            $album->setReleaseYear((int) $faker->year());
            $album->setCoverImage('https://picsum.photos/300/300?random=' . $i); // Image aléatoire
            $manager->persist($album);
            $albums[] = $album;
        }

        // 3. Création de 20 Reviews
        for ($i = 0; $i < 20; $i++) {
            $review = new Review();
            $review->setContent($faker->paragraphs(2, true));
            $review->setRating($faker->numberBetween(1, 10));
            
            // Faker génère un DateTime classique, on le convertit en Immutable
            $date = $faker->dateTimeBetween('-6 months');
            $review->setCreatedAt(\DateTimeImmutable::createFromMutable($date));
            
            // Assignation aléatoire d'un utilisateur et d'un album
            $review->setUser($faker->randomElement($users));
            $review->setAlbum($faker->randomElement($albums));
            
            $manager->persist($review);
        }

        $manager->flush();
    }
}