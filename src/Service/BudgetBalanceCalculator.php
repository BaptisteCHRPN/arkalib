<?php

namespace App\Service;

use App\Entity\Budget;
use App\Enum\TransactionDirection;
use App\Model\BudgetBalance;
use App\Repository\ReallocationLineRepository;
use App\Repository\TransactionRepository;

/**
 * Seul endroit où se calculent le réel et le disponible d'un budget.
 *
 * Toutes les transactions comptent comme réalisées : le projet n'a pas de
 * statut prévu / réalisé. S'il en apparaît un, c'est ici seulement qu'il
 * faudra filtrer.
 */
final class BudgetBalanceCalculator
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private ReallocationLineRepository $reallocationLineRepository,
    ) {
    }

    public function balanceOf(Budget $budget): BudgetBalance
    {
        $cashIn = 0;
        $cashOut = 0;

        foreach ($this->transactionRepository->findRealizedByBudget($budget) as $transaction) {
            $direction = $transaction->getDirection();

            if (TransactionDirection::CASH_IN === $direction) {
                $cashIn += self::toCents($transaction->getAmount());
            } elseif (TransactionDirection::CASH_OUT === $direction) {
                $cashOut += self::toCents($transaction->getAmount());
            }
            // Sens indéterminé (lignes mixtes saisies avant la validation) :
            // on ne peut pas savoir dans quelle colonne compter le montant.
        }

        return new BudgetBalance(
            $cashIn,
            $cashOut,
            self::toCents($this->reallocationLineRepository->sumReceivedBy($budget)),
            self::toCents($this->reallocationLineRepository->sumGivenBy($budget)),
        );
    }

    /**
     * Public pour que tout le code convertisse les montants de la même façon.
     *
     * round() avant (int) : 19.99 * 100 vaut 1998.9999… en float, et un
     * simple (int) tronquerait à 1998.
     */
    public static function toCents(float|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
