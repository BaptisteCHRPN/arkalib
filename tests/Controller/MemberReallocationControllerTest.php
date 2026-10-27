<?php

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberReallocationControllerTest extends WebTestCase
{
    use RevenueProtectionScenarioTrait;

    private const NEW_URL = '/budget/asso-protection-recettes/fete-protection/reaffectations/nouvelle';
    private const BUDGET_URL = '/budget/asso-protection-recettes/fete-protection';
    private const LIST_URL = '/organisation/asso-protection-recettes/reaffectations';

    public function testTreasurerOpensTheFormFromTheBudgetPage(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', self::BUDGET_URL);
        $link = $crawler->filter('a[href="' . self::NEW_URL . '"]');
        $this->assertGreaterThan(0, $link->count());

        $client->click($link->first()->link());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form#reallocation-form');
    }

    public function testReaderNeitherSeesTheButtonNorOpensTheForm(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $reader = $this->scenarioUser('lecteur-page@example.com');
        $scenario['organization']->addUser($reader, OrganizationRole::READER);
        static::getContainer()->get(EntityManagerInterface::class)->persist($reader);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->startRequestsAs($client, $reader);

        $client->request('GET', self::BUDGET_URL);
        $this->assertSelectorNotExists('a[href="' . self::NEW_URL . '"]');

        $client->request('GET', self::NEW_URL);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testNoButtonOnAClosedBudget(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $scenario['source']->setIsClosed(true);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $client->request('GET', self::BUDGET_URL);

        $this->assertSelectorNotExists('a[href="' . self::NEW_URL . '"]');
    }

    /**
     * Le budget est cherché par slug ET organisation : le slug d'un budget
     * d'une autre organisation donne une 404.
     */
    public function testBudgetOfAnotherOrganizationGives404(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $other = (new Organization())->setName('Autre')->setSlug('autre-asso-page')->setIsActive(true);
        $em->persist($other);
        $em->persist($this->scenarioBudget($other, 'Étranger', 'budget-etranger-page'));
        $em->flush();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $client->request('GET', '/budget/asso-protection-recettes/budget-etranger-page/reaffectations/nouvelle');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReaderReachesTheListFromTheOrganizationPage(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $this->startRequestsAs($client, $this->addReader($scenario['organization']));

        $crawler = $client->request('GET', '/asso-protection-recettes/budgets');
        $client->click($crawler->filter('a[href="' . self::LIST_URL . '"]')->link());

        $this->assertResponseIsSuccessful();
        $item = $client->getCrawler()->filter('[data-reallocation-item]');
        $this->assertCount(1, $item);
        $this->assertStringContainsString('Répartition don chauffage', $item->text());
        $this->assertStringContainsString('800,00 €', $item->text());
        $this->assertCount(1, $item->filter('[data-status="active"]'));
    }

    /**
     * Le piège de l'étape 3 : une réaffectation annulée peut citer un budget
     * passé ensuite à la corbeille. La liste et le détail l'affichent quand même.
     */
    public function testCancelledReallocationCitingATrashedBudgetIsStillShown(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $scenario['reallocation']->setDeletedAt(new \DateTimeImmutable('2026-10-20 10:00'))->setDeletedBy($scenario['admin']);
        $em->flush();
        $scenario['target']->setDeletedAt(new \DateTimeImmutable());
        $em->flush();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $crawler = $client->request('GET', self::LIST_URL);

        $this->assertResponseIsSuccessful();
        $item = $crawler->filter('[data-reallocation-item]');
        $this->assertCount(1, $item->filter('[data-status="cancelled"]'));
        $this->assertStringContainsString('« Chauffage » corbeille', preg_replace('/\s+/u', ' ', $item->text()));

        $client->request('GET', $this->showUrl($scenario['reallocation']->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-cancelled-notice]', 'annulée le 20/10/2026 à 10:00 par Test');
        $this->assertSelectorTextContains('[data-reallocation-line]', 'corbeille');
    }

    public function testDetailShowsTheDecisionAndItsLines(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $this->startRequestsAs($client, $this->addReader($scenario['organization']));

        $client->request('GET', $this->showUrl($scenario['reallocation']->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-status="active"]');
        $this->assertSelectorTextContains('dl', 'Conseil d\'administration');
        $this->assertSelectorTextContains('[data-reallocation-line]', 'Chauffage');
        $this->assertSelectorTextContains('[data-total]', '800,00 €');
        $this->assertSelectorNotExists('[data-cancelled-notice]');
    }

    /**
     * Une réaffectation est cherchée dans l'organisation de l'URL : celle d'une
     * autre organisation donne un 404, même pour un membre des deux.
     */
    public function testReallocationOfAnotherOrganizationGives404(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $other = (new Organization())->setName('Autre')->setSlug('autre-asso-detail')->setIsActive(true);
        $other->addUser($scenario['treasurer'], OrganizationRole::TREASURER);
        $em->persist($other);
        $em->flush();
        $this->startRequestsAs($client, $scenario['treasurer']);

        $client->request('GET', sprintf('/organisation/autre-asso-detail/reaffectations/%d', $scenario['reallocation']->getId()));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testOutsiderCannotSeeTheListNorTheDetail(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $outsider = $this->scenarioUser('etranger-reaffectations@example.com');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($outsider);
        $em->flush();
        $this->startRequestsAs($client, $outsider);

        $client->request('GET', self::LIST_URL);
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', $this->showUrl($scenario['reallocation']->getId()));
        $this->assertResponseStatusCodeSame(403);
    }

    public function testTreasurerCancelsWithAReason(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $id = $scenario['reallocation']->getId();
        $token = $this->loginWithCsrfToken($client, $scenario['treasurer'], 'cancel-reallocation' . $id);

        $client->request('POST', $this->showUrl($id) . '/annuler', ['_token' => $token, 'cancellation_reason' => 'Décision du CA du 20/10']);

        $this->assertResponseRedirects($this->showUrl($id), 303);
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'a été annulée');
        $this->assertSelectorTextContains('[data-cancellation-reason]', 'Décision du CA du 20/10');
        $this->assertSelectorExists('[data-restore-form]');

        $reallocation = $this->reloadIncludingTrash(Reallocation::class, $id);
        $this->assertTrue($reallocation->isCancelled());
        $this->assertSame('Décision du CA du 20/10', $reallocation->getCancellationReason());
        $this->assertSame('tresorier-protection@example.com', $reallocation->getDeletedBy()->getEmail());
    }

    public function testCancelWithoutReasonIsRefused(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $id = $scenario['reallocation']->getId();
        $token = $this->loginWithCsrfToken($client, $scenario['treasurer'], 'cancel-reallocation' . $id);

        $client->request('POST', $this->showUrl($id) . '/annuler', ['_token' => $token, 'cancellation_reason' => '   ']);

        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Indiquez le motif de l\'annulation.');
        $this->assertFalse($this->reloadIncludingTrash(Reallocation::class, $id)->isCancelled());
    }

    public function testCancelWithAnInvalidTokenChangesNothing(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $id = $scenario['reallocation']->getId();
        $this->loginWithCsrfToken($client, $scenario['treasurer'], 'cancel-reallocation' . $id);

        $client->request('POST', $this->showUrl($id) . '/annuler', ['_token' => 'faux', 'cancellation_reason' => 'Motif']);

        $this->assertFalse($this->reloadIncludingTrash(Reallocation::class, $id)->isCancelled());
    }

    public function testReaderSeesNoActionAndCannotCancel(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $id = $scenario['reallocation']->getId();
        $reader = $this->addReader($scenario['organization']);

        $this->startRequestsAs($client, $reader);
        $client->request('GET', $this->showUrl($id));
        $this->assertSelectorNotExists('[data-cancel-form]');

        $token = $this->loginWithCsrfToken($client, $reader, 'cancel-reallocation' . $id);
        $client->request('POST', $this->showUrl($id) . '/annuler', ['_token' => $token, 'cancellation_reason' => 'Motif']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertFalse($this->reloadIncludingTrash(Reallocation::class, $id)->isCancelled());
    }

    public function testTreasurerRestoresACancelledReallocation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $id = $scenario['reallocation']->getId();
        $this->cancelInDatabase($scenario);
        $token = $this->loginWithCsrfToken($client, $scenario['treasurer'], 'restore-reallocation' . $id);

        $client->request('POST', $this->showUrl($id) . '/restaurer', ['_token' => $token]);

        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'a été restaurée');
        $this->assertSelectorExists('[data-cancel-form]');
        $reallocation = $this->reloadIncludingTrash(Reallocation::class, $id);
        $this->assertFalse($reallocation->isCancelled());
        $this->assertNull($reallocation->getCancellationReason());
    }

    /**
     * Mêmes règles qu'à la saisie : un budget clôturé depuis l'annulation
     * empêche la restauration, avec le message de la règle.
     */
    public function testRestoreRefusedWhenATargetWasClosedSinceCancellation(): void
    {
        $client = static::createClient();
        $scenario = $this->reallocatedIncomeScenario();
        $id = $scenario['reallocation']->getId();
        $this->cancelInDatabase($scenario);
        $scenario['target']->setIsClosed(true);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $token = $this->loginWithCsrfToken($client, $scenario['treasurer'], 'restore-reallocation' . $id);

        $client->request('POST', $this->showUrl($id) . '/restaurer', ['_token' => $token]);

        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Impossible de restaurer cette réaffectation : Le budget « Chauffage » est clôturé');
        $this->assertTrue($this->reloadIncludingTrash(Reallocation::class, $id)->isCancelled());
    }

    private function cancelInDatabase(array $scenario): void
    {
        $scenario['reallocation']
            ->setDeletedAt(new \DateTimeImmutable())
            ->setDeletedBy($scenario['admin'])
            ->setCancellationReason('Erreur de saisie');
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    private function showUrl(int $id): string
    {
        return sprintf('/organisation/asso-protection-recettes/reaffectations/%d', $id);
    }

    private function addReader(Organization $organization): User
    {
        $reader = $this->scenarioUser('lecteur-liste@example.com');
        $organization->addUser($reader, OrganizationRole::READER);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($reader);
        $em->flush();

        return $reader;
    }
}
