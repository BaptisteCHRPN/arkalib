<?php

namespace App\Controller\Public\Security;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
class EmailChangeController extends AbstractController
{
    #[Route('/confirm-email/{token}', name: 'app_member_user_confirm_email', methods: ['GET'])]
    public function confirmEmailChange(string $token, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneByEmailChangeToken($token);

        if (!$user || $user->getEmailChangeTokenExpiresAt() < new DateTimeImmutable()) {
            $this->addFlash('error', 'Ce lien de confirmation est invalide ou a expiré. Veuillez faire une nouvelle demande de changement d\'email.');
            return $this->redirectToRoute('app_dashboard');
        }

        $pendingEmail = $user->getPendingEmail();

        $user->setEmail($pendingEmail);
        $user->setPendingEmail(null);
        $user->setEmailChangeToken(null);
        $user->setEmailChangeTokenExpiresAt(null);

        $entityManager->flush();

        return $this->redirectToRoute('app_email_change_confirmed');


    }

    #[Route('/email-change-confirmed', name: 'app_email_change_confirmed')]
    public function emailChangeConfirmed(): Response
    {
        return $this->render('public/security/email_change_confirmed.html.twig');
    }

}