<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Le total d'une réaffectation ne dépasse pas le disponible réel de sa source.
 *
 * Contrainte de classe : elle a besoin à la fois de la source et des lignes.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class SufficientAvailableBalance extends Constraint
{
    public string $message = 'Le budget « {{ name }} » dispose de {{ available }} : impossible d\'en réaffecter {{ total }}.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
