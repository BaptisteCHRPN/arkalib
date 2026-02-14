<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Form\BudgetLineType;
use App\Service\BudgetCalculatorServie;
use App\Repository\BudgetLineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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
        BudgetLine $budgetLine,
    ): Response
    {
        // Fetch current budget et check organization's owner
        $budget = $entityManager->getRepository(Budget::class)->findOneBy([
            'slug' => $budgetSlug,
            'organization' => $organization
        ]);

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
            if($attachment) {
                $nameFile = $budgetLineName . '-' . date('YmdHis') . '.' . $attachment->getClientOriginalExtension();
                $attachment->move($this->getParameter('budget_file'), $nameFile);

                if($budgetLine->getAttachment()) {
                    unlink($this->getParameter('budget_file') .  '/' . $budgetLine->getAttachment());
                }
                
                $budgetLine->setAttachment($nameFile);
            }

            $entityManager->persist($budgetLine);

            $entityManager->flush();

            if($budgetLine->isExpense() === false){
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

    #[Route('/{id}', name: 'app_budget_line_show', methods: ['GET'])]
    public function show(BudgetLine $budgetLine): Response
    {
        return $this->render('member/budget_line/show.html.twig', [
            'budget_line' => $budgetLine,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_member_budget_line_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        BudgetLine $budgetLine, 
        EntityManagerInterface $entityManager
        ): Response
    {
        $budget = $budgetLine->getBudget();
        $organization = $budget->getOrganization();

        $form = $this->createForm(BudgetLineType::class, $budgetLine, [
            'budget' => $budget,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $attachment = $form->get('attachment')->getData();
            $budgetLineName = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $budgetLine->getName()), '-'));
            if($attachment) {
                $nameFile = $budgetLineName . '-' . date('YmdHis') . '.' . $attachment->getClientOriginalExtension();
                $attachment->move($this->getParameter('budget_file'), $nameFile);

                if($budgetLine->getAttachment()) {
                    unlink($this->getParameter('budget_file') .  '/' . $budgetLine->getAttachment());
                }
                
                $budgetLine->setAttachment($nameFile);
            }
            
            $entityManager->flush();
            if($budgetLine->isExpense() === false){
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

    #[Route('/{id}/delete', name: 'app_soft_delete_budget_line', methods: ['POST'])]
    public function softDelete(
        Request $request, 
        BudgetLine $budgetLine, 
        EntityManagerInterface $entityManager
        ) : Response 
    {
        $budget = $budgetLine->getBudget();
        $organization = $budget->getOrganization();

        if ($this->isCsrfTokenValid('delete' . $budgetLine->getId(), $request->getPayload()->getString('_token'))) {
            $budgetLine->setIsActive(false);
            $entityManager->flush();
            if($budgetLine->isExpense() === false){
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
