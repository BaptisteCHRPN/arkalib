<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('public/home/index.html.twig');
    }

    #[Route('/a-porpos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('public/about.html.twig');
    }
}
