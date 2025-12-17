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
        return $this->render('legals/legals.html.twig', [
            'controller_name' => 'LegalsController',
        ]);
    }

    #[Route('/cgu', name: 'app_cgu')]
    public function cgu(): Response
    {
        return $this->render('legals/cgu.html.twig', [
            'controller_name' => 'CguController',
        ]);
    }

    #[Route('/cgv', name: 'app_cgv')]
    public function cgv(): Response
    {
        return $this->render('legals/cgv.html.twig', [
            'controller_name' => 'CgvController',
        ]);
    }
}
