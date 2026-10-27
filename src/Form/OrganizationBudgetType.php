<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\Organization;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Liste des budgets d'une organisation, et d'elle seule.
 *
 * Sécurité : un id de budget d'une autre organisation, envoyé à la main, ne
 * fait pas partie des choix et est refusé comme « choix invalide »
 * (Reallocation::validateBudgets() le refuserait aussi). Les budgets à la
 * corbeille sont écartés par le filtre soft_delete.
 *
 * Les budgets clôturés restent proposés, marqués « (clôturé) » : les choisir
 * affiche le message qui explique pourquoi et propose de les rouvrir.
 */
class OrganizationBudgetType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Budget::class,
            'query_builder' => fn(Options $options) => fn(EntityRepository $repository) => $repository
                ->createQueryBuilder('b')
                ->where('b.organization = :organization')
                ->setParameter('organization', $options['organization'])
                ->orderBy('b.name', 'ASC'),
            'choice_label' => fn(Budget $budget) => $budget->getName() . ($budget->isClosed() ? ' (clôturé)' : ''),
            'attr' => [
                'class' => 'form-select',
            ],
        ]);
        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', Organization::class);
    }

    public function getParent(): string
    {
        return EntityType::class;
    }
}
