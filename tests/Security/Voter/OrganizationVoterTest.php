<?php

namespace App\Tests\Security\Voter;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\User;
use App\Security\Voter\OrganizationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class OrganizationVoterTest extends TestCase
{
    public function testMemberCanViewOrganization(): void
    {
        $user = new User();
        $organization = new Organization();
        $organization->addUser($user);

        $token = new UsernamePasswordToken($user, 'main');

        $voter = new OrganizationVoter();

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $organization, ['ORGANIZATION_VIEW']),
        );
    }

    public function testNonMemberCannotViewOrganization(): void
    {
        $user = new User();
        $organization = new Organization();
        // volontairement : on n'ajoute PAS $user à $organization

        $token = new UsernamePasswordToken($user, 'main');

        $voter = new OrganizationVoter();

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $organization, ['ORGANIZATION_VIEW']),
        );
    }

    public function testVoterAbstainsOnUnsupportedSubject(): void
    {
        $user = new User();
        $token = new UsernamePasswordToken($user, 'main');

        $voter = new OrganizationVoter();

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($token, new Budget(), ['ORGANIZATION_VIEW']),
        );
    }

    public function testVoterAbstainsOnUnsupportedAttribute(): void
    {
        $user = new User();
        $organization = new Organization();
        $organization->addUser($user);

        $token = new UsernamePasswordToken($user, 'main');

        $voter = new OrganizationVoter();

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($token, $organization, ['SOME_OTHER_ATTRIBUTE']),
        );
    }

    public function testAdminCanViewOrganizationTheyDoNotBelongTo(): void
    {
        $admin = new User();
        $admin->setRoles(['ROLE_ADMIN']);

        $organization = new Organization();
        // l'admin n'est volontairement PAS membre

        $token = new UsernamePasswordToken($admin, 'main', $admin->getRoles());

        $voter = new OrganizationVoter();

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $organization, ['ORGANIZATION_VIEW']),
        );
    }
}
