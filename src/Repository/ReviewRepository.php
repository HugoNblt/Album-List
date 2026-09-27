<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * Recherche dans les critiques d'un utilisateur spécifique
     */
    public function searchInUserReviews(User $user, string $query): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.album', 'a')  // Jointure sur la relation album
            ->addSelect('a')            // Charge l'album associé
            ->where('r.user = :user')
            ->andWhere('a.title LIKE :q OR a.artist LIKE :q OR r.content LIKE :q')
            ->setParameter('user', $user)
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult(); // Ne posera plus d'erreur !
    }

    /**
     * Recherche globale dans toutes les critiques
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.album', 'a')
            ->addSelect('a')
            ->where('a.title LIKE :q OR a.artist LIKE :q OR r.content LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}