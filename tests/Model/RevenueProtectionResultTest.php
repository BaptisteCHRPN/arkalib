<?php

namespace App\Tests\Model;

use App\Entity\Reallocation;
use App\Enum\ReallocationInstance;
use App\Model\RevenueProtectionResult;
use PHPUnit\Framework\TestCase;

final class RevenueProtectionResultTest extends TestCase
{
    public function testAllowedWhenNothingToCancel(): void
    {
        $this->assertFalse((new RevenueProtectionResult(-5000, []))->isBlocked());
    }

    public function testMessageNamesTheSingleReallocation(): void
    {
        $result = new RevenueProtectionResult(-80000, [
            $this->reallocation('2026-10-12', ReallocationInstance::BOARD, 'Répartition don chauffage'),
        ]);

        $this->assertTrue($result->isBlocked());
        $this->assertSame(
            'Impossible de supprimer cette transaction : cette recette finance la réaffectation du 12/10/2026 (CA) – Répartition don chauffage. Annulez d\'abord cette réaffectation.',
            $result->message('supprimer cette transaction'),
        );
    }

    public function testMessageListsSeveralReallocations(): void
    {
        $result = new RevenueProtectionResult(-80000, [
            $this->reallocation('2026-10-12', ReallocationInstance::BOARD, 'Chauffage'),
            $this->reallocation('2026-06-20', ReallocationInstance::GENERAL_ASSEMBLY, 'Travaux'),
            $this->reallocation('2026-03-01', ReallocationInstance::OTHER, 'Sorties'),
        ]);

        $this->assertSame(
            'Impossible de supprimer cette ligne : cette recette finance les réaffectations du 12/10/2026 (CA) – Chauffage, du 20/06/2026 (AG) – Travaux et du 01/03/2026 (Autre) – Sorties. Annulez d\'abord ces réaffectations.',
            $result->message('supprimer cette ligne'),
        );
    }

    public function testAllowedResultHasNoMessage(): void
    {
        $this->expectException(\LogicException::class);

        (new RevenueProtectionResult(0, []))->message('supprimer cette transaction');
    }

    private function reallocation(string $date, ReallocationInstance $instance, string $reason): Reallocation
    {
        return (new Reallocation())
            ->setDecidedAt(new \DateTime($date))
            ->setInstance($instance)
            ->setReason($reason);
    }
}
