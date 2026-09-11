<?php

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\Repository\AdminActionLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminActionLogSubscriberTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createUser(string $email, array $roles = [], bool $verified = false): User
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

    private function logs(): array
    {
        return static::getContainer()->get(AdminActionLogRepository::class)->findRecent();
    }

    public function testAnAdminActionIsRecorded(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN'], true);
        $this->client->loginUser($admin);
        $target = $this->createUser('cible@example.com');

        $crawler = $this->client->request('GET', '/admin/user/' . $target->getId());
        $this->client->submit($crawler->filter('form[action$="/renvoyer-verification"] button')->form());

        $logs = $this->logs();

        $this->assertCount(1, $logs, 'une intervention, une entrée');
        $this->assertSame("Renvoyer l'e-mail de vérification", $logs[0]->getAction());
        $this->assertSame($admin->getId(), $logs[0]->getActor()?->getId());
        $this->assertSame('POST', $logs[0]->getMethod());
        $this->assertSame(303, $logs[0]->getStatusCode());
    }

    public function testConsultingChangesNothingAndIsNotRecorded(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN'], true);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/user');
        $this->client->request('GET', '/admin/backoffice');

        $this->assertSame([], $this->logs());
    }

    public function testARefusedActionIsRecordedToo(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN'], true);
        $this->client->loginUser($admin);
        $target = $this->createUser('cible@example.com');

        // Jeton CSRF invalide : l'action est refusée.
        $this->client->request(
            'POST',
            '/admin/user/' . $target->getId() . '/renvoyer-verification',
            ['_token' => 'faux'],
        );

        $logs = $this->logs();

        $this->assertCount(1, $logs, 'une tentative se raconte aussi');
        $this->assertTrue($logs[0]->wasRefused());
        $this->assertSame(403, $logs[0]->getStatusCode());
    }

    public function testAnActionByANonAdminIsNotRecorded(): void
    {
        $member = $this->createUser('simple@example.com', [], true);
        $this->client->loginUser($member);

        $this->client->request('POST', '/admin/user/1/renvoyer-verification', ['_token' => 'faux']);

        $this->assertSame([], $this->logs(), 'le journal ne suit que les administrateurs');
    }

    public function testTheActionIsNamedInPlainLanguage(): void
    {
        $admin = $this->createUser('admin@example.com', ['ROLE_ADMIN'], true);
        $this->client->loginUser($admin);
        $target = $this->createUser('cible@example.com');

        $crawler = $this->client->request('GET', '/admin/user/' . $target->getId());
        $this->client->submit($crawler->filter('form[action$="/role-admin"] button')->form());

        $logs = $this->logs();

        $this->assertCount(1, $logs);
        $this->assertSame("Modifier les droits d'administrateur", $logs[0]->getAction());
    }
}
