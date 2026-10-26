<?php

namespace App\Tests\Validator;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\ReallocationLine;
use App\Entity\Transaction;
use App\Enum\ReallocationInstance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SufficientAvailableBalanceValidatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;
    private Organization $organization;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
        $this->organization = $this->organization('asso-available-test');
    }

    public function testTotalEqualToAvailableIsAccepted(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 1000.0, false);

        $this->assertCount(0, $this->validate($this->newReallocation($source, '1000.00')));
    }

    public function testTotalAboveAvailableIsRejectedWithBothAmounts(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 1000.0, false);

        $violations = $this->validate($this->newReallocation($source, '1000.01'));

        $this->assertCount(1, $violations);
        $this->assertSame('lines', $violations[0]->getPropertyPath());
        $this->assertStringContainsString('« Dons » dispose de 1 000,00 €', $violations[0]->getMessage());
        $this->assertStringContainsString('réaffecter 1 000,01 €', $violations[0]->getMessage());
    }

    public function testAllLinesAreAddedUp(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 1000.0, false);

        $this->assertCount(1, $this->validate($this->newReallocation($source, '600.00', '500.00')));
    }

    public function testActiveReallocationsAlreadyGivenAreDeducted(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 2000.0, false);
        $this->persistedReallocation($source, '1500.00', cancelled: false);

        // Il ne reste que 500 €.
        $this->assertCount(1, $this->validate($this->newReallocation($source, '600.00')));
    }

    public function testCancelledReallocationsAreNotDeducted(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 2000.0, false);
        $this->persistedReallocation($source, '1500.00', cancelled: true);

        $this->assertCount(0, $this->validate($this->newReallocation($source, '2000.00')));
    }

    /**
     * Les dépenses ne sont jamais bloquées : une source peut être en négatif.
     * Elle ne peut alors plus rien donner.
     */
    public function testSourceInDeficitCannotGiveAnything(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 100.0, false);
        $this->transaction($source, 300.0, true);

        $this->assertCount(1, $this->validate($this->newReallocation($source, '0.01')));
    }

    /**
     * Point de sécurité : seule l'erreur d'appartenance sort, jamais le
     * disponible d'un budget étranger.
     */
    public function testForeignSourceDoesNotRevealItsAvailable(): void
    {
        $foreignSource = $this->budget('Étranger', $this->organization('asso-etrangere'));
        $this->transaction($foreignSource, 5000.0, false);

        $violations = $this->validate($this->newReallocation($foreignSource, '10.00'));

        $this->assertCount(1, $violations);
        $this->assertSame('sourceBudget', $violations[0]->getPropertyPath());
    }

    /**
     * flush() d'abord : le calcul du disponible lit la base.
     */
    private function validate(Reallocation $reallocation): ConstraintViolationListInterface
    {
        $this->em->flush();

        return $this->validator->validate($reallocation);
    }

    private function organization(string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($slug);
        $organization->setSlug($slug);
        $organization->setIsActive(true);
        $this->em->persist($organization);

        return $organization;
    }

    private function budget(string $name, ?Organization $organization = null): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setSlug(strtolower($name));
        $budget->setOrganization($organization ?? $this->organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));
        $this->em->persist($budget);

        return $budget;
    }

    private function transaction(Budget $budget, float $amount, bool $isExpense): void
    {
        $line = new BudgetLine();
        $line->setName($isExpense ? 'Dépense' : 'Recette');
        $line->setAmount(0.0);
        $line->setIsExpense($isExpense);
        $line->setBudget($budget);
        $this->em->persist($line);

        $transaction = new Transaction();
        $transaction->setDate(new \DateTime('2026-06-01'));
        $transaction->setAmount($amount);
        $transaction->setPaymentMethod('Virement');
        $transaction->addBudgetLine($line);
        $this->em->persist($transaction);
    }

    /**
     * Réaffectation saisie, pas encore enregistrée : un budget cible par montant.
     */
    private function newReallocation(Budget $source, string ...$amounts): Reallocation
    {
        $reallocation = (new Reallocation())
            ->setOrganization($this->organization)
            ->setDecidedAt(new \DateTime('2026-10-12'))
            ->setInstance(ReallocationInstance::BOARD)
            ->setReason('Répartition don')
            ->setSourceBudget($source);

        foreach ($amounts as $i => $amount) {
            $reallocation->addLine((new ReallocationLine())
                ->setTargetBudget($this->budget('Cible ' . $i))
                ->setAmount($amount));
        }

        return $reallocation;
    }

    /**
     * Réaffectation déjà enregistrée en base, active ou annulée.
     */
    private function persistedReallocation(Budget $source, string $amount, bool $cancelled): void
    {
        $reallocation = (new Reallocation())
            ->setOrganization($this->organization)
            ->setDecidedAt(new \DateTime('2026-09-01'))
            ->setInstance(ReallocationInstance::BOARD)
            ->setReason('Réaffectation précédente')
            ->setSourceBudget($source)
            ->addLine((new ReallocationLine())->setTargetBudget($this->budget('Déjà servi'))->setAmount($amount));

        if ($cancelled) {
            $reallocation->setDeletedAt(new \DateTimeImmutable());
        }

        $this->em->persist($reallocation);
    }
}
