<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\ReallocationLine;
use App\Entity\RevenueProtectionOverride;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Enum\ReallocationInstance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Scénario commun aux tests de la protection des recettes : la Fête a reçu
 * un don de 1 000 € et en a réaffecté 800 au Chauffage.
 */
trait RevenueProtectionScenarioTrait
{
    private static string $csrfToken = 'jeton-csrf-de-test';

    /**
     * @return array{organization: Organization, admin: User, treasurer: User, source: Budget, incomeLine: BudgetLine, donation: Transaction}
     */
    private function reallocatedIncomeScenario(): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Asso protection');
        $organization->setSlug('asso-protection-recettes');
        $organization->setIsActive(true);

        $admin = $this->scenarioUser('admin-protection@example.com');
        $treasurer = $this->scenarioUser('tresorier-protection@example.com');
        $organization->addUser($admin, OrganizationRole::ADMIN);
        $organization->addUser($treasurer, OrganizationRole::TREASURER);

        $source = $this->scenarioBudget($organization, 'Fête', 'fete-protection');
        $target = $this->scenarioBudget($organization, 'Chauffage', 'chauffage-protection');

        $incomeLine = $this->scenarioLine($source, 'Dons', false);

        $donation = $this->scenarioTransaction(1000.0, $incomeLine);
        $donation->setReference('DON-12');

        $reallocation = (new Reallocation())
            ->setOrganization($organization)
            ->setDecidedAt(new \DateTime('2026-10-12'))
            ->setInstance(ReallocationInstance::BOARD)
            ->setReason('Répartition don chauffage')
            ->setSourceBudget($source)
            ->addLine((new ReallocationLine())->setTargetBudget($target)->setAmount('800.00'));

        foreach ([$organization, $admin, $treasurer, $source, $target, $incomeLine, $donation, $reallocation] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [
            'organization' => $organization,
            'admin' => $admin,
            'treasurer' => $treasurer,
            'source' => $source,
            'incomeLine' => $incomeLine,
            'donation' => $donation,
        ];
    }

    /**
     * Connecte l'utilisateur après avoir vidé la mémoire de Doctrine : sinon
     * la requête réutiliserait les objets créés par le test, dont les
     * collections inverses sont vides (addBudgetLine() ne remplit pas
     * BudgetLine::$transactions). En production, chaque requête relit la base.
     */
    private function startRequestsAs(KernelBrowser $client, User $user): void
    {
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $client->loginUser($user);
    }

    /**
     * Comme startRequestsAs(), avec un jeton CSRF connu placé dans la session.
     */
    private function loginWithCsrfToken(KernelBrowser $client, User $user, string $tokenId): string
    {
        $this->startRequestsAs($client, $user);

        $session = $client->getSession();
        $session->set('_csrf/' . $tokenId, self::$csrfToken);
        $session->save();

        return self::$csrfToken;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    private function reloadIncludingTrash(string $class, int $id): ?object
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $em->getFilters()->disable('soft_delete');
        $entity = $em->find($class, $id);
        $em->getFilters()->enable('soft_delete');

        return $entity;
    }

    /**
     * @return RevenueProtectionOverride[]
     */
    private function overrides(): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(RevenueProtectionOverride::class)->findAll();
    }

    private function scenarioUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname('Test');

        return $user;
    }

    private function scenarioBudget(Organization $organization, string $name, string $slug): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setSlug($slug);
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));

        return $budget;
    }

    private function scenarioLine(Budget $budget, string $name, bool $isExpense): BudgetLine
    {
        $line = new BudgetLine();
        $line->setName($name);
        $line->setAmount(1000.0);
        $line->setIsExpense($isExpense);
        $line->setBudget($budget);

        return $line;
    }

    private function scenarioTransaction(float $amount, BudgetLine ...$lines): Transaction
    {
        $transaction = new Transaction();
        $transaction->setDate(new \DateTime('2026-06-01'));
        $transaction->setAmount($amount);
        $transaction->setPaymentMethod('Chèque');

        foreach ($lines as $line) {
            $transaction->addBudgetLine($line);
        }

        return $transaction;
    }
}
