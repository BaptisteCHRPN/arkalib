<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

trait TraceableTrait
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $created_by = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $updated_by = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getCreatedBy(): ?User
    {
        return $this->created_by;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updated_by;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->created_at = $createdAt;
        return $this;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->created_by = $createdBy;
        return $this;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updated_at = $updatedAt;
        return $this;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updated_by = $updatedBy;
        return $this;
    }
}
