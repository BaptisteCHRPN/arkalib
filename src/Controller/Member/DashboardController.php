<?php

namespace App\Controller\Member;

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
        return $this->render('member/dashboard/index.html.twig', [
            'organizations' => $organizationRepository->findOrganizationsByUser($user)
        ]);
    }
}
