<?php

namespace App\Entity;

use App\Repository\ReallocationLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Part d'une réaffectation attribuée à un budget cible.
 *
 * Pas de soft delete propre : une ligne vit et s'annule avec sa réaffectation.
 */
#[ORM\Entity(repositoryClass: ReallocationLineRepository::class)]
#[ORM\UniqueConstraint(name: 'reallocation_line_target', columns: ['reallocation_id', 'target_budget_id'])]
class ReallocationLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reallocation $reallocation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Choisissez le budget qui reçoit ce montant.')]
    private ?Budget $targetBudget = null;

    /**
     * Decimal et non float : Doctrine le rend en string pour garder la valeur
     * exacte. Les calculs se font en centimes (voir BudgetBalanceCalculator).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank(message: 'Indiquez un montant.')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à zéro.')]
    private ?string $amount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReallocation(): ?Reallocation
    {
        return $this->reallocation;
    }

    public function setReallocation(?Reallocation $reallocation): static
    {
        $this->reallocation = $reallocation;

        return $this;
    }

    public function getTargetBudget(): ?Budget
    {
        return $this->targetBudget;
    }

    public function setTargetBudget(?Budget $targetBudget): static
    {
        $this->targetBudget = $targetBudget;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }
}
