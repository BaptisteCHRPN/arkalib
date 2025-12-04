<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalsController extends AbstractController
{
    #[Route('/legals', name: 'app_legals')]
    public function index(): Response
    {
        return $this->render('legals/index.html.twig', [
            'controller_name' => 'LegalsController',
        ]);
    }
}
