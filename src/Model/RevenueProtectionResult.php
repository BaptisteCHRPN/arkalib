<?php

namespace App\Model;

use App\Entity\Reallocation;

/**
 * Verdict de RevenueProtectionService sur une perte d'encaissement.
 *
 * Il ne dit pas qui peut passer outre : c'est une question de permission,
 * tranchée par le contrôleur.
 */
final readonly class RevenueProtectionResult
{
    /**
     * @param int            $availableAfter        disponible après l'opération, en centimes
     * @param Reallocation[] $reallocationsToCancel les plus récentes d'abord ; vide si l'opération est autorisée
     */
    public function __construct(
        public int $availableAfter,
        public array $reallocationsToCancel,
    ) {
    }

    public function isBlocked(): bool
    {
        return [] !== $this->reallocationsToCancel;
    }

    /**
     * @param string $action ce que l'utilisateur voulait faire, ex. « supprimer cette transaction »
     */
    public function message(string $action): string
    {
        if (!$this->isBlocked()) {
            throw new \LogicException('Aucun message : l\'opération est autorisée.');
        }

        return sprintf(
            'Impossible de %s : cette recette finance %s. Annulez d\'abord %s.',
            $action,
            Reallocation::phrase($this->reallocationsToCancel),
            1 === count($this->reallocationsToCancel) ? 'cette réaffectation' : 'ces réaffectations',
        );
    }

    /**
     * Une description par réaffectation, ex. « du 12/10/2026 (CA) – Répartition don ».
     * Partagée par le message et la trace d'un forçage.
     *
     * @return string[]
     */
    public function describeReallocations(): array
    {
        return array_map(fn(Reallocation $reallocation) => $reallocation->describe(), $this->reallocationsToCancel);
    }
}
