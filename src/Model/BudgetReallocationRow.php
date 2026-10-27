<?php

namespace App\Model;

use App\Entity\Budget;
use App\Entity\Reallocation;

/**
 * Une réaffectation vue depuis un budget : reçue ou donnée, et pour quel
 * montant le concernant.
 */
final readonly class BudgetReallocationRow
{
    /**
     * @param int      $amount       montant pour ce budget, en centimes, toujours positif
     * @param Budget[] $counterparts la source si la réaffectation est reçue, les cibles si elle est donnée
     */
    public function __construct(
        public Reallocation $reallocation,
        public bool $received,
        public int $amount,
        public array $counterparts,
    ) {
    }

    /**
     * Positif si reçue, négatif si donnée : ce qu'elle ajoute au disponible.
     */
    public function signedAmount(): int
    {
        return $this->received ? $this->amount : -$this->amount;
    }
}
