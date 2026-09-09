<?php

namespace App\Tests\Service;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Service\AccountAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountAnonymizerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AccountAnonymizer $anonymizer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->anonymizer = static::getContainer()->get(AccountAnonymizer::class);
    }

    private function createUser(string $email = 'marie@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('hachage-initial');
        $user->setFirstname('Marie');
        $user->setLastname('Dupont');
        $user->setIsVerified(true);
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createOrganization(string $name, string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        return $organization;
    }

    public function testPersonalDataIsErased(): void
    {
        $user = $this->createUser();
        $id = $user->getId();

        $this->anonymizer->anonymize($user);

        $this->assertSame(AccountAnonymizer::EMAIL_PREFIX . $id . AccountAnonymizer::EMAIL_DOMAIN, $user->getEmail());
        $this->assertNull($user->getFirstname());
        $this->assertNull($user->getLastname());
        $this->assertNull($user->getPicture());
        $this->assertFalse($user->isVerified());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testTheAccountCanNoLongerBeUsedToLogIn(): void
    {
        $user = $this->createUser();

        $this->anonymizer->anonymize($user);

        $this->assertNotSame('hachage-initial', $user->getPassword());
    }

    /**
     * Le cœur de la décision : on efface la personne, pas la ligne, pour que
     * les écritures comptables de ses collègues gardent leur auteur.
     */
    public function testTheRowSurvivesSoThatAuthorshipIsPreserved(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization('Asso X', 'asso-x');

        $budget = new Budget();
        $budget->setName('Budget 2025');
        $budget->setSlug('budget-2025');
        $budget->setStartDate(new \DateTime('2025-01-01'));
        $budget->setEndDate(new \DateTime('2025-12-31'));
        $budget->setOrganization($organization);
        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        // TraceabilitySubscriber écrase created_by au prePersist avec
        // l'utilisateur connecté — il n'y en a aucun ici. On pose donc
        // l'auteur après l'insertion pour reproduire l'état réel.
        $budget->setCreatedBy($user);

        $other = $this->createUser('autre@example.com');
        $organization->addUser($other, OrganizationRole::ADMIN);
        $organization->addUser($user, OrganizationRole::TREASURER);
        $this->entityManager->flush();

        $this->anonymizer->anonymize($user);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Budget::class, $budget->getId());

        $this->assertNotNull($reloaded->getCreatedBy());
    }

    public function testMembershipsAreRemoved(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization('Asso X', 'asso-x');
        $other = $this->createUser('autre@example.com');
        $organization->addUser($other, OrganizationRole::ADMIN);
        $organization->addUser($user, OrganizationRole::READER);
        $this->entityManager->flush();

        $this->anonymizer->anonymize($user);

        $this->assertCount(0, $user->getMemberships());
    }

    public function testAnonymizingIsRefusedWhenItWouldLeaveAnOrganizationWithoutAnAdmin(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization('Orpheline', 'orpheline');
        $organization->addUser($user, OrganizationRole::ADMIN);
        $this->entityManager->flush();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Orpheline/');

        $this->anonymizer->anonymize($user);
    }

    public function testARefusedAnonymizationChangesNothing(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization('Orpheline', 'orpheline');
        $organization->addUser($user, OrganizationRole::ADMIN);
        $this->entityManager->flush();

        try {
            $this->anonymizer->anonymize($user);
        } catch (\LogicException) {
            // attendu
        }

        $this->assertSame('marie@example.com', $user->getEmail());
        $this->assertSame('Marie', $user->getFirstname());
    }

    public function testBeingAnAdminAlongsideAnotherAdminIsNotBlocking(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization('Asso X', 'asso-x');
        $other = $this->createUser('autre@example.com');
        $organization->addUser($user, OrganizationRole::ADMIN);
        $organization->addUser($other, OrganizationRole::ADMIN);
        $this->entityManager->flush();

        $this->assertSame([], $this->anonymizer->blockingOrganizations($user));
    }

    public function testAnAnonymizedAccountIsRecognizedAsSuch(): void
    {
        $user = $this->createUser();

        $this->assertFalse($this->anonymizer->isAnonymized($user));

        $this->anonymizer->anonymize($user);

        $this->assertTrue($this->anonymizer->isAnonymized($user));
    }
}
