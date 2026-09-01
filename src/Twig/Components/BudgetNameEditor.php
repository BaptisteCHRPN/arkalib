<?php

namespace App\Twig\Components;

use App\Entity\Budget;
use App\Repository\BudgetRepository;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class BudgetNameEditor
{
    use DefaultActionTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BudgetRepository $budgetRepository,
        private Security $security,
    ) {
    }

    #[LiveProp]
    public Budget $budget;

    #[LiveProp]
    public bool $isEditing = false;

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public ?string $startDate = null;

    #[LiveProp(writable: true)]
    public ?string $endDate = null;

    public ?string $error = null;

    #[LiveAction]
    public function edit(): void
    {
        $this->name = $this->budget->getName();
        $this->startDate = $this->budget->getStartDate()->format('Y-m-d');
        $this->endDate = $this->budget->getEndDate()->format('Y-m-d');
        $this->isEditing = true;
    }

    #[LiveAction]
    public function cancel(): void
    {
        $this->isEditing = false;
    }

    #[LiveAction]
    public function save(): void
    {
        if (!$this->security->isGranted(OrganizationVoter::EDIT, $this->budget->getOrganization())) {
            $this->error = 'Vous n\'êtes pas autorisé à modifier ce budget.';
            return;
        }

        $name = trim($this->name);

        if ($name === '') {
            $this->error = 'Le nom du budget ne peut pas être vide.';
            return;
        }

        $startDate = new \DateTime($this->startDate);
        $endDate = new \DateTime($this->endDate);

        if ($endDate < $startDate) {
            $this->error = 'La date de fin doit être postérieure à la date de début.';
            return;
        }

        $existing = $this->budgetRepository->findOneBy([
            'organization' => $this->budget->getOrganization(),
            'name' => $name,
        ]);

        if ($existing && $existing->getId() !== $this->budget->getId()) {
            $this->error = 'Un budget avec ce nom existe déjà dans cette organisation.';
            return;
        }

        $this->budget->setName($name);
        $this->budget->setStartDate($startDate);
        $this->budget->setEndDate($endDate);

        $this->entityManager->flush();

        $this->isEditing = false;
    }
}
