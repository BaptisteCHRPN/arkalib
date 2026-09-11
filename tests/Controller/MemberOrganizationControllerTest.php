<?php

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberOrganizationControllerTest extends WebTestCase
{
    public function testNonMemberCannotDeleteOrganization(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Autre organisation');
        $organization->setSlug('autre-organisation-test');
        $organization->setIsActive(true);
        $entityManager->persist($organization);

        $outsider = new User();
        $outsider->setEmail('outsider@example.com');
        $outsider->setPassword('not-checked-by-loginUser');
        $outsider->setFirstname('Test');
        $entityManager->persist($outsider);

        $entityManager->flush();

        $client->loginUser($outsider);

        $client->request('POST', '/organization/' . $organization->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMemberCanDeleteOrganization(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Mon organisation');
        $organization->setSlug('mon-organisation-test');
        $organization->setIsActive(true);

        $member = new User();
        $member->setEmail('membre@example.com');
        $member->setPassword('not-checked-by-loginUser');
        $member->setFirstname('Test');

        $organization->addUser($member, OrganizationRole::ADMIN);

        $entityManager->persist($organization);
        $entityManager->persist($member);
        $entityManager->flush();

        $client->loginUser($member);

        $crawler = $client->request('GET', '/' . $organization->getSlug() . '/edit');
        $token = $crawler->filter('#deleteOrganizationModal input[name="_token"]')->attr('value');

        $client->request('POST', '/organization/' . $organization->getId(), [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/dashboard');
    }

    public function testNonMemberCannotEditOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Org cible');
        $organization->setSlug('org-cible-edit-test');
        $organization->setIsActive(true);

        $outsider = new User();
        $outsider->setEmail('outsider-edit@example.com');
        $outsider->setPassword('not-checked-by-loginUser');
        $outsider->setFirstname('Test');

        $em->persist($organization);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('GET', '/' . $organization->getSlug() . '/edit');

        $this->assertResponseStatusCodeSame(403);

        $refreshed = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Organization::class)
            ->find($organization->getId());

        $this->assertFalse($refreshed->getUsers()->contains($outsider));
    }

    /**
     * Les gardes de template : un rôle ne doit pas voir les boutons des actions
     * qui lui seraient refusées. Le contrôle d'accès reste côté serveur — ceci
     * ne vérifie que l'interface.
     */
    public function testAReaderSeesNoActionButtonOnTheOrganizationPage(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Org boutons');
        $organization->setSlug('org-boutons-lecteur');
        $organization->setIsActive(true);

        $reader = new User();
        $reader->setEmail('lecteur-boutons@example.com');
        $reader->setPassword('not-checked-by-loginUser');
        $reader->setFirstname('Test');
        $organization->addUser($reader, OrganizationRole::READER);

        $em->persist($organization);
        $em->persist($reader);
        $em->flush();

        $client->loginUser($reader);
        $client->request('GET', '/' . $organization->getSlug() . '/budgets');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('a[href="/budget/new/' . $organization->getId() . '"]');
        $this->assertSelectorNotExists('a[href="/' . $organization->getSlug() . '/invite"]');
        $this->assertSelectorNotExists('a[href="/' . $organization->getSlug() . '/edit"]');
        // La liste des membres reste ouverte à tous.
        $this->assertSelectorExists('a[href="/' . $organization->getSlug() . '/membres"]');
    }

    public function testATreasurerSeesTheBudgetButtonsButNotTheAdminOnes(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Org boutons tresorier');
        $organization->setSlug('org-boutons-tresorier');
        $organization->setIsActive(true);

        $treasurer = new User();
        $treasurer->setEmail('tresorier-boutons@example.com');
        $treasurer->setPassword('not-checked-by-loginUser');
        $treasurer->setFirstname('Test');
        $organization->addUser($treasurer, OrganizationRole::TREASURER);

        $em->persist($organization);
        $em->persist($treasurer);
        $em->flush();

        $client->loginUser($treasurer);
        $client->request('GET', '/' . $organization->getSlug() . '/budgets');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/budget/new/' . $organization->getId() . '"]');
        $this->assertSelectorNotExists('a[href="/' . $organization->getSlug() . '/invite"]');
        $this->assertSelectorNotExists('a[href="/' . $organization->getSlug() . '/edit"]');
    }

    public function testAnAdminSeesEveryButton(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Org boutons admin');
        $organization->setSlug('org-boutons-admin');
        $organization->setIsActive(true);

        $admin = new User();
        $admin->setEmail('admin-boutons@example.com');
        $admin->setPassword('not-checked-by-loginUser');
        $admin->setFirstname('Test');
        $organization->addUser($admin, OrganizationRole::ADMIN);

        $em->persist($organization);
        $em->persist($admin);
        $em->flush();

        $client->loginUser($admin);
        $client->request('GET', '/' . $organization->getSlug() . '/budgets');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/budget/new/' . $organization->getId() . '"]');
        $this->assertSelectorExists('a[href="/' . $organization->getSlug() . '/invite"]');
        $this->assertSelectorExists('a[href="/' . $organization->getSlug() . '/edit"]');
    }
}
