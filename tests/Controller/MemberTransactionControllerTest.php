<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\ReallocationLine;
use App\Entity\RevenueProtectionOverride;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Enum\ReallocationInstance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberTransactionControllerTest extends WebTestCase
{
    private const CSRF_TOKEN = 'jeton-csrf-de-test';

    public function testMemberCannotViewTransactionFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = new Organization();
        $orgA->setName('Org A');
        $orgA->setSlug('org-a-transaction-test');
        $orgA->setIsActive(true);

        $member = new User();
        $member->setEmail('membre-transaction-a@example.com');
        $member->setPassword('not-checked-by-loginUser');
        $member->setFirstname('Test');
        $orgA->addUser($member);

        $orgB = new Organization();
        $orgB->setName('Org B');
        $orgB->setSlug('org-b-transaction-test');
        $orgB->setIsActive(true);

        $budgetB = new Budget();
        $budgetB->setName('Budget B');
        $budgetB->setSlug('budget-b-transaction-test');
        $budgetB->setOrganization($orgB);
        $budgetB->setStartDate(new \DateTime('2026-01-01'));
        $budgetB->setEndDate(new \DateTime('2026-12-31'));

        $lineB = new BudgetLine();
        $lineB->setName('Ligne B');
        $lineB->setAmount(100.0);
        $lineB->setIsExpense(true);
        $lineB->setBudget($budgetB);

        $transactionB = new Transaction();
        $transactionB->setDate(new \DateTime('2026-06-01'));
        $transactionB->setAmount(100.0);
        $transactionB->setPaymentMethod('Virement');
        $transactionB->addBudgetLine($lineB);

        $em->persist($orgA);
        $em->persist($member);
        $em->persist($orgB);
        $em->persist($budgetB);
        $em->persist($lineB);
        $em->persist($transactionB);
        $em->flush();

        $client->loginUser($member);

        // Slug de SA PROPRE organisation, mais budget/transaction d'orgB
        $client->request('GET', sprintf(
            '/budget/%s/%s/transaction/%d',
            $orgA->getSlug(),
            $budgetB->getSlug(),
            $transactionB->getId(),
        ));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testTransactionListShowsCashInBadgeForIncomeTransaction(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Org sens');
        $organization->setSlug('org-direction-badge-test');
        $organization->setIsActive(true);

        $member = new User();
        $member->setEmail('membre-direction-badge@example.com');
        $member->setPassword('not-checked-by-loginUser');
        $member->setFirstname('Test');
        $organization->addUser($member);

        $budget = new Budget();
        $budget->setName('Budget sens');
        $budget->setSlug('budget-direction-badge-test');
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));

        $incomeLine = new BudgetLine();
        $incomeLine->setName('Don');
        $incomeLine->setAmount(500.0);
        $incomeLine->setIsExpense(false);
        $incomeLine->setBudget($budget);

        $transaction = new Transaction();
        $transaction->setDate(new \DateTime('2026-06-01'));
        $transaction->setAmount(500.0);
        $transaction->setPaymentMethod('Chèque');
        $transaction->addBudgetLine($incomeLine);

        $em->persist($organization);
        $em->persist($member);
        $em->persist($budget);
        $em->persist($incomeLine);
        $em->persist($transaction);
        $em->flush();

        $client->loginUser($member);
        $client->request('GET', sprintf(
            '/budget/%s/%s/transaction',
            $organization->getSlug(),
            $budget->getSlug(),
        ));

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('.badge', 'Encaissement');
    }

    public function testTreasurerCannotDeleteAnIncomeThatFundsAReallocation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->deleteTransaction($client, $scenario['treasurer'], $scenario['donation']);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de supprimer cette transaction');
        $this->assertSelectorTextContains('.alert-danger', 'Répartition don chauffage');
        $this->assertSelectorTextContains('.alert-danger', 'Seul un administrateur');
        $this->assertNull($this->reloadTransaction($scenario['donation'])->getDeletedAt());
    }

    public function testIncomeStillCoveringReallocationsCanBeDeleted(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        // Second don de 900 € : sans le premier, il reste 900 − 800 = 100 €.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($this->transaction(900.0, $scenario['incomeLine']));
        $em->flush();

        $this->deleteTransaction($client, $scenario['treasurer'], $scenario['donation']);

        $this->assertResponseRedirects();
        $this->assertNotNull($this->reloadTransaction($scenario['donation'])->getDeletedAt());
    }

    public function testAdminIsAskedForAReasonBeforeForcing(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->deleteTransaction($client, $scenario['admin'], $scenario['donation']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de supprimer cette transaction');
        $this->assertSelectorExists('textarea[name="force_reason"]');
        $this->assertSelectorTextContains('strong.text-danger', '-800,00 €');
        $this->assertNull($this->reloadTransaction($scenario['donation'])->getDeletedAt());
    }

    public function testAdminForcingWithoutReasonIsAskedAgain(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->deleteTransaction($client, $scenario['admin'], $scenario['donation'], forceReason: '   ');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.invalid-feedback', 'Indiquez pourquoi');
        $this->assertNull($this->reloadTransaction($scenario['donation'])->getDeletedAt());
        $this->assertCount(0, $this->overrides());
    }

    public function testAdminForcingWithReasonDeletesAndKeepsATrace(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->deleteTransaction($client, $scenario['admin'], $scenario['donation'], forceReason: 'Don remboursé au donateur le 03/11');

        $this->assertResponseRedirects();
        $this->assertNotNull($this->reloadTransaction($scenario['donation'])->getDeletedAt());

        $overrides = $this->overrides();
        $this->assertCount(1, $overrides);
        $this->assertSame('Don remboursé au donateur le 03/11', $overrides[0]->getReason());
        $this->assertSame('admin-protection@example.com', $overrides[0]->getForcedBy()->getEmail());
        $this->assertSame('Suppression de la transaction du 01/06/2026 de 1 000,00 € (réf. DON-12)', $overrides[0]->getOperation());
        $this->assertSame(['du 12/10/2026 (CA) – Répartition don chauffage'], $overrides[0]->getReallocations());
        $this->assertSame(-80000, $overrides[0]->getAvailableAfterInCents());

        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-warning', 'Blocage levé');
    }

    /**
     * La Fête a reçu un don de 1 000 € et en a réaffecté 800 au Chauffage.
     *
     * @return array{admin: User, treasurer: User, donation: Transaction, incomeLine: BudgetLine}
     */
    private function reallocatedIncomeScenario(): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Asso protection');
        $organization->setSlug('asso-protection-recettes');
        $organization->setIsActive(true);

        $admin = $this->user('admin-protection@example.com');
        $treasurer = $this->user('tresorier-protection@example.com');
        $organization->addUser($admin, OrganizationRole::ADMIN);
        $organization->addUser($treasurer, OrganizationRole::TREASURER);

        $source = $this->budget($organization, 'fete-protection');
        $target = $this->budget($organization, 'chauffage-protection');

        $incomeLine = new BudgetLine();
        $incomeLine->setName('Dons');
        $incomeLine->setAmount(1000.0);
        $incomeLine->setIsExpense(false);
        $incomeLine->setBudget($source);

        $donation = $this->transaction(1000.0, $incomeLine);
        $donation->setReference('DON-12');

        $reallocation = (new Reallocation())
            ->setOrganization($organization)
            ->setDecidedAt(new \DateTime('2026-10-12'))
            ->setInstance(ReallocationInstance::BOARD)
            ->setReason('Répartition don chauffage')
            ->setSourceBudget($source)
            ->addLine((new ReallocationLine())->setTargetBudget($target)->setAmount('800.00'));

        foreach ([$organization, $admin, $treasurer, $source, $target, $incomeLine, $donation, $reallocation] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return ['admin' => $admin, 'treasurer' => $treasurer, 'donation' => $donation, 'incomeLine' => $incomeLine];
    }

    private function deleteTransaction(KernelBrowser $client, User $user, Transaction $transaction, ?string $forceReason = null): void
    {
        $client->loginUser($user);

        $session = $client->getSession();
        $session->set('_csrf/delete' . $transaction->getId(), self::CSRF_TOKEN);
        $session->save();

        $budget = $transaction->getBudgetLine()->first()->getBudget();
        $parameters = ['_token' => self::CSRF_TOKEN];

        if (null !== $forceReason) {
            $parameters['force_reason'] = $forceReason;
        }

        $client->request('POST', sprintf(
            '/budget/%s/%s/transaction/%d',
            $budget->getOrganization()->getSlug(),
            $budget->getSlug(),
            $transaction->getId(),
        ), $parameters);
    }

    private function reloadTransaction(Transaction $transaction): Transaction
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $em->getFilters()->disable('soft_delete');
        $reloaded = $em->find(Transaction::class, $transaction->getId());
        $em->getFilters()->enable('soft_delete');

        return $reloaded;
    }

    /**
     * @return RevenueProtectionOverride[]
     */
    private function overrides(): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(RevenueProtectionOverride::class)->findAll();
    }

    private function user(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname('Test');

        return $user;
    }

    private function budget(Organization $organization, string $slug): Budget
    {
        $budget = new Budget();
        $budget->setName(ucfirst(explode('-', $slug)[0]));
        $budget->setSlug($slug);
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));

        return $budget;
    }

    private function transaction(float $amount, BudgetLine $line): Transaction
    {
        $transaction = new Transaction();
        $transaction->setDate(new \DateTime('2026-06-01'));
        $transaction->setAmount($amount);
        $transaction->setPaymentMethod('Chèque');
        $transaction->addBudgetLine($line);

        return $transaction;
    }
}
