<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberBudgetControllerTest extends WebTestCase
{
    use RevenueProtectionScenarioTrait;

    public function testSourceOfAnActiveReallocationCannotBeTrashed(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->trashBudget($client, $scenario['treasurer'], $scenario['source']);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de mettre ce budget à la corbeille');
        $this->assertSelectorTextContains('.alert-danger', 'du 12/10/2026 (CA) – Répartition don chauffage');
        $this->assertNull($this->reloadIncludingTrash(Budget::class, $scenario['source']->getId())->getDeletedAt());
    }

    public function testTargetOfAnActiveReallocationCannotBeTrashed(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $this->trashBudget($client, $scenario['treasurer'], $scenario['target']);

        $this->assertResponseRedirects();
        $this->assertNull($this->reloadIncludingTrash(Budget::class, $scenario['target']->getId())->getDeletedAt());
    }

    public function testBudgetCitedOnlyByCancelledReallocationsCanBeTrashed(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $scenario['reallocation']->setDeletedAt(new \DateTimeImmutable());
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->trashBudget($client, $scenario['treasurer'], $scenario['source']);

        $this->assertResponseRedirects();
        $this->assertNotNull($this->reloadIncludingTrash(Budget::class, $scenario['source']->getId())->getDeletedAt());
    }

    /**
     * La Fête a reçu 1 000 € de dons (1 000 € prévus) et en a donné 800.
     */
    public function testRealizedPageShowsGivenReallocationAndAvailable(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', '/budget-realise/asso-protection-recettes/fete-protection');

        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('[data-reallocation-row]');
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Donnée à « Chauffage »', $rows->text());
        $this->assertStringContainsString('Répartition don chauffage', $rows->text());
        $this->assertStringContainsString('- 800,00 €', $rows->text());
        $this->assertSame('+200,00 €', trim($crawler->filter('[data-available="planned"]')->text()));
        $this->assertSame('+200,00 €', trim($crawler->filter('[data-available="real"]')->text()));
        // Ni recette ni dépense : la carte Recettes est inchangée.
        $this->assertSelectorTextContains('.card.border-success', '+ 1 000,00 €');
    }

    public function testRealizedPageShowsReceivedReallocation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', '/budget-realise/asso-protection-recettes/chauffage-protection');

        $rows = $crawler->filter('[data-reallocation-row]');
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Reçue de « Fête »', $rows->text());
        $this->assertStringContainsString('+ 800,00 €', $rows->text());
        $this->assertSame('+800,00 €', trim($crawler->filter('[data-available="planned"]')->text()));
        $this->assertSame('+800,00 €', trim($crawler->filter('[data-available="real"]')->text()));
        // Les 800 € reçus ne sont pas des recettes.
        $this->assertSelectorTextContains('.card.border-success', '+ 0,00 €');
    }

    public function testCancelledReallocationIsNotShownOnTheRealizedPage(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $scenario['reallocation']->setDeletedAt(new \DateTimeImmutable());
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', '/budget-realise/asso-protection-recettes/fete-protection');

        $this->assertSelectorNotExists('[data-reallocations]');
        $this->assertSelectorNotExists('[data-synthesis-reallocations]');
        // Sans réaffectation, la synthèse se termine sur le solde.
        $this->assertSame('+1 000,00 €', trim($crawler->filter('[data-available="real"]')->text()));
    }

    /**
     * La synthèse se lit de haut en bas : la réaffectation pèse autant au prévu
     * qu'au réel, sans écart, et le badge du haut reprend le disponible réel.
     */
    public function testRealizedPageSynthesisLeadsToTheAvailable(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', '/budget-realise/asso-protection-recettes/fete-protection');

        $cells = $crawler->filter('[data-synthesis-reallocations] td')->each(fn($td) => $this->normalized($td->text()));
        $this->assertSame(['Réaffectations détail plus bas', '-800,00 €', '-800,00 €', '—'], $cells);
        $this->assertSame('+200,00 €', $this->normalized($crawler->filter('[data-header-available]')->text()));
        $this->assertSelectorTextContains('[data-synthesis] tfoot', 'Disponible');
    }

    /**
     * Décision 4 : une dépense n'est jamais bloquée, mais le négatif se voit.
     */
    public function testNegativeAvailableIsClearlyShown(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $expenseLine = $this->scenarioLine($scenario['source'], 'Location salle', true);
        $em->persist($expenseLine);
        $em->persist($this->scenarioTransaction(500.0, $expenseLine));
        $em->flush();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', '/budget-realise/asso-protection-recettes/fete-protection');

        $real = $crawler->filter('[data-available="real"]');
        $this->assertSame('-300,00 €', trim($real->text()));
        $this->assertStringContainsString('text-danger', $real->attr('class'));
        $this->assertSelectorExists('[data-negative-available]');
    }

    /**
     * La vue prévisionnelle : 1 000 € de dons prévus, 800 € donnés.
     */
    public function testBudgetPageShowsPlannedAvailableWithGivenReallocation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', '/budget/asso-protection-recettes/fete-protection');

        $this->assertResponseIsSuccessful();
        $this->assertSame('+ 200,00 €', $this->normalized($crawler->filter('[data-planned-available]')->text()));
        $this->assertSame('dont -800,00 € de réaffectations', $this->normalized($crawler->filter('[data-planned-reallocations]')->text()));
        // Ni recette ni dépense : le total des recettes prévues est inchangé.
        $this->assertSelectorTextContains('.card.border-success', '1 000,00');
    }

    public function testBudgetPageShowsPlannedAvailableWithReceivedReallocation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', '/budget/asso-protection-recettes/chauffage-protection');

        $this->assertSame('+ 800,00 €', $this->normalized($crawler->filter('[data-planned-available]')->text()));
        $this->assertSame('dont +800,00 € de réaffectations', $this->normalized($crawler->filter('[data-planned-reallocations]')->text()));
    }

    public function testBudgetPageWithoutReallocationKeepsItsBalance(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $scenario['reallocation']->setDeletedAt(new \DateTimeImmutable());
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', '/budget/asso-protection-recettes/fete-protection');

        $this->assertSame('+ 1 000,00 €', $this->normalized($crawler->filter('[data-planned-available]')->text()));
        $this->assertSelectorNotExists('[data-planned-reallocations]');
    }

    private function normalized(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function trashBudget(KernelBrowser $client, User $user, Budget $budget): void
    {
        $token = $this->loginWithCsrfToken($client, $user, 'delete' . $budget->getId());

        $client->request('POST', sprintf(
            '/budget/%s/%s/delete',
            $budget->getOrganization()->getSlug(),
            $budget->getSlug(),
        ), ['_token' => $token]);
    }

    private function makeOrganization(string $name, string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);
        $organization->setIsActive(true);

        return $organization;
    }

    private function makeBudget(Organization $organization, string $slug): Budget
    {
        $budget = new Budget();
        $budget->setName('Budget');
        $budget->setSlug($slug);
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));

        return $budget;
    }

    private function makeUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname('Test');

        return $user;
    }

    /**
     * Décision produit : créer un budget est un geste de trésorier, pas
     * d'administrateur. C'est additif et réversible — rien n'est retiré à
     * personne — et c'est le travail courant du trésorier en début d'exercice.
     */
    public function testATreasurerCanOpenTheBudgetCreationForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-tresorier-cree-budget');
        $treasurer = $this->makeUser('tresorier-cree-budget@example.com');
        $organization->addUser($treasurer, OrganizationRole::TREASURER);

        $em->persist($organization);
        $em->persist($treasurer);
        $em->flush();

        $client->loginUser($treasurer);
        $client->request('GET', '/budget/new/' . $organization->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testAReaderCannotOpenTheBudgetCreationForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-lecteur-cree-budget');
        $reader = $this->makeUser('lecteur-cree-budget@example.com');
        $organization->addUser($reader, OrganizationRole::READER);

        $em->persist($organization);
        $em->persist($reader);
        $em->flush();

        $client->loginUser($reader);
        $client->request('GET', '/budget/new/' . $organization->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testOutsiderCannotViewTheRealizedBudgetOfAnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org proprietaire', 'org-realise-proprietaire');
        $budget = $this->makeBudget($organization, 'budget-realise-test');
        $outsider = $this->makeUser('outsider-realise@example.com');

        $em->persist($organization);
        $em->persist($budget);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('GET', sprintf(
            '/budget-realise/%s/%s',
            $organization->getSlug(),
            $budget->getSlug(),
        ));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMemberCannotViewTheRealizedBudgetOfAnotherOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $orgA = $this->makeOrganization('Org A', 'org-a-realise-test');
        $member = $this->makeUser('membre-realise-a@example.com');
        $orgA->addUser($member);

        $orgB = $this->makeOrganization('Org B', 'org-b-realise-test');
        $budgetB = $this->makeBudget($orgB, 'budget-b-realise-test');

        $em->persist($orgA);
        $em->persist($member);
        $em->persist($orgB);
        $em->persist($budgetB);
        $em->flush();

        $client->loginUser($member);

        // Slug de SA PROPRE organisation dans l'URL, mais budget d'orgB.
        $client->request('GET', sprintf(
            '/budget-realise/%s/%s',
            $orgA->getSlug(),
            $budgetB->getSlug(),
        ));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testMemberCanViewTheRealizedBudgetOfTheirOwnOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'mon-orga-realise-test');
        $member = $this->makeUser('membre-realise@example.com');
        $organization->addUser($member);
        $budget = $this->makeBudget($organization, 'mon-budget-realise-test');

        $em->persist($organization);
        $em->persist($member);
        $em->persist($budget);
        $em->flush();

        $client->loginUser($member);

        $client->request('GET', sprintf(
            '/budget-realise/%s/%s',
            $organization->getSlug(),
            $budget->getSlug(),
        ));

        $this->assertResponseIsSuccessful();
    }

    public function testUnknownBudgetSlugReturns404RatherThanCrashing(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Mon orga', 'orga-budget-inconnu-test');
        $member = $this->makeUser('membre-budget-inconnu@example.com');
        $organization->addUser($member);

        $em->persist($organization);
        $em->persist($member);
        $em->flush();

        $client->loginUser($member);

        $client->request('GET', sprintf(
            '/budget-realise/%s/%s',
            $organization->getSlug(),
            'ce-budget-nexiste-pas',
        ));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testOutsiderCannotSoftDeleteABudget(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = $this->makeOrganization('Org cible', 'org-delete-budget-test');
        $budget = $this->makeBudget($organization, 'budget-a-supprimer-test');
        $outsider = $this->makeUser('outsider-delete-budget@example.com');

        $em->persist($organization);
        $em->persist($budget);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('POST', sprintf(
            '/budget/%s/%s/delete',
            $organization->getSlug(),
            $budget->getSlug(),
        ));

        $this->assertResponseStatusCodeSame(403);

        // Le noyau redémarre à chaque requête : on relit le budget depuis le
        // conteneur courant pour vérifier son état réel en base.
        $refreshed = static::getContainer()
            ->get(EntityManagerInterface::class)
            ->getRepository(Budget::class)
            ->find($budget->getId());

        $this->assertNull($refreshed->getDeletedAt());
    }
}
