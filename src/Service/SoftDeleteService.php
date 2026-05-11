<?php

namespace App\Service;

use App\Entity\User;

class SoftDeleteService
{
    public function softDelete(object $entity, User $user): void
    {
        $this->assertSoftDeletable($entity);
        $entity->setDeletedAt(new \DateTimeImmutable());
        $entity->setDeletedBy($user);
    }

    public function restore(object $entity): void
    {
        $this->assertSoftDeletable($entity);
        $entity->setDeletedAt(null);
        $entity->setDeletedBy(null);
    }

    private function assertSoftDeletable(object $entity): void
    {
        if (!method_exists($entity, 'setDeletedAt')) {
            throw new \LogicException(sprintf('L\'entité %s n\'utilise pas SoftDeleteTrait.', get_class($entity)));
        }
    }
}
