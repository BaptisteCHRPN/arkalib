<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminOrganizationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createUser(string $email, ?string $firstname = null, ?string $lastname = null, array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function loginAsAdmin(): User
    {
        $admin = $this->createUser('admin@example.com', 'Super', 'Admin', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);

        return $admin;
    }

    private function createOrganization(string $name, string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        return $organization;
    }

    public function testTheListIsClosedToNonAdmins(): void
    {
        $this->client->loginUser($this->createUser('simple@example.com'));

        $this->client->request('GET', '/admin/organization');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSearchNarrowsTheListDown(): void
    {
        $this->loginAsAdmin();
        $this->createOrganization('Asso Chorale', 'asso-chorale');
        $this->createOrganization('Club de tennis', 'club-tennis');

        $crawler = $this->client->request('GET', '/admin/organization?q=chorale');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#organizationTable tbody tr'));
        $this->assertSelectorTextContains('#organizationTable', 'Asso Chorale');
    }

    public function testSearchAlsoMatchesTheSlug(): void
    {
        $this->loginAsAdmin();
        $this->createOrganization('Amicale des anciens', 'amicale-2025');

        $crawler = $this->client->request('GET', '/admin/organization?q=amicale-2025');

        $this->assertCount(1, $crawler->filter('#organizationTable tbody tr'));
    }

    public function testAFruitlessSearchExplainsItself(): void
    {
        $this->loginAsAdmin();
        $this->createOrganization('Asso Chorale', 'asso-chorale');

        $this->client->request('GET', '/admin/organization?q=introuvable');

        $this->assertSelectorTextContains('.empty-state', 'introuvable');
    }

    public function testTheFicheListsMembersWithTheirRole(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Asso Chorale', 'asso-chorale');
        $marie = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $paul = $this->createUser('paul@example.com', 'Paul', 'Martin');
        $organization->addUser($marie, OrganizationRole::ADMIN);
        $organization->addUser($paul, OrganizationRole::TREASURER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'marie@example.com');
        $this->assertSelectorTextContains('body', 'Administrateur');
        $this->assertSelectorTextContains('body', 'paul@example.com');
        $this->assertSelectorTextContains('body', 'Trésorier');
    }

    /**
     * Le va-et-vient entre les deux fiches est le geste de base du support :
     * d'une personne vers ses organisations, et retour.
     */
    public function testEachMemberLinksToTheirOwnFiche(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Asso Chorale', 'asso-chorale');
        $marie = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $organization->addUser($marie, OrganizationRole::READER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertSelectorExists('a[href="/admin/user/' . $marie->getId() . '"]');
    }

    public function testTheFicheLinksIntoTheMemberInterface(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Asso Chorale', 'asso-chorale');

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertSelectorExists('a[href="/asso-chorale/budgets"]');
    }

    public function testAnOrganizationWithoutAnAdminIsFlagged(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Orpheline', 'orpheline');
        $organization->addUser($this->createUser('marie@example.com'), OrganizationRole::TREASURER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertSelectorTextContains('body', "n'a plus aucun administrateur");
    }

    public function testAnOrganizationWithAnAdminIsNotFlagged(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Saine', 'saine');
        $organization->addUser($this->createUser('marie@example.com'), OrganizationRole::ADMIN);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertSelectorTextNotContains('body', "n'a plus aucun administrateur");
    }

    public function testTheFicheListsBudgetsWithTheirState(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Asso Chorale', 'asso-chorale');

        $budget = new Budget();
        $budget->setName('Saison 2025');
        $budget->setSlug('saison-2025');
        $budget->setStartDate(new \DateTime('2025-01-01'));
        $budget->setEndDate(new \DateTime('2025-12-31'));
        // addBudget() renseigne les deux côtés de la relation ; setOrganization()
        // seul laisserait la collection de l'organisation vide en mémoire.
        $organization->addBudget($budget);
        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertSelectorTextContains('body', 'Saison 2025');
        $this->assertSelectorExists('a[href="/budget/asso-chorale/saison-2025"]');
    }

    public function testAnEmptyOrganizationRendersWithoutBlanks(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Toute neuve', 'toute-neuve');

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Aucun membre');
        $this->assertSelectorTextContains('body', 'Aucun budget');
        $this->assertSelectorTextContains('body', 'Aucune invitation');
    }
}
