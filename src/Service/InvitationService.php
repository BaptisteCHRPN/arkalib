<?php
// src/Service/InvitationService.php

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InvitationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private InvitationRepository $invitationRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * ÉTAPE 1 : Créer et envoyer une invitation.
     * 
     * Appelé quand un membre d'une orga saisit un email dans le formulaire d'invitation.
     * 
     * @param string       $email      L'email de la personne à inviter
     * @param Organization $organisation  L'orga à rejoindre
     * @param User         $invitedBy    Le membre qui envoie l'invitation
     */
    public function invite(string $email, Organization $organisation, User $invitedBy): Invitation
    {
        // ── Vérification 1 : l'invité est-il déjà membre ? ──
        // On cherche si un User existe avec cet email
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        // Si oui ET qu'il est déjà dans l'orga → inutile d'inviter
        if ($existingUser && $existingUser->getOrganizations()->contains($organisation)) {
            throw new \LogicException('Cet utilisateur est déjà membre de cette organisation.');
        }

        // ── Vérification 2 : y a-t-il déjà une invitation en attente ? ──
        // Évite d'envoyer 10 emails au même invité pour la même orga
        $existing = $this->invitationRepository->findPendingByEmailAndOrga($email, $organisation);
        if ($existing) {
            throw new \LogicException('Une invitation est déjà en attente pour cet email.');
        }

        // ── Génération du token ──
        // bin2hex(random_bytes(20)) → produit 40 caractères hex aléatoires
        // C'est le TOKEN EN CLAIR, celui qui sera dans le lien de l'email
        $token = bin2hex(random_bytes(20));

        // hash('sha256', ...) → transforme le token en une empreinte de 64 caractères
        // C'est le HASH, celui qui sera stocké en BDD
        // Même si quelqu'un accède à ta BDD, il ne peut pas retrouver le token original
        $hashedToken = hash('sha256', $token);

        // ── Création de l'invitation ──
        // Le constructeur reçoit le hash (jamais le token en clair)
        $invitation = new Invitation($hashedToken);
        $invitation->setEmail($email);
        $invitation->setOrganisation($organisation);
        $invitation->setInvitedBy($invitedBy);

        // persist = "prépare l'insertion en BDD"
        // flush = "exécute réellement la requête SQL"
        $this->em->persist($invitation);
        $this->em->flush();

        // ── Envoi de l'email ──
        // On passe le token EN CLAIR pour construire le lien dans l'email
        $this->sendInvitationEmail($invitation, $token);

        return $invitation;
    }

    /**
     * ÉTAPE 2 : Accepter une invitation.
     * 
     * Appelé quand l'invité clique sur le lien et est connecté (ou vient de s'inscrire).
     * 
     * @param string $tokenFromUrl  Le token en clair récupéré depuis l'URL
     * @param User   $user          L'utilisateur connecté qui accepte
     */
    public function accept(string $tokenFromUrl, User $user): Invitation
    {
        // ── On re-hash le token reçu dans l'URL ──
        // Si quelqu'un a envoyé "abc123" dans l'URL,
        // on calcule hash('sha256', 'abc123') → "f7d8e9..."
        // et on cherche en BDD une invitation qui a ce hash
        $hashedToken = hash('sha256', $tokenFromUrl);

        // ── Recherche de l'invitation ──
        // On cherche par hashedToken + status pending
        $invitation = $this->invitationRepository->findOneBy([
            'hashedToken' => $hashedToken,
            'status' => Invitation::STATUS_PENDING,
        ]);

        // ── Validations ──
        if (!$invitation) {
            throw new \LogicException('Cette invitation est invalide.');
        }

        if ($invitation->isExpired()) {
            $this->markAsExpired($invitation);
            throw new \LogicException('Cette invitation a expiré.');
        }

        if (strtolower($user->getEmail()) !== strtolower($invitation->getEmail())) {
            throw new \LogicException('Cette invitation ne vous est pas destinée.');
        }

        // ── Rattachement à l'organisation ──
        // addOrganization() sur le User ajoute aussi le User côté Organization
        // grâce à la synchronisation bidirectionnelle
        $user->addOrganization($invitation->getOrganisation());

        // ── Marquage comme acceptée ──
        $invitation->setStatus(Invitation::STATUS_ACCEPTED);

        // flush = sauvegarde les deux modifications en BDD :
        // 1. La nouvelle ligne dans la table pivot organization_user
        // 2. Le status "accepted" sur l'invitation
        $this->em->flush();

        return $invitation;
    }

    /**
     * Méthode privée : envoie l'email d'invitation.
     * 
     * Privée car elle n'est appelée que par invite() ci-dessus.
     * Personne d'autre n'a besoin de l'appeler directement.
     * 
     * @param Invitation $invitation  L'invitation (pour les infos : email, orga, inviteur)
     * @param string     $token       Le token EN CLAIR (pour construire le lien)
     */
    public function markAsExpired(Invitation $invitation): void
    {
        $invitation->setStatus(Invitation::STATUS_EXPIRED);
        $this->em->flush();
    }

    private function sendInvitationEmail(Invitation $invitation, string $token): void
    {
        // ── Construction de l'URL ──
        // Génère : https://tonsite.com/invitation/abc123def456...
        // ABSOLUTE_URL = avec le domaine complet (obligatoire dans un email)
        $acceptUrl = $this->urlGenerator->generate('app_invitation_accept', [
            'token' => $token,  // ← le token EN CLAIR dans l'URL
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // ── Nom de l'inviteur ──
        // Si firstname/lastname sont remplis → "Jean Dupont"
        // Sinon → on utilise l'email comme fallback
        $invitedBy = $invitation->getInvitedBy();
        $inviterName = trim($invitedBy->getFirstname() . ' ' . $invitedBy->getLastname());
        if (empty($inviterName)) {
            $inviterName = $invitedBy->getEmail();
        }

        // ── Construction de l'email ──
        // TemplatedEmail = email dont le contenu HTML est un template Twig
        $email = (new TemplatedEmail())
            ->from(new Address('noreply@arkalib.fr', 'Arkalib'))
            ->to($invitation->getEmail())
            ->subject(sprintf('%s vous invite à rejoindre %s',
                $inviterName,
                $invitation->getOrganisation()->getName()
            ))
            ->htmlTemplate('member/invitation/email_invitation.html.twig')
            ->context([
                // Ces variables seront disponibles dans le template Twig
                'invitation' => $invitation,
                'inviterName' => $inviterName,
                'acceptUrl' => $acceptUrl,
            ]);

        // ── Envoi ──
        // Le mailer utilise la config MAILER_DSN de ton .env
        $this->mailer->send($email);
    }
}