<?php

namespace App\Controller;

use App\Service\SpotifyService;
use App\Entity\Album;
use App\Entity\Review;
use App\Form\ReviewType;
use App\Controller\App\Entity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PostController extends AbstractController
{
   
    #[Route('/modal/search-form', name: 'app_modal_search_form', methods: ['GET'])]
    public function modalSearchForm(): Response
    {
        return $this->render('post/_modal_search.html.twig');
    }

    #[Route('/modal/search-results', name: 'app_modal_search_results', methods: ['GET'])]
    public function modalSearchResults(Request $request, \App\Service\SpotifyService $spotifyService): Response
    {
        $query = $request->query->get('q', '');
        $albums = $query ? $spotifyService->searchAlbums($query) : [];

        return $this->render('post/_modal_results.html.twig', [
            'albums' => $albums,
            'query' => $query,
        ]);
    }

    #[Route('/modal/write/{spotifyId}', name: 'app_modal_write', methods: ['GET', 'POST'])]
    public function modalWrite(
        string $spotifyId,
        Request $request,
        SpotifyService $spotifyService,
        \App\Repository\AlbumRepository $albumRepository,
        \Doctrine\ORM\EntityManagerInterface $em
    ): Response {
        if (!$this->getUser()) {
            return new Response('Non autorisé', 403);
        }

        $album = $albumRepository->findOneBy(['spotifyId' => $spotifyId]);
        if (!$album) {
            $spotifyData = $spotifyService->getAlbum($spotifyId);
            if (!$spotifyData) {
                return new Response('Album introuvable sur Spotify', 404);
            }

            $album = new \App\Entity\Album();
            $album->setSpotifyId($spotifyId);
            $album->setTitle($spotifyData['name']);
            $album->setArtist($spotifyData['artists'][0]['name']);
            $album->setReleaseYear((int) substr($spotifyData['release_date'], 0, 4));
            $album->setCoverImage($spotifyData['images'][1]['url'] ?? 'https://via.placeholder.com/300');
            
            $em->persist($album);
        }

        $review = new \App\Entity\Review();
        $form = $this->createForm(\App\Form\ReviewType::class, $review, [
            'action' => $this->generateUrl('app_modal_write', ['spotifyId' => $spotifyId]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setAlbum($album);
            $review->setUser($this->getUser());
            $review->setCreatedAt(new \DateTimeImmutable());
            
            $em->persist($review);
            $em->flush();

            return $this->json([
                'status' => 'success',
                'redirect' => $this->generateUrl('app_profile')
            ]);
        }

        return $this->render('post/_modal_write.html.twig', [
            'form' => $form->createView(),
            'album' => $album,
        ]);
    }


    #[Route('/modal/edit/{id}', name: 'app_modal_edit', methods: ['GET', 'POST'])]
    public function modalEdit(Review $review, Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        if (!$this->getUser() || $this->getUser() !== $review->getUser()) {
            return new Response('Non autorisé', 403);
        }

        $form = $this->createForm(ReviewType::class, $review, [
            'action' => $this->generateUrl('app_modal_edit', ['id' => $review->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->json([
                'status' => 'success',
                'redirect' => $this->generateUrl('app_profile')
            ]);
        }

        return $this->render('post/_modal_edit.html.twig', [
            'form' => $form->createView(),
            'review' => $review,
        ]);
    }

    #[Route('/post/delete/{id}', name: 'app_post_delete', methods: ['POST'])]
    public function delete(Review $review, Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        if (!$this->getUser() || $this->getUser() !== $review->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $submittedToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_review_' . $review->getId(), $submittedToken)) {
            $em->remove($review);
            $em->flush();
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/modal/history/{id}', name: 'app_modal_history', methods: ['GET'])]
    public function modalHistory(Review $review, \App\Repository\ReviewRepository $reviewRepository): Response
    {
        // Récupère tous les avis de CET utilisateur pour CET album, du plus récent au plus ancien
        $allUserReviews = $reviewRepository->findBy(
            ['album' => $review->getAlbum(), 'user' => $review->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('post/_modal_history.html.twig', [
            'album' => $review->getAlbum(),
            'reviews' => $allUserReviews,
            'reviewUser' => $review->getUser(),
        ]);
    }
}