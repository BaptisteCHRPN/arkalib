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
}
