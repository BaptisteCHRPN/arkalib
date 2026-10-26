<?php

namespace App\Service;

use App\Entity\Budget;
use App\Model\BudgetBalance;
use App\Model\RevenueProtectionResult;
use App\Repository\ReallocationRepository;

/**
 * Seul endroit qui décide si un budget peut perdre des encaissements alors
 * qu'il a réaffecté de l'argent (suppression ou baisse d'une transaction,
 * ligne de recette supprimée ou passée en dépense).
 *
 * Une perte est bloquée si elle fait passer le disponible sous zéro alors que
 * le budget a donné des réaffectations actives. Sans réaffectation, elle
 * passe toujours, comme une dépense.
 */
final class RevenueProtectionService
{
    public function __construct(
        private BudgetBalanceCalculator $calculator,
        private ReallocationRepository $reallocationRepository,
    ) {
    }

    /**
     * Point d'entrée des contrôleurs : compare les transactions touchées avant
     * et après l'opération (BudgetBalanceCalculator::balanceOfTransactions()).
     *
     * Dès qu'une recette est perdue, on mesure l'effet total sur le disponible :
     * une ligne passée de Recette à Dépense compte deux fois. Une opération qui
     * ne fait qu'ajouter des dépenses n'est jamais bloquée.
     */
    public function checkChange(Budget $budget, BudgetBalance $before, BudgetBalance $after): RevenueProtectionResult
    {
        $drop = $before->realizedBalance() - $after->realizedBalance();

        if ($before->cashIn - $after->cashIn <= 0) {
            return new RevenueProtectionResult($this->calculator->balanceOf($budget)->available() - $drop, []);
        }

        return $this->check($budget, $drop);
    }

    /**
     * À appeler avant le flush de l'opération : le disponible lu en base doit
     * encore inclure les encaissements que le budget s'apprête à perdre.
     *
     * @param int $drop baisse du disponible causée par l'opération, en centimes
     */
    public function check(Budget $budget, int $drop): RevenueProtectionResult
    {
        $availableAfter = $this->calculator->balanceOf($budget)->available() - $drop;

        if ($drop <= 0 || $availableAfter >= 0) {
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
