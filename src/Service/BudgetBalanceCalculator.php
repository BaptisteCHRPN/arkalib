<?php

namespace App\Service;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Transaction;
use App\Enum\TransactionDirection;
use App\Model\BudgetBalance;
use App\Repository\BudgetLineRepository;
use App\Repository\ReallocationLineRepository;
use App\Repository\TransactionRepository;

/**
 * Seul endroit où se calculent le réel, le prévu et le disponible d'un budget.
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
        private BudgetLineRepository $budgetLineRepository,
    ) {
    }

    public function balanceOf(Budget $budget): BudgetBalance
    {
        // La requête ne renvoie déjà que les transactions de ce budget.
        $realized = self::sum($this->transactionRepository->findRealizedByBudget($budget));

        return new BudgetBalance(
            $realized->cashIn,
            $realized->cashOut,
            ...$this->reallocationsOf($budget),
        );
    }

    /**
     * Le prévu : cashIn et cashOut y sont les lignes de recette et de dépense
     * actives. Une réaffectation y compte pour le même montant que dans le
     * réel : décidée par l'instance, elle est à la fois prévue et réalisée.
     */
    public function plannedBalanceOf(Budget $budget): BudgetBalance
    {
        return new BudgetBalance(
            self::toCents($this->budgetLineRepository->sumIncomesBudget($budget)),
            self::toCents($this->budgetLineRepository->sumExpensesBudget($budget)),
            ...$this->reallocationsOf($budget),
        );
    }

    /**
     * @return array{int, int} réaffectations reçues, puis données, en centimes
     */
    private function reallocationsOf(Budget $budget): array
    {
        return [
            self::toCents($this->reallocationLineRepository->sumReceivedBy($budget)),
            self::toCents($this->reallocationLineRepository->sumGivenBy($budget)),
        ];
    }

    /**
     * Encaissé et décaissé de ces transactions pour ce budget, d'après leur
     * état en mémoire : une modification pas encore flushée compte déjà.
     * Sert à comparer un avant et un après ; les réaffectations valent zéro.
     *
     * @param iterable<Transaction> $transactions
     */
    public function balanceOfTransactions(Budget $budget, iterable $transactions): BudgetBalance
    {
        $counted = [];

        foreach ($transactions as $transaction) {
            if (self::countsFor($transaction, $budget)) {
                $counted[] = $transaction;
            }
        }

        return self::sum($counted);
    }

    /**
     * @param iterable<Transaction> $transactions
     */
    private static function sum(iterable $transactions): BudgetBalance
    {
        $cashIn = 0;
        $cashOut = 0;

        foreach ($transactions as $transaction) {
            $direction = $transaction->getDirection();

            if (TransactionDirection::CASH_IN === $direction) {
                $cashIn += self::toCents($transaction->getAmount());
            } elseif (TransactionDirection::CASH_OUT === $direction) {
                $cashOut += self::toCents($transaction->getAmount());
            }
            // Sens indéterminé (lignes mixtes saisies avant la validation) :
            // on ne peut pas savoir dans quelle colonne compter le montant.
        }

        return new BudgetBalance($cashIn, $cashOut, 0, 0);
    }

    /**
     * Les conditions de findRealizedByBudget(), lues en mémoire : transaction
     * hors corbeille, rattachée à au moins une ligne de ce budget hors corbeille.
     */
    private static function countsFor(Transaction $transaction, Budget $budget): bool
    {
        if (null !== $transaction->getDeletedAt()) {
            return false;
        }

        return $transaction->getBudgetLine()->exists(
            fn(int $i, BudgetLine $line) => null === $line->getDeletedAt() && $line->getBudget() === $budget
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
