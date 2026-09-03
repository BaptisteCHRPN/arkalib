<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Budget;
use App\Entity\User;
use App\EventSubscriber\TraceabilitySubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class TraceabilitySubscriberTest extends TestCase
{
    private function eventFor(object $entity): LifecycleEventArgs
    {
        return new LifecycleEventArgs($entity, $this->createMock(ObjectManager::class));
    }

    private function subscriberLoggedInAs(?User $user): TraceabilitySubscriber
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new TraceabilitySubscriber($security);
    }

    public function testPrePersistStampsCreationDateAndAuthor(): void
    {
        $user = new User();
        $budget = new Budget();

        $this->subscriberLoggedInAs($user)->prePersist($this->eventFor($budget));

        $this->assertNotNull($budget->getCreatedAt());
        $this->assertSame($user, $budget->getCreatedBy());
        $this->assertNull($budget->getUpdatedAt());
    }

    public function testPreUpdateStampsUpdateDateAndAuthor(): void
    {
        $user = new User();
        $budget = new Budget();

        $this->subscriberLoggedInAs($user)->preUpdate($this->eventFor($budget));

        $this->assertNotNull($budget->getUpdatedAt());
        $this->assertSame($user, $budget->getUpdatedBy());
        $this->assertNull($budget->getCreatedAt());
    }

    public function testStampsDateEvenWhenNobodyIsLoggedIn(): void
    {
        // Cas d'une commande console ou d'une fixture : pas d'utilisateur en session.
        $budget = new Budget();

        $this->subscriberLoggedInAs(null)->prePersist($this->eventFor($budget));

        $this->assertNotNull($budget->getCreatedAt());
        $this->assertNull($budget->getCreatedBy());
    }

    public function testEntitiesWithoutTraceableTraitAreLeftUntouched(): void
    {
        // Une entité sans setCreatedAt()/setUpdatedAt() ne doit provoquer aucune erreur.
        $entity = new \stdClass();
        $subscriber = $this->subscriberLoggedInAs(new User());

        $subscriber->prePersist($this->eventFor($entity));
        $subscriber->preUpdate($this->eventFor($entity));

        $this->assertSame([], get_object_vars($entity));
    }
}
