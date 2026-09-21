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
    $q = trim((string) $request->query->get('q', ''));

    if ($q !== '') {
        $reviews = $reviewRepository->search($q);
        // On récupère les utilisateurs correspondants
        $users = $userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->getQuery()
            ->getResult();
    } else {
        $reviews = $reviewRepository->findBy([], ['createdAt' => 'DESC']);
        $users = [];
    }

    return $this->render('home/index.html.twig', [
    'reviews' => $reviews,
    'users' => $users,
    'q' => $q,
    'query' => $q, // 👈 Ajoute cette ligne si ton template attend "query"
]);
}
}