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

        $this->addFlash('success', 'Votre nouvelle adresse email a été confirmée. L\'ancienne adresse email a été supprimée.');

        return $this->redirectToRoute('app_member_user_edit', ['id' => $user->getId()]);


    }
}