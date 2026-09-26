<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReviewController extends AbstractController
{
    #[Route('/review/{id}/like', name: 'app_review_like', methods: ['POST'])]
    public function like(Review $review, EntityManagerInterface $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        if ($review->isLikedByUser($user)) {
            $review->removeLike($user);
            $isLiked = false;
        } else {
            $review->addLike($user);
            $isLiked = true;
        }

        $em->flush();

        return new JsonResponse([
            'isLiked' => $isLiked,
            'count' => $review->getLikes()->count(),
        ]);
    }
}