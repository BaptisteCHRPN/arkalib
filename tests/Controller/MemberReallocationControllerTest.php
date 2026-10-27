<?php

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberReallocationControllerTest extends WebTestCase
{
    use RevenueProtectionScenarioTrait;

    private const NEW_URL = '/budget/asso-protection-recettes/fete-protection/reaffectations/nouvelle';
    private const BUDGET_URL = '/budget/asso-protection-recettes/fete-protection';

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
}
