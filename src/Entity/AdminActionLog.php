<?php

namespace App\Entity;

use App\Repository\AdminActionLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une intervention d'un administrateur global, enregistrée pour pouvoir en
 * rendre compte.
 *
 * Complète la traçabilité portée par les entités elles-mêmes, qui ne suffit
 * pas : une suppression définitive emporte sa trace avec la ligne, seul le
 * dernier auteur d'une modification est conservé, et les actes qui n'écrivent
 * rien — envoyer un e-mail, accorder un rôle — n'y figurent pas.
 *
 * L'auteur est une relation plutôt qu'un nom recopié : anonymiser un compte
 * doit effacer son identité ici aussi, sans quoi le droit à l'effacement
 * s'arrêterait à la porte du journal. Le nom de l'organisation, lui, est figé :
 * une organisation supprimée ne doit pas rendre le journal illisible.
 */
#[ORM\Entity(repositoryClass: AdminActionLogRepository::class)]
#[ORM\Table(name: 'admin_action_log')]
#[ORM\Index(name: 'idx_admin_action_performed_at', columns: ['performed_at'])]
class AdminActionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $performedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    /** Libellé lisible, déduit de la route : « Anonymiser un compte ». */
    #[ORM\Column(length: 255)]
    private string $action;

    #[ORM\Column(length: 255)]
    private string $routeName;

    #[ORM\Column(length: 10)]
    private string $method;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    /** Instantané du nom : l'organisation peut disparaître, pas le journal. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $organizationName = null;

    /** L'objet visé quand la route permet de l'identifier : « Budget #12 ». */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $target = null;

    #[ORM\Column]
    private int $statusCode;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function __construct()
    {
        $this->performedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPerformedAt(): \DateTimeImmutable
    {
        return $this->performedAt;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): static
    {
        $this->routeName = $routeName;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    /** Fige le nom au passage : il doit survivre à la suppression. */
    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;
        $this->organizationName = $organization?->getName();

        return $this;
    }

    public function getOrganizationName(): ?string
    {
        return $this->organizationName;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    /** Une action refusée reste au journal : une tentative se raconte aussi. */
    public function wasRefused(): bool
    {
        return $this->statusCode >= 400;
    }
}
