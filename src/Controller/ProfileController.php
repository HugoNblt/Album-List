<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileFormType;
use App\Repository\ReviewRepository;
use App\Service\AccountDeleter;
use App\Service\AvatarUploader;
use App\Util\SearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
        AvatarUploader $avatarUploader,
        AccountDeleter $accountDeleter,
        #[Autowire('%app.avatar_dir%')] string $avatarDir
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
                try {
                    $user->setAvatar($avatarUploader->upload($avatarFile));
                } catch (\RuntimeException) {
                    $em->refresh($user);
                    $form->get('avatarFile')->addError(new FormError('Impossible de lire cette image, essaie avec un autre fichier.'));

                    return $this->render('profile/edit.html.twig', [
                        'form' => $form,
                        'reviewCount' => $accountDeleter->countReviews($user),
                    ]);
                }
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
            'reviewCount' => $accountDeleter->countReviews($user),
        ]);
    }

    /**
     * Suppression définitive du compte (droit à l'effacement) : mot de passe redemandé, jeton CSRF obligatoire.
     */
    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        AccountDeleter $accountDeleter,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_account', $request->request->getString('_token'))) {
            $this->addFlash('error', 'La demande a expiré, recharge la page et réessaie.');

            return $this->redirectToRoute('app_profile_edit');
        }

        if (!$passwordHasher->isPasswordValid($user, $request->request->getString('password'))) {
            $this->addFlash('error', 'Mot de passe incorrect : ton compte n\'a pas été supprimé.');

            return $this->redirectToRoute('app_profile_edit');
        }

        $accountDeleter->delete($user);

        // Déconnexion : on oublie l'utilisateur et on repart d'une session vierge
        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();
        $this->addFlash('success', 'Ton compte et toutes tes critiques ont été supprimés.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/user/{username}', name: 'app_profile_show')]
    public function show(
        #[MapEntity(mapping: ['username' => 'username'])] User $user,
        Request $request,
        ReviewRepository $reviewRepository
    ): Response {
        $q = SearchQuery::normalize($request->query->get('q'));

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