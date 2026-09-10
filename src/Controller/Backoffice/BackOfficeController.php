<?php

namespace App\Controller\Backoffice;

use App\Service\BackOfficeDashboard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BackOfficeController extends AbstractController
{
    #[Route('/admin/backoffice', name: 'app_back_office')]
    public function index(BackOfficeDashboard $dashboard): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/back_office/index.html.twig', [
            'counts' => $dashboard->counts(),
            'organizationsWithoutAdmin' => $dashboard->organizationsWithoutAdmin(),
            'staleInvitations' => $dashboard->countStaleInvitations(),
        ]);
    }
}
