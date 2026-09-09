<?php

namespace App\Tests\Controller;

use App\Entity\User;
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
}
