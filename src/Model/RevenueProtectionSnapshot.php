<?php

namespace App\Model;

use App\Entity\Budget;
use App\Entity\Transaction;

/**
 * État d'un budget pris AVANT de modifier quoi que ce soit en mémoire, à
 * passer ensuite à RevenueProtectionService::checkChange().
 *
 * Le disponible ne peut pas être relu après coup : Doctrine rendrait les
 * objets déjà modifiés en mémoire, et le « avant » serait faussé.
 */
final readonly class RevenueProtectionSnapshot
{
    /**
     * @param Transaction[] $transactions transactions que l'opération peut toucher
     * @param int           $availableBefore disponible avant l'opération, en centimes
     */
    public function __construct(
        public Budget $budget,
        public array $transactions,
        public int $availableBefore,
        public BudgetBalance $before,
    ) {
    }
}
