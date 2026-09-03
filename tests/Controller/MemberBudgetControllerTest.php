<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberBudgetControllerTest extends WebTestCase
{
    private function makeOrganization(string $name, string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);
        $organization->setIsActive(true);

        return $organization;
    }

    private function makeBudget(Organization $organization, string $slug): Budget
    {
        $budget = new Budget();
        $budget->setName('Budget');
        $budget->setSlug($slug);
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));

        return $budget;
    }

    private function makeUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');

        return $user;
    }

    public function testOutsiderCannotViewTheRealizedBudgetOfAnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org proprietaire', 'org-realise-proprietaire');
        $budget = $this->makeBudget($organization, 'budget-realise-test');
        $outsider = $this->makeUser('outsider-realise@example.com');

        $em->persist($organization);
        $em->persist($budget);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('GET', sprintf(
            '/budget-realise/%s/%s',
            $organization->getSlug(),
            $budget->getSlug(),
        ));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMemberCannotViewTheRealizedBudgetOfAnotherOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = $this->makeOrganization('Org A', 'org-a-realise-test');
        $member = $this->makeUser('membre-realise-a@example.com');
        $orgA->addUser($member);

        $orgB = $this->makeOrganization('Org B', 'org-b-realise-test');
        $budgetB = $this->makeBudget($orgB, 'budget-b-realise-test');

        $em->persist($orgA);
        $em->persist($member);
        $em->persist($orgB);
        $em->persist($budgetB);
        $em->flush();

        $client->loginUser($member);

        // Slug de SA PROPRE organisation dans l'URL, mais budget d'orgB.
        $client->request('GET', sprintf(
            '/budget-realise/%s/%s',
            $orgA->getSlug(),
            $budgetB->getSlug(),
        ));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testMemberCanViewTheRealizedBudgetOfTheirOwnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'mon-orga-realise-test');
        $member = $this->makeUser('membre-realise@example.com');
        $organization->addUser($member);
        $budget = $this->makeBudget($organization, 'mon-budget-realise-test');

        $em->persist($organization);
        $em->persist($member);
        $em->persist($budget);
        $em->flush();

        $client->loginUser($member);

        $client->request('GET', sprintf(
            '/budget-realise/%s/%s',
            $organization->getSlug(),
            $budget->getSlug(),
        ));

        $this->assertResponseIsSuccessful();
    }

    public function testUnknownBudgetSlugReturns404RatherThanCrashing(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-budget-inconnu-test');
        $member = $this->makeUser('membre-budget-inconnu@example.com');
        $organization->addUser($member);

        $em->persist($organization);
        $em->persist($member);
        $em->flush();

        $client->loginUser($member);

        $client->request('GET', sprintf(
            '/budget-realise/%s/%s',
            $organization->getSlug(),
            'ce-budget-nexiste-pas',
        ));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testOutsiderCannotSoftDeleteABudget(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org cible', 'org-delete-budget-test');
        $budget = $this->makeBudget($organization, 'budget-a-supprimer-test');
        $outsider = $this->makeUser('outsider-delete-budget@example.com');

        $em->persist($organization);
        $em->persist($budget);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('POST', sprintf(
            '/budget/%s/%s/delete',
            $organization->getSlug(),
            $budget->getSlug(),
        ));

        $this->assertResponseStatusCodeSame(403);

        // Le noyau redémarre à chaque requête : on relit le budget depuis le
        // conteneur courant pour vérifier son état réel en base.
        $refreshed = static::getContainer()
            ->get(EntityManagerInterface::class)
            ->getRepository(Budget::class)
            ->find($budget->getId());

        $this->assertNull($refreshed->getDeletedAt());
    }
}
