<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Enregistre la date de dernière connexion, pour répondre en back-office à
 * « ce compte est-il encore utilisé ? ».
 */
class LastLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User || null === $user->getId()) {
            return;
        }

        $now = new \DateTimeImmutable();

        // Écriture directe en DQL plutôt qu'un flush sur l'entité : une
        // connexion n'est pas une modification du profil, elle n'a donc pas à
        // déclencher preUpdate et à écraser `updated_at` / `updated_by`.
        $this->entityManager->createQuery(
            'UPDATE ' . User::class . ' u SET u.lastLoginAt = :now WHERE u.id = :id'
        )
            ->setParameter('now', $now)
            ->setParameter('id', $user->getId())
            ->execute();

        // L'objet en mémoire (et donc celui qui part en session) doit refléter
        // ce que la requête vient d'écrire.
        $user->setLastLoginAt($now);
    }
}
