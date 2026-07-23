<?php

namespace App\Twig\Components;

use App\Entity\Budget;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class CategoryFormComponent
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private Security $security,
    ) {
    }

    #[LiveProp]
    public Budget $budget;

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public ?int $parentCategoryId = null;

    public ?string $error = null;

    public function getRootCategories(): array
    {
        return $this->categoryRepository->findRootCategories($this->budget);
    }

    #[LiveAction]
    public function save(): void
    {
        if (!$this->budget->getOrganization()->getUsers()->contains($this->security->getUser())) {
            $this->error = 'Vous n\'êtes pas autorisé à modifier ce budget.';
            return;
        }

        if ($this->budget->isClosed()) {
            $this->error = 'Ce budget est clôturé, impossible de créer une catégorie.';
            return;
        }

        $name = trim($this->name);

        if ($name === '') {
            $this->error = 'Le nom de la catégorie ne peut pas être vide.';
            return;
        }

        $existing = $this->categoryRepository->findOneBy([
            'budget' => $this->budget,
            'name' => $name,
        ]);

        if ($existing) {
            $this->error = 'Une catégorie avec ce nom existe déjà dans ce budget.';
            return;
        }

        $parentCategory = null;
        if ($this->parentCategoryId !== null) {
            $parentCategory = $this->categoryRepository->find($this->parentCategoryId);

            if (!$parentCategory || $parentCategory->getBudget()->getId() !== $this->budget->getId()) {
                $this->error = 'Catégorie parente invalide.';
                return;
            }
        }

        $category = new Category();
        $category->setName($name);
        $category->setBudget($this->budget);
        $category->setParentCategory($parentCategory);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $this->name = '';
        $this->parentCategoryId = null;
        $this->error = null;

        $this->dispatchBrowserEvent('category-created');
    }
}
