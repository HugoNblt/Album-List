<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class ReviewController extends AbstractController
{
    #[Route('/review/{id}/like', name: 'app_review_like', methods: ['POST'])]
    public function like(
        Review $review,
        Request $request,
        EntityManagerInterface $em,
        #[Autowire(service: 'limiter.review_like')] RateLimiterFactoryInterface $likeLimiter
    ): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid('review_like', $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'Jeton CSRF invalide'], Response::HTTP_FORBIDDEN);
        }

        if (!$likeLimiter->create('user_' . $user->getId())->consume()->isAccepted()) {
            return new JsonResponse(['error' => 'Trop de likes, ralentis un peu.'], Response::HTTP_TOO_MANY_REQUESTS);
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