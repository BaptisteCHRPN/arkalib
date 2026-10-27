<?php

namespace App\Form;

use App\Entity\Organization;
use App\Entity\ReallocationLine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Une ligne de réaffectation : le budget qui reçoit et son montant.
 */
class ReallocationLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('targetBudget', OrganizationBudgetType::class, [
                'organization' => $options['organization'],
                'label' => 'Budget qui reçoit <span class="text-danger">*</span>',
                'label_html' => true,
                'placeholder' => '-- Choisir un budget --',
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant <span class="text-danger">*</span>',
                'label_html' => true,
                'currency' => 'EUR',
                // Chaîne et non float : la colonne est un decimal exact.
                'input' => 'string',
                'attr' => [
                    'data-controller' => 'money-input',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReallocationLine::class,
        ]);
        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', Organization::class);
    }
}
