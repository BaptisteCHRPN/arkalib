<?php

namespace App\Tests\Controller;

use App\Entity\AdminActionLog;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminActionLogControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createUser(string $email, array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname('Marie');
        $user->setLastname('Dupont');
        $user->setRoles($roles);
        $user->setIsVerified(true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
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

    private function createLog(User $actor, ?Organization $organization, string $action): AdminActionLog
    {
        $log = (new AdminActionLog())
            ->setActor($actor)
            ->setAction($action)
            ->setRouteName('route_de_test')
            ->setMethod('POST')
            ->setOrganization($organization)
            ->setStatusCode(303);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    public function testTheJournalIsClosedToNonAdmins(): void
    {
        $this->client->loginUser($this->createUser('simple@example.com'));

        $this->client->request('GET', '/admin/journal');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheJournalListsInterventions(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);
        $organization = $this->createOrganization('Asso Chorale', 'asso-chorale');
        $this->createLog($admin, $organization, 'Créer une transaction');

        $this->client->request('GET', '/admin/journal');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Créer une transaction');
        $this->assertSelectorTextContains('body', 'Asso Chorale');
    }

    public function testAnEmptyJournalExplainsItself(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/admin/journal');

        $this->assertSelectorTextContains('.empty-state', 'Aucune intervention enregistrée');
    }

    /**
     * Le cas où le journal sert le plus : l'objet du litige a disparu, et il
     * faut pouvoir dire de quelle organisation il s'agissait.
     */
    public function testADeletedOrganizationStillAppearsByName(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);
        $organization = $this->createOrganization('Disparue', 'disparue');
        $this->createLog($admin, $organization, 'Supprimer définitivement une organisation');

        $this->entityManager->remove($organization);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/journal');

        $this->assertSelectorTextContains('body', 'Disparue');
        $this->assertSelectorTextContains('body', '(supprimée)');
    }

    public function testTheOrganizationFicheShowsItsOwnInterventions(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);
        $chorale = $this->createOrganization('Asso Chorale', 'asso-chorale');
        $tennis = $this->createOrganization('Club de tennis', 'club-tennis');

        $this->createLog($admin, $chorale, 'Créer une transaction');
        $this->createLog($admin, $tennis, 'Clôturer un budget');

        $this->client->request('GET', '/admin/organization/' . $chorale->getId());

        $this->assertSelectorTextContains('body', 'Créer une transaction');
        $this->assertSelectorTextNotContains('body', 'Clôturer un budget');
    }

    public function testAnUntouchedOrganizationSaysSo(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_ADMIN']));
        $organization = $this->createOrganization('Tranquille', 'tranquille');

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertSelectorTextContains('body', "Personne d'extérieur n'a modifié");
    }
}
