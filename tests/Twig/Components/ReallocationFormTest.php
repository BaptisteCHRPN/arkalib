<?php

namespace App\Tests\Twig\Components;

use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Enum\ReallocationInstance;
use App\Tests\Controller\RevenueProtectionScenarioTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Le LiveComponent piloté comme le ferait le navigateur. Scénario commun : la
 * Fête a 1 000 € de dons, dont 800 déjà réaffectés au Chauffage, soit 200 €
 * de disponible.
 */
final class ReallocationFormTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    use RevenueProtectionScenarioTrait;

    public function testTreasurerSavesAReallocation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $component = $this->component($scenario, $scenario['treasurer'])
            ->submitForm($this->formValues($scenario, '150'), 'save');

        $this->assertTrue($component->response()->isRedirect('/budget/asso-protection-recettes/fete-protection'));

        $saved = $this->newReallocations();
        $this->assertCount(1, $saved);
        $this->assertSame('Achat de bûches', $saved[0]->getReason());
        $this->assertSame('asso-protection-recettes', $saved[0]->getOrganization()->getSlug());
        $this->assertSame('fete-protection', $saved[0]->getSourceBudget()->getSlug());
        $this->assertSame(15000, (int) round((float) $saved[0]->getLines()->first()->getAmount() * 100));

        $client->request('GET', '/budget/asso-protection-recettes/fete-protection');
        $this->assertSelectorTextContains('.alert-success', 'La réaffectation du 20/10/2026 (CA) – Achat de bûches a été enregistrée.');
    }

    public function testInsufficientAvailableIsShownAndNothingIsSaved(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $component = $this->component($scenario, $scenario['treasurer'])
            ->submitForm($this->formValues($scenario, '300'), 'save');

        $this->assertSame(422, $component->response()->getStatusCode());
        $this->assertStringContainsString(
            'Le budget « Fête » dispose de 200,00 € : impossible d\'en réaffecter 300,00 €.',
            $component->render()->crawler()->filter('form')->text(),
        );
        $this->assertCount(0, $this->newReallocations());
    }

    public function testReaderCannotSave(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $reader = $this->memberWithRole($scenario['organization'], OrganizationRole::READER);

        $component = $this->component($scenario, $reader)
            ->submitForm($this->formValues($scenario, '150'), 'save');

        $this->assertSame(403, $component->response()->getStatusCode());
        $this->assertCount(0, $this->newReallocations());
    }

    /**
     * Point de sécurité : un id de budget d'une autre organisation, glissé
     * dans la requête, est refusé.
     */
    public function testForeignBudgetCannotBeInjected(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $foreign = (new Organization())->setName('Autre')->setSlug('autre-asso-live')->setIsActive(true);
        $foreignBudget = $this->scenarioBudget($foreign, 'Étranger', 'etranger-live');
        $em->persist($foreign);
        $em->persist($foreignBudget);
        $em->flush();

        $values = $this->formValues($scenario, '150');
        $values['reallocation']['lines'][0]['targetBudget'] = (string) $foreignBudget->getId();

        $component = $this->component($scenario, $scenario['treasurer'])->submitForm($values, 'save');

        $this->assertSame(422, $component->response()->getStatusCode());
        $this->assertCount(0, $this->newReallocations());
    }

    public function testLinesCanBeAddedAndRemoved(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $component = $this->component($scenario, $scenario['treasurer']);

        $this->assertCount(1, $component->render()->crawler()->filter('[data-reallocation-line]'));

        $component->call('addCollectionItem', ['name' => 'reallocation[lines]']);
        $this->assertCount(2, $component->render()->crawler()->filter('[data-reallocation-line]'));

        $component->call('removeCollectionItem', ['name' => 'reallocation[lines]', 'index' => 0]);
        $this->assertCount(1, $component->render()->crawler()->filter('[data-reallocation-line]'));
    }

    /**
     * Non-régression : avec AutoNumeric, les montants saisis disparaissaient
     * quand le composant se réaffichait, et le récapitulatif n'était mis à
     * jour qu'en quittant le champ. Le comportement du navigateur se vérifie
     * à la main ; on vérifie ici ce que le serveur rend pour l'obtenir.
     */
    public function testAmountsAreSentWhileTypingWithoutAutoNumeric(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $crawler = $this->component($scenario, $scenario['treasurer'])->render()->crawler();

        $this->assertSame('debounce(300)|*', $crawler->filter('form')->attr('data-model'));

        $amount = $crawler->filter('input[name="reallocation[lines][0][amount]"]');
        $this->assertSame('money-input', $amount->attr('data-controller'));
        $this->assertSame('false', $amount->attr('data-money-input-format-value'));
    }

    public function testTheBudgetWeCameFromIsTheProposedSource(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $selected = $this->component($scenario, $scenario['treasurer'])->render()->crawler()
            ->filter('select[name="reallocation[sourceBudget]"] option[selected]');

        $this->assertSame('Fête', $selected->text());
    }

    public function testSummaryShowsTheAvailableOfTheSource(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $summary = $this->summary($this->component($scenario, $scenario['treasurer']));

        $this->assertSame('200,00 €', $summary['available']);
        $this->assertSame('0,00 €', $summary['total']);
        $this->assertSame('200,00 €', $summary['remaining']);
    }

    public function testSummaryFollowsTheAmountsTyped(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        // Sans action : simple nouvel affichage, comme quand un champ change.
        $component = $this->component($scenario, $scenario['treasurer'])
            ->submitForm($this->formValues($scenario, '150'));
        $summary = $this->summary($component);

        $this->assertSame('150,00 €', $summary['total']);
        $this->assertSame('50,00 €', $summary['remaining']);
        $this->assertStringContainsString('text-success', $component->render()->crawler()->filter('[data-summary="remaining"]')->attr('class'));
    }

    public function testSummaryWarnsWhenTheTotalExceedsTheAvailable(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();

        $component = $this->component($scenario, $scenario['treasurer'])
            ->submitForm($this->formValues($scenario, '300'));
        $crawler = $component->render()->crawler();

        $this->assertSame('-100,00 €', $this->summary($component)['remaining']);
        $this->assertStringContainsString('text-danger', $crawler->filter('[data-summary="remaining"]')->attr('class'));
        $this->assertStringContainsString('Le total dépasse le disponible', $crawler->filter('[data-reallocation-summary]')->text());
    }

    /**
     * Le Chauffage a reçu 800 € de la Fête : c'est son disponible.
     */
    public function testSummaryFollowsTheChosenSource(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $values = $this->formValues($scenario, '0');
        $values['reallocation']['sourceBudget'] = (string) $scenario['target']->getId();
        $values['reallocation']['lines'][0]['targetBudget'] = (string) $scenario['source']->getId();

        $component = $this->component($scenario, $scenario['treasurer'])->submitForm($values);

        $this->assertStringContainsString('Disponible de « Chauffage »', $component->render()->crawler()->filter('[data-reallocation-summary]')->text());
        $this->assertSame('800,00 €', $this->summary($component)['available']);
    }

    /**
     * Point de sécurité : le disponible d'un budget étranger ne s'affiche pas.
     * Le choix invalide n'est pas recopié dans la réaffectation, qui garde
     * sa source précédente (la Fête, de notre organisation).
     */
    public function testSummaryRevealsNothingAboutAForeignSource(): void
    {
        static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $foreign = (new Organization())->setName('Autre')->setSlug('autre-asso-recap')->setIsActive(true);
        $foreignBudget = $this->scenarioBudget($foreign, 'Budget secret', 'budget-secret-recap');
        $em->persist($foreign);
        $em->persist($foreignBudget);
        $em->flush();

        $values = $this->formValues($scenario, '150');
        $values['reallocation']['sourceBudget'] = (string) $foreignBudget->getId();
        $crawler = $this->component($scenario, $scenario['treasurer'])->submitForm($values)->render()->crawler();
        $summary = $crawler->filter('[data-reallocation-summary]')->text();

        $this->assertStringNotContainsString('Budget secret', $summary);
        $this->assertStringContainsString('Disponible de « Fête »', $summary);
    }

    /**
     * @return array{available: string, total: string, remaining: string}
     */
    private function summary(TestLiveComponent $component): array
    {
        $crawler = $component->render()->crawler();

        return [
            'available' => $crawler->filter('[data-summary="available"]')->text(),
            'total' => $crawler->filter('[data-summary="total"]')->text(),
            'remaining' => $crawler->filter('[data-summary="remaining"]')->text(),
        ];
    }

    /**
     * Sans startRequestsAs() : le composant est monté dans ce processus avec
     * les objets du scénario, qui doivent rester gérés par Doctrine.
     *
     * On passe le client du test (test.client n'est pas partagé : sans cela,
     * l'outil en créerait un autre, avec une autre session). L'outil laisse
     * remonter les exceptions ; on rétablit leur traitement normal pour
     * vérifier ce que reçoit le navigateur (422 avec le composant réaffiché, 403).
     */
    private function component(array $scenario, User $user): TestLiveComponent
    {
        $client = static::getClient();
        $component = $this->createLiveComponent('ReallocationForm', [
            'organization' => $scenario['organization'],
            'returnBudget' => $scenario['source'],
        ], $client)->actingAs($user);

        $client->catchExceptions(true);

        return $component;
    }

    private function formValues(array $scenario, string $amount): array
    {
        return ['reallocation' => [
            'decidedAt' => '20/10/2026',
            'instance' => ReallocationInstance::BOARD->value,
            'pvReference' => '',
            'reason' => 'Achat de bûches',
            'sourceBudget' => (string) $scenario['source']->getId(),
            'lines' => [
                ['targetBudget' => (string) $scenario['target']->getId(), 'amount' => $amount],
            ],
        ]];
    }

    private function memberWithRole(Organization $organization, OrganizationRole $role): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->scenarioUser('lecteur-live@example.com');
        $organization->addUser($user, $role);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Les réaffectations autres que celle du scénario.
     *
     * @return Reallocation[]
     */
    private function newReallocations(): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(Reallocation::class)->findBy(['reason' => 'Achat de bûches']);
    }
}
