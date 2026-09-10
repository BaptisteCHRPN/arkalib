<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le bloc de traçabilité affiche des colonnes collectées depuis l'origine et
 * qui n'étaient montrées nulle part.
 */
final class AdminTraceabilityTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createUser(string $email, ?string $firstname = null, ?string $lastname = null, array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function loginAsAdmin(): User
    {
        $admin = $this->createUser('admin@example.com', 'Super', 'Admin', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);

        return $admin;
    }

    private function createBudget(Organization $organization): Budget
    {
        $budget = new Budget();
        $budget->setName('Saison 2025');
        $budget->setSlug('saison-2025');
        $budget->setStartDate(new \DateTime('2025-01-01'));
        $budget->setEndDate(new \DateTime('2025-12-31'));
        $organization->addBudget($budget);
        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        return $budget;
    }

    private function createOrganization(): Organization
    {
        $organization = new Organization();
        $organization->setName('Asso Chorale');
        $organization->setSlug('asso-chorale');
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        return $organization;
    }

    public function testTheAuthorOfACreationIsShown(): void
    {
        $this->loginAsAdmin();
        $marie = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $budget = $this->createBudget($this->createOrganization());

        // TraceabilitySubscriber écrase created_by au prePersist avec
        // l'utilisateur connecté — il n'y en a aucun dans le processus de test.
        $budget->setCreatedBy($marie);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/budget/' . $budget->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Traçabilité');
        $this->assertSelectorTextContains('body', 'Marie Dupont');
    }

    public function testAnUnmodifiedEntitySaysSoRatherThanShowingABlank(): void
    {
        $this->loginAsAdmin();
        $budget = $this->createBudget($this->createOrganization());

        $this->client->request('GET', '/admin/budget/' . $budget->getId());

        $this->assertSelectorTextContains('body', 'jamais modifié depuis sa création');
    }

    public function testWhoPutSomethingInTheTrashIsShown(): void
    {
        $this->loginAsAdmin();
        $marie = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $budget = $this->createBudget($this->createOrganization());

        $budget->setDeletedAt(new \DateTimeImmutable('2025-06-01 10:00'));
        $budget->setDeletedBy($marie);
        $this->entityManager->flush();

        // Le filtre soft_delete masque l'entité : on le désactive comme le fait
        // l'interface membre pour sa corbeille.
        $this->entityManager->getFilters()->disable('soft_delete');

        $this->client->request('GET', '/admin/budget/' . $budget->getId());

        $this->assertSelectorTextContains('body', 'À la corbeille');
        $this->assertSelectorTextContains('body', '01/06/2025');
        $this->assertSelectorTextContains('body', 'Marie Dupont');
    }

    /**
     * Le point délicat du partial : Organization et User n'utilisent pas
     * SoftDeleteTrait, la ligne « Corbeille » ne doit pas s'y afficher — ni
     * faire échouer le rendu.
     */
    public function testTheTrashRowIsAbsentFromEntitiesThatCannotBeTrashed(): void
    {
        $this->loginAsAdmin();
        $organization = $this->createOrganization();

        $this->client->request('GET', '/admin/organization/' . $organization->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Traçabilité');
        $this->assertSelectorTextNotContains('body', 'Corbeille');
    }

    public function testTheUserFicheSaysInscriptionRatherThanCreation(): void
    {
        $admin = $this->loginAsAdmin();

        $this->client->request('GET', '/admin/user/' . $admin->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Inscription');
        $this->assertSelectorTextNotContains('body', 'Corbeille');
    }

    public function testTheTrashRowIsPresentButNegativeOnALiveEntity(): void
    {
        $this->loginAsAdmin();
        $budget = $this->createBudget($this->createOrganization());

        $this->client->request('GET', '/admin/budget/' . $budget->getId());

        $this->assertSelectorTextContains('body', 'Corbeille');
        $this->assertSelectorTextNotContains('body', 'À la corbeille');
    }
}
