<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileFormType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')] string $avatarDir
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $oldUsername = $user->getUsername();
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            // Annule les modifications en mémoire pour ne pas désynchroniser la session
            $em->refresh($user);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $filesystem = new Filesystem();
            $oldAvatar = $user->getAvatar();

            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $form->get('avatarFile')->getData();

            if ($avatarFile) {
                $filename = bin2hex(random_bytes(12)) . '.' . ($avatarFile->guessExtension() ?? 'jpg');
                $avatarFile->move($avatarDir, $filename);
                $user->setAvatar($filename);
            } elseif ($form->get('removeAvatar')->getData()) {
                $user->setAvatar(null);
            }

            if ($oldAvatar && $oldAvatar !== $user->getAvatar()) {
                $filesystem->remove($avatarDir . '/' . $oldAvatar);
            }

            $em->flush();

            // L'identifiant de session est le pseudo : on régénère le token s'il a changé
            if ($user->getUsername() !== $oldUsername) {
                $security->login($user, 'form_login', 'main');
            }

            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile_show', [
                'username' => $user->getUsername(),
            ]);
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
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
            'stats' => $reviewRepository->getUserStats($user),
        ]);
    }
}