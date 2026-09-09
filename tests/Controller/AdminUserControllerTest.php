<?php

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminUserControllerTest extends WebTestCase
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

    public function testTheListIsClosedToNonAdmins(): void
    {
        $this->client->loginUser($this->createUser('simple@example.com'));

        $this->client->request('GET', '/admin/user');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheListShowsEveryAccount(): void
    {
        $this->loginAsAdmin();
        $this->createUser('marie.dupont@example.com', 'Marie', 'Dupont');
        $this->createUser('paul.martin@example.com', 'Paul', 'Martin');

        $crawler = $this->client->request('GET', '/admin/user');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Utilisateurs');
        $this->assertCount(3, $crawler->filter('#userTable tbody tr'));
    }

    public function testSearchNarrowsTheListDownToOneAccount(): void
    {
        $this->loginAsAdmin();
        $this->createUser('marie.dupont@example.com', 'Marie', 'Dupont');
        $this->createUser('paul.martin@example.com', 'Paul', 'Martin');

        $crawler = $this->client->request('GET', '/admin/user?q=Marie+Dupont');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#userTable tbody tr'));
        $this->assertSelectorTextContains('#userTable', 'marie.dupont@example.com');
    }

    /**
     * Le terme cherché doit rester dans le champ, sinon on ne sait plus ce
     * qu'on vient de taper en lisant les résultats.
     */
    public function testTheSearchTermStaysInTheField(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/user?q=Dupont');

        $this->assertSame('Dupont', $crawler->filter('#searchInput')->attr('value'));
    }

    public function testAFruitlessSearchExplainsItselfAndOffersAWayBack(): void
    {
        $this->loginAsAdmin();
        $this->createUser('marie.dupont@example.com', 'Marie', 'Dupont');

        $this->client->request('GET', '/admin/user?q=introuvable');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.empty-state', 'introuvable');
        $this->assertSelectorExists('.empty-state a[href="/admin/user"]');
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

    public function testTheFicheIsClosedToNonAdmins(): void
    {
        $target = $this->createUser('cible@example.com');
        $this->client->loginUser($this->createUser('simple@example.com'));

        $this->client->request('GET', '/admin/user/' . $target->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheFicheListsTheOrganizationsTheAccountBelongsTo(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('marie@example.com', 'Marie', 'Dupont');

        $assoX = $this->createOrganization('Asso X', 'asso-x');
        $clubY = $this->createOrganization('Club Y', 'club-y');
        $assoX->addUser($user, OrganizationRole::ADMIN);
        $clubY->addUser($user, OrganizationRole::TREASURER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Asso X');
        $this->assertSelectorTextContains('body', 'Club Y');
        $this->assertSelectorTextContains('body', 'Administrateur');
        $this->assertSelectorTextContains('body', 'Trésorier');
    }

    /**
     * Le chemin qui n'existait pas : de la réclamation vers les données du
     * client, dans l'interface membre.
     */
    public function testEachOrganizationLinksIntoTheMemberInterface(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $organization = $this->createOrganization('Asso X', 'asso-x');
        $organization->addUser($user, OrganizationRole::READER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorExists('a[href="/asso-x/budgets"]');
    }

    public function testAnOrganizationLeftWithoutAnAdminIsFlagged(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $organization = $this->createOrganization('Orpheline', 'orpheline');
        $organization->addUser($user, OrganizationRole::TREASURER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextContains('body', 'Plus aucun administrateur');
    }

    public function testAnOrganizationWithAnAdminIsNotFlagged(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $organization = $this->createOrganization('Saine', 'saine');
        $organization->addUser($user, OrganizationRole::ADMIN);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextNotContains('body', 'Plus aucun administrateur');
    }

    public function testAPendingEmailChangeIsVisible(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('ancienne@example.com', 'Marie', 'Dupont');
        $user->setPendingEmail('nouvelle@example.com');
        $user->setEmailChangeTokenExpiresAt(new \DateTimeImmutable('+2 hours'));
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextContains('body', 'nouvelle@example.com');
        $this->assertSelectorTextContains('body', 'Lien valable jusqu');
    }

    public function testAnUnverifiedAccountSaysWhyItCannotLogIn(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('jamais@example.com');

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextContains('body', 'Non vérifié');
        $this->assertSelectorTextContains('body', 'Inscription jamais confirmée');
    }

    public function testAnAccountWithoutHistoryShowsDashesRatherThanBlanks(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('vierge@example.com');

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', "Ce compte n'appartient à aucune organisation");
        $this->assertSelectorTextContains('body', 'Aucune invitation envoyée');
        $this->assertSelectorTextContains('body', 'Aucune demande enregistrée');
    }
}
