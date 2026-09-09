<?php

namespace App\Tests\Security\Voter;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Security\Voter\OrganizationVoter;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * La matrice de droits, cas par cas. C'est le seul endroit du projet où
     * elle est vérifiée : les contrôleurs se contentent de nommer un attribut.
     */
    #[DataProvider('provideRoleAndAttribute')]
    public function testTheRoleMatrixIsEnforced(
        OrganizationRole $role,
        string $attribute,
        bool $expectedToBeGranted,
    ): void {
        $user = new User();
        $organization = new Organization();
        $organization->addUser($user, $role);

        $token = new UsernamePasswordToken($user, 'main');

        $vote = (new OrganizationVoter())->vote($token, $organization, [$attribute]);

        $this->assertSame(
            $expectedToBeGranted ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED,
            $vote,
            sprintf(
                'Un %s devrait %s se voir accorder %s',
                $role->label(),
                $expectedToBeGranted ? '' : 'ne pas',
                $attribute
            )
        );
    }

    public static function provideRoleAndAttribute(): iterable
    {
        // Le lecteur consulte, et rien d'autre.
        yield 'lecteur / consulter' => [OrganizationRole::READER, OrganizationVoter::VIEW, true];
        yield 'lecteur / tenir les comptes' => [OrganizationRole::READER, OrganizationVoter::CONTRIBUTE, false];
        yield 'lecteur / administrer' => [OrganizationRole::READER, OrganizationVoter::ADMINISTER, false];
        yield 'lecteur / supprimer' => [OrganizationRole::READER, OrganizationVoter::DELETE, false];

        // Le trésorier tient les comptes mais ne touche pas à la structure.
        yield 'trésorier / consulter' => [OrganizationRole::TREASURER, OrganizationVoter::VIEW, true];
        yield 'trésorier / tenir les comptes' => [OrganizationRole::TREASURER, OrganizationVoter::CONTRIBUTE, true];
        yield 'trésorier / administrer' => [OrganizationRole::TREASURER, OrganizationVoter::ADMINISTER, false];
        yield 'trésorier / supprimer' => [OrganizationRole::TREASURER, OrganizationVoter::DELETE, false];

        // L'administrateur peut tout.
        yield 'admin / consulter' => [OrganizationRole::ADMIN, OrganizationVoter::VIEW, true];
        yield 'admin / tenir les comptes' => [OrganizationRole::ADMIN, OrganizationVoter::CONTRIBUTE, true];
        yield 'admin / administrer' => [OrganizationRole::ADMIN, OrganizationVoter::ADMINISTER, true];
        yield 'admin / supprimer' => [OrganizationRole::ADMIN, OrganizationVoter::DELETE, true];
    }

    /**
     * Le rôle interne ne doit jamais faire entrer quelqu'un dans une
     * organisation dont il n'est pas membre.
     */
    public function testANonMemberIsRefusedWhateverTheAttribute(): void
    {
        $user = new User();
        $organization = new Organization();

        $token = new UsernamePasswordToken($user, 'main');
        $voter = new OrganizationVoter();

        foreach ([OrganizationVoter::VIEW, OrganizationVoter::CONTRIBUTE, OrganizationVoter::ADMINISTER, OrganizationVoter::DELETE] as $attribute) {
            $this->assertSame(
                VoterInterface::ACCESS_DENIED,
                $voter->vote($token, $organization, [$attribute]),
                "Un non-membre ne devrait pas obtenir $attribute"
            );
        }
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
