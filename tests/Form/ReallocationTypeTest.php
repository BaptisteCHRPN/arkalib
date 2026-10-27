<?php

namespace App\Tests\Form;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\Transaction;
use App\Enum\ReallocationInstance;
use App\Form\ReallocationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Le formulaire seul, sans page ni LiveComponent : ce qu'il accepte, ce
 * qu'il refuse, et la réaffectation qu'il produit.
 */
final class ReallocationTypeTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Organization $organization;
    private Budget $source;
    private Budget $heating;
    private Budget $outings;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->organization = $this->organization('asso-form-test');
        $this->source = $this->budget($this->organization, 'Fête');
        $this->heating = $this->budget($this->organization, 'Chauffage');
        $this->outings = $this->budget($this->organization, 'Sorties');

        // 1 000 € de dons : de quoi réaffecter.
        $line = (new BudgetLine())->setName('Dons')->setAmount(1000.0)->setIsExpense(false)->setBudget($this->source);
        $donation = (new Transaction())->setDate(new \DateTime('2026-06-01'))->setAmount(1000.0)->setPaymentMethod('Chèque');
        $donation->addBudgetLine($line);
        $this->em->persist($line);
        $this->em->persist($donation);
        $this->em->flush();
    }

    public function testValidSubmissionProducesTheReallocation(): void
    {
        $form = $this->submit($this->validData());

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));

        /** @var Reallocation $reallocation */
        $reallocation = $form->getData();
        $this->assertSame('12/10/2026', $reallocation->getDecidedAt()->format('d/m/Y'));
        $this->assertSame(ReallocationInstance::BOARD, $reallocation->getInstance());
        $this->assertSame('PV-2026-07', $reallocation->getPvReference());
        $this->assertSame('Répartition don chauffage', $reallocation->getReason());
        $this->assertSame($this->source, $reallocation->getSourceBudget());
        $this->assertCount(2, $reallocation->getLines());

        $line = $reallocation->getLines()->first();
        $this->assertSame($this->heating, $line->getTargetBudget());
        $this->assertSame(80000, (int) round((float) $line->getAmount() * 100));
        // by_reference à false : la ligne est rattachée à sa réaffectation.
        $this->assertSame($reallocation, $line->getReallocation());
    }

    /**
     * Point de sécurité : un id trafiqué vers le budget d'une autre
     * organisation n'est pas un choix possible.
     */
    public function testForeignSourceIsNotAValidChoice(): void
    {
        $foreign = $this->budget($this->organization('asso-etrangere-form'), 'Étranger');
        $this->em->flush();

        $form = $this->submit(['sourceBudget' => (string) $foreign->getId()] + $this->validData());

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('sourceBudget')->getErrors());
        $this->assertNull($form->getData()->getSourceBudget());
    }

    public function testForeignTargetIsNotAValidChoice(): void
    {
        $foreign = $this->budget($this->organization('asso-etrangere-form'), 'Étranger');
        $this->em->flush();

        $data = $this->validData();
        $data['lines'][0]['targetBudget'] = (string) $foreign->getId();
        $form = $this->submit($data);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('lines')->get('0')->get('targetBudget')->getErrors());
    }

    public function testOnlyBudgetsOfTheOrganizationAreProposedAndClosedOnesAreMarked(): void
    {
        $this->budget($this->organization('asso-etrangere-form'), 'Étranger');
        $this->source->setIsClosed(true);
        $this->em->flush();

        $choices = array_map(
            fn($choice) => $choice->label,
            $this->form()->createView()['sourceBudget']->vars['choices'],
        );

        $this->assertSame(['Chauffage', 'Fête (clôturé)', 'Sorties'], array_values($choices));
    }

    /**
     * Les règles de l'étape 3 s'appliquent par le formulaire : ici, le
     * disponible de la source (1 000 €) ne suffit pas.
     */
    public function testBusinessRulesApplyThroughTheForm(): void
    {
        $data = $this->validData();
        $data['lines'][1]['amount'] = '900';

        $form = $this->submit($data);

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('impossible d\'en réaffecter', (string) $form->getErrors(true));
    }

    private function validData(): array
    {
        return [
            'decidedAt' => '12/10/2026',
            'instance' => ReallocationInstance::BOARD->value,
            'pvReference' => 'PV-2026-07',
            'reason' => 'Répartition don chauffage',
            'sourceBudget' => (string) $this->source->getId(),
            'lines' => [
                ['targetBudget' => (string) $this->heating->getId(), 'amount' => '800'],
                ['targetBudget' => (string) $this->outings->getId(), 'amount' => '200'],
            ],
        ];
    }

    private function form(): FormInterface
    {
        $reallocation = (new Reallocation())->setOrganization($this->organization);

        return static::getContainer()->get(FormFactoryInterface::class)->create(ReallocationType::class, $reallocation, [
            'organization' => $this->organization,
            'csrf_protection' => false,
        ]);
    }

    private function submit(array $data): FormInterface
    {
        $form = $this->form();
        $form->submit($data);

        return $form;
    }

    private function organization(string $slug): Organization
    {
        $organization = (new Organization())->setName($slug)->setSlug($slug)->setIsActive(true);
        $this->em->persist($organization);

        return $organization;
    }

    private function budget(Organization $organization, string $name): Budget
    {
        $budget = new Budget();
        $budget->setName($name);
        $budget->setSlug(strtolower($name) . '-' . $organization->getSlug());
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));
        $this->em->persist($budget);

        return $budget;
    }
}
