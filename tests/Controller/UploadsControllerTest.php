<?php

namespace App\Tests\Controller;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UploadsControllerTest extends WebTestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $directory = dirname(__DIR__, 2) . '/uploads/budget_line_attachments';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $this->filePath = $directory . '/test-attachment.pdf';
        file_put_contents($this->filePath, 'contenu factice');
    }


    protected function tearDown(): void
    {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
        parent::tearDown();
    }

    public function testNonMemberCannotDownloadBudgetLineAttachment(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Org proprietaire');
        $organization->setSlug('org-proprietaire-test');
        $organization->setIsActive(true);

        $budget = new Budget();
        $budget->setName('Budget');
        $budget->setSlug('budget-test-upload');
        $budget->setOrganization($organization);
        $budget->setStartDate(new \DateTime('2026-01-01'));
        $budget->setEndDate(new \DateTime('2026-12-31'));

        $line = new BudgetLine();
        $line->setName('Ligne');
        $line->setAmount(50.0);
        $line->setIsExpense(true);
        $line->setBudget($budget);
        $line->setAttachment('test-attachment.pdf');

        $outsider = new User();
        $outsider->setEmail('outsider-upload@example.com');
        $outsider->setPassword('not-checked-by-loginUser');

        $em->persist($organization);
        $em->persist($budget);
        $em->persist($line);
        $em->persist($outsider);
        $em->flush();

        $client->loginUser($outsider);

        $client->request('GET', '/uploads/budget_file/test-attachment.pdf');

        $this->assertResponseStatusCodeSame(403);
    }
}
