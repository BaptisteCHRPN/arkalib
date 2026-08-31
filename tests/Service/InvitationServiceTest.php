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
use Symfony\Component\Mailer\MailerInterface;
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
}
