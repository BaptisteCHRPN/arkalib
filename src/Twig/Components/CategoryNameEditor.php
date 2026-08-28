<?php

namespace App\Twig\Components;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class CategoryNameEditor
{
    use DefaultActionTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private Security $security,
    ) {
    }

    #[LiveProp]
    public Category $category;

    #[LiveProp]
    public bool $isEditing = false;

    #[LiveProp(writable: true)]
    public string $name = '';

    public ?string $error = null;

    #[LiveAction]
    public function edit(): void
    {
        $this->name = $this->category->getName();
        $this->isEditing = true;
        $this->error = null;
    }

    #[LiveAction]
    public function cancel(): void
    {
        $this->isEditing = false;
        $this->error = null;
    }

    #[LiveAction]
    public function save(): void
    {
        $budget = $this->category->getBudget();

        if (!$this->security->isGranted(OrganizationVoter::EDIT, $budget->getOrganization())) {
            $this->error = 'Vous n\'êtes pas autorisé à modifier ce budget.';
            return;
        }

        if ($budget->isClosed()) {
            $this->error = 'Ce budget est clôturé, impossible de renommer une catégorie.';
            return;
        }

        $name = trim($this->name);

        if ($name === '') {
            $this->error = 'Le nom de la catégorie ne peut pas être vide.';
            return;
        }

        $existing = $this->categoryRepository->findOneBy([
            'budget' => $budget,
            'name' => $name,
        ]);

        if ($existing && $existing->getId() !== $this->category->getId()) {
            $this->error = 'Une catégorie avec ce nom existe déjà dans ce budget.';
            return;
        }

        $this->category->setName($name);
        $this->entityManager->flush();

        $this->isEditing = false;
    }
}
