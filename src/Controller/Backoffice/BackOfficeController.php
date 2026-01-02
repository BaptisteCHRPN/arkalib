<?php

namespace App\Controller\Backoffice;

use App\Repository\BudgetLineRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Config\Security\ProviderConfig\Memory\UserConfig;

final class BackOfficeController extends AbstractController
{
    #[Route('/admin/backoffice', name: 'app_back_office')]
    public function index(OrganizationRepository $organization, BudgetLineRepository $budget, UserRepository $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/back_office/index.html.twig', [
            'organizations' => $organization,
            'budgets' => $budget,
            'users' => $user,
        ]);
    }
}
