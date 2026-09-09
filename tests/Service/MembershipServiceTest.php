<?php

namespace App\Tests\Service;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Repository\OrganizationMembershipRepository;
use App\Service\MembershipService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MembershipServiceTest extends TestCase
{
    private function createService(
        int $adminCount = 2,
        ?EntityManagerInterface $em = null,
    ): MembershipService {
        $repository = $this->createMock(OrganizationMembershipRepository::class);
        $repository->method('countAdmins')->willReturn($adminCount);

        return new MembershipService(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $repository,
        );
    }

    private function makeMembership(OrganizationRole $role): OrganizationMembership
    {
        $organization = new Organization();
        $user = new User();
        $organization->addUser($user, $role);

        return $organization->getMembershipFor($user);
    }

    public function testARoleCanBeChanged(): void
    {
        $membership = $this->makeMembership(OrganizationRole::READER);

        $this->createService()->changeRole($membership, OrganizationRole::TREASURER);

        $this->assertSame(OrganizationRole::TREASURER, $membership->getRole());
    }

    public function testTheLastAdminCannotBeDemoted(): void
    {
        $membership = $this->makeMembership(OrganizationRole::ADMIN);
        $service = $this->createService(adminCount: 1);

        $this->expectException(\LogicException::class);

        $service->changeRole($membership, OrganizationRole::TREASURER);
    }

    public function testTheLastAdminKeepsTheirRoleWhenTheDemotionIsRefused(): void
    {
        $membership = $this->makeMembership(OrganizationRole::ADMIN);
        $service = $this->createService(adminCount: 1);

        try {
            $service->changeRole($membership, OrganizationRole::READER);
        } catch (\LogicException) {
        }

        $this->assertSame(OrganizationRole::ADMIN, $membership->getRole());
    }

    public function testAnAdminCanBeDemotedWhenAnotherOneRemains(): void
    {
        $membership = $this->makeMembership(OrganizationRole::ADMIN);

        $this->createService(adminCount: 2)->changeRole($membership, OrganizationRole::READER);

        $this->assertSame(OrganizationRole::READER, $membership->getRole());
    }

    /**
     * Promouvoir quelqu'un ne peut jamais faire disparaître un administrateur :
     * l'invariant ne doit pas s'y opposer, même s'il n'en reste qu'un.
     */
    public function testPromotingToAdminIsNeverBlocked(): void
    {
        $membership = $this->makeMembership(OrganizationRole::READER);

        $this->createService(adminCount: 1)->changeRole($membership, OrganizationRole::ADMIN);

        $this->assertSame(OrganizationRole::ADMIN, $membership->getRole());
    }

    public function testTheLastAdminCannotBeRemoved(): void
    {
        $membership = $this->makeMembership(OrganizationRole::ADMIN);
        $service = $this->createService(adminCount: 1);

        $this->expectException(\LogicException::class);

        $service->remove($membership);
    }

    public function testTheLastAdminCannotLeave(): void
    {
        $organization = new Organization();
        $user = new User();
        $organization->addUser($user, OrganizationRole::ADMIN);

        $service = $this->createService(adminCount: 1);

        $this->expectException(\LogicException::class);

        $service->leave($organization, $user);
    }

    public function testALastMemberWhoIsNotAdminCanLeave(): void
    {
        $organization = new Organization();
        $user = new User();
        $organization->addUser($user, OrganizationRole::READER);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove');
        $em->expects($this->once())->method('flush');

        $this->createService(adminCount: 1, em: $em)->leave($organization, $user);

        $this->assertCount(0, $organization->getMemberships());
        $this->assertCount(0, $user->getMemberships());
    }

    public function testLeavingAnOrganizationYouAreNotAMemberOfIsRefused(): void
    {
        $this->expectException(\LogicException::class);

        $this->createService()->leave(new Organization(), new User());
    }

    public function testRemovingAMemberDetachesThemFromBothSides(): void
    {
        $organization = new Organization();
        $admin = new User();
        $reader = new User();
        $organization->addUser($admin, OrganizationRole::ADMIN);
        $organization->addUser($reader, OrganizationRole::READER);

        $this->createService(adminCount: 1)->remove($organization->getMembershipFor($reader));

        $this->assertNull($organization->getMembershipFor($reader));
        $this->assertCount(0, $reader->getMemberships());
        $this->assertCount(1, $organization->getMemberships());
    }
}
