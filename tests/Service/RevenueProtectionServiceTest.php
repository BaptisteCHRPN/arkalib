<?php

namespace App\Tests\Service;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\ReallocationLine;
use App\Entity\Transaction;
use App\Enum\ReallocationInstance;
use App\Model\BudgetBalance;
use App\Model\RevenueProtectionResult;
use App\Service\BudgetBalanceCalculator;
use App\Service\RevenueProtectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RevenueProtectionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RevenueProtectionService $service;
    private Organization $organization;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Construit à la main : le service n'est encore utilisé nulle part,
        // Symfony l'a donc retiré du conteneur.
        $this->service = new RevenueProtectionService(
            static::getContainer()->get(BudgetBalanceCalculator::class),
            $this->em->getRepository(Reallocation::class),
        );

        $this->organization = new Organization();
        $this->organization->setName('Asso test');
        $this->organization->setSlug('asso-revenue-protection-test');
        $this->organization->setIsActive(true);
        $this->em->persist($this->organization);
    }

    public function testLossIsAllowedWhenEnoughRemains(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1000.0, false);
        $this->reallocation($source, '300.00', '2026-10-12');

        $result = $this->check($source, 50000);

        $this->assertFalse($result->isBlocked());
        $this->assertSame(20000, $result->availableAfter);
    }

    /**
     * Sans réaffectation, une perte de recette se comporte comme une dépense :
     * jamais bloquée, même en négatif.
     */
    public function testLossIsAllowedWithoutReallocationEvenIfNegative(): void
    {
        $budget = $this->budget('Fête');
        $this->transaction($budget, 1000.0, false);
        $this->transaction($budget, 800.0, true);

        $result = $this->check($budget, 100000);

        $this->assertFalse($result->isBlocked());
        $this->assertSame(-80000, $result->availableAfter);
    }

    public function testLossIsBlockedAndNamesTheReallocation(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1000.0, false);
        $reallocation = $this->reallocation($source, '800.00', '2026-10-12');

        $result = $this->check($source, 100000);

        $this->assertTrue($result->isBlocked());
        $this->assertSame([$reallocation], $result->reallocationsToCancel);
        $this->assertSame(-80000, $result->availableAfter);
    }

    /**
     * Mars : 300 €, octobre : 800 €. La perte creuse un déficit de 500 € :
     * annuler celle d'octobre suffit, on ne nomme pas celle de mars.
     *
     * Octobre est saisie en premier : c'est bien la date de décision qui
     * ordonne, pas l'ordre de saisie.
     */
    public function testOnlyTheMostRecentReallocationsNeededAreNamed(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1600.0, false);
        $october = $this->reallocation($source, '800.00', '2026-10-12');
        $this->reallocation($source, '300.00', '2026-03-01');

        $result = $this->check($source, 100000);

        $this->assertSame(-50000, $result->availableAfter);
        $this->assertSame([$october], $result->reallocationsToCancel);
    }

    public function testAllReallocationsAreNamedWhenNeeded(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1100.0, false);
        $march = $this->reallocation($source, '300.00', '2026-03-01');
        $october = $this->reallocation($source, '800.00', '2026-10-12');

        $result = $this->check($source, 110000);

        $this->assertSame([$october, $march], $result->reallocationsToCancel);
    }

    public function testCancelledReallocationsAreIgnored(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1000.0, false);
        $this->reallocation($source, '800.00', '2026-10-12', cancelled: true);

        $this->assertFalse($this->check($source, 100000)->isBlocked());
    }

    /**
     * Un budget déjà dans le rouge à cause de ses dépenses n'est pas bloqué
     * tant qu'il ne perd aucun encaissement.
     */
    public function testNoLossIsAlwaysAllowed(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1000.0, false);
        $this->reallocation($source, '800.00', '2026-10-12');
        $this->transaction($source, 500.0, true);

        $this->assertFalse($this->check($source, 0)->isBlocked());
    }

    /**
     * Cas limite validé : 1 000 € de dons, 600 € réaffectés, 400 € de
     * disponible. Une transaction de 300 € passe de Recette à Dépense :
     * −300 € d'encaissé et +300 € de décaissé, soit −200 € au final.
     */
    public function testIncomeTurnedIntoExpenseCountsTwice(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1000.0, false);
        $this->reallocation($source, '600.00', '2026-10-12');

        $result = $this->checkChange($source, new BudgetBalance(30000, 0, 0, 0), new BudgetBalance(0, 30000, 0, 0));

        $this->assertTrue($result->isBlocked());
        $this->assertSame(-20000, $result->availableAfter);
    }

    /**
     * Une dépense de plus n'est jamais bloquée, même si tout a été réaffecté.
     */
    public function testAddingExpensesIsNeverBlocked(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1000.0, false);
        $this->reallocation($source, '1000.00', '2026-10-12');

        $result = $this->checkChange($source, new BudgetBalance(0, 10000, 0, 0), new BudgetBalance(0, 50000, 0, 0));

        $this->assertFalse($result->isBlocked());
        $this->assertSame(-40000, $result->availableAfter);
    }

    public function testLowerIncomeWithinAvailableIsAllowed(): void
    {
        $source = $this->budget('Fête');
        $this->transaction($source, 1000.0, false);
        $this->reallocation($source, '600.00', '2026-10-12');

        $result = $this->checkChange($source, new BudgetBalance(100000, 0, 0, 0), new BudgetBalance(70000, 0, 0, 0));

        $this->assertFalse($result->isBlocked());
        $this->assertSame(10000, $result->availableAfter);
    }

    private function checkChange(Budget $budget, BudgetBalance $before, BudgetBalance $after): RevenueProtectionResult
    {
        $this->em->flush();

        return $this->service->checkChange($budget, $before, $after);
    }

    private function check(Budget $budget, int $lostCashIn): RevenueProtectionResult
    {
        $this->em->flush();

        return $this->service->check($budget, $lostCashIn);
    }

    private function budget(string $name): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setSlug(strtolower($name) . '-' . uniqid());
        $budget->setOrganization($this->organization);
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
     * Chaque réaffectation va vers un nouveau budget cible.
     */
    private function reallocation(Budget $source, string $amount, string $decidedAt, bool $cancelled = false): Reallocation
    {
        $reallocation = (new Reallocation())
            ->setOrganization($this->organization)
            ->setDecidedAt(new \DateTime($decidedAt))
            ->setInstance(ReallocationInstance::BOARD)
            ->setReason('Répartition')
            ->setSourceBudget($source)
            ->addLine((new ReallocationLine())->setTargetBudget($this->budget('Cible'))->setAmount($amount));

        if ($cancelled) {
            $reallocation->setDeletedAt(new \DateTimeImmutable());
        }

        $this->em->persist($reallocation);

        return $reallocation;
    }
}
