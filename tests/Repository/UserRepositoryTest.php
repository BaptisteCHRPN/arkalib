<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(UserRepository::class);
    }

    private function createUser(string $email, ?string $firstname = null, ?string $lastname = null): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-here');
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param User[] $results
     *
     * @return string[]
     */
    private function emailsOf(array $results): array
    {
        return array_map(static fn (User $user) => $user->getEmail(), $results);
    }

    public function testAnEmptyTermReturnsEveryone(): void
    {
        $this->createUser('a@example.com');
        $this->createUser('b@example.com');

        $this->assertCount(2, $this->repository->search(''));
        $this->assertCount(2, $this->repository->search(null));
        $this->assertCount(2, $this->repository->search('   '));
    }

    public function testSearchByEmailFragment(): void
    {
        $this->createUser('marie.dupont@example.com');
        $this->createUser('paul.martin@example.com');

        $this->assertSame(
            ['marie.dupont@example.com'],
            $this->emailsOf($this->repository->search('dupont')),
        );
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $this->createUser('marie@example.com', 'Marie', 'Dupont');

        $this->assertCount(1, $this->repository->search('MARIE'));
        $this->assertCount(1, $this->repository->search('dupont'));
    }

    /**
     * Le cas qui justifie la concaténation : une réclamation donne un nom
     * complet, qui n'existe dans aucune colonne prise isolément.
     */
    public function testSearchByFullName(): void
    {
        $this->createUser('m.d@example.com', 'Marie', 'Dupont');
        $this->createUser('p.m@example.com', 'Paul', 'Martin');

        $this->assertSame(
            ['m.d@example.com'],
            $this->emailsOf($this->repository->search('Marie Dupont')),
        );
    }

    public function testAccountsWithoutANameAreStillSearchable(): void
    {
        $this->createUser('anonyme@example.com');

        $this->assertCount(1, $this->repository->search('anonyme'));
    }

    /**
     * Sans échappement, « % » se comporterait comme un joker et ramènerait
     * toute la table au lieu de ne rien trouver.
     */
    public function testWildcardsTypedByTheOperatorAreNotInterpreted(): void
    {
        $this->createUser('marie@example.com');
        $this->createUser('paul@example.com');

        $this->assertEmpty($this->repository->search('%'));
        $this->assertEmpty($this->repository->search('mari%e'));
    }

    public function testUnderscoreIsNotAWildcard(): void
    {
        $this->createUser('marie@example.com');
        $this->createUser('jean_luc@example.com');

        $this->assertSame(
            ['jean_luc@example.com'],
            $this->emailsOf($this->repository->search('jean_luc')),
        );
        $this->assertEmpty($this->repository->search('mari_'));
    }

    public function testMostRecentAccountsComeFirst(): void
    {
        $this->createUser('premier@example.com');
        $this->createUser('second@example.com');

        $this->assertSame(
            ['second@example.com', 'premier@example.com'],
            $this->emailsOf($this->repository->search('example.com')),
        );
    }
}
