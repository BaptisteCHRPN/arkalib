<?php

namespace App\EventSubscriber;

use App\Service\InvitationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class InvitationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private InvitationService $invitationService,
    ) {}

    // Dit à Symfony : "appelle onLoginSuccess() à chaque login réussi"
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    /**
     * Déclenché après chaque login réussi (connexion classique OU inscription + auto-login).
     * 
     * Vérifie s'il y a un token d'invitation en session.
     * Si oui → accepte l'invitation et rattache le user à l'orga.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $session = $this->requestStack->getSession();
        $token = $session->get('pending_invitation_token');

        // Pas de token en session → rien à faire, c'est un login normal
        if (!$token) {
            return;
        }

        // Nettoyer la session tout de suite (usage unique)
        $session->remove('pending_invitation_token');

        // Accepter l'invitation
        try {
            $invitation = $this->invitationService->accept($token, $event->getUser());
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('success', sprintf(
                    'Vous avez rejoint l\'organisation « %s » !',
                    $invitation->getOrganisation()->getName()
                ));
            }
        } catch (\LogicException) {
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('error', 'L\'invitation avait expiré ou est invalide.');
            }
        }
    }
}