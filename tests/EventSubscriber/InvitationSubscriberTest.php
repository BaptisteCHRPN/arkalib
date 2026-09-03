<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\User;
use App\EventSubscriber\InvitationSubscriber;
use App\Service\InvitationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class InvitationSubscriberTest extends TestCase
{
    private function sessionWith(?string $pendingToken): Session
    {
        $session = new Session(new MockArraySessionStorage());

        if ($pendingToken !== null) {
            $session->set('pending_invitation_token', $pendingToken);
        }

        return $session;
    }

    private function requestStackFor(Session $session): RequestStack
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        return $requestStack;
    }

    private function loginEventFor(User $user): LoginSuccessEvent
    {
        $event = $this->createMock(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        return $event;
    }

    public function testSubscribesToLoginSuccess(): void
    {
        $this->assertArrayHasKey(
            LoginSuccessEvent::class,
            InvitationSubscriber::getSubscribedEvents(),
        );
    }

    public function testNothingHappensOnAnOrdinaryLogin(): void
    {
        $session = $this->sessionWith(null);

        $invitationService = $this->createMock(InvitationService::class);
        $invitationService->expects($this->never())->method('accept');

        $subscriber = new InvitationSubscriber($this->requestStackFor($session), $invitationService);
        $subscriber->onLoginSuccess($this->loginEventFor(new User()));

        $this->assertSame([], $session->getFlashBag()->all());
    }

    public function testPendingInvitationIsAcceptedOnLogin(): void
    {
        $organization = new Organization();
        $organization->setName('Mon orga');

        $invitation = new Invitation('un-hash');
        $invitation->setOrganisation($organization);

        $user = new User();
        $session = $this->sessionWith('le-token');

        $invitationService = $this->createMock(InvitationService::class);
        $invitationService->expects($this->once())
            ->method('accept')
            ->with('le-token', $user)
            ->willReturn($invitation);

        $subscriber = new InvitationSubscriber($this->requestStackFor($session), $invitationService);
        $subscriber->onLoginSuccess($this->loginEventFor($user));

        $this->assertSame(
            ['Vous avez rejoint l\'organisation « Mon orga » !'],
            $session->getFlashBag()->get('success'),
        );
    }

    public function testTokenIsRemovedFromSessionSoItCannotBeReplayed(): void
    {
        $invitation = new Invitation('un-hash');
        $invitation->setOrganisation((new Organization())->setName('Mon orga'));

        $session = $this->sessionWith('le-token');

        $invitationService = $this->createMock(InvitationService::class);
        $invitationService->method('accept')->willReturn($invitation);

        $subscriber = new InvitationSubscriber($this->requestStackFor($session), $invitationService);
        $subscriber->onLoginSuccess($this->loginEventFor(new User()));

        $this->assertFalse($session->has('pending_invitation_token'));
    }

    public function testTokenIsAlsoRemovedWhenAcceptanceFails(): void
    {
        $session = $this->sessionWith('le-token');

        $invitationService = $this->createMock(InvitationService::class);
        $invitationService->method('accept')->willThrowException(new \LogicException('expirée'));

        $subscriber = new InvitationSubscriber($this->requestStackFor($session), $invitationService);
        $subscriber->onLoginSuccess($this->loginEventFor(new User()));

        $this->assertFalse($session->has('pending_invitation_token'));
        $this->assertSame(
            ['L\'invitation avait expiré ou est invalide.'],
            $session->getFlashBag()->get('error'),
        );
    }

    public function testFailedAcceptanceDoesNotBreakTheLogin(): void
    {
        // Le login doit réussir même si l'invitation est invalide :
        // l'exception ne doit jamais remonter hors du subscriber.
        $session = $this->sessionWith('le-token');

        $invitationService = $this->createMock(InvitationService::class);
        $invitationService->method('accept')->willThrowException(new \LogicException('invalide'));

        $subscriber = new InvitationSubscriber($this->requestStackFor($session), $invitationService);

        $subscriber->onLoginSuccess($this->loginEventFor(new User()));

        $this->assertSame([], $session->getFlashBag()->get('success'));
    }
}
