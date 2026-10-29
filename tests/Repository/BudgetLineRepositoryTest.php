<?php

namespace App\Tests\Repository;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Repository\BudgetLineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Totaux par catégorie de la page Prévu vs Réel. Le piège : joindre les
 * transactions aux lignes produit une rangée par couple ligne × transaction,
 * et un SUM sur ces rangées compte plusieurs fois les mêmes montants.
 */
final class BudgetLineRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Budget $budget;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Asso Chorale');
        $organization->setSlug('asso-chorale');
        $this->entityManager->persist($organization);

        $this->budget = new Budget();
        $this->budget->setName('Saison');
        $this->budget->setSlug('saison');
        $this->budget->setStartDate(new \DateTime('2025-01-01'));
        $this->budget->setEndDate(new \DateTime('2025-12-31'));
        $organization->addBudget($this->budget);
        $this->entityManager->persist($this->budget);
    }

    public function testPlannedTotalIsNotMultipliedByTheNumberOfTransactions(): void
    {
        $category = $this->category('Matériel');
        $line = $this->line('Partitions', 100.0, true, $category);
        $this->transaction(10.0, $line);
        $this->transaction(10.0, $line);
        $this->transaction(10.0, $line);
        $this->entityManager->flush();

        $row = $this->onlyRow($this->repository()->sumExpensesByCategory($this->budget));

        $this->assertEqualsWithDelta(100.0, (float) $row['total'], 0.001, 'prévu');
        $this->assertEqualsWithDelta(30.0, (float) $row['real_total'], 0.001, 'réalisé');
    }

    public function testTransactionSplitOverTwoLinesOfTheSameCategoryCountsOnce(): void
    {
        $category = $this->category('Matériel');
        $partitions = $this->line('Partitions', 50.0, true, $category);
        $pupitres = $this->line('Pupitres', 50.0, true, $category);
        $this->transaction(80.0, $partitions, $pupitres);
        $this->entityManager->flush();

        $row = $this->onlyRow($this->repository()->sumExpensesByCategory($this->budget));

        $this->assertEqualsWithDelta(100.0, (float) $row['total'], 0.001, 'prévu');
        $this->assertEqualsWithDelta(80.0, (float) $row['real_total'], 0.001, 'réalisé');
    }

    public function testIncomesAreNotMultipliedEither(): void
    {
        $category = $this->category('Cotisations');
        $line = $this->line('Adhésions', 200.0, false, $category);
        $this->transaction(50.0, $line);
        $this->transaction(50.0, $line);
        $this->entityManager->flush();

        $row = $this->onlyRow($this->repository()->sumIncomesByCategory($this->budget));

        $this->assertEqualsWithDelta(200.0, (float) $row['total'], 0.001, 'prévu');
        $this->assertEqualsWithDelta(100.0, (float) $row['real_total'], 0.001, 'réalisé');
    }

    private function repository(): BudgetLineRepository
    {
        return static::getContainer()->get(BudgetLineRepository::class);
    }

    private function category(string $name): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setBudget($this->budget);
        $this->entityManager->persist($category);

        return $category;
    }

    private function line(string $name, float $amount, bool $isExpense, Category $category): BudgetLine
    {
        $line = new BudgetLine();
        $line->setName($name);
        $line->setAmount($amount);
        $line->setIsExpense($isExpense);
        $line->setIsActive(true);
        $line->setCategory($category);
        $this->budget->addBudgetLine($line);
        $this->entityManager->persist($line);

        return $line;
    }

    private function transaction(float $amount, BudgetLine ...$lines): Transaction
    {
        $transaction = new Transaction();
        $transaction->setDate(new \DateTime('2025-03-01'));
        $transaction->setAmount($amount);
        $transaction->setPaymentMethod('Virement');

        foreach ($lines as $line) {
            $line->addTransaction($transaction);
        }

        $this->entityManager->persist($transaction);

        return $transaction;
    }

    /**
     * @param array<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function onlyRow(array $rows): array
    {
        $this->assertCount(1, $rows, 'une seule catégorie attendue');

        return $rows[0];
    }
}
