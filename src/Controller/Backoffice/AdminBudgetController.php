<?php

namespace App\Controller\Backoffice;

use App\Entity\Budget;
use App\Repository\BudgetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annuaire des budgets, en lecture seule.
 *
 * La suppression qui vivait ici était un remove() physique : elle contournait
 * la corbeille et emportait lignes et catégories au passage, alors même que la
 * mise à la corbeille est réversible côté membre. Écrire passe désormais par
 * l'interface membre, où ROLE_ADMIN accède déjà à toutes les organisations.
 */
#[Route('/admin/budget')]
final class AdminBudgetController extends AbstractController
{
    #[Route(name: 'app_admin_budget_index', methods: ['GET'])]
    public function index(BudgetRepository $budgetRepository): Response
    {
        return $this->render('admin/budget/index.html.twig', [
            'budgets' => $budgetRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_budget_show', methods: ['GET'])]
    public function show(Budget $budget): Response
    {
        return $this->render('admin/budget/show.html.twig', [
            'budget' => $budget,
        ]);
    }
}
