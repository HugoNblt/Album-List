<?php

namespace App\Controller;

use App\Service\SpotifyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PostController extends AbstractController
{
    #[Route('/post/create', name: 'app_post_create')]
    public function create(Request $request, SpotifyService $spotifyService): Response
    {
        // On sécurise la page : il faut être connecté pour créer un post
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // On récupère le paramètre "q" dans l'URL (ex: /post/create?q=Daft+Punk)
        $query = $request->query->get('q');
        $albums = [];

        // Si l'utilisateur a fait une recherche, on appelle Spotify
        if ($query) {
            $albums = $spotifyService->searchAlbums($query);
        }

        return $this->render('post/create.html.twig', [
            'albums' => $albums,
            'query' => $query,
        ]);
    }
    
    #[Route('/post/write/{spotifyId}', name: 'app_post_write')]
    public function write(
        string $spotifyId,
        Request $request,
        SpotifyService $spotifyService,
        \App\Repository\AlbumRepository $albumRepository,
        \Doctrine\ORM\EntityManagerInterface $em
    ): Response {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // 1. Chercher si l'album existe déjà dans notre BDD locale
        $album = $albumRepository->findOneBy(['spotifyId' => $spotifyId]);

        // 2. S'il n'existe pas, on le crée avec les données de Spotify
        if (!$album) {
            $spotifyData = $spotifyService->getAlbum($spotifyId);
            
            if (!$spotifyData) {
                throw $this->createNotFoundException('Album introuvable sur Spotify');
            }

            $album = new \App\Entity\Album();
            $album->setSpotifyId($spotifyId);
            $album->setTitle($spotifyData['name']);
            $album->setArtist($spotifyData['artists'][0]['name']);
            $album->setReleaseYear((int) substr($spotifyData['release_date'], 0, 4));
            $album->setCoverImage($spotifyData['images'][1]['url'] ?? 'https://via.placeholder.com/300');
            
            $em->persist($album);
        }

        // 3. Création de la Review
        $review = new \App\Entity\Review();
        $form = $this->createForm(\App\Form\ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setAlbum($album);
            $review->setUser($this->getUser());
            $review->setCreatedAt(new \DateTimeImmutable());
            
            // Si c'est un nouvel album, le persist de l'album et de la review se font en même temps
            $em->persist($review);
            $em->flush();

            return $this->redirectToRoute('app_profile'); // Retour au profil pour voir l'ajout
        }

        return $this->render('post/write.html.twig', [
            'form' => $form->createView(),
            'album' => $album,
        ]);
    }
}