<?php

namespace App\Tests\Security\Voter;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Security\Voter\OrganizationVoter;
use App\Security\Voter\ReallocationVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Le Voter délègue à OrganizationVoter : on branche le vrai derrière, pour
 * vérifier la matrice telle qu'elle s'appliquera réellement.
 */
final class ReallocationVoterTest extends TestCase
{
    /**
     * @param ?OrganizationRole $role null : l'utilisateur n'est pas membre
     */
    #[DataProvider('provideRoleAndAttribute')]
    public function testTheRoleMatrixIsEnforced(?OrganizationRole $role, string $attribute, bool $expectedToBeGranted): void
    {
        $user = new User();
        $organization = new Organization();

        if (null !== $role) {
            $organization->addUser($user, $role);
        }

        $subject = ReallocationVoter::CREATE === $attribute
            ? $organization
            : (new Reallocation())->setOrganization($organization);

        $this->assertSame(
            $expectedToBeGranted ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED,
            $this->vote($user, $subject, $attribute),
            sprintf('%s devrait %s obtenir %s', $role?->label() ?? 'Un non-membre', $expectedToBeGranted ? '' : 'ne pas', $attribute),
        );
    }

    public static function provideRoleAndAttribute(): iterable
    {
        // Le non-membre n'obtient rien.
        yield 'non-membre / consulter' => [null, ReallocationVoter::VIEW, false];
        yield 'non-membre / saisir' => [null, ReallocationVoter::CREATE, false];
        yield 'non-membre / annuler' => [null, ReallocationVoter::CANCEL, false];
        yield 'non-membre / restaurer' => [null, ReallocationVoter::RESTORE, false];

        // Le lecteur consulte, et rien d'autre.
        yield 'lecteur / consulter' => [OrganizationRole::READER, ReallocationVoter::VIEW, true];
        yield 'lecteur / saisir' => [OrganizationRole::READER, ReallocationVoter::CREATE, false];
        yield 'lecteur / annuler' => [OrganizationRole::READER, ReallocationVoter::CANCEL, false];
        yield 'lecteur / restaurer' => [OrganizationRole::READER, ReallocationVoter::RESTORE, false];

        // Option A : le trésorier saisit, annule et restaure.
        yield 'trésorier / consulter' => [OrganizationRole::TREASURER, ReallocationVoter::VIEW, true];
        yield 'trésorier / saisir' => [OrganizationRole::TREASURER, ReallocationVoter::CREATE, true];
        yield 'trésorier / annuler' => [OrganizationRole::TREASURER, ReallocationVoter::CANCEL, true];
        yield 'trésorier / restaurer' => [OrganizationRole::TREASURER, ReallocationVoter::RESTORE, true];

        yield 'admin / consulter' => [OrganizationRole::ADMIN, ReallocationVoter::VIEW, true];
        yield 'admin / saisir' => [OrganizationRole::ADMIN, ReallocationVoter::CREATE, true];
        yield 'admin / annuler' => [OrganizationRole::ADMIN, ReallocationVoter::CANCEL, true];
        yield 'admin / restaurer' => [OrganizationRole::ADMIN, ReallocationVoter::RESTORE, true];
    }

    /**
     * Sécurité : le droit est jugé sur l'organisation de la réaffectation,
     * pas sur celle où l'utilisateur est admin.
     */
    public function testAReallocationOfAnotherOrganizationIsRefused(): void
    {
        $user = new User();
        (new Organization())->addUser($user, OrganizationRole::ADMIN);
        $foreign = (new Reallocation())->setOrganization(new Organization());

        foreach ([ReallocationVoter::VIEW, ReallocationVoter::CANCEL, ReallocationVoter::RESTORE] as $attribute) {
            $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($user, $foreign, $attribute), $attribute);
        }
    }

    public function testGlobalAdminIsGrantedEverything(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $organization = new Organization();
        $reallocation = (new Reallocation())->setOrganization($organization);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $organization, ReallocationVoter::CREATE));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $reallocation, ReallocationVoter::CANCEL));
    }

    public function testReallocationWithoutOrganizationIsRefused(): void
    {
        $user = new User();

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($user, new Reallocation(), ReallocationVoter::VIEW));
    }

    public function testVoterAbstainsOnUnsupportedSubjectOrAttribute(): void
    {
        $user = new User();
        $organization = new Organization();
        $organization->addUser($user, OrganizationRole::ADMIN);

        // Saisir se juge sur l'organisation, annuler sur la réaffectation.
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($user, (new Reallocation())->setOrganization($organization), ReallocationVoter::CREATE));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($user, $organization, ReallocationVoter::CANCEL));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($user, new Budget(), ReallocationVoter::VIEW));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($user, $organization, OrganizationVoter::VIEW));
    }

    private function vote(User $user, mixed $subject, string $attribute): int
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $voter = new ReallocationVoter(new AccessDecisionManager([new OrganizationVoter()]));

        return $voter->vote($token, $subject, [$attribute]);
    }
}
