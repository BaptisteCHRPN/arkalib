<?php

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\LastLoginSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LastLoginSubscriberTest extends KernelTestCase
{
    private function loginEventFor(User $user): LoginSuccessEvent
    {
        $event = $this->createMock(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        return $event;
    }

    private function persistedUser(EntityManagerInterface $entityManager): User
    {
        $user = new User();
        $user->setEmail('last-login@example.com');
        $user->setPassword('not-checked-here');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function testSubscribesToLoginSuccess(): void
    {
        $this->assertArrayHasKey(
            LoginSuccessEvent::class,
            LastLoginSubscriber::getSubscribedEvents(),
        );
    }

    public function testLastLoginIsWrittenToTheDatabase(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->persistedUser($entityManager);
        $this->assertNull($user->getLastLoginAt());

        (new LastLoginSubscriber($entityManager))->onLoginSuccess($this->loginEventFor($user));

        // Relire depuis la base : la mise à jour est faite en DQL, donc
        // l'objet en mémoire ne prouverait rien à lui seul.
        $id = $user->getId();
        $entityManager->clear();
        $reloaded = $entityManager->find(User::class, $id);

        $this->assertNotNull($reloaded->getLastLoginAt());
    }

    public function testTheInMemoryUserReflectsTheWrite(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->persistedUser($entityManager);

        (new LastLoginSubscriber($entityManager))->onLoginSuccess($this->loginEventFor($user));

        $this->assertNotNull($user->getLastLoginAt());
    }

    /**
     * Se connecter n'est pas modifier son profil : c'est toute la raison
     * d'écrire en DQL plutôt que de flusher l'entité.
     */
    public function testLoggingInDoesNotCountAsAProfileUpdate(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->persistedUser($entityManager);

        (new LastLoginSubscriber($entityManager))->onLoginSuccess($this->loginEventFor($user));

        $id = $user->getId();
        $entityManager->clear();
        $reloaded = $entityManager->find(User::class, $id);

        $this->assertNull($reloaded->getUpdatedAt());
        $this->assertNull($reloaded->getUpdatedBy());
    }

    public function testAnUnsavedUserIsIgnored(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $subscriber = new LastLoginSubscriber($entityManager);

        // Un utilisateur sans id ne peut pas être ciblé par un UPDATE ... WHERE id = ?
        $subscriber->onLoginSuccess($this->loginEventFor(new User()));

        $this->expectNotToPerformAssertions();
    }
}
