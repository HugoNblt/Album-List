<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;

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
     * Recherche les critiques par titre d'album ou nom d'artiste
     */
    public function searchByAlbumOrArtist(string $query): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.album', 'a')
            ->addSelect('a')
            ->innerJoin('r.user', 'u')
            ->addSelect('u')
            ->where('a.title LIKE :q OR a.artist LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function searchInUserReviews(User $user, string $query): array
{
    return $this->createQueryBuilder('r')
        ->distinct()
        ->leftJoin('r.album', 'a')->addSelect('a')
        ->leftJoin('r.user', 'u')->addSelect('u')
        ->where('r.user = :user')
        ->andWhere('a.title LIKE :q OR a.artist LIKE :q OR r.content LIKE :q')
        ->setParameter('user', $user)
        ->setParameter('q', '%' . $query . '%')
        ->orderBy('r.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
public function search(string $query): array
{
    return $this->createQueryBuilder('r')
        ->distinct()
        ->leftJoin('r.album', 'a')->addSelect('a')
        ->leftJoin('r.user', 'u')->addSelect('u')
        ->where('a.title LIKE :q')
        ->orWhere('a.artist LIKE :q')
        ->orWhere('r.content LIKE :q')
        ->setParameter('q', '%' . $query . '%')
        ->orderBy('r.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}

    //    /**
    //     * @return Review[] Returns an array of Review objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Review
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
