<?php

namespace App\Controller\Backoffice;

use App\Entity\BudgetLine;
use App\Repository\BudgetLineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annuaire des lignes budgétaires, en lecture seule.
 *
 * Écrire passe par l'interface membre, où ROLE_ADMIN accède déjà à toutes les
 * organisations et où s'appliquent la corbeille, la clôture du budget et les
 * contrôles d'appartenance. Un second chemin d'écriture ici les contournerait.
 */
#[Route('/admin/budgetline')]
final class AdminBudgetLineController extends AbstractController
{
    #[Route(name: 'app_admin_budget_line_index', methods: ['GET'])]
    public function index(Request $request, BudgetLineRepository $budgetLineRepository): Response
    {
        $search = $request->query->getString('q');

        return $this->render('admin/budget_line/index.html.twig', [
            'budget_lines' => $budgetLineRepository->search($search),
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_budget_line_show', methods: ['GET'])]
    public function show(BudgetLine $budgetLine): Response
    {
        return $this->render('admin/budget_line/show.html.twig', [
            'budget_line' => $budgetLine,
        ]);
    }
}
