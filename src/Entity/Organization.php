<?php

namespace App\Entity;

use App\Enum\OrganizationRole;
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Trait\TraceableTrait;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
class Organization
{
    use TraceableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private bool $is_active;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, Budget>
     */
    #[ORM\OneToMany(targetEntity: Budget::class, mappedBy: 'organization', cascade: ['remove'])]
    private Collection $budgets;


    /**
     * @var Collection<int, OrganizationMembership>
     */
    #[ORM\OneToMany(
        targetEntity: OrganizationMembership::class,
        mappedBy: 'organization',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $memberships;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $picture = null;

    /**
     * @var Collection<int, Invitation>
     */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'organisation', cascade: ['remove'])]
    private Collection $invitations;

    public function __construct()
    {
        $this->budgets = new ArrayCollection();
        $this->memberships = new ArrayCollection();
        $this->is_active = true;
        $this->invitations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Budget>
     */
    public function getBudgets(): Collection
    {
        return $this->budgets;
    }

    public function addBudget(Budget $budget): static
    {
        if (!$this->budgets->contains($budget)) {
            $this->budgets->add($budget);
            $budget->setOrganization($this);
        }

        return $this;
    }

    public function removeBudget(Budget $budget): static
    {
        if ($this->budgets->removeElement($budget)) {
            // set the owning side to null (unless already changed)
            if ($budget->getOrganization() === $this) {
                $budget->setOrganization(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrganizationMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function getMembershipFor(User $user): ?OrganizationMembership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getUser() === $user) {
                return $membership;
            }
        }

        return null;
    }

    public function addMembership(OrganizationMembership $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setOrganization($this);
        }

        return $this;
    }

    public function removeMembership(OrganizationMembership $membership): static
    {
        $this->memberships->removeElement($membership);

        return $this;
    }

    /**
     * Compatibilité : la liste des membres, dérivée des appartenances.
     * Attention, c'est une copie : la modifier n'a aucun effet.
     *
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->memberships->map(
            fn (OrganizationMembership $membership) => $membership->getUser()
        );
    }

    public function addUser(User $user, OrganizationRole $role = OrganizationRole::READER): static
    {
        if (null !== $this->getMembershipFor($user)) {
            return $this;
        }

        $membership = new OrganizationMembership($this, $user, $role);
        $this->addMembership($membership);
        $user->addMembership($membership);

        return $this;
    }

    public function removeUser(User $user): static
    {
        $membership = $this->getMembershipFor($user);

        if (null !== $membership) {
            $this->removeMembership($membership);
            $user->removeMembership($membership);
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(?string $picture): static
    {
        $this->picture = $picture;

        return $this;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(Invitation $invitation): static
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setOrganisation($this);
        }

        return $this;
    }

    public function removeInvitation(Invitation $invitation): static
    {
        if ($this->invitations->removeElement($invitation)) {
            // set the owning side to null (unless already changed)
            if ($invitation->getOrganisation() === $this) {
                $invitation->setOrganisation(null);
            }
        }

        return $this;
    }
}
