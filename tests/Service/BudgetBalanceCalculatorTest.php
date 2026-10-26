<?php

namespace App\Tests\Service;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\ReallocationLine;
use App\Entity\Transaction;
use App\Enum\ReallocationInstance;
use App\Model\BudgetBalance;
use App\Service\BudgetBalanceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BudgetBalanceCalculatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BudgetBalanceCalculator $calculator;
    private Organization $organization;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Construit à la main : le service n'est encore utilisé nulle part,
        // Symfony l'a donc retiré du conteneur.
        $this->calculator = new BudgetBalanceCalculator(
            $this->em->getRepository(Transaction::class),
            $this->em->getRepository(ReallocationLine::class),
        );

        $this->organization = new Organization();
        $this->organization->setName('Asso test');
        $this->organization->setSlug('asso-balance-test');
        $this->organization->setIsActive(true);
        $this->em->persist($this->organization);
    }

    public function testBudgetWithoutReallocation(): void
    {
        $budget = $this->budget('Fête');
        $this->transaction(1000.0, $this->line($budget, false));
        $this->transaction(300.0, $this->line($budget, true));

        $balance = $this->balanceOf($budget);

        $this->assertSame(100000, $balance->cashIn);
        $this->assertSame(30000, $balance->cashOut);
        $this->assertSame(70000, $balance->available());
    }

    /**
     * Défaut n°1 de l'ancien calcul : la jointure sur les catégories
     * ignorait les lignes qui n'en ont pas.
     */
    public function testLinesWithAndWithoutCategoryAreBothCounted(): void
    {
        $budget = $this->budget('Fête');
        $category = (new Category())->setName('Dons')->setBudget($budget);
        $this->em->persist($category);

        $this->transaction(200.0, $this->line($budget, false, $category));
        $this->transaction(500.0, $this->line($budget, false));

        $this->assertSame(70000, $this->balanceOf($budget)->cashIn);
    }

    /**
     * Défaut n°2 : une facture rattachée à deux lignes était comptée deux fois.
     */
    public function testTransactionLinkedToSeveralLinesIsCountedOnce(): void
    {
        $budget = $this->budget('Fête');
        $this->transaction(300.0, $this->line($budget, true), $this->line($budget, true));

        $this->assertSame(30000, $this->balanceOf($budget)->cashOut);
    }

    public function testTrashedTransactionIsIgnored(): void
    {
        $budget = $this->budget('Fête');
        $this->transaction(1000.0, $this->line($budget, false))
            ->setDeletedAt(new \DateTimeImmutable());

        $this->assertSame(0, $this->balanceOf($budget)->cashIn);
    }

    public function testReallocationToOneTarget(): void
    {
        $source = $this->budget('Dons');
        $heating = $this->budget('Chauffage');
        $this->transaction(2000.0, $this->line($source, false));
        $this->reallocate($source, [[$heating, '1200.00']]);

        $sourceBalance = $this->balanceOf($source);
        $heatingBalance = $this->balanceOf($heating);

        $this->assertSame(80000, $sourceBalance->available());
        // Le budget source garde son encaissement : la réaffectation n'est pas une dépense
        $this->assertSame(200000, $sourceBalance->cashIn);
        $this->assertSame(120000, $heatingBalance->available());
        // … et pour la cible, ce n'est pas une recette
        $this->assertSame(0, $heatingBalance->cashIn);
    }

    public function testReallocationToSeveralTargets(): void
    {
        $source = $this->budget('Dons');
        $heating = $this->budget('Chauffage');
        $works = $this->budget('Travaux');
        $this->transaction(2000.0, $this->line($source, false));
        $this->reallocate($source, [[$heating, '1200.00'], [$works, '800.00']]);

        $this->assertSame(0, $this->balanceOf($source)->available());
        $this->assertSame(120000, $this->balanceOf($heating)->available());
        $this->assertSame(80000, $this->balanceOf($works)->available());
    }

    public function testCancelledReallocationIsIgnored(): void
    {
        $source = $this->budget('Dons');
        $heating = $this->budget('Chauffage');
        $this->transaction(2000.0, $this->line($source, false));
        $this->reallocate($source, [[$heating, '1200.00']], cancelled: true);

        $this->assertSame(200000, $this->balanceOf($source)->available());
        $this->assertSame(0, $this->balanceOf($heating)->available());
    }

    /**
     * Règle 7 : une dépense n'est jamais bloquée, même si tout a déjà été réaffecté.
     */
    public function testLaterExpenseCanMakeSourceNegative(): void
    {
        $source = $this->budget('Dons');
        $heating = $this->budget('Chauffage');
        $this->transaction(2000.0, $this->line($source, false));
        $this->reallocate($source, [[$heating, '2000.00']]);
        $this->transaction(500.0, $this->line($source, true));

        $this->assertSame(-50000, $this->balanceOf($source)->available());
    }

    /**
     * « Photo » avant / après d'une opération : ce qui compte est l'état en
     * mémoire, pas encore flushé.
     */
    public function testInMemoryChangesAreCountedBeforeFlush(): void
    {
        $budget = $this->budget('Fête');
        $income = $this->transaction(1000.0, $this->line($budget, false));
        $expense = $this->transaction(300.0, $this->line($budget, true));
        $income->setAmount(400.0);

        $balance = $this->calculator->balanceOfTransactions($budget, [$income, $expense]);

        $this->assertSame(40000, $balance->cashIn);
        $this->assertSame(30000, $balance->cashOut);
        $this->assertSame(0, $balance->reallocatedIn);
        $this->assertSame(0, $balance->reallocatedOut);
    }

    public function testInMemoryTrashedTransactionOrLineNoLongerCounts(): void
    {
        $budget = $this->budget('Fête');

        $trashedTransaction = $this->transaction(1000.0, $this->line($budget, false));
        $trashedTransaction->setDeletedAt(new \DateTimeImmutable());

        $trashedLine = $this->line($budget, false);
        $onlyOnTrashedLine = $this->transaction(500.0, $trashedLine);

        // Rattachée aussi à une ligne restante : elle reste comptée.
        $alsoOnAnotherLine = $this->transaction(200.0, $trashedLine, $this->line($budget, false));

        $trashedLine->setDeletedAt(new \DateTimeImmutable());

        $balance = $this->calculator->balanceOfTransactions($budget, [$trashedTransaction, $onlyOnTrashedLine, $alsoOnAnotherLine]);

        $this->assertSame(20000, $balance->cashIn);
    }

    public function testTransactionsOfAnotherBudgetAreIgnored(): void
    {
        $budget = $this->budget('Fête');
        $other = $this->transaction(1000.0, $this->line($this->budget('Chauffage'), false));

        $this->assertSame(0, $this->calculator->balanceOfTransactions($budget, [$other])->cashIn);
    }

    private function budget(string $name): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setSlug(strtolower($name));
        $budget->setOrganization($this->organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));
        $this->em->persist($budget);

        return $budget;
    }

    private function line(Budget $budget, bool $isExpense, ?Category $category = null): BudgetLine
    {
        $line = new BudgetLine();
        $line->setName($isExpense ? 'Dépense' : 'Recette');
        $line->setAmount(0.0);
        $line->setIsExpense($isExpense);
        $line->setBudget($budget);
        $line->setCategory($category);
        $this->em->persist($line);

        return $line;
    }

    private function transaction(float $amount, BudgetLine ...$lines): Transaction
    {
        $transaction = new Transaction();
        $transaction->setDate(new \DateTime('2026-06-01'));
        $transaction->setAmount($amount);
        $transaction->setPaymentMethod('Virement');

        foreach ($lines as $line) {
            $transaction->addBudgetLine($line);
        }

        $this->em->persist($transaction);

        return $transaction;
    }

    /**
     * @param array<array{Budget, string}> $targets couples [budget cible, montant]
     */
    private function reallocate(Budget $source, array $targets, bool $cancelled = false): void
    {
        $reallocation = (new Reallocation())
            ->setOrganization($this->organization)
            ->setDecidedAt(new \DateTime('2026-10-12'))
            ->setInstance(ReallocationInstance::BOARD)
            ->setReason('Répartition don')
            ->setSourceBudget($source);

        foreach ($targets as [$target, $amount]) {
            $reallocation->addLine((new ReallocationLine())->setTargetBudget($target)->setAmount($amount));
        }

        if ($cancelled) {
            $reallocation->setDeletedAt(new \DateTimeImmutable());
        }

        $this->em->persist($reallocation);
    }

    /**
     * flush() écrit en base, clear() vide la mémoire de Doctrine : le calcul
     * lit donc réellement la base, pas les objets restés en mémoire.
     */
    private function balanceOf(Budget $budget): BudgetBalance
    {
        $this->em->flush();
        $this->em->clear();

        return $this->calculator->balanceOf($budget);
    }
}
