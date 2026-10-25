<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberTransactionControllerTest extends WebTestCase
{
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
}
