<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

trait SoftDeleteTrait
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deleted_at = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $deleted_by = null;

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deleted_at;
    }

    public function getDeletedBy(): ?User
    {
        return $this->deleted_by;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deleted_at = $deletedAt;
        return $this;
    }

    public function setDeletedBy(?User $deletedBy): static
    {
        $this->deleted_by = $deletedBy;
        return $this;
    }

}