<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column(length: 255)]
    private ?string $payment_method = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?bool $is_active = null;

    /**
     * @var Collection<int, BudgetLine>
     */
    #[ORM\ManyToMany(targetEntity: BudgetLine::class, inversedBy: 'transactions')]
    private Collection $budget_line;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    public function __construct()
    {
        $this->budget_line = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
        $this->is_active = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->payment_method;
    }

    public function setPaymentMethod(string $payment_method): static
    {
        $this->payment_method = $payment_method;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

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

    /**
     * @return Collection<int, BudgetLine>
     */
    public function getBudgetLine(): Collection
    {
        return $this->budget_line;
    }

    public function addBudgetLine(BudgetLine $budgetLine): static
    {
        if (!$this->budget_line->contains($budgetLine)) {
            $this->budget_line->add($budgetLine);
        }

        return $this;
    }

    public function removeBudgetLine(BudgetLine $budgetLine): static
    {
        $this->budget_line->removeElement($budgetLine);

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }
}
