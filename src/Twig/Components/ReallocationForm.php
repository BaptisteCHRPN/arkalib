<?php

namespace App\Twig\Components;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Entity\ReallocationLine;
use App\Form\ReallocationType;
use App\Security\Voter\ReallocationVoter;
use App\Service\BudgetBalanceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

/**
 * Saisie d'une réaffectation, avec des lignes qu'on ajoute ou retire sans
 * recharger la page.
 *
 * Sécurité (sur le modèle de CategoryFormComponent) : l'organisation et le
 * budget de retour sont des LiveProp non modifiables, donc signées par
 * Symfony ; la permission est revérifiée dans save() ; les listes du
 * formulaire n'acceptent que des budgets de l'organisation.
 */
#[AsLiveComponent]
final class ReallocationForm extends AbstractController
{
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public Organization $organization;

    /** Budget d'où l'on vient : source proposée, et page de retour. */
    #[LiveProp]
    public Budget $returnBudget;

    public function __construct(
        private BudgetBalanceCalculator $balanceCalculator,
    ) {
    }

    /**
     * Budget qui donne, tel que saisi. Null si aucun n'est choisi, ou si l'id
     * reçu n'est pas un budget de l'organisation : le formulaire le refuse
     * alors, et on n'en affiche surtout pas le disponible.
     */
    public function getSelectedSource(): ?Budget
    {
        return $this->getForm()->getData()->getSourceBudget();
    }

    /**
     * Disponible de la source avant la réaffectation, en centimes.
     */
    public function getSourceAvailable(): ?int
    {
        $source = $this->getSelectedSource();

        return null === $source ? null : $this->balanceCalculator->balanceOf($source)->available();
    }

    /**
     * Total des lignes saisies, en centimes. Un montant négatif ou illisible
     * ne compte pas : il est déjà signalé sur sa ligne.
     */
    public function getTotal(): int
    {
        $total = 0;

        foreach ($this->getForm()->getData()->getLines() as $line) {
            $total += max(0, BudgetBalanceCalculator::toCents($line->getAmount()));
        }

        return $total;
    }

    /**
     * Récapitulatif mis à jour 300 ms après la dernière frappe, et non
     * seulement quand le champ perd le focus (défaut : on(change)).
     */
    private function getDataModelValue(): ?string
    {
        return 'debounce(300)|*';
    }

    protected function instantiateForm(): FormInterface
    {
        $reallocation = (new Reallocation())
            ->setOrganization($this->organization)
            ->setSourceBudget($this->returnBudget)
            ->addLine(new ReallocationLine());

        return $this->createForm(ReallocationType::class, $reallocation, [
            'organization' => $this->organization,
            // Pas de jeton CSRF de formulaire : chaque requête LiveComponent
            // exige un en-tête qu'un site tiers ne peut pas poser (règles de
            // même origine), ce qui protège déjà de la falsification de
            // requête. Symfony UX a retiré ses propres jetons pour cette raison.
            'csrf_protection' => false,
        ]);
    }

    #[LiveAction]
    public function save(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ReallocationVoter::CREATE, $this->organization);

        // Formulaire invalide : exception attrapée par LiveComponent, qui
        // réaffiche le composant avec les erreurs.
        $this->submitForm();

        /** @var Reallocation $reallocation */
        $reallocation = $this->getForm()->getData();
        $entityManager->persist($reallocation);
        $entityManager->flush();

        $this->addFlash('success', sprintf('La réaffectation %s a été enregistrée.', $reallocation->describe()));

        return $this->redirectToRoute('app_membre_budget_show', [
            'organizationSlug' => $this->organization->getSlug(),
            'budgetSlug' => $this->returnBudget->getSlug(),
        ]);
    }
}
