<?php

namespace App\Tests\Repository;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Repository\BudgetLineRepository;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Les quatre annuaires du domaine cherchent à travers leurs relations : la
 * question posée à ces écrans est presque toujours « ceux de l'asso X ».
 * Ces tests valident surtout les jointures DQL, qui ne se voient qu'à
 * l'exécution.
 */
final class AdminDirectorySearchTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function organization(string $name, string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);
        $this->entityManager->persist($organization);

        return $organization;
    }

    private function budget(Organization $organization, string $name, string $slug): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setSlug($slug);
        $budget->setStartDate(new \DateTime('2025-01-01'));
        $budget->setEndDate(new \DateTime('2025-12-31'));
        $organization->addBudget($budget);
        $this->entityManager->persist($budget);

        return $budget;
    }

    public function testBudgetsAreFoundByOrganizationName(): void
    {
        $chorale = $this->organization('Asso Chorale', 'asso-chorale');
        $tennis = $this->organization('Club de tennis', 'club-tennis');
        $this->budget($chorale, 'Saison', 'saison-chorale');
        $this->budget($tennis, 'Saison', 'saison-tennis');
        $this->entityManager->flush();

        $repository = static::getContainer()->get(BudgetRepository::class);
        $results = $repository->search('Chorale');

        $this->assertCount(1, $results);
        $this->assertSame('saison-chorale', $results[0]->getSlug());
    }

    public function testBudgetsAreAlsoFoundByTheirOwnName(): void
    {
        $chorale = $this->organization('Asso Chorale', 'asso-chorale');
        $this->budget($chorale, 'Festival de printemps', 'festival');
        $this->budget($chorale, 'Fonctionnement', 'fonctionnement');
        $this->entityManager->flush();

        $results = static::getContainer()->get(BudgetRepository::class)->search('Festival');

        $this->assertCount(1, $results);
    }

    public function testBudgetLinesAreFoundThroughTwoJoins(): void
    {
        $chorale = $this->organization('Asso Chorale', 'asso-chorale');
        $budget = $this->budget($chorale, 'Saison', 'saison');

        $line = new BudgetLine();
        $line->setName('Partitions');
        $line->setAmount(120.0);
        $line->setIsExpense(true);
        $line->setIsActive(true);
        $line->setBudget($budget);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        $repository = static::getContainer()->get(BudgetLineRepository::class);

        $this->assertCount(1, $repository->search('Chorale'), 'par organisation');
        $this->assertCount(1, $repository->search('Saison'), 'par budget');
        $this->assertCount(1, $repository->search('Partitions'), 'par nom de ligne');
        $this->assertCount(0, $repository->search('Introuvable'));
    }

    public function testCategoriesAreFoundThroughTwoJoins(): void
    {
        $chorale = $this->organization('Asso Chorale', 'asso-chorale');
        $budget = $this->budget($chorale, 'Saison', 'saison');

        $category = new Category();
        $category->setName('Déplacements');
        $category->setBudget($budget);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $repository = static::getContainer()->get(CategoryRepository::class);

        $this->assertCount(1, $repository->search('Chorale'), 'par organisation');
        $this->assertCount(1, $repository->search('Saison'), 'par budget');
        $this->assertCount(1, $repository->search('Déplacements'), 'par nom');
    }

    public function testTransactionsAreFoundByReferenceAndComment(): void
    {
        $transaction = new Transaction();
        $transaction->setDate(new \DateTime('2025-03-01'));
        $transaction->setAmount(42.0);
        $transaction->setPaymentMethod('Virement');
        $transaction->setReference('FA-2025-017');
        $transaction->setComment('Achat de partitions');
        $transaction->setIsActive(true);
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $repository = static::getContainer()->get(TransactionRepository::class);

        $this->assertCount(1, $repository->search('FA-2025-017'), 'par référence');
        $this->assertCount(1, $repository->search('partitions'), 'par commentaire');
        $this->assertCount(1, $repository->search('Virement'), 'par moyen de paiement');
        $this->assertCount(0, $repository->search('Introuvable'));
    }

    /**
     * Une ligne sans budget ne doit pas disparaître de l'annuaire : c'est
     * précisément le genre d'anomalie qu'on vient y chercher. D'où le leftJoin.
     */
    public function testAnOrphanBudgetLineStillAppearsInTheDirectory(): void
    {
        $line = new BudgetLine();
        $line->setName('Ligne orpheline');
        $line->setAmount(10.0);
        $line->setIsExpense(true);
        $line->setIsActive(true);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        $repository = static::getContainer()->get(BudgetLineRepository::class);

        $this->assertCount(1, $repository->search(''));
        $this->assertCount(1, $repository->search('orpheline'));
    }
}
