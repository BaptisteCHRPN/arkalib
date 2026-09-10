<?php

namespace App\Controller\Backoffice;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annuaire des catégories, en lecture seule.
 *
 * Écrire passe par l'interface membre, où ROLE_ADMIN accède déjà à toutes les
 * organisations et où s'appliquent la corbeille, la clôture du budget et les
 * contrôles d'appartenance. Un second chemin d'écriture ici les contournerait.
 */
#[Route('/admin/category')]
final class AdminCategoryController extends AbstractController
{
    #[Route(name: 'app_admin_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_category_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        return $this->render('admin/category/show.html.twig', [
            'category' => $category,
        ]);
    }
}
