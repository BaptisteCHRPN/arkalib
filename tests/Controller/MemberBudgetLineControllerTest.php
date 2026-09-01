<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberBudgetLineControllerTest extends WebTestCase
{
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
}
