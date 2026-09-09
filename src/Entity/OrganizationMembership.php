<?php

namespace App\Entity;

use App\Entity\Trait\TraceableTrait;
use App\Enum\OrganizationRole;
use App\Repository\OrganizationMembershipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganizationMembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'membership_organization_user', columns: ['organization_id', 'user_id'])]
class OrganizationMembership
{
    use TraceableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20, enumType: OrganizationRole::class)]
    private OrganizationRole $role = OrganizationRole::READER;

    public function __construct(
        ?Organization $organization = null,
        ?User $user = null,
        OrganizationRole $role = OrganizationRole::READER,
    ) {
        $this->organization = $organization;
        $this->user = $user;
        $this->role = $role;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): OrganizationRole
    {
        return $this->role;
    }

    public function setRole(OrganizationRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isAdmin(): bool
    {
        return OrganizationRole::ADMIN === $this->role;
    }
}
