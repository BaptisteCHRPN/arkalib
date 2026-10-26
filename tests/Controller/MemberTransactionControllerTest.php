<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class MemberTransactionControllerTest extends WebTestCase
{
    use RevenueProtectionScenarioTrait;

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
        $this->assertNull($this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getDeletedAt());
    }

    public function testIncomeStillCoveringReallocationsCanBeDeleted(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        // Second don de 900 € : sans le premier, il reste 900 − 800 = 100 €.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($this->scenarioTransaction(900.0, $scenario['incomeLine']));
        $em->flush();

        $this->deleteTransaction($client, $scenario['treasurer'], $scenario['donation']);

        $this->assertResponseRedirects();
        $this->assertNotNull($this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getDeletedAt());
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
        $this->assertNull($this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getDeletedAt());
    }

    public function testAdminForcingWithoutReasonIsAskedAgain(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->deleteTransaction($client, $scenario['admin'], $scenario['donation'], forceReason: '   ');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.invalid-feedback', 'Indiquez pourquoi');
        $this->assertNull($this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getDeletedAt());
        $this->assertCount(0, $this->overrides());
    }

    public function testAdminForcingWithReasonDeletesAndKeepsATrace(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->deleteTransaction($client, $scenario['admin'], $scenario['donation'], forceReason: 'Don remboursé au donateur le 03/11');

        $this->assertResponseRedirects();
        $this->assertNotNull($this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getDeletedAt());

        $overrides = $this->overrides();
        $this->assertCount(1, $overrides);
        $this->assertSame('Don remboursé au donateur le 03/11', $overrides[0]->getReason());
        $this->assertSame('admin-protection@example.com', $overrides[0]->getForcedBy()->getEmail());
        $this->assertSame('Suppression de la transaction du 01/06/2026 de 1 000,00 € sur « Dons » (réf. DON-12)', $overrides[0]->getOperation());
        $this->assertSame(['du 12/10/2026 (CA) – Répartition don chauffage'], $overrides[0]->getReallocations());
        $this->assertSame(-80000, $overrides[0]->getAvailableAfterInCents());

        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-warning', 'Blocage levé');
    }

    public function testTreasurerCannotLowerAnIncomeThatFundsAReallocation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->submitEdit($client, $scenario['treasurer'], $scenario['donation'], ['transaction[amount]' => '100']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de modifier cette transaction');
        $this->assertSelectorTextContains('.alert-danger', 'Seul un administrateur');
        $this->assertSelectorNotExists('textarea[name="transaction[force_reason]"]');
        $this->assertSame(1000.0, $this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getAmount());
    }

    public function testIncomeCanBeLoweredWithinTheAvailable(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        // 1 000 − 800 = 200 € de disponible : baisser de 100 € passe.
        $this->submitEdit($client, $scenario['treasurer'], $scenario['donation'], ['transaction[amount]' => '900']);

        $this->assertResponseRedirects();
        $this->assertSame(900.0, $this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getAmount());
    }

    /**
     * Changer les lignes d'une transaction peut aussi faire perdre une recette.
     */
    public function testTreasurerCannotMoveAnIncomeToAnExpenseLine(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $expenseLine = $this->scenarioLine($scenario['source'], 'Achats', true);
        $em->persist($expenseLine);
        $em->flush();

        $this->submitEdit($client, $scenario['treasurer'], $scenario['donation'], [
            'transaction[budget_line]' => [(string) $expenseLine->getId()],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de modifier cette transaction');

        $reloaded = $this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId());
        $this->assertSame(['Dons'], $reloaded->getBudgetLine()->map(fn(BudgetLine $line) => $line->getName())->getValues());
    }

    public function testAdminForcesALowerIncomeFromTheEditForm(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $crawler = $this->submitEdit($client, $scenario['admin'], $scenario['donation'], ['transaction[amount]' => '100']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.alert-danger', 'vous pouvez passer outre');
        $this->assertSelectorExists('textarea[name="transaction[force_reason]"]');

        // La saisie est conservée : l'admin n'ajoute que le motif.
        $form = $crawler->filter('#transaction-form')->form();
        $this->assertSame('100', $form['transaction[amount]']->getValue());
        $form['transaction[force_reason]'] = 'Don remboursé en partie au donateur';
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertSame(100.0, $this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getAmount());

        $overrides = $this->overrides();
        $this->assertCount(1, $overrides);
        $this->assertSame(
            'Modification de la transaction du 01/06/2026 de 1 000,00 € sur « Dons » (réf. DON-12), devenue du 01/06/2026 de 100,00 € sur « Dons » (réf. DON-12)',
            $overrides[0]->getOperation(),
        );
        $this->assertSame(-70000, $overrides[0]->getAvailableAfterInCents());
    }

    public function testAdminForcingWithBlankReasonFromTheEditFormIsAskedAgain(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->submitEdit($client, $scenario['admin'], $scenario['donation'], [
            'transaction[amount]' => '100',
            'transaction[force_reason]' => '   ',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.invalid-feedback', 'Indiquez pourquoi');
        $this->assertSame(1000.0, $this->reloadIncludingTrash(Transaction::class, $scenario['donation']->getId())->getAmount());
        $this->assertCount(0, $this->overrides());
    }

    /**
     * Ouvre le formulaire de modification, change ces champs et l'envoie.
     *
     * @param array<string, string|string[]> $values
     */
    private function submitEdit(KernelBrowser $client, User $user, Transaction $transaction, array $values): Crawler
    {
        $budget = $transaction->getBudgetLine()->first()->getBudget();
        $this->startRequestsAs($client, $user);

        $crawler = $client->request('GET', sprintf(
            '/budget/%s/%s/transaction/%d/edit',
            $budget->getOrganization()->getSlug(),
            $budget->getSlug(),
            $transaction->getId(),
        ));

        // Le champ motif n'est affiché qu'en cas de blocage : on l'ajoute à la
        // main pour simuler un admin qui le remplit d'emblée.
        $form = $crawler->filter('#transaction-form')->form();
        $parameters = $form->getPhpValues();

        foreach ($values as $name => $value) {
            preg_match('/^transaction\[(\w+)\]$/', $name, $match);
            $parameters['transaction'][$match[1]] = $value;
        }

        return $client->request($form->getMethod(), $form->getUri(), $parameters);
    }

    private function deleteTransaction(KernelBrowser $client, User $user, Transaction $transaction, ?string $forceReason = null): void
    {
        $parameters = ['_token' => $this->loginWithCsrfToken($client, $user, 'delete' . $transaction->getId())];

        if (null !== $forceReason) {
            $parameters['force_reason'] = $forceReason;
        }

        $budget = $transaction->getBudgetLine()->first()->getBudget();

        $client->request('POST', sprintf(
            '/budget/%s/%s/transaction/%d',
            $budget->getOrganization()->getSlug(),
            $budget->getSlug(),
            $transaction->getId(),
        ), $parameters);
    }
}
