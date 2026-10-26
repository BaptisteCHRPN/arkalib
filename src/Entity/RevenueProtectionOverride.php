<?php

namespace App\Entity;

use App\Model\RevenueProtectionResult;
use App\Repository\RevenueProtectionOverrideRepository;
use App\Service\BudgetBalanceCalculator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Trace d'un admin qui a passé outre la protection des recettes : un budget a
 * perdu des encaissements alors qu'il avait réaffecté cet argent.
 *
 * C'est une entrée de journal : tout est fixé à la construction, rien ne se
 * modifie ensuite. L'opération et les réaffectations sont figées en texte,
 * car la transaction forcée est justement supprimée ou modifiée.
 *
 * Pas de TraceableTrait : son auteur peut rester vide, ce qui n'a pas de sens
 * pour une trace de contrôle.
 */
#[ORM\Entity(repositoryClass: RevenueProtectionOverrideRepository::class)]
class RevenueProtectionOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Budget $budget;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $forcedBy;

    #[ORM\Column]
    private \DateTimeImmutable $forcedAt;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Indiquez pourquoi vous passez outre ce blocage.')]
    #[Assert\Length(max: 255)]
    private string $reason;

    /**
     * Ex. « Suppression de la transaction « Don Dupont » (1 000,00 €) ».
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $operation;

    /**
     * Une réaffectation par ligne, telles que le message de blocage les nommait.
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $reallocations;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $availableAfter;

    public function __construct(
        Budget $budget,
        User $forcedBy,
        string $reason,
        string $operation,
        RevenueProtectionResult $result,
    ) {
        if (!$result->isBlocked()) {
            throw new \LogicException('Rien à forcer : l\'opération est autorisée.');
        }

        $this->organization = $budget->getOrganization()
            ?? throw new \LogicException('Le budget doit appartenir à une organisation.');
        $this->budget = $budget;
        $this->forcedBy = $forcedBy;
        $this->forcedAt = new \DateTimeImmutable();
        $this->reason = trim($reason);
        $this->operation = $operation;
        $this->reallocations = implode("\n", $result->describeReallocations());
        $this->availableAfter = number_format($result->availableAfter / 100, 2, '.', '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getBudget(): Budget
    {
        return $this->budget;
    }

    public function getForcedBy(): User
    {
        return $this->forcedBy;
    }

    public function getForcedAt(): \DateTimeImmutable
    {
        return $this->forcedAt;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @return string[]
     */
    public function getReallocations(): array
    {
        return explode("\n", $this->reallocations);
    }

    /**
     * En centimes : la chaîne relue varie selon la base (« -800.00 » sous
     * MySQL, « -800 » sous SQLite), pas sa valeur.
     */
    public function getAvailableAfterInCents(): int
    {
        return BudgetBalanceCalculator::toCents($this->availableAfter);
    }
}
