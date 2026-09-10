<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Les quatre entités du domaine ne sont plus modifiables depuis le
 * back-office : écrire passe par l'interface membre, où la corbeille, la
 * clôture du budget et les contrôles d'appartenance s'appliquent.
 *
 * Ce test existe pour que la remise en place d'un formulaire d'administration
 * soit un choix explicite, et non le résultat d'un make:crud relancé.
 */
final class AdminReadOnlyControllersTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function writeRouteProvider(): iterable
    {
        foreach (['budget', 'budget_line', 'category', 'transaction'] as $entity) {
            foreach (['new', 'edit', 'delete'] as $verb) {
                $route = 'app_admin_' . $entity . '_' . $verb;

                yield $route => [$route];
            }
        }
    }

    #[DataProvider('writeRouteProvider')]
    public function testTheWriteRouteNoLongerExists(string $route): void
    {
        self::bootKernel();
        $router = static::getContainer()->get(RouterInterface::class);

        $this->assertNull(
            $router->getRouteCollection()->get($route),
            sprintf('La route %s devrait avoir disparu avec le passage en lecture seule.', $route),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function readRouteProvider(): iterable
    {
        yield 'budgets' => ['/admin/budget'];
        yield 'lignes budgétaires' => ['/admin/budgetline'];
        yield 'catégories' => ['/admin/category'];
        yield 'transactions' => ['/admin/transaction'];
    }

    #[DataProvider('readRouteProvider')]
    public function testTheDirectoryStillOpens(string $uri): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setPassword('not-checked-by-loginUser');
        $admin->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($admin);
        $entityManager->flush();

        $client->loginUser($admin);
        $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
    }
}
