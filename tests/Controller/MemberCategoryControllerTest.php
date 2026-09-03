<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberCategoryControllerTest extends WebTestCase
{
    private const CSRF_TOKEN = 'jeton-csrf-de-test';

    private function makeOrganization(string $name, string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);
        $organization->setIsActive(true);

        return $organization;
    }

    private function makeBudget(Organization $organization, string $slug, bool $closed = false): Budget
    {
        $budget = new Budget();
        $budget->setName('Budget');
        $budget->setSlug($slug);
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));
        $budget->setIsClosed($closed);

        return $budget;
    }

    private function makeCategory(Budget $budget, string $name): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setBudget($budget);

        return $category;
    }

    private function makeUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');

        return $user;
    }

    private function reloadCategory(int $id): ?Category
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $em->getFilters()->disable('soft_delete');
        $category = $em->getRepository(Category::class)->find($id);
        $em->getFilters()->enable('soft_delete');

        return $category;
    }

    private function primeCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        $session = $client->getSession();
        $session->set('_csrf/' . $tokenId, self::CSRF_TOKEN);
        $session->save();

        return self::CSRF_TOKEN;
    }

    private function categoryUrl(Organization $organization, Budget $budget, string $suffix = ''): string
    {
        return sprintf(
            '/organizations/%s/budgets/%s/categories%s',
            $organization->getSlug(),
            $budget->getSlug(),
            $suffix,
        );
    }

    public function testOutsiderCannotListTheCategoriesOfABudget(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org privee', 'org-privee-categorie-test');
        $budget = $this->makeBudget($organization, 'budget-categorie-privee-test');
        $outsider = $this->makeUser('outsider-categorie@example.com');

        $em->persist($organization);
        $em->persist($budget);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('GET', $this->categoryUrl($organization, $budget));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMemberCannotListTheCategoriesOfABudgetOfAnotherOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = $this->makeOrganization('Org A', 'org-a-categorie-test');
        $member = $this->makeUser('membre-categorie-a@example.com');
        $orgA->addUser($member);

        $orgB = $this->makeOrganization('Org B', 'org-b-categorie-test');
        $budgetB = $this->makeBudget($orgB, 'budget-b-categorie-test');

        $em->persist($orgA);
        $em->persist($member);
        $em->persist($orgB);
        $em->persist($budgetB);
        $em->flush();

        $client->loginUser($member);

        // Slug de SA PROPRE organisation dans l'URL, mais budget d'orgB.
        $client->request('GET', $this->categoryUrl($orgA, $budgetB));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCategoryOfAnotherBudgetCannotBeDeletedThroughThisBudget(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-categorie-croisee-test');
        $member = $this->makeUser('membre-categorie-croisee@example.com');
        $organization->addUser($member);

        $budget = $this->makeBudget($organization, 'budget-categorie-croisee-test');
        $otherBudget = $this->makeBudget($organization, 'autre-budget-categorie-croisee-test');
        $categoryOfOtherBudget = $this->makeCategory($otherBudget, 'Catégorie de l\'autre budget');

        $em->persist($organization);
        $em->persist($member);
        $em->persist($budget);
        $em->persist($otherBudget);
        $em->persist($categoryOfOtherBudget);
        $em->flush();

        $client->loginUser($member);

        $client->request(
            'POST',
            $this->categoryUrl($organization, $budget, '/' . $categoryOfOtherBudget->getId() . '/delete'),
            ['_token' => $this->primeCsrfToken($client, 'delete' . $categoryOfOtherBudget->getId())],
        );

        $this->assertResponseStatusCodeSame(404);
        $this->assertNull($this->reloadCategory($categoryOfOtherBudget->getId())->getDeletedAt());
    }

    /**
     * Test de contrôle : il valide le mécanisme de jeton CSRF utilisé par les
     * autres tests de suppression.
     */
    public function testMemberCanSoftDeleteACategoryOfAnOpenBudget(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-categorie-delete-test');
        $member = $this->makeUser('membre-categorie-delete@example.com');
        $organization->addUser($member);

        $budget = $this->makeBudget($organization, 'budget-categorie-delete-test');
        $category = $this->makeCategory($budget, 'Fournitures');

        $em->persist($organization);
        $em->persist($member);
        $em->persist($budget);
        $em->persist($category);
        $em->flush();

        $client->loginUser($member);

        $client->request(
            'POST',
            $this->categoryUrl($organization, $budget, '/' . $category->getId() . '/delete'),
            ['_token' => $this->primeCsrfToken($client, 'delete' . $category->getId())],
        );

        $this->assertResponseRedirects();
        $this->assertNotNull($this->reloadCategory($category->getId())->getDeletedAt());
    }

    public function testACategoryCannotBeCreatedOnAClosedBudget(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-budget-cloture-new-test');
        $member = $this->makeUser('membre-budget-cloture-new@example.com');
        $organization->addUser($member);

        $budget = $this->makeBudget($organization, 'budget-cloture-new-test', closed: true);

        $em->persist($organization);
        $em->persist($member);
        $em->persist($budget);
        $em->flush();

        $client->loginUser($member);

        $client->request('GET', $this->categoryUrl($organization, $budget, '/new'));

        $this->assertResponseRedirects(sprintf(
            '/budget/%s/%s',
            $organization->getSlug(),
            $budget->getSlug(),
        ));
    }

    public function testACategoryCannotBeDeletedOnAClosedBudget(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-budget-cloture-delete-test');
        $member = $this->makeUser('membre-budget-cloture-delete@example.com');
        $organization->addUser($member);

        $budget = $this->makeBudget($organization, 'budget-cloture-delete-test', closed: true);
        $category = $this->makeCategory($budget, 'Fournitures');

        $em->persist($organization);
        $em->persist($member);
        $em->persist($budget);
        $em->persist($category);
        $em->flush();

        $client->loginUser($member);

        $client->request(
            'POST',
            $this->categoryUrl($organization, $budget, '/' . $category->getId() . '/delete'),
            ['_token' => $this->primeCsrfToken($client, 'delete' . $category->getId())],
        );

        $this->assertResponseRedirects();
        $this->assertNull(
            $this->reloadCategory($category->getId())->getDeletedAt(),
            'Un budget clôturé est en lecture seule : la catégorie ne doit pas partir en corbeille.',
        );
    }

    public function testACategoryStillHoldingSubCategoriesCannotBeDeleted(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-categorie-parente-test');
        $member = $this->makeUser('membre-categorie-parente@example.com');
        $organization->addUser($member);

        $budget = $this->makeBudget($organization, 'budget-categorie-parente-test');
        $parent = $this->makeCategory($budget, 'Parente');
        $child = $this->makeCategory($budget, 'Enfant');
        $parent->addSubCategory($child);

        $em->persist($organization);
        $em->persist($member);
        $em->persist($budget);
        $em->persist($parent);
        $em->persist($child);
        $em->flush();

        $client->loginUser($member);

        $client->request(
            'POST',
            $this->categoryUrl($organization, $budget, '/' . $parent->getId() . '/delete'),
            ['_token' => $this->primeCsrfToken($client, 'delete' . $parent->getId())],
        );

        $this->assertResponseRedirects();
        $this->assertNull(
            $this->reloadCategory($parent->getId())->getDeletedAt(),
            'Supprimer une catégorie parente orphelinerait ses sous-catégories.',
        );
    }
}
