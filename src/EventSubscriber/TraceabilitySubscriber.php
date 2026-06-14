<?php

namespace App\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class TraceabilitySubscriber
{

    public function __construct(private Security $security) {}

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'setCreatedAt')) {
            return;
        }

        $entity->setCreatedAt(new \DateTimeImmutable());

        if (method_exists($entity, 'setCreatedBy')) {
            $entity->setCreatedBy($this->security->getUser());
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'setUpdatedAt')) {
            return;
        }

        $entity->setUpdatedAt(new \DateTimeImmutable());

        if (method_exists($entity, 'setUpdatedBy')) {
            $entity->setUpdatedBy($this->security->getUser());
        }

    }
}
