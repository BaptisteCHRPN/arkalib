<?php

namespace App\Tests\Controller;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberInvitationControllerTest extends WebTestCase
{
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

        return $user;
    }

    private function makeInvitation(Organization $organization, User $invitedBy, string $email, string $hash): Invitation
    {
        $invitation = new Invitation($hash);
        $invitation->setEmail($email);
        $invitation->setOrganisation($organization);
        $invitation->setInvitedBy($invitedBy);

        return $invitation;
    }

    /**
     * Le noyau redémarre à chaque requête du client : les objets créés avant la
     * requête ne reflètent plus la base. On relit donc l'invitation depuis le
     * conteneur courant pour vérifier son état réel.
     */
    private function reloadInvitation(int $id): Invitation
    {
        return static::getContainer()
            ->get(EntityManagerInterface::class)
            ->getRepository(Invitation::class)
            ->find($id);
    }

    public function testMemberCannotRevokeAnInvitationOfAnotherOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = $this->makeOrganization('Org A', 'org-a-invitation-test');
        $member = $this->makeUser('membre-invitation-a@example.com');
        $orgA->addUser($member);

        $orgB = $this->makeOrganization('Org B', 'org-b-invitation-test');
        $ownerB = $this->makeUser('proprietaire-invitation-b@example.com');
        $orgB->addUser($ownerB);

        $invitationB = $this->makeInvitation($orgB, $ownerB, 'invite-b@example.com', 'hash-invitation-b');

        $em->persist($orgA);
        $em->persist($member);
        $em->persist($orgB);
        $em->persist($ownerB);
        $em->persist($invitationB);
        $em->flush();

        $client->loginUser($member);

        // Slug de SA PROPRE organisation dans l'URL (le Voter passe),
        // mais l'id pointe une invitation d'orgB.
        $client->request('POST', sprintf(
            '/%s/invitations/%d/revoke',
            $orgA->getSlug(),
            $invitationB->getId(),
        ));

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame(
            Invitation::STATUS_PENDING,
            $this->reloadInvitation($invitationB->getId())->getStatus(),
        );
    }

    public function testOutsiderCannotRevokeAnInvitation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org cible', 'org-cible-invitation-test');
        $owner = $this->makeUser('proprietaire-invitation@example.com');
        $organization->addUser($owner);

        $invitation = $this->makeInvitation($organization, $owner, 'invite@example.com', 'hash-invitation-outsider');

        $outsider = $this->makeUser('outsider-invitation@example.com');

        $em->persist($organization);
        $em->persist($owner);
        $em->persist($invitation);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('POST', sprintf(
            '/%s/invitations/%d/revoke',
            $organization->getSlug(),
            $invitation->getId(),
        ));

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(
            Invitation::STATUS_PENDING,
            $this->reloadInvitation($invitation->getId())->getStatus(),
        );
    }

    public function testMemberCanRevokeAnInvitationOfTheirOwnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'mon-orga-invitation-test');
        $member = $this->makeUser('membre-invitation@example.com');
        $organization->addUser($member);

        $invitation = $this->makeInvitation($organization, $member, 'invite@example.com', 'hash-invitation-ok');
        // La page de liste lit organization.invitations : on synchronise les deux
        // côtés de la relation pour que le formulaire soit rendu.
        $organization->addInvitation($invitation);

        $em->persist($organization);
        $em->persist($member);
        $em->persist($invitation);
        $em->flush();

        $client->loginUser($member);

        // On récupère le jeton CSRF depuis la page qui affiche le formulaire,
        // comme le ferait un vrai navigateur.
        $crawler = $client->request('GET', '/' . $organization->getSlug() . '/invitations');
        $token = $crawler->filter('form input[name="_token"]')->attr('value');

        $client->request('POST', sprintf(
            '/%s/invitations/%d/revoke',
            $organization->getSlug(),
            $invitation->getId(),
        ), ['_token' => $token]);

        $this->assertResponseRedirects('/' . $organization->getSlug() . '/invitations');
        $this->assertSame(
            Invitation::STATUS_REVOKED,
            $this->reloadInvitation($invitation->getId())->getStatus(),
        );
    }

    public function testRevokeIsRefusedWithoutAValidCsrfToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-invitation-csrf-test');
        $member = $this->makeUser('membre-invitation-csrf@example.com');
        $organization->addUser($member);

        $invitation = $this->makeInvitation($organization, $member, 'invite@example.com', 'hash-invitation-csrf');

        $em->persist($organization);
        $em->persist($member);
        $em->persist($invitation);
        $em->flush();

        $client->loginUser($member);

        $client->request('POST', sprintf(
            '/%s/invitations/%d/revoke',
            $organization->getSlug(),
            $invitation->getId(),
        ), ['_token' => 'jeton-bidon']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(
            Invitation::STATUS_PENDING,
            $this->reloadInvitation($invitation->getId())->getStatus(),
        );
    }

    public function testOutsiderCannotListTheInvitationsOfAnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org privee', 'org-privee-invitation-test');
        $outsider = $this->makeUser('outsider-liste-invitation@example.com');

        $em->persist($organization);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('GET', '/' . $organization->getSlug() . '/invitations');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testOutsiderCannotOpenTheInviteForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org privee', 'org-privee-invite-form-test');
        $outsider = $this->makeUser('outsider-invite-form@example.com');

        $em->persist($organization);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('GET', '/' . $organization->getSlug() . '/invite');

        $this->assertResponseStatusCodeSame(403);
    }
}
