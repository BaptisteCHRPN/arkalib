<?php

namespace App\Tests\Service;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Service\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InvitationServiceTest extends TestCase
{
    private function createService(
        ?EntityManagerInterface $em = null,
        ?MailerInterface $mailer = null,
        ?UrlGeneratorInterface $urlGenerator = null,
        ?InvitationRepository $invitationRepository = null,
        ?UserRepository $userRepository = null,
    ): InvitationService {
        return new InvitationService(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $mailer ?? $this->createMock(MailerInterface::class),
            $urlGenerator ?? $this->createMock(UrlGeneratorInterface::class),
            $invitationRepository ?? $this->createMock(InvitationRepository::class),
            $userRepository ?? $this->createMock(UserRepository::class),
            'noreply@arkalib.fr',
            'Arkalib',
        );
    }

    public function testInviteThrowsWhenUserAlreadyMember(): void
    {
        $organization = new Organization();
        $invitedBy = new User();

        $existingUser = new User();
        $existingUser->addOrganization($organization);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($existingUser);

        $service = $this->createService(userRepository: $userRepository);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cet utilisateur est déjà membre de cette organisation.');

        $service->invite('deja-membre@example.com', $organization, $invitedBy);
    }

    public function testInviteThrowsWhenInvitationAlreadyPending(): void
    {
        $organization = new Organization();
        $invitedBy = new User();

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findPendingByEmailAndOrga')->willReturn(new Invitation('un-hash'));

        $service = $this->createService(
            userRepository: $userRepository,
            invitationRepository: $invitationRepository,
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Une invitation est déjà en attente pour cet email.');

        $service->invite('deja-invite@example.com', $organization, $invitedBy);
    }

    public function testAcceptThrowsWhenInvitationExpired(): void
    {
        $invitation = $this->createMock(Invitation::class);
        $invitation->method('isExpired')->willReturn(true);

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findOneBy')->willReturn($invitation);

        $service = $this->createService(invitationRepository: $invitationRepository);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cette invitation a expiré.');

        $service->accept('un-token', new User());
    }

    public function testAcceptThrowsWhenEmailMismatch(): void
    {
        $invitation = $this->createMock(Invitation::class);
        $invitation->method('isExpired')->willReturn(false);
        $invitation->method('getEmail')->willReturn('invite@example.com');

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findOneBy')->willReturn($invitation);

        $service = $this->createService(invitationRepository: $invitationRepository);

        $user = new User();
        $user->setEmail('quelquun-dautre@example.com');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cette invitation ne vous est pas destinée.');

        $service->accept('un-token', $user);
    }

    public function testAcceptThrowsWhenTokenMatchesNothing(): void
    {
        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findOneBy')->willReturn(null);

        $service = $this->createService(invitationRepository: $invitationRepository);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cette invitation est invalide.');

        $service->accept('token-inconnu', new User());
    }

    public function testAcceptLooksUpTheHashOfTheToken(): void
    {
        // Le token circule en clair dans l'URL, mais seul son hash est stocké.
        // On vérifie que le service cherche bien par hash, jamais par token brut.
        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'hashedToken' => hash('sha256', 'token-en-clair'),
                'status' => Invitation::STATUS_PENDING,
            ])
            ->willReturn(null);

        $service = $this->createService(invitationRepository: $invitationRepository);

        $this->expectException(\LogicException::class);

        $service->accept('token-en-clair', new User());
    }

    public function testAcceptAttachesUserToOrganizationAndMarksInvitationAccepted(): void
    {
        $organization = new Organization();
        $organization->setName('Mon orga');

        $invitation = new Invitation('peu-importe-le-hash');
        $invitation->setEmail('invite@example.com');
        $invitation->setOrganisation($organization);

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findOneBy')->willReturn($invitation);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = $this->createService(em: $em, invitationRepository: $invitationRepository);

        $user = new User();
        $user->setEmail('invite@example.com');

        $service->accept('un-token', $user);

        $this->assertTrue($user->getOrganizations()->contains($organization));
        $this->assertTrue($organization->getUsers()->contains($user));
        $this->assertSame(Invitation::STATUS_ACCEPTED, $invitation->getStatus());
    }

    public function testAcceptIsCaseInsensitiveOnTheEmail(): void
    {
        $organization = new Organization();
        $organization->setName('Mon orga');

        $invitation = new Invitation('peu-importe-le-hash');
        $invitation->setEmail('Invite@Example.COM');
        $invitation->setOrganisation($organization);

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findOneBy')->willReturn($invitation);

        $service = $this->createService(invitationRepository: $invitationRepository);

        $user = new User();
        $user->setEmail('invite@example.com');

        $service->accept('un-token', $user);

        $this->assertSame(Invitation::STATUS_ACCEPTED, $invitation->getStatus());
    }

    public function testAcceptMarksExpiredInvitationAsExpired(): void
    {
        $invitation = new Invitation('peu-importe-le-hash');
        $invitation->setEmail('invite@example.com');
        $invitation->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findOneBy')->willReturn($invitation);

        $service = $this->createService(invitationRepository: $invitationRepository);

        try {
            $service->accept('un-token', new User());
            $this->fail('Une LogicException était attendue.');
        } catch (\LogicException) {
            // attendu
        }

        // L'invitation est aussi marquée en base pour ne plus jamais ressortir
        // comme « pending ».
        $this->assertSame(Invitation::STATUS_EXPIRED, $invitation->getStatus());
    }

    public function testInviteCreatesPendingInvitationAndSendsEmail(): void
    {
        $organization = new Organization();
        $organization->setName('Mon orga');

        $invitedBy = new User();
        $invitedBy->setEmail('inviteur@example.com');
        $invitedBy->setFirstname('Jean');
        $invitedBy->setLastname('Dupont');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findPendingByEmailAndOrga')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (RawMessage $message) use (&$sentEmail): void {
                $sentEmail = $message;
            });

        $service = $this->createService(
            em: $em,
            mailer: $mailer,
            invitationRepository: $invitationRepository,
            userRepository: $userRepository,
        );

        $invitation = $service->invite('nouveau@example.com', $organization, $invitedBy);

        $this->assertSame('nouveau@example.com', $invitation->getEmail());
        $this->assertSame($organization, $invitation->getOrganisation());
        $this->assertSame($invitedBy, $invitation->getInvitedBy());
        $this->assertSame(Invitation::STATUS_PENDING, $invitation->getStatus());

        $this->assertInstanceOf(TemplatedEmail::class, $sentEmail);
        $this->assertSame('Jean Dupont vous invite à rejoindre Mon orga', $sentEmail->getSubject());
        $this->assertSame('nouveau@example.com', $sentEmail->getTo()[0]->getAddress());
    }

    public function testInviteFallsBackToInviterEmailWhenNameIsEmpty(): void
    {
        $organization = new Organization();
        $organization->setName('Mon orga');

        $invitedBy = new User();
        $invitedBy->setEmail('inviteur@example.com');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findPendingByEmailAndOrga')->willReturn(null);

        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (RawMessage $message) use (&$sentEmail): void {
            $sentEmail = $message;
        });

        $service = $this->createService(
            mailer: $mailer,
            invitationRepository: $invitationRepository,
            userRepository: $userRepository,
        );

        $service->invite('nouveau@example.com', $organization, $invitedBy);

        $this->assertSame(
            'inviteur@example.com vous invite à rejoindre Mon orga',
            $sentEmail->getSubject(),
        );
    }

    public function testInviteAllowsAnExistingUserWhoIsNotYetAMember(): void
    {
        $organization = new Organization();
        $organization->setName('Mon orga');

        // L'utilisateur existe déjà sur le site, mais dans une AUTRE organisation.
        $existingUser = new User();
        $existingUser->addOrganization(new Organization());

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($existingUser);

        $invitationRepository = $this->createMock(InvitationRepository::class);
        $invitationRepository->method('findPendingByEmailAndOrga')->willReturn(null);

        $service = $this->createService(
            invitationRepository: $invitationRepository,
            userRepository: $userRepository,
        );

        $invitation = $service->invite('deja-inscrit@example.com', $organization, new User());

        $this->assertSame(Invitation::STATUS_PENDING, $invitation->getStatus());
    }

    public function testMarkAsRevokedOnAPendingInvitation(): void
    {
        $invitation = new Invitation('un-hash');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = $this->createService(em: $em);
        $service->markAsRevoked($invitation);

        $this->assertSame(Invitation::STATUS_REVOKED, $invitation->getStatus());
    }

    public function testMarkAsRevokedThrowsOnAnInvitationThatIsNoLongerPending(): void
    {
        $invitation = new Invitation('un-hash');
        $invitation->setStatus(Invitation::STATUS_ACCEPTED);

        $service = $this->createService();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Seules les invitations en attente peuvent être révoquées.');

        $service->markAsRevoked($invitation);
    }

    public function testMarkAsRevokedThrowsOnAnExpiredInvitation(): void
    {
        $invitation = new Invitation('un-hash');
        $invitation->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $service = $this->createService();

        $this->expectException(\LogicException::class);

        $service->markAsRevoked($invitation);
    }
}
