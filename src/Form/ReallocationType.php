<?php

namespace App\Form;

use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Enum\ReallocationInstance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * Saisie d'une réaffectation. L'organisation n'est pas un champ : elle est
 * fixée par l'appelant et limite les budgets proposés.
 */
class ReallocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $organization = $options['organization'];

        $builder
            ->add('decidedAt', DateType::class, [
                'label' => 'Date de la décision <span class="text-danger">*</span>',
                'label_html' => true,
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'dd/MM/yyyy',
                'attr' => [
                    'data-controller' => 'date-paste',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('instance', EnumType::class, [
                'label' => 'Instance <span class="text-danger">*</span>',
                'label_html' => true,
                'class' => ReallocationInstance::class,
                'choice_label' => fn(ReallocationInstance $instance) => $instance->label(),
                'placeholder' => '-- Choisir l\'instance --',
            ])
            ->add('pvReference', null, [
                'label' => 'Référence du procès-verbal',
                'required' => false,
            ])
            ->add('reason', null, [
                'label' => 'Motif <span class="text-danger">*</span>',
                'label_html' => true,
            ])
            ->add('sourceBudget', OrganizationBudgetType::class, [
                'organization' => $organization,
                'label' => 'Budget qui donne <span class="text-danger">*</span>',
                'label_html' => true,
                'placeholder' => '-- Choisir un budget --',
            ])
            ->add('lines', LiveCollectionType::class, [
                'label' => 'Budgets qui reçoivent',
                'entry_type' => ReallocationLineType::class,
                'entry_options' => [
                    'organization' => $organization,
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                // addLine() / removeLine() : la ligne connaît sa réaffectation.
                'by_reference' => false,
                // Une collection fait remonter ses erreurs au formulaire par
                // défaut : celle du disponible doit rester près des lignes.
                'error_bubbling' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reallocation::class,
        ]);
        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', Organization::class);
    }
}
