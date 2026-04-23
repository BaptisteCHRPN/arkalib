<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Form\BudgetLineType;
use App\Repository\BudgetLineRepository;
use App\Service\BudgetCalculatorServie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/member/budgetline')]
final class MemberBudgetLineController extends AbstractController
{
    #[Route('/new/{organizationSlug}/{budgetSlug}', name: 'app_budget_line_new', methods: ['GET', 'POST'])]
    public function new(
        string $budgetSlug,
        Request $request,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        // BudgetLine $budgetLine,
    ): Response {
        // Fetch current budget et check organization's owner
        $budget = $entityManager->getRepository(Budget::class)->findOneBy([
            'slug' => $budgetSlug,
            'organization' => $organization
        ]);

        if ($budget->isClosed()) {
            $this->addFlash('warning', 'Ce budget est clôturé. Impossible d\'ajouter une ligne budgétaire.');
            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        if (!$budget) {
            throw $this->createNotFoundException('Budget non trouvé dans cette organisation');
        }

        $budgetLine = new BudgetLine();
        $budgetLine->setBudget($budget);

        $form = $this->createForm(BudgetLineType::class, $budgetLine, [
            'budget' => $budget
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $attachment = $form->get('attachment')->getData();
            $budgetLineName = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $budgetLine->getName()), '-'));
            if ($attachment) {
                $nameFile = $budgetLineName . '-' . date('YmdHis') . '.' . $attachment->getClientOriginalExtension();
                $attachment->move($this->getParameter('budget_file'), $nameFile);

                if ($budgetLine->getAttachment()) {
                    unlink($this->getParameter('budget_file') .  '/' . $budgetLine->getAttachment());
                }

                $budgetLine->setAttachment($nameFile);
            }

            $entityManager->persist($budgetLine);

            $entityManager->flush();

            if ($budgetLine->isExpense() === false) {
                $this->addFlash('success', 'La recette à été créée avec succès !');
            } else {
                $this->addFlash('success', 'La dépense à été créée avec succès !');
            }

            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/budget_line/new.html.twig', [
            'budget' => $budget,
            'organization' => $organization,
            'budget_line' => $budgetLine,
            'form' => $form,
        ]);
    }

    #[Route('/{organizationSlug}/{budgetSlug}/{id}', name: 'app_budget_line_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])]
        Budget $budget,
        BudgetLine $budgetLine,
    ): Response {
        return $this->render('member/budget_line/show.html.twig', [
            'budget' => $budget,
            'budget_line' => $budgetLine,
            'organization' => $organization,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_member_budget_line_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        BudgetLine $budgetLine,
        EntityManagerInterface $entityManager
    ): Response {
        $budget = $budgetLine->getBudget();
        $organization = $budget->getOrganization();

        if ($budget->isClosed()) {
            $this->addFlash('warning', 'Ce budget est clôturé. Impossible de modifier une ligne budgétaire.');
            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(BudgetLineType::class, $budgetLine, [
            'budget' => $budget,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $attachment = $form->get('attachment')->getData();
            $budgetLineName = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $budgetLine->getName()), '-'));
            if ($attachment) {
                $nameFile = $budgetLineName . '-' . date('YmdHis') . '.' . $attachment->getClientOriginalExtension();
                $attachment->move($this->getParameter('budget_file'), $nameFile);

                if ($budgetLine->getAttachment()) {
                    unlink($this->getParameter('budget_file') .  '/' . $budgetLine->getAttachment());
                }

                $budgetLine->setAttachment($nameFile);
            }

            $entityManager->flush();
            if ($budgetLine->isExpense() === false) {
                $this->addFlash('success', 'La recette à été modifiée avec succès !');
            } else {
                $this->addFlash('success', 'La dépense à été modifiée avec succès !');
            }

            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/budget_line/edit.html.twig', [
            'budgetLine' => $budgetLine,
            'budget' => $budget,
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete-attachment', name: 'app_member_budget_line_delete_attachment', methods: ['GET'])]
    public function deleteAttachment(
        Request $request,
        BudgetLine $budgetLine,
        EntityManagerInterface $entityManager
    ): Response {
        if ($budgetLine->getAttachment()) {
            $filePath = $this->getParameter('budget_file') . '/' . $budgetLine->getAttachment();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $budgetLine->setAttachment(null);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_member_budget_line_edit', ['id' => $budgetLine->getId()], Response::HTTP_SEE_OTHER);
    }


    #[Route('/{id}/delete', name: 'app_soft_delete_budget_line', methods: ['POST'])]
    public function softDelete(
        Request $request,
        BudgetLine $budgetLine,
        EntityManagerInterface $entityManager
    ): Response {
        $budget = $budgetLine->getBudget();
        $organization = $budget->getOrganization();

        if ($budget->isClosed()) {
            $this->addFlash('warning', 'Ce budget est clôturé. Impossible de supprimer une ligne budgétaire.');
            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete' . $budgetLine->getId(), $request->getPayload()->getString('_token'))) {
            $budgetLine->setIsActive(false);
            $entityManager->flush();
            if ($budgetLine->isExpense() === false) {
                $this->addFlash('success', 'La recette à été suprimée avec succès !');
            } else {
                $this->addFlash('success', 'La dépense à été suprimée avec succès !');
            }
        }

        return $this->redirectToRoute('app_membre_budget_show', [
            'organizationSlug' => $organization->getSlug(),
            'budgetSlug' => $budget->getSlug(),
        ], Response::HTTP_SEE_OTHER);
    }
}
