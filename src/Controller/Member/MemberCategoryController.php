<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/budget/{budget}/category')]
final class MemberCategoryController extends AbstractController
{
     #[Route('/', name: 'app_category_index', methods: ['GET'])]
    public function index(
        Budget $budget,
        CategoryRepository $categoryRepository
    ): Response {
        // Récupérer toutes les catégories de ce budget
        $categories = $categoryRepository->findBy(['budget' => $budget], ['name' => 'ASC']);

        return $this->render('member/category/index.html.twig', [
            'budget' => $budget,
            'categories' => $categories,
        ]);
    }

     #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(
        Budget $budget,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $category = new Category();
        $category->setBudget($budget);

        $form = $this->createForm(CategoryType::class, $category, [
            'budget' => $budget
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Catégorie créée avec succès !');

            return $this->redirectToRoute('app_category_index', ['budget' => $budget->getId()]);
        }

        return $this->render('/member/category/new.html.twig', [
            'form' => $form,
            'budget' => $budget,
        ]);
    }


}