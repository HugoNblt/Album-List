<?php

namespace App\Controller;

use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        ReviewRepository $reviewRepository,
        UserRepository $userRepository
    ): Response {
        $query = trim($request->query->get('q', ''));

        $reviews = [];
        $users = [];

        if (!empty($query)) {
            // Résultats de recherche
            $reviews = $reviewRepository->searchByAlbumOrArtist($query);
            $users = $userRepository->searchByQuery($query);
        } else {
            // Fil d'actualité général (20 dernières critiques)
            $reviews = $reviewRepository->findBy([], ['createdAt' => 'DESC'], 20);
        }

        return $this->render('home/index.html.twig', [
            'query' => $query,
            'reviews' => $reviews,
            'users' => $users,
        ]);
    }
}