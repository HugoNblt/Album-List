<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ReviewRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('app_profile_show', [
            'username' => $user->getUsername()
        ]);
    }

    #[Route('/user/{username}', name: 'app_profile_show')]
    public function show(
        #[MapEntity(mapping: ['username' => 'username'])] User $user,
        Request $request,
        ReviewRepository $reviewRepository
    ): Response {
        $q = trim((string) $request->query->get('q', ''));

        $reviews = $q !== ''
            ? $reviewRepository->searchInUserReviews($user, $q)
            : $reviewRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'reviews' => $reviews,
            'q' => $q,
        ]);
    }
}