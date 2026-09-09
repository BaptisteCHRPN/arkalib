<?php

namespace App\Tests\Entity;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use PHPUnit\Framework\TestCase;

final class OrganizationMembershipTest extends TestCase
{
    public function testAddUserWithoutRoleFallsBackToReader(): void
    {
        $organization = new Organization();
        $user = new User();

        $organization->addUser($user);

        $membership = $organization->getMembershipFor($user);
        $this->assertNotNull($membership);
        $this->assertSame(OrganizationRole::READER, $membership->getRole());
    }

    public function testCreatorCanBeAddedAsAdmin(): void
    {
        $organization = new Organization();
        $creator = new User();

        $organization->addUser($creator, OrganizationRole::ADMIN);

        $this->assertSame(OrganizationRole::ADMIN, $organization->getMembershipFor($creator)?->getRole());
    }

    /**
     * L'appartenance doit être visible depuis les deux entités sans repasser par
     * la base : sinon un getOrganizations() juste après un addUser() mentirait.
     */
    public function testMembershipIsRegisteredOnBothSides(): void
    {
        $organization = new Organization();
        $user = new User();

        $organization->addUser($user, OrganizationRole::TREASURER);

        $this->assertCount(1, $organization->getMemberships());
        $this->assertCount(1, $user->getMemberships());
        $this->assertSame(
            $organization->getMemberships()->first(),
            $user->getMemberships()->first()
        );
    }

    public function testAddOrganizationFromUserSideProducesTheSameMembership(): void
    {
        $organization = new Organization();
        $user = new User();

        $user->addOrganization($organization, OrganizationRole::ADMIN);

        $this->assertSame(OrganizationRole::ADMIN, $organization->getMembershipFor($user)?->getRole());
        $this->assertCount(1, $user->getMemberships());
    }

    public function testAddingTheSameUserTwiceKeepsTheFirstRole(): void
    {
        $organization = new Organization();
        $user = new User();

        $organization->addUser($user, OrganizationRole::ADMIN);
        $organization->addUser($user, OrganizationRole::READER);

        $this->assertCount(1, $organization->getMemberships());
        $this->assertSame(OrganizationRole::ADMIN, $organization->getMembershipFor($user)?->getRole());
    }

    public function testRemoveUserDetachesFromBothSides(): void
    {
        $organization = new Organization();
        $user = new User();
        $organization->addUser($user, OrganizationRole::ADMIN);

        $organization->removeUser($user);

        $this->assertNull($organization->getMembershipFor($user));
        $this->assertCount(0, $organization->getMemberships());
        $this->assertCount(0, $user->getMemberships());
    }

    public function testGetMembershipForReturnsNullForANonMember(): void
    {
        $organization = new Organization();
        $organization->addUser(new User(), OrganizationRole::ADMIN);

        $this->assertNull($organization->getMembershipFor(new User()));
    }

    /**
     * getUsers() est la couche de compatibilité sur laquelle reposent encore le
     * Voter, les templates et les fixtures de tests.
     */
    public function testGetUsersStillExposesTheMembers(): void
    {
        $organization = new Organization();
        $member = new User();
        $outsider = new User();
        $organization->addUser($member, OrganizationRole::TREASURER);

        $this->assertTrue($organization->getUsers()->contains($member));
        $this->assertFalse($organization->getUsers()->contains($outsider));
        $this->assertCount(1, $organization->getUsers());
    }

    public function testGetOrganizationsStillExposesTheOrganizations(): void
    {
        $organization = new Organization();
        $user = new User();
        $organization->addUser($user, OrganizationRole::READER);

        $this->assertTrue($user->getOrganizations()->contains($organization));
    }
}
