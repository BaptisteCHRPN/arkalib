<?php

namespace App\Model;

/**
 * Disponible réel d'un budget, en centimes.
 *
 * Produit uniquement par BudgetBalanceCalculator : aucun autre endroit du
 * code ne doit additionner ces montants lui-même.
 */
final readonly class BudgetBalance
{
    public function __construct(
        public int $cashIn,
        public int $cashOut,
        public int $reallocatedIn,
        public int $reallocatedOut,
    ) {
    }

    /**
     * disponible = encaissements − décaissements
     *            + réaffectations reçues − réaffectations données
     *
     * Peut être négatif : une dépense n'est jamais bloquée (règle 7).
     */
    public function available(): int
    {
        return $this->cashIn - $this->cashOut + $this->reallocatedIn - $this->reallocatedOut;
    }

    /**
     * Ce que le budget a réellement encaissé moins décaissé, sans les
     * réaffectations : le « solde réel » de la page Prévu vs Réel.
     */
    public function realizedBalance(): int
    {
        return $this->cashIn - $this->cashOut;
    }
}
