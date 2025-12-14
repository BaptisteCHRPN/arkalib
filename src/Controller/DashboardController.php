<?php

namespace App\Controller;

use App\Repository\OrganizationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(OrganizationRepository $organizationRepository,): Response
    {
        $user = $this->getUser();
        return $this->render('dashboard/index.html.twig', [
            'organizations' => $organizationRepository->findOrganizationsByUser($user)
        ]);
    }
}
