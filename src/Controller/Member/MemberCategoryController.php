<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\Organization;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\SoftDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #[Route('/budget/{budget}/category')]
final class MemberCategoryController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/organizations/{organizationSlug}/budgets/{budgetSlug}/categories', name: 'app_category_index', methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
        CategoryRepository $categoryRepository
    ): Response {
        if ($budget->getOrganization()->getId() !== $organization->getId()) {
            throw $this->createAccessDeniedException('Ce budget n\'appartient pas à cette organisation');
        }

        $categories = $categoryRepository->findBy(['budget' => $budget], ['name' => 'ASC']);

        return $this->render('member/category/index.html.twig', [
            'budget' => $budget,
            'organization' => $organization,
            'categories' => $categories,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/organizations/{organizationSlug}/budgets/{budgetSlug}/categories/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if current budget is owned by organization
        if ($budget->getOrganization()->getId() !== $organization->getId()) {
            throw $this->createAccessDeniedException('Ce budget n\'appartient pas à cette organisation');
        }

        if ($budget->isClosed()) {
            $this->addFlash('warning', 'Ce budget est clôturé. Impossible de créer une catégorie.');
            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        $category = new Category();
        $category->setBudget($budget);

        $form = $this->createForm(CategoryType::class, $category, [
            'budget' => $budget
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setBudget($budget);
            $entityManager->persist($category);
            $entityManager->flush();
            $this->addFlash('success', 'La catégorie à été créée avec succès !');

            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug()
            ]);
        }

        return $this->render('/member/category/new.html.twig', [
            'form' => $form,
            'budget' => $budget,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/organizations/{organizationSlug}/budgets/{budgetSlug}/categories/{id}/delete', name: 'app_member_category_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        SoftDeleteService $softDeleteService,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
        Category $category,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->getPayload()->getString('_token'))) {
            $softDeleteService->softDelete($category, $this->getUser());
            $entityManager->flush();
            $this->addFlash('success', 'La catégorie a été déplacée dans la corbeille.');
        }

        return $this->redirectToRoute('app_category_index', [
            'organizationSlug' => $organization->getSlug(),
            'budgetSlug' => $budget->getSlug(),
        ], Response::HTTP_SEE_OTHER);
    }
}
