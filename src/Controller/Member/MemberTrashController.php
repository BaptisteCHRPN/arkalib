<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Repository\BudgetLineRepository;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use App\Service\SoftDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MemberTrashController extends AbstractController
{
    #[Route('/organisation/{slug}/corbeille', name: 'app_member_trash_index', methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['slug' => 'slug'])] Organization $organization,
        Request $request,
        BudgetRepository $budgetRepository,
        BudgetLineRepository $budgetLineRepository,
        CategoryRepository $categoryRepository,
        TransactionRepository $transactionRepository,
    ): Response {
        $type = $request->query->getString('type', 'budgets');

        $items = match ($type) {
            'depenses'     => $budgetLineRepository->findDeletedByOrganization($organization, true),
            'recettes'     => $budgetLineRepository->findDeletedByOrganization($organization, false),
            'categories'   => $categoryRepository->findDeletedByOrganization($organization),
            'transactions' => $transactionRepository->findDeletedByOrganization($organization),
            default        => $budgetRepository->findDeletedByOrganization($organization),
        };

        return $this->render('member/trash/index.html.twig', [
            'organization' => $organization,
            'type'         => $type,
            'items'        => $items,
        ]);
    }

    private function findEntityInTrash(EntityManagerInterface $em, string $type, int $id): ?object
    {
        $class = match ($type) {
            'depenses', 'recettes' => BudgetLine::class,
            'categories'           => Category::class,
            'transactions'         => Transaction::class,
            default                => Budget::class,
        };

        $em->getFilters()->disable('soft_delete');
        $entity = $em->find($class, $id);
        $em->getFilters()->enable('soft_delete');

        return $entity;
    }

    #[Route('/organisation/{slug}/corbeille/{type}/{id}/restore', name: 'app_member_trash_restore', methods: ['POST'])]
    public function restore(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Organization $organization,
        string $type,
        int $id,
        EntityManagerInterface $entityManager,
        SoftDeleteService $softDeleteService,
    ): Response {
        if ($this->isCsrfTokenValid('restore' . $id, $request->getPayload()->getString('_token'))) {
            $entity = $this->findEntityInTrash($entityManager, $type, $id);

            if ($entity) {
                $softDeleteService->restore($entity);
                $entityManager->flush();
                $this->addFlash('success', 'L\'élément a été restauré.');
            }
        }

        return $this->redirectToRoute('app_member_trash_index', [
            'slug' => $organization->getSlug(),
            'type' => $type,
        ]);
    }

    #[Route('/organisation/{slug}/corbeille/{type}/{id}/delete', name: 'app_member_trash_hard_delete', methods: ['POST'])]
    public function hardDelete(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Organization $organization,
        string $type,
        int $id,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('hard-delete' . $id, $request->getPayload()->getString('_token'))) {
            $entity = $this->findEntityInTrash($entityManager, $type, $id);

            if ($entity) {
                $entityManager->remove($entity);
                $entityManager->flush();
                $this->addFlash('success', 'L\'élément a été supprimé définitivement.');
            }
        }

        return $this->redirectToRoute('app_member_trash_index', [
            'slug' => $organization->getSlug(),
            'type' => $type,
        ]);
    }
}
