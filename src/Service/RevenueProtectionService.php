<?php

namespace App\Service;

use App\Entity\Budget;
use App\Entity\Transaction;
use App\Model\RevenueProtectionResult;
use App\Model\RevenueProtectionSnapshot;
use App\Repository\ReallocationRepository;

/**
 * Seul endroit qui décide si un budget peut perdre des encaissements alors
 * qu'il a réaffecté de l'argent (suppression ou baisse d'une transaction,
 * ligne de recette supprimée ou passée en dépense).
 *
 * Une perte est bloquée si elle fait passer le disponible sous zéro alors que
 * le budget a donné des réaffectations actives. Sans réaffectation, elle
 * passe toujours, comme une dépense.
 *
 * Usage dans un contrôleur : snapshot() AVANT de toucher aux objets, puis la
 * modification en mémoire, puis checkChange(), et le flush seulement si
 * l'opération n'est pas bloquée.
 */
final class RevenueProtectionService
{
    public function __construct(
        private BudgetBalanceCalculator $calculator,
        private ReallocationRepository $reallocationRepository,
    ) {
    }

    /**
     * @param iterable<Transaction> $transactions transactions que l'opération peut toucher
     */
    public function snapshot(Budget $budget, iterable $transactions): RevenueProtectionSnapshot
    {
        $transactions = is_array($transactions) ? $transactions : iterator_to_array($transactions, false);

        return new RevenueProtectionSnapshot(
            $budget,
            $transactions,
            $this->calculator->balanceOf($budget)->available(),
            $this->calculator->balanceOfTransactions($budget, $transactions),
        );
    }

    /**
     * Compare les transactions de la photo avant et après la modification
     * faite en mémoire.
     *
     * Dès qu'une recette est perdue, on mesure l'effet total sur le disponible :
     * une ligne passée de Recette à Dépense compte deux fois. Une opération qui
     * ne fait qu'ajouter des dépenses n'est jamais bloquée.
     */
    public function checkChange(RevenueProtectionSnapshot $snapshot): RevenueProtectionResult
    {
        $before = $snapshot->before;
        $after = $this->calculator->balanceOfTransactions($snapshot->budget, $snapshot->transactions);

        $drop = $before->realizedBalance() - $after->realizedBalance();
        $availableAfter = $snapshot->availableBefore - $drop;

        if ($before->cashIn - $after->cashIn <= 0) {
            return new RevenueProtectionResult($availableAfter, []);
        }

        return $this->verdict($snapshot->budget, $availableAfter);
    }

    /**
     * Variante sans modification en mémoire : le disponible lu en base doit
     * encore inclure les encaissements que le budget s'apprête à perdre.
     *
     * @param int $drop baisse du disponible causée par l'opération, en centimes
     */
    public function check(Budget $budget, int $drop): RevenueProtectionResult
    {
        $availableAfter = $this->calculator->balanceOf($budget)->available() - $drop;

        if ($drop <= 0) {
            return new RevenueProtectionResult($availableAfter, []);
        }

        return $this->verdict($budget, $availableAfter);
    }

    private function verdict(Budget $budget, int $availableAfter): RevenueProtectionResult
    {
        if ($availableAfter >= 0) {
            return new RevenueProtectionResult($availableAfter, []);
        }

        // On nomme le strict nécessaire : les plus récentes d'abord, jusqu'à
        // ce que leur annulation ramène le disponible à zéro ou plus.
        $toCancel = [];
        $recovered = 0;

        foreach ($this->reallocationRepository->findActiveGivenBy($budget) as $reallocation) {
            $toCancel[] = $reallocation;

            foreach ($reallocation->getLines() as $line) {
                $recovered += BudgetBalanceCalculator::toCents($line->getAmount());
            }

            if ($availableAfter + $recovered >= 0) {
                break;
            }
        }

        return new RevenueProtectionResult($availableAfter, $toCancel);
    }
}
