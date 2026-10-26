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

        $descriptions = $this->describeReallocations();

        if (1 === count($descriptions)) {
            return sprintf(
                'Impossible de %s : cette recette finance la réaffectation %s. Annulez d\'abord cette réaffectation.',
                $action,
                $descriptions[0],
            );
        }

        $last = array_pop($descriptions);

        return sprintf(
            'Impossible de %s : cette recette finance les réaffectations %s et %s. Annulez d\'abord ces réaffectations.',
            $action,
            implode(', ', $descriptions),
            $last,
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
        return array_map(
            fn(Reallocation $reallocation) => sprintf(
                'du %s (%s) – %s',
                $reallocation->getDecidedAt()?->format('d/m/Y'),
                $reallocation->getInstance()?->shortLabel(),
                $reallocation->getReason(),
            ),
            $this->reallocationsToCancel,
        );
    }
}
