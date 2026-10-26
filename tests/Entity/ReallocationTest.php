<?php

namespace App\Tests\Entity;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\ReallocationLine;
use App\Enum\ReallocationInstance;
use App\Validator\SufficientAvailableBalanceValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

final class ReallocationTest extends TestCase
{
    private Organization $organization;

    protected function setUp(): void
    {
        $this->organization = (new Organization())->setName('Asso test');
    }

    public function testValidReallocationIsAccepted(): void
    {
        $reallocation = $this->reallocation($this->budget('Fête'), $this->budget('Chauffage'), $this->budget('Sorties'));

        $this->assertCount(0, $this->validate($reallocation));
    }

    public function testReallocationWithoutLineIsRejected(): void
    {
        $violations = $this->validate($this->reallocation($this->budget('Fête')));

        $this->assertCount(1, $violations);
        $this->assertSame('lines', $violations[0]->getPropertyPath());
    }

    public function testZeroOrNegativeAmountIsRejected(): void
    {
        foreach (['0.00', '-10.00'] as $amount) {
            $reallocation = $this->reallocation($this->budget('Fête'), $this->budget('Chauffage'));
            $reallocation->getLines()->first()->setAmount($amount);

            $violations = $this->validate($reallocation);

            $this->assertCount(1, $violations, "Montant $amount");
            $this->assertSame('lines[0].amount', $violations[0]->getPropertyPath());
        }
    }

    public function testSourceCannotBeATarget(): void
    {
        $source = $this->budget('Fête');

        $violations = $this->validate($this->reallocation($source, $source));

        $this->assertCount(1, $violations);
        $this->assertSame('lines[0].targetBudget', $violations[0]->getPropertyPath());
    }

    public function testSameTargetTwiceIsReportedOnTheSecondLine(): void
    {
        $target = $this->budget('Chauffage');

        $violations = $this->validate($this->reallocation($this->budget('Fête'), $target, $target));

        $this->assertCount(1, $violations);
        $this->assertSame('lines[1].targetBudget', $violations[0]->getPropertyPath());
    }

    /**
     * Point de sécurité : simule un id de budget trafiqué dans la requête.
     */
    public function testSourceFromAnotherOrganizationIsRejected(): void
    {
        $foreignSource = $this->budget('Budget étranger')->setOrganization(new Organization());

        $violations = $this->validate($this->reallocation($foreignSource, $this->budget('Chauffage')));

        $this->assertCount(1, $violations);
        $this->assertSame('sourceBudget', $violations[0]->getPropertyPath());
    }

    public function testTargetFromAnotherOrganizationIsRejected(): void
    {
        $foreignTarget = $this->budget('Budget étranger')->setOrganization(new Organization());

        $violations = $this->validate($this->reallocation($this->budget('Fête'), $foreignTarget));

        $this->assertCount(1, $violations);
        $this->assertSame('lines[0].targetBudget', $violations[0]->getPropertyPath());
        $this->assertStringNotContainsString('Budget étranger', $violations[0]->getMessage());
    }

    public function testClosedSourceIsRejectedWithItsName(): void
    {
        $closedSource = $this->budget('Fête')->setIsClosed(true);

        $violations = $this->validate($this->reallocation($closedSource, $this->budget('Chauffage')));

        $this->assertCount(1, $violations);
        $this->assertSame('sourceBudget', $violations[0]->getPropertyPath());
        $this->assertStringContainsString('« Fête » est clôturé', $violations[0]->getMessage());
    }

    public function testClosedTargetIsRejected(): void
    {
        $closedTarget = $this->budget('Chauffage')->setIsClosed(true);

        $violations = $this->validate($this->reallocation($this->budget('Fête'), $closedTarget));

        $this->assertCount(1, $violations);
        $this->assertSame('lines[0].targetBudget', $violations[0]->getPropertyPath());
    }

    public function testDecisionDetailsAreRequired(): void
    {
        $reallocation = (new Reallocation())
            ->setOrganization($this->organization)
            ->setSourceBudget($this->budget('Fête'))
            ->addLine((new ReallocationLine())->setTargetBudget($this->budget('Chauffage'))->setAmount('100.00'));

        $paths = array_map(
            fn($violation) => $violation->getPropertyPath(),
            iterator_to_array($this->validate($reallocation)),
        );

        $this->assertSame(['decidedAt', 'instance', 'reason'], $paths);
    }

    private function validate(Reallocation $reallocation): ConstraintViolationListInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            // Le contrôle du disponible a besoin de la base : il est testé à part,
            // dans SufficientAvailableBalanceValidatorTest. Ici on le remplace
            // par un validateur qui ne fait rien.
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                SufficientAvailableBalanceValidator::class => new class extends ConstraintValidator {
                    public function validate(mixed $value, Constraint $constraint): void
                    {
                    }
                },
            ]))
            ->getValidator()
            ->validate($reallocation);
    }

    private function budget(string $name): Budget
    {
        return (new Budget())->setName($name)->setOrganization($this->organization);
    }

    /**
     * Réaffectation de l'organisation de test, avec 100 € par cible fournie.
     */
    private function reallocation(Budget $source, Budget ...$targets): Reallocation
    {
        $reallocation = (new Reallocation())
            ->setOrganization($this->organization)
            ->setDecidedAt(new \DateTime('2026-10-12'))
            ->setInstance(ReallocationInstance::BOARD)
            ->setReason('Répartition don')
            ->setSourceBudget($source);

        foreach ($targets as $target) {
            $reallocation->addLine((new ReallocationLine())->setTargetBudget($target)->setAmount('100.00'));
        }

        return $reallocation;
    }
}
