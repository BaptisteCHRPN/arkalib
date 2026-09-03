<?php

namespace App\Tests\Entity;

use App\Entity\Invitation;
use PHPUnit\Framework\TestCase;

final class InvitationTest extends TestCase
{
    public function testNewInvitationIsPendingAndValidForSevenDays(): void
    {
        $invitation = new Invitation('un-hash');

        $this->assertSame(Invitation::STATUS_PENDING, $invitation->getStatus());
        $this->assertFalse($invitation->isExpired());
        $this->assertTrue($invitation->isPending());
        $this->assertSame('un-hash', $invitation->getHashedToken());
    }

    public function testInvitationIsExpiredOnceExpiresAtIsInThePast(): void
    {
        $invitation = new Invitation('un-hash');
        $invitation->setExpiresAt(new \DateTimeImmutable('-1 second'));

        $this->assertTrue($invitation->isExpired());
    }

    public function testExpiredInvitationIsNotPendingEvenWithPendingStatus(): void
    {
        $invitation = new Invitation('un-hash');
        $invitation->setExpiresAt(new \DateTimeImmutable('-1 day'));

        // Le statut en base dit encore « pending », mais la date fait foi.
        $this->assertSame(Invitation::STATUS_PENDING, $invitation->getStatus());
        $this->assertFalse($invitation->isPending());
    }

    /**
     * Une invitation déjà traitée (acceptée, expirée, révoquée) ne doit plus
     * être considérée comme en attente, même si sa date de validité court encore.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonPendingStatuses')]
    public function testInvitationIsNotPendingWhenStatusIsNotPending(string $status): void
    {
        $invitation = new Invitation('un-hash');
        $invitation->setStatus($status);

        $this->assertFalse($invitation->isExpired());
        $this->assertFalse($invitation->isPending());
    }

    public static function nonPendingStatuses(): array
    {
        return [
            'acceptée' => [Invitation::STATUS_ACCEPTED],
            'expirée'  => [Invitation::STATUS_EXPIRED],
            'révoquée' => [Invitation::STATUS_REVOKED],
        ];
    }
}
