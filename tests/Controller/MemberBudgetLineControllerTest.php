<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class MemberBudgetLineControllerTest extends WebTestCase
{
    use RevenueProtectionScenarioTrait;

    public function testMemberCannotViewBudgetLineFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = new Organization();
        $orgA->setName('Org A');
        $orgA->setSlug('org-a-test');
        $orgA->setIsActive(true);

        $member = new User();
        $member->setEmail('membre-a@example.com');
        $member->setPassword('not-checked-by-loginUser');
        $member->setFirstname('Test');
        $orgA->addUser($member);

        $orgB = new Organization();
        $orgB->setName('Org B');
        $orgB->setSlug('org-b-test');
        $orgB->setIsActive(true);

        $budgetB = new Budget();
        $budgetB->setName('Budget B');
        $budgetB->setSlug('budget-b-test');
        $budgetB->setOrganization($orgB);
        $budgetB->setStartDate(new \DateTime('2026-01-01'));
        $budgetB->setEndDate(new \DateTime('2026-12-31'));

        $lineB = new BudgetLine();
        $lineB->setName('Ligne B');
        $lineB->setAmount(100.0);
        $lineB->setIsExpense(true);
        $lineB->setBudget($budgetB);

        $em->persist($orgA);
        $em->persist($member);
        $em->persist($orgB);
        $em->persist($budgetB);
        $em->persist($lineB);
        $em->flush();

        $client->loginUser($member);

        // On utilise le slug de SA PROPRE organisation dans l'URL,
        // mais le budget/la ligne appartiennent à orgB.
        $client->request('GET', sprintf(
            '/member/budgetline/%s/%s/%d',
            $orgA->getSlug(),
            $budgetB->getSlug(),
            $lineB->getId(),
        ));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testTreasurerCannotDeleteAnIncomeLineThatFundsAReallocation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->deleteLine($client, $scenario['treasurer'], $scenario['incomeLine']);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de supprimer cette ligne');
        $this->assertNull($this->reloadIncludingTrash(BudgetLine::class, $scenario['incomeLine']->getId())->getDeletedAt());
    }

    /**
     * Le don est aussi rattaché à une autre ligne de recette : il reste
     * compté, la suppression ne fait rien perdre.
     */
    public function testLineCanBeDeletedWhenItsTransactionsRemainOnAnotherLine(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $otherLine = $this->scenarioLine($scenario['source'], 'Mécénat', false);
        $scenario['donation']->addBudgetLine($otherLine);
        $em->persist($otherLine);
        $em->flush();

        $this->deleteLine($client, $scenario['treasurer'], $scenario['incomeLine']);

        $this->assertResponseRedirects();
        $this->assertNotNull($this->reloadIncludingTrash(BudgetLine::class, $scenario['incomeLine']->getId())->getDeletedAt());
        $this->assertCount(0, $this->overrides());
    }

    public function testAdminForcingDeletesTheLineAndKeepsATrace(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->deleteLine($client, $scenario['admin'], $scenario['incomeLine']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de supprimer cette ligne');

        $this->deleteLine($client, $scenario['admin'], $scenario['incomeLine'], forceReason: 'Subvention retirée par la mairie');

        $this->assertResponseRedirects();
        $this->assertNotNull($this->reloadIncludingTrash(BudgetLine::class, $scenario['incomeLine']->getId())->getDeletedAt());

        $overrides = $this->overrides();
        $this->assertCount(1, $overrides);
        $this->assertSame('Suppression de la ligne de recette « Dons »', $overrides[0]->getOperation());
        $this->assertSame(-80000, $overrides[0]->getAvailableAfterInCents());
    }

    /**
     * Régression : un administrateur global (support) obtient l'écran de
     * forçage, et AdminActionLogSubscriber flushe en fin de sa requête pour
     * son journal. Ce flush ne doit pas enregistrer la suppression arrêtée.
     */
    public function testBlockedDeletionIsNotSavedByTheAdminActionLogFlush(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $support = $this->scenarioUser('support-protection@example.com');
        $support->setRoles(['ROLE_ADMIN']);
        $em->persist($support);
        $em->flush();

        $this->deleteLine($client, $support, $scenario['incomeLine']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->reloadIncludingTrash(BudgetLine::class, $scenario['incomeLine']->getId())->getDeletedAt());
    }

    /**
     * Passer « Dons » en dépense : −1 000 € d'encaissé et +1 000 € de
     * décaissé, soit 200 − 2 000 = −1 800 €.
     */
    public function testTreasurerCannotTurnAFundingIncomeLineIntoAnExpense(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->submitLineEdit($client, $scenario['treasurer'], $scenario['incomeLine'], ['is_expense' => 'Dépense']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de modifier cette ligne');
        $this->assertSelectorTextContains('.alert-danger', 'Seul un administrateur');
        $this->assertFalse($this->reloadIncludingTrash(BudgetLine::class, $scenario['incomeLine']->getId())->isExpense());
    }

    /**
     * Le montant d'une ligne est du prévu : le changer ne touche pas au réel.
     */
    public function testRenamingOrRepricingAFundingIncomeLineIsAllowed(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->submitLineEdit($client, $scenario['treasurer'], $scenario['incomeLine'], ['name' => 'Dons 2026', 'amount' => '10']);

        $this->assertResponseRedirects();
        $this->assertSame('Dons 2026', $this->reloadIncludingTrash(BudgetLine::class, $scenario['incomeLine']->getId())->getName());
    }

    public function testAdminForcesTurningAFundingIncomeLineIntoAnExpense(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $crawler = $this->submitLineEdit($client, $scenario['admin'], $scenario['incomeLine'], ['is_expense' => 'Dépense']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.alert-danger', '-1 800,00 €');

        $form = $crawler->filter('#budget-line-form')->form();
        $form['budget_line[force_reason]'] = 'Saisie erronée : il s\'agissait d\'un achat';
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertTrue($this->reloadIncludingTrash(BudgetLine::class, $scenario['incomeLine']->getId())->isExpense());

        $overrides = $this->overrides();
        $this->assertCount(1, $overrides);
        $this->assertSame('Passage de la ligne « Dons » de recette à dépense', $overrides[0]->getOperation());
        $this->assertSame(-180000, $overrides[0]->getAvailableAfterInCents());
    }

    /**
     * Ouvre la page de modification de la ligne, change ces champs et l'envoie.
     * Pour is_expense, on donne le libellé affiché (« Dépense » / « Recette »).
     *
     * @param array<string, string> $values
     */
    private function submitLineEdit(KernelBrowser $client, User $user, BudgetLine $line, array $values): Crawler
    {
        $this->startRequestsAs($client, $user);
        $crawler = $client->request('GET', sprintf('/member/budgetline/%d/edit', $line->getId()));
        $form = $crawler->filter('#budget-line-form')->form();

        foreach ($values as $field => $value) {
            if ('is_expense' === $field) {
                $value = $crawler->filter('select[name="budget_line[is_expense]"] option')
                    ->reduce(fn(Crawler $option) => $option->text() === $value)
                    ->attr('value');
            }

            $form[sprintf('budget_line[%s]', $field)] = $value;
        }

        return $client->submit($form);
    }

    private function deleteLine(KernelBrowser $client, User $user, BudgetLine $line, ?string $forceReason = null): void
    {
        $parameters = ['_token' => $this->loginWithCsrfToken($client, $user, 'delete' . $line->getId())];

        if (null !== $forceReason) {
            $parameters['force_reason'] = $forceReason;
        }

        $client->request('POST', sprintf('/member/budgetline/%d/delete', $line->getId()), $parameters);
    }
}
