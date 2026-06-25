<?php

namespace App\Controller\Member;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Form\InvitationType;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Service\InvitationService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MemberInvitationController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/invite', name: 'app_invitation_send', methods: ['GET', 'POST'])]
    public function send(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        Request $request,
        InvitationService $invitationService,
    ): Response {
        // Vérifier que l'utilisateur connecté est bien membre de cette orga
        $user = $this->getUser();
        if (!$organization->getUsers()->contains($user)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(InvitationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            try {
                $invitationService->invite($email, $organization, $user);
                $this->addFlash('success', "Invitation envoyée à $email !");
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_invitation_send', [
                'organizationSlug' => $organization->getSlug(),
            ]);
        }

        return $this->render('member/invitation/send.html.twig', [
            'form' => $form,
            'organization' => $organization,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/invitations/{id}/revoke', name: 'app_invitation_revoke', methods: ['POST'])]
    public function revoke(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        Invitation $invitation,
        InvitationService $invitationService,
        Request $request
    ): Response {

        $user = $this->getUser();
        if (!$organization->getUsers()->contains($user)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('revoke_invitation_' . $invitation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
                $invitationService->markAsRevoked($invitation);
                $this->addFlash('success', "Invitation révoquée.");
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            }

        return $this->redirectToRoute('app_invitation_list', [
            'organizationSlug' => $organization->getSlug(),
        ]);
    }

    #[Route('/invitation/{token}', name: 'app_invitation_accept')]
    public function accept(
        string $token,
        Request $request,
        InvitationService $invitationService,
        InvitationRepository $invitationRepository,
        UserRepository $userRepository,
    ): Response {

        // ── Vérifier que le token correspond à une invitation valide ──
        $hashedToken = hash('sha256', $token);
        $invitation = $invitationRepository->findOneBy([
            'hashedToken' => $hashedToken,
            'status' => Invitation::STATUS_PENDING,
        ]);

        if (!$invitation || $invitation->isExpired()) {
            if ($invitation?->isExpired()) {
                $invitationService->markAsExpired($invitation);
            }
            $this->addFlash('error', 'Cette invitation est invalide ou a expiré.');
            return $this->redirectToRoute('app_login');
        }

        // ── Cas 1 : L'utilisateur est connecté → acceptation directe ──
        if ($this->getUser()) {
            try {
                $invitationService->accept($token, $this->getUser());
                $this->addFlash('success', sprintf(
                    'Vous avez rejoint l\'organisation « %s » !',
                    $invitation->getOrganisation()->getName()
                ));
            } catch (\LogicException) {
                $this->addFlash('error', 'Impossible d\'accepter cette invitation.');
            }

            return $this->redirectToRoute('app_dashboard');
        }

        // ── Cas 2 & 3 : Non connecté → on stocke le token en session ──
        // Le InvitationSubscriber le récupérera après le login/inscription
        $request->getSession()->set('pending_invitation_token', $token);

        // On vérifie si un compte existe déjà avec cet email
        $existingUser = $userRepository->findOneBy(['email' => $invitation->getEmail()]);

        if ($existingUser) {
            // Cas 2 : Compte existant → vers la page de connexion
            $this->addFlash('info', 'Connectez-vous pour accepter l\'invitation.');
            return $this->redirectToRoute('app_login');
        }

        // Cas 3 : Pas de compte → vers la page d'inscription
        $this->addFlash('info', 'Créez votre compte pour rejoindre l\'organisation.');
        return $this->redirectToRoute('app_register');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/invitations', name: 'app_invitation_list')]
    public function list(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
    ): Response {
        $user = $this->getUser();
        if (!$organization->getUsers()->contains($user)) {
            throw $this->createAccessDeniedException();
        }

        $invitations = $organization->getInvitations();

        return $this->render('member/invitation/list.html.twig', [
            'organization' => $organization,
            'invitations' => $invitations,
        ]);
    }
}
