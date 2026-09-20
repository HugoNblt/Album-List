<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use App\Repository\ReviewRepository;

class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile')]
    public function index(): Response
    {
        // Récupère l'utilisateur actuellement connecté
        $user = $this->getUser();

        // Sécurité : si personne n'est connecté, on redirige vers le login
        if (!$user) {
            return $this->redirectToRoute('app_login'); // Assure-toi que ce nom de route correspond à ton login
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            // Grâce au "yes" de tout à l'heure, Doctrine récupère toutes les reviews liées
            'reviews' => $user->getReviews(), 
        ]);
    }
    #[Route('/user/{id}', name: 'app_profile_show', methods: ['GET'])]
public function show(User $user, ReviewRepository $reviewRepository): Response
{
    // Récupère les avis de cet utilisateur du plus récent au plus ancien
    $reviews = $reviewRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

    return $this->render('profile/show.html.twig', [
        'profileUser' => $user,
        'reviews' => $reviews,
    ]);
}
}