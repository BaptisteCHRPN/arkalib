<?php

namespace App\Tests\Service;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\ReallocationLine;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\ReallocationInstance;
use App\Service\BudgetBalanceCalculator;
use App\Service\ReallocationService;
use App\Service\SoftDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ReallocationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReallocationService $service;
    private Organization $organization;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Construit à la main : le service n'est encore utilisé nulle part,
        // Symfony l'a donc retiré du conteneur.
        $this->service = new ReallocationService(
            $this->em,
            static::getContainer()->get(ValidatorInterface::class),
            new SoftDeleteService(),
        );

        $this->organization = new Organization();
        $this->organization->setName('Asso test');
        $this->organization->setSlug('asso-reallocation-service-test');
        $this->organization->setIsActive(true);
        $this->em->persist($this->organization);
    }

    public function testRestoreWhenAvailableIsStillSufficient(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 1000.0, false);
        $reallocation = $this->reallocation($source, $this->budget('Chauffage'), '800.00', cancelled: true);
        $this->em->flush();

        $violations = $this->service->restore($reallocation);

        $this->assertCount(0, $violations);
        $this->assertFalse($reallocation->isCancelled());
    }

    public function testRestoreIsRefusedWhenAvailableDroppedSinceCancellation(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 1000.0, false);
        $reallocation = $this->reallocation($source, $this->budget('Chauffage'), '800.00', cancelled: true);
        // Dépense saisie après l'annulation : il ne reste que 700 €.
        $this->transaction($source, 300.0, true);
        $this->em->flush();

        $violations = $this->service->restore($reallocation);

        $this->assertCount(1, $violations);
        $this->assertSame('lines', $violations[0]->getPropertyPath());
        $this->assertTrue($reallocation->isCancelled());
    }

    public function testRestoreIsRefusedWhenBudgetClosedSinceCancellation(): void
    {
        $source = $this->budget('Dons');
        $target = $this->budget('Chauffage');
        $this->transaction($source, 1000.0, false);
        $reallocation = $this->reallocation($source, $target, '800.00', cancelled: true);
        $target->setIsClosed(true);
        $this->em->flush();

        $violations = $this->service->restore($reallocation);

        $this->assertCount(1, $violations);
        $this->assertSame('lines[0].targetBudget', $violations[0]->getPropertyPath());
        $this->assertTrue($reallocation->isCancelled());
    }

    /**
     * Le budget est caché par le filtre soft_delete : sans précaution, le lire
     * lèverait une EntityNotFoundException (erreur 500).
     */
    public function testRestoreWithSourceInTrashIsRefusedWithoutCrashing(): void
    {
        $source = $this->budget('Dons');
        $this->transaction($source, 1000.0, false);
        $id = $this->reallocation($source, $this->budget('Chauffage'), '800.00', cancelled: true)->getId();
        $source->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        $violations = $this->service->restore($this->reloadCancelled($id));

        $this->assertCount(1, $violations);
        $this->assertSame('sourceBudget', $violations[0]->getPropertyPath());
        $this->assertStringContainsString('« Dons » est dans la corbeille', $violations[0]->getMessage());
    }

    public function testCancelIsNeverBlockedEvenIfTargetAlreadySpentTheMoney(): void
    {
        $source = $this->budget('Dons');
        $target = $this->budget('Chauffage');
        $this->transaction($source, 1000.0, false);
        $reallocation = $this->reallocation($source, $target, '800.00', cancelled: false);
        $this->transaction($target, 800.0, true);
        $user = $this->user();
        $this->em->flush();

        $this->service->cancel($reallocation, $user);
        $this->em->flush();

        $this->assertTrue($reallocation->isCancelled());
        $this->assertSame($user, $reallocation->getDeletedBy());
        $this->assertSame(-80000, $this->calculator()->balanceOf($target)->available());
        $this->assertSame(100000, $this->calculator()->balanceOf($source)->available());
    }

    public function testCancellingTwiceIsAProgrammingError(): void
    {
        $reallocation = $this->reallocation($this->budget('Dons'), $this->budget('Chauffage'), '10.00', cancelled: true);

        $this->expectException(\LogicException::class);

        $this->service->cancel($reallocation, $this->user());
    }

    public function testRestoringAnActiveReallocationIsAProgrammingError(): void
    {
        $reallocation = $this->reallocation($this->budget('Dons'), $this->budget('Chauffage'), '10.00', cancelled: false);

        $this->expectException(\LogicException::class);

        $this->service->restore($reallocation);
    }

    /**
     * Recharge la réaffectation comme le fera le contrôleur : mémoire de
     * Doctrine vidée, filtre désactivé le temps de la trouver.
     */
    private function reloadCancelled(int $id): Reallocation
    {
        $this->em->clear();

        $this->em->getFilters()->disable('soft_delete');
        $reallocation = $this->em->find(Reallocation::class, $id);
        $this->em->getFilters()->enable('soft_delete');

        return $reallocation;
    }

    private function calculator(): BudgetBalanceCalculator
    {
        return static::getContainer()->get(BudgetBalanceCalculator::class);
    }

    private function user(): User
    {
        $user = new User();
        $user->setEmail('tresorier@example.org');
        $user->setPassword('not-checked-here');
        $user->setFirstname('Camille');
        $user->setLastname('Martin');
        $this->em->persist($user);

        return $user;
    }

    private function budget(string $name): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setSlug(strtolower($name));
        $budget->setOrganization($this->organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));
        $this->em->persist($budget);

        return $budget;
    }

    private function transaction(Budget $budget, float $amount, bool $isExpense): void
    {
        $line = new BudgetLine();
        $line->setName($isExpense ? 'Dépense' : 'Recette');
        $line->setAmount(0.0);
        $line->setIsExpense($isExpense);
        $line->setBudget($budget);
        $this->em->persist($line);

        $transaction = new Transaction();
        $transaction->setDate(new \DateTime('2026-06-01'));
        $transaction->setAmount($amount);
        $transaction->setPaymentMethod('Virement');
        $transaction->addBudgetLine($line);
        $this->em->persist($transaction);
    }

    /**
     * Réaffectation enregistrée (flush immédiat pour disposer de son id).
     */
    private function reallocation(Budget $source, Budget $target, string $amount, bool $cancelled): Reallocation
    {
        $reallocation = (new Reallocation())
            ->setOrganization($this->organization)
            ->setDecidedAt(new \DateTime('2026-10-12'))
            ->setInstance(ReallocationInstance::BOARD)
            ->setReason('Répartition don')
            ->setSourceBudget($source)
            ->addLine((new ReallocationLine())->setTargetBudget($target)->setAmount($amount));

        if ($cancelled) {
            $reallocation->setDeletedAt(new \DateTimeImmutable());
        }

        $this->em->persist($reallocation);
        $this->em->flush();

        return $reallocation;
    }
}
