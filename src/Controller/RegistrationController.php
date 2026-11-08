<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        Security $security, 
        EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.registration')] RateLimiterFactoryInterface $registrationLimiter,
        #[Autowire('%app.registration_enabled%')] bool $registrationEnabled
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Bêta privée : les comptes sont créés par l'administrateur (commande app:user:create)
        if (!$registrationEnabled) {
            return $this->render('registration/closed.html.twig', [], new Response(status: Response::HTTP_FORBIDDEN));
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        // Anti-spam : 3 comptes par heure et par IP (seules les inscriptions valides comptent)
        if ($form->isSubmitted() && $form->isValid()
            && !$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $form->addError(new FormError('Trop d\'inscriptions depuis ta connexion. Réessaie dans une heure.'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            return $security->login($user, 'form_login', 'main');
        }

        return $this->render('registration/register.html.twig', [
            // Passer le formulaire (et non sa vue) : Symfony répond 422 s'il est soumis et invalide
            'registrationForm' => $form,
        ]);
    }
}