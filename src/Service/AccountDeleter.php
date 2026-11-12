<?php

namespace App\Service;

use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Supprime définitivement un compte et tout ce qui lui est rattaché :
 * critiques (et leurs révisions), likes donnés et reçus, photo de profil.
 * Les albums restent : ils peuvent être critiqués par d'autres utilisateurs.
 */
class AccountDeleter
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%app.avatar_dir%')] private string $avatarDir,
    ) {
    }

    public function delete(User $user): void
    {
        $avatar = $user->getAvatar();

        // Les likes reçus par ces critiques et les likes donnés par l'utilisateur sont supprimés
        // par la base de données (clés étrangères de review_likes en ON DELETE CASCADE).
        foreach ($this->em->getRepository(Review::class)->findBy(['user' => $user]) as $review) {
            $this->em->remove($review);
        }

        $this->em->remove($user);
        $this->em->flush();

        if ($avatar) {
            (new Filesystem())->remove($this->avatarDir . '/' . $avatar);
        }
    }

    public function countReviews(User $user): int
    {
        return $this->em->getRepository(Review::class)->count(['user' => $user]);
    }
}
