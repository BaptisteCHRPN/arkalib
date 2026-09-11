<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Renvoie vers l'écran de complétion tant que le compte n'a pas de prénom.
 *
 * C'est ici, et non dans un formulaire, que se joue le caractère obligatoire du
 * prénom : un écran qu'on peut quitter en fermant l'onglet n'oblige à rien.
 * Le détour couvre du même coup les comptes antérieurs à cette règle et ceux
 * créés depuis le back-office, sans migration ni relance par e-mail.
 *
 * La priorité par défaut suffit : le pare-feu s'exécute sur le même événement
 * en priorité 8, le token est donc déjà établi quand on arrive ici.
 */
class ProfileCompletionSubscriber implements EventSubscriberInterface
{
    /**
     * Routes joignables sans prénom. Sans `app_logout`, une personne qui refuse
     * de renseigner le sien ne pourrait même plus se déconnecter.
     */
    private const ALLOWED_ROUTES = [
        'app_profile_completion',
        'app_logout',
    ];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Une sous-requête (un `render()` appelé depuis un template) ne mène
        // l'utilisateur nulle part : la rediriger n'aurait aucun sens.
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User || '' !== trim((string) $user->getFirstname())) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // `_route` est absent sur une 404 : la laisser passer évite de masquer
        // les erreurs derrière une redirection. Les routes internes du profileur
        // et de la barre de débogage commencent par un souligné.
        if (null === $route || str_starts_with($route, '_')) {
            return;
        }

        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        // Un appel asynchrone qui reçoit un 302 suit la redirection en silence
        // et récupère une page HTML là où il attendait autre chose.
        if ($request->isXmlHttpRequest()) {
            return;
        }

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate('app_profile_completion'))
        );
    }
}
