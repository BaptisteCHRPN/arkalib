<?php

namespace App\Controller\Backoffice;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annuaire des transactions, en lecture seule.
 *
 * L'écriture depuis ici était particulièrement risquée : une transaction ne
 * peut regrouper que des lignes d'un même budget, invariant qu'aucune
 * contrainte de modèle ne garantit et que seul le query_builder de
 * TransactionType applique — à partir du budget de l'URL, que le back-office
 * n'a pas. Écrire passe donc par l'interface membre.
 */
#[Route('/admin/transaction')]
final class AdminTransactionController extends AbstractController
{
    #[Route(name: 'app_admin_transaction_index', methods: ['GET'])]
    public function index(Request $request, TransactionRepository $transactionRepository): Response
    {
        $search = $request->query->getString('q');

        return $this->render('admin/transaction/index.html.twig', [
            'transactions' => $transactionRepository->search($search),
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_transaction_show', methods: ['GET'])]
    public function show(Transaction $transaction): Response
    {
        return $this->render('admin/transaction/show.html.twig', [
            'transaction' => $transaction,
        ]);
    }
}
