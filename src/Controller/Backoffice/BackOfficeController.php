<?php

namespace App\Controller\Backoffice;

use App\Entity\Budget;
use App\Repository\BudgetLineRepository;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Config\Security\ProviderConfig\Memory\UserConfig;

final class BackOfficeController extends AbstractController
{
    #[Route('/admin/backoffice', name: 'app_back_office')]
    public function index(
        OrganizationRepository $organizationRepository,
        BudgetRepository $budgetRepository,
        BudgetLineRepository $budgetLineRepository,
        CategoryRepository $categoryRepository,
        TransactionRepository $transactionRepository,
        UserRepository $user
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/back_office/index.html.twig', [
            'organizations' => $organizationRepository->findAll(),
            'budgets' => $budgetRepository->findAll(),
            'budgetLines' => $budgetLineRepository->findAll(),
            'categories' => $categoryRepository->findAll(),
            'transactions' => $transactionRepository->findAll(),
            'users' => $user->findAll(),
        ]);
    }
}
