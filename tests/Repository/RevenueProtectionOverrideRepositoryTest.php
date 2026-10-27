<?php

namespace App\Tests\Repository;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\RevenueProtectionOverride;
use App\Entity\User;
use App\Enum\ReallocationInstance;
use App\Model\RevenueProtectionResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que la table créée par la migration garde la trace à l'identique.
 */
final class RevenueProtectionOverrideRepositoryTest extends KernelTestCase
{
    public function testTraceIsStoredAndReadBackUnchanged(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Asso test');
        $organization->setSlug('asso-override-test');
        $organization->setIsActive(true);
        $em->persist($organization);

        $budget = new Budget();
        $budget->setName('Fête');
        $budget->setSlug('fete');
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));
        $em->persist($budget);

        $user = new User();
        $user->setEmail('admin@example.org');
        $user->setPassword('not-checked-here');
        $user->setFirstname('Camille');
        $user->setLastname('Martin');
        $em->persist($user);

        $result = new RevenueProtectionResult(-80000, [
            (new Reallocation())->setDecidedAt(new \DateTime('2026-10-12'))->setInstance(ReallocationInstance::BOARD)->setReason('Chauffage'),
        ]);
        $em->persist(new RevenueProtectionOverride($budget, $user, 'Don remboursé', 'Suppression de la transaction « Don Dupont » (1 000,00 €)', $result));
        $em->flush();
        $em->clear();

        $stored = $em->getRepository(RevenueProtectionOverride::class)->findAll();

        $this->assertCount(1, $stored);
        $this->assertSame('Fête', $stored[0]->getBudget()->getName());
        $this->assertSame('asso-override-test', $stored[0]->getOrganization()->getSlug());
        $this->assertSame('admin@example.org', $stored[0]->getForcedBy()->getEmail());
        $this->assertSame('Don remboursé', $stored[0]->getReason());
        $this->assertSame(['du 12/10/2026 (CA) – Chauffage'], $stored[0]->getReallocations());
        $this->assertSame(-80000, $stored[0]->getAvailableAfterInCents());
    }

    public function testFindByBudgetKeepsOnlyThisBudgetMostRecentFirst(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Asso test');
        $organization->setSlug('asso-override-order');
        $organization->setIsActive(true);
        $em->persist($organization);

        $budgets = [];
        foreach (['fete', 'chauffage'] as $slug) {
            $budget = new Budget();
            $budget->setName($slug);
            $budget->setSlug($slug);
            $budget->setOrganization($organization);
            $budget->setStartDate(new \DateTime('2026-01-01'));
            $budget->setEndDate(new \DateTime('2026-12-31'));
            $em->persist($budget);
            $budgets[$slug] = $budget;
        }

        $user = new User();
        $user->setEmail('admin-order@example.org');
        $user->setPassword('not-checked-here');
        $user->setFirstname('Camille');
        $em->persist($user);

        $result = new RevenueProtectionResult(-100, [
            (new Reallocation())->setDecidedAt(new \DateTime('2026-10-12'))->setInstance(ReallocationInstance::BOARD)->setReason('Chauffage'),
        ]);
        // Même seconde : c'est l'identifiant qui départage.
        foreach (['Premier', 'Second'] as $reason) {
            $em->persist(new RevenueProtectionOverride($budgets['fete'], $user, $reason, 'Opération', $result));
        }
        $em->persist(new RevenueProtectionOverride($budgets['chauffage'], $user, 'Autre budget', 'Opération', $result));
        $em->flush();

        $found = $em->getRepository(RevenueProtectionOverride::class)->findByBudget($budgets['fete']);

        $this->assertSame(['Second', 'Premier'], array_map(fn(RevenueProtectionOverride $o) => $o->getReason(), $found));
    }
}
