<?php

namespace App\Tests\Entity;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\RevenueProtectionOverride;
use App\Entity\User;
use App\Enum\ReallocationInstance;
use App\Model\RevenueProtectionResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class RevenueProtectionOverrideTest extends TestCase
{
    public function testTraceIsFixedFromTheBlockingResult(): void
    {
        $organization = new Organization();
        $budget = (new Budget())->setName('Fête')->setOrganization($organization);
        $user = new User();

        $override = new RevenueProtectionOverride(
            $budget,
            $user,
            '  Don remboursé au donateur le 03/11  ',
            'Suppression de la transaction « Don Dupont » (1 000,00 €)',
            $this->blockedResult(-80000),
        );

        $this->assertSame($organization, $override->getOrganization());
        $this->assertSame($budget, $override->getBudget());
        $this->assertSame($user, $override->getForcedBy());
        $this->assertEqualsWithDelta(time(), $override->getForcedAt()->getTimestamp(), 5);
        $this->assertSame('Don remboursé au donateur le 03/11', $override->getReason());
        $this->assertSame('Suppression de la transaction « Don Dupont » (1 000,00 €)', $override->getOperation());
        $this->assertSame(
            ['du 12/10/2026 (CA) – Chauffage', 'du 01/03/2026 (AG) – Sorties'],
            $override->getReallocations(),
        );
        $this->assertSame(-80000, $override->getAvailableAfterInCents());
    }

    public function testReasonIsRequired(): void
    {
        $violations = $this->validate($this->override('   '));

        $this->assertCount(1, $violations);
        $this->assertSame('reason', $violations[0]->getPropertyPath());
    }

    public function testReasonIsLimitedTo255Characters(): void
    {
        $this->assertCount(0, $this->validate($this->override(str_repeat('a', 255))));
        $this->assertCount(1, $this->validate($this->override(str_repeat('a', 256))));
    }

    public function testNothingToForceWhenTheOperationIsAllowed(): void
    {
        $this->expectException(\LogicException::class);

        new RevenueProtectionOverride(
            (new Budget())->setOrganization(new Organization()),
            new User(),
            'Motif',
            'Opération',
            new RevenueProtectionResult(-5000, []),
        );
    }

    private function override(string $reason): RevenueProtectionOverride
    {
        return new RevenueProtectionOverride(
            (new Budget())->setOrganization(new Organization()),
            new User(),
            $reason,
            'Opération',
            $this->blockedResult(-100),
        );
    }

    private function blockedResult(int $availableAfter): RevenueProtectionResult
    {
        return new RevenueProtectionResult($availableAfter, [
            (new Reallocation())->setDecidedAt(new \DateTime('2026-10-12'))->setInstance(ReallocationInstance::BOARD)->setReason('Chauffage'),
            (new Reallocation())->setDecidedAt(new \DateTime('2026-03-01'))->setInstance(ReallocationInstance::GENERAL_ASSEMBLY)->setReason('Sorties'),
        ]);
    }

    private function validate(RevenueProtectionOverride $override)
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($override);
    }
}
