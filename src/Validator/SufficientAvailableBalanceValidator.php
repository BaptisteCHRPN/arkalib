<?php

namespace App\Validator;

use App\Entity\Reallocation;
use App\Service\BudgetBalanceCalculator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * À lancer avant le flush qui crée ou restaure la réaffectation : tant
 * qu'elle n'est pas active en base, elle n'est pas déjà déduite du
 * disponible de sa source.
 */
final class SufficientAvailableBalanceValidator extends ConstraintValidator
{
    public function __construct(
        private BudgetBalanceCalculator $calculator,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SufficientAvailableBalance) {
            throw new UnexpectedTypeException($constraint, SufficientAvailableBalance::class);
        }

        if (!$value instanceof Reallocation) {
            throw new UnexpectedValueException($value, Reallocation::class);
        }

        $source = $value->getSourceBudget();

        // Source absente ou d'une autre organisation : déjà refusée par
        // Reallocation::validateBudgets(). On ne calcule surtout pas le
        // disponible d'un budget étranger, que le message révélerait.
        if (null === $source || $source->getOrganization() !== $value->getOrganization()) {
            return;
        }

        $total = 0;

        foreach ($value->getLines() as $line) {
            // Un montant négatif est déjà refusé sur sa ligne : il ne doit
            // pas en plus faire baisser le total.
            $total += max(0, BudgetBalanceCalculator::toCents($line->getAmount()));
        }

        $available = $this->calculator->balanceOf($source)->available();

        if ($total > $available) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ name }}', (string) $source->getName())
                ->setParameter('{{ available }}', self::format($available))
                ->setParameter('{{ total }}', self::format($total))
                ->atPath('lines')
                ->addViolation();
        }
    }

    private static function format(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}
