<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberTrashControllerTest extends WebTestCase
{
    private const CSRF_TOKEN = 'jeton-csrf-de-test';

    private function makeOrganization(string $name, string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);
        $organization->setIsActive(true);

        return $organization;
    }

    private function makeUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');

        return $user;
    }

    /**
     * Crée un budget déjà présent dans la corbeille (soft-deleted).
     */
    private function makeTrashedBudget(Organization $organization, string $slug): Budget
    {
        $budget = new Budget();
        $budget->setName('Budget supprimé');
        $budget->setSlug($slug);
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));
        $budget->setDeletedAt(new \DateTimeImmutable('-1 hour'));

        return $budget;
    }

    /**
     * Relit le budget en désactivant le filtre soft_delete, sinon un budget
     * supprimé est invisible et on ne pourrait pas distinguer « toujours dans la
     * corbeille » de « supprimé définitivement ».
     */
    private function reloadTrashedBudget(int $id): ?Budget
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $em->getFilters()->disable('soft_delete');
        $budget = $em->getRepository(Budget::class)->find($id);
        $em->getFilters()->enable('soft_delete');

        return $budget;
    }

    public function testOutsiderCannotOpenTheTrashOfAnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org privee', 'org-privee-corbeille-test');
        $outsider = $this->makeUser('outsider-corbeille@example.com');

        $em->persist($organization);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('GET', '/organisation/' . $organization->getSlug() . '/corbeille');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMemberCanOpenTheTrashOfTheirOwnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'mon-orga-corbeille-test');
        $member = $this->makeUser('membre-corbeille@example.com');
        $organization->addUser($member);

        $em->persist($organization);
        $em->persist($member);
        $em->flush();

        $client->loginUser($member);

        $client->request('GET', '/organisation/' . $organization->getSlug() . '/corbeille');

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test de contrôle : il valide le mécanisme de jeton CSRF utilisé par les
     * deux tests suivants. S'il échoue, leurs assertions ne prouvent plus rien.
     */
    public function testMemberCanRestoreAnItemOfTheirOwnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-restore-ok-test');
        $member = $this->makeUser('membre-restore-ok@example.com');
        $organization->addUser($member);
        $trashedBudget = $this->makeTrashedBudget($organization, 'budget-restore-ok-test');

        $em->persist($organization);
        $em->persist($member);
        $em->persist($trashedBudget);
        $em->flush();

        $client->loginUser($member);

        $client->request('POST', sprintf(
            '/organisation/%s/corbeille/budgets/%d/restore',
            $organization->getSlug(),
            $trashedBudget->getId(),
        ), ['_token' => $this->primeCsrfToken($client, 'restore' . $trashedBudget->getId())]);

        $this->assertResponseRedirects();
        $this->assertNull($this->reloadTrashedBudget($trashedBudget->getId())->getDeletedAt());
    }

    public function testMemberCannotRestoreAnItemFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = $this->makeOrganization('Org A', 'org-a-corbeille-test');
        $member = $this->makeUser('membre-corbeille-a@example.com');
        $orgA->addUser($member);

        $orgB = $this->makeOrganization('Org B', 'org-b-corbeille-test');
        $trashedBudgetB = $this->makeTrashedBudget($orgB, 'budget-b-corbeille-test');

        $em->persist($orgA);
        $em->persist($member);
        $em->persist($orgB);
        $em->persist($trashedBudgetB);
        $em->flush();

        $client->loginUser($member);

        // Le jeton CSRF est valide et l'utilisateur est membre de l'orga de l'URL :
        // seul le contrôle d'appartenance de l'élément protège ici.
        $client->request('POST', sprintf(
            '/organisation/%s/corbeille/budgets/%d/restore',
            $orgA->getSlug(),
            $trashedBudgetB->getId(),
        ), ['_token' => $this->primeCsrfToken($client, 'restore' . $trashedBudgetB->getId())]);

        $restored = $this->reloadTrashedBudget($trashedBudgetB->getId());

        $this->assertNotNull($restored, 'Le budget d\'orgB ne doit pas avoir disparu.');
        $this->assertNotNull($restored->getDeletedAt(), 'Le budget d\'orgB doit rester dans la corbeille.');
    }

    public function testMemberCannotHardDeleteAnItemFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = $this->makeOrganization('Org A', 'org-a-corbeille-hard-test');
        $member = $this->makeUser('membre-corbeille-hard-a@example.com');
        $orgA->addUser($member);

        $orgB = $this->makeOrganization('Org B', 'org-b-corbeille-hard-test');
        $trashedBudgetB = $this->makeTrashedBudget($orgB, 'budget-b-corbeille-hard-test');

        $em->persist($orgA);
        $em->persist($member);
        $em->persist($orgB);
        $em->persist($trashedBudgetB);
        $em->flush();

        $client->loginUser($member);

        $client->request('POST', sprintf(
            '/organisation/%s/corbeille/budgets/%d/delete',
            $orgA->getSlug(),
            $trashedBudgetB->getId(),
        ), ['_token' => $this->primeCsrfToken($client, 'hard-delete' . $trashedBudgetB->getId())]);

        $this->assertNotNull(
            $this->reloadTrashedBudget($trashedBudgetB->getId()),
            'Le budget d\'orgB ne doit pas avoir été supprimé définitivement.',
        );
    }

    public function testOutsiderCannotRestoreAnItem(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org cible', 'org-cible-corbeille-test');
        $trashedBudget = $this->makeTrashedBudget($organization, 'budget-cible-corbeille-test');
        $outsider = $this->makeUser('outsider-restore@example.com');

        $em->persist($organization);
        $em->persist($trashedBudget);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('POST', sprintf(
            '/organisation/%s/corbeille/budgets/%d/restore',
            $organization->getSlug(),
            $trashedBudget->getId(),
        ));

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->reloadTrashedBudget($trashedBudget->getId())->getDeletedAt());
    }

    /**
     * Dépose un jeton CSRF connu dans la session du client, pour pouvoir
     * envoyer un POST valide sans passer par une page HTML.
     *
     * Sans cela, un POST refusé pour cause de jeton invalide serait
     * indiscernable d'un POST refusé pour cause de mauvaise organisation :
     * le test passerait « pour la mauvaise raison ».
     */
    private function primeCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        $session = $client->getSession();
        $session->set('_csrf/' . $tokenId, self::CSRF_TOKEN);
        $session->save();

        return self::CSRF_TOKEN;
    }
}
