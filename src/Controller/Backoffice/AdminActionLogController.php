<?php

namespace App\Controller\Backoffice;

use App\Repository\AdminActionLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Le journal des interventions, en lecture seule.
 *
 * Aucune action d'écriture ici, et c'est délibéré : un journal que l'on peut
 * modifier depuis l'application qu'il surveille ne prouve plus grand-chose.
 */
#[Route('/admin/journal')]
#[IsGranted('ROLE_ADMIN')]
final class AdminActionLogController extends AbstractController
{
    #[Route(name: 'app_admin_action_log', methods: ['GET'])]
    public function index(AdminActionLogRepository $repository): Response
    {
        return $this->render('admin/action_log/index.html.twig', [
            'logs' => $repository->findRecent(),
        ]);
    }
}
