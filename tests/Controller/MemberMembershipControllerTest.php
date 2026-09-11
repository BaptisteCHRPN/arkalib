<?php

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberMembershipControllerTest extends WebTestCase
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
        $user->setFirstname('Test');

        return $user;
    }

    private function primeCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        $session = $client->getSession();
        $session->set('_csrf/' . $tokenId, self::CSRF_TOKEN);
        $session->save();

        return self::CSRF_TOKEN;
    }

    private function reloadMembership(int $id): ?OrganizationMembership
    {
        return static::getContainer()
            ->get(EntityManagerInterface::class)
            ->getRepository(OrganizationMembership::class)
            ->find($id);
    }

    public function testAnOutsiderCannotSeeTheMembersOfAnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-membres-outsider');
        $outsider = $this->makeUser('outsider-membres@example.com');

        $em->persist($organization);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);
        $client->request('GET', '/' . $organization->getSlug() . '/membres');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAReaderCanSeeTheMembers(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-membres-lecteur');
        $reader = $this->makeUser('lecteur-membres@example.com');
        $organization->addUser($reader, OrganizationRole::READER);

        $em->persist($organization);
        $em->persist($reader);
        $em->flush();

        $client->loginUser($reader);
        $client->request('GET', '/' . $organization->getSlug() . '/membres');

        $this->assertResponseIsSuccessful();
    }

    public function testAReaderCannotChangeARole(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-membres-lecteur-role');
        $reader = $this->makeUser('lecteur-role@example.com');
        $target = $this->makeUser('cible-role@example.com');
        $organization->addUser($reader, OrganizationRole::READER);
        $organization->addUser($target, OrganizationRole::READER);

        $em->persist($organization);
        $em->persist($reader);
        $em->persist($target);
        $em->flush();

        $membership = $organization->getMembershipFor($target);

        $client->loginUser($reader);
        $client->request(
            'POST',
            sprintf('/%s/membres/%d/role', $organization->getSlug(), $membership->getId()),
            [
                '_token' => $this->primeCsrfToken($client, 'membership_role_' . $membership->getId()),
                'role' => 'admin',
            ],
        );

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(OrganizationRole::READER, $this->reloadMembership($membership->getId())->getRole());
    }

    public function testAnAdminCanChangeARole(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-membres-admin-role');
        $admin = $this->makeUser('admin-role@example.com');
        $target = $this->makeUser('cible-promue@example.com');
        $organization->addUser($admin, OrganizationRole::ADMIN);
        $organization->addUser($target, OrganizationRole::READER);

        $em->persist($organization);
        $em->persist($admin);
        $em->persist($target);
        $em->flush();

        $membership = $organization->getMembershipFor($target);

        $client->loginUser($admin);
        $client->request(
            'POST',
            sprintf('/%s/membres/%d/role', $organization->getSlug(), $membership->getId()),
            [
                '_token' => $this->primeCsrfToken($client, 'membership_role_' . $membership->getId()),
                'role' => 'treasurer',
            ],
        );

        $this->assertResponseRedirects();
        $this->assertSame(OrganizationRole::TREASURER, $this->reloadMembership($membership->getId())->getRole());
    }

    /**
     * Même classe de faille que celle du slug de budget non unique : le slug de
     * l'URL et l'identifiant de l'appartenance sont résolus indépendamment.
     */
    public function testAMembershipOfAnotherOrganizationCannotBeTouched(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = $this->makeOrganization('Orga A', 'orga-a-membres-croise');
        $orgB = $this->makeOrganization('Orga B', 'orga-b-membres-croise');

        $adminA = $this->makeUser('admin-a-croise@example.com');
        $victimB = $this->makeUser('victime-b-croise@example.com');
        $orgA->addUser($adminA, OrganizationRole::ADMIN);
        $orgB->addUser($victimB, OrganizationRole::ADMIN);

        $em->persist($orgA);
        $em->persist($orgB);
        $em->persist($adminA);
        $em->persist($victimB);
        $em->flush();

        $membershipB = $orgB->getMembershipFor($victimB);

        $client->loginUser($adminA);
        $client->request(
            'POST',
            sprintf('/%s/membres/%d/retirer', $orgA->getSlug(), $membershipB->getId()),
            ['_token' => $this->primeCsrfToken($client, 'membership_remove_' . $membershipB->getId())],
        );

        $this->assertResponseStatusCodeSame(404);
        $this->assertNotNull($this->reloadMembership($membershipB->getId()));
    }

    public function testTheLastAdminCannotLeaveTheOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-dernier-admin');
        $admin = $this->makeUser('dernier-admin@example.com');
        $reader = $this->makeUser('simple-lecteur-reste@example.com');
        $organization->addUser($admin, OrganizationRole::ADMIN);
        $organization->addUser($reader, OrganizationRole::READER);

        $em->persist($organization);
        $em->persist($admin);
        $em->persist($reader);
        $em->flush();

        $membership = $organization->getMembershipFor($admin);

        $client->loginUser($admin);
        $client->request(
            'POST',
            sprintf('/%s/membres/quitter', $organization->getSlug()),
            ['_token' => $this->primeCsrfToken($client, 'membership_leave_' . $organization->getId())],
        );

        $this->assertResponseRedirects();
        $this->assertNotNull(
            $this->reloadMembership($membership->getId()),
            "Le dernier administrateur ne doit pas avoir quitté l'organisation"
        );
    }

    public function testAReaderCanLeaveTheOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-lecteur-quitte');
        $admin = $this->makeUser('admin-qui-reste@example.com');
        $reader = $this->makeUser('lecteur-qui-part@example.com');
        $organization->addUser($admin, OrganizationRole::ADMIN);
        $organization->addUser($reader, OrganizationRole::READER);

        $em->persist($organization);
        $em->persist($admin);
        $em->persist($reader);
        $em->flush();

        $membership = $organization->getMembershipFor($reader);
        $membershipId = $membership->getId();

        $client->loginUser($reader);
        $client->request(
            'POST',
            sprintf('/%s/membres/quitter', $organization->getSlug()),
            ['_token' => $this->primeCsrfToken($client, 'membership_leave_' . $organization->getId())],
        );

        $this->assertResponseRedirects();
        $this->assertNull($this->reloadMembership($membershipId));
    }

    public function testRemovingAMemberIsRefusedWithoutAValidCsrfToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-membres-csrf');
        $admin = $this->makeUser('admin-csrf-membres@example.com');
        $target = $this->makeUser('cible-csrf-membres@example.com');
        $organization->addUser($admin, OrganizationRole::ADMIN);
        $organization->addUser($target, OrganizationRole::READER);

        $em->persist($organization);
        $em->persist($admin);
        $em->persist($target);
        $em->flush();

        $membership = $organization->getMembershipFor($target);

        $client->loginUser($admin);
        $client->request(
            'POST',
            sprintf('/%s/membres/%d/retirer', $organization->getSlug(), $membership->getId()),
            ['_token' => 'jeton-invalide'],
        );

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->reloadMembership($membership->getId()));
    }
}
