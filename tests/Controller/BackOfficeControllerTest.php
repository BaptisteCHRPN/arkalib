<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BackOfficeControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createUser(string $email, array $roles = [], bool $verified = true): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname('Test');
        $user->setRoles($roles);
        $user->setIsVerified($verified);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function loginAsAdmin(): User
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
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

    public function testTheDashboardIsClosedToNonAdmins(): void
    {
        $this->client->loginUser($this->createUser('simple@example.com'));

        $this->client->request('GET', '/admin/backoffice');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheDashboardCountsWhatExists(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Asso Chorale', 'asso-chorale');
        $organization->addUser($this->createUser('marie@example.com'), OrganizationRole::ADMIN);

        $budget = new Budget();
        $budget->setName('Saison');
        $budget->setSlug('saison');
        $budget->setStartDate(new \DateTime('2025-01-01'));
        $budget->setEndDate(new \DateTime('2025-12-31'));
        $organization->addBudget($budget);
        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/admin/backoffice');

        $this->assertResponseIsSuccessful();

        $values = $crawler->filter('.stat-value')->each(fn ($node) => trim($node->text()));

        // Organisations, comptes (l'admin connecté + Marie), budgets, transactions
        $this->assertSame(['1', '2', '1', '0'], $values);
    }

    public function testAnOrganizationWithoutAnAdminIsRaisedAsAnAttentionPoint(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Orpheline', 'orpheline');
        $organization->addUser($this->createUser('marie@example.com'), OrganizationRole::TREASURER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/backoffice');

        $this->assertSelectorTextContains('.attention-serious', 'sans administrateur');
        $this->assertSelectorTextContains('.attention-serious', 'Orpheline');
        $this->assertSelectorExists('.attention-serious a[href="/admin/organization/' . $organization->getId() . '"]');
    }

    public function testAnOrganizationWithAnAdminIsNotRaised(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Saine', 'saine');
        $organization->addUser($this->createUser('marie@example.com'), OrganizationRole::ADMIN);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/backoffice');

        $this->assertSelectorNotExists('.attention-serious');
    }

    public function testUnverifiedAccountsAreRaised(): void
    {
        $this->loginAsAdmin();
        $this->createUser('jamais@example.com', [], false);

        $this->client->request('GET', '/admin/backoffice');

        $this->assertSelectorTextContains('.attention-warning', 'non vérifié');
    }

    public function testAnExpiredPendingInvitationIsRaised(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Asso Chorale', 'asso-chorale');
        $inviter = $this->createUser('marie@example.com');
        $organization->addUser($inviter, OrganizationRole::ADMIN);

        $invitation = new Invitation('jeton-de-test');
        $invitation->setEmail('invite@example.com');
        $invitation->setOrganisation($organization);
        $invitation->setInvitedBy($inviter);
        $invitation->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/backoffice');

        $this->assertSelectorTextContains('.attention-warning', 'expirée');
    }

    public function testAHealthyInstallationSaysSo(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization('Saine', 'saine');
        $organization->addUser($this->createUser('marie@example.com'), OrganizationRole::ADMIN);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/backoffice');

        $this->assertSelectorTextContains('.attention-good', 'Rien à signaler');
    }
}
