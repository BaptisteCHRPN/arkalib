<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Transaction;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $budget = $options['budget'];

        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'dd/MM/yyyy',
                'label' => 'Date de la transaction <span class="text-danger">*</span>',
                'label_html' => true,
                'attr' => [
                    'data-controller' => 'date-paste',
                    'autocomplete' => 'off',
                ],
            ])

            ->add('amount', null, [
                'label' => 'Montant <span class="text-danger">*</span>',
                'label_html' => true,
                'attr' => [
                    'data-controller' => 'money-input',
                ],
            ])
            ->add('payment_method', null, [
                'label' => 'Moyen de paiement <span class="text-danger">*</span>',
                'label_html' => true,
            ])
            ->add('comment', null, [
                'label' => 'Information complémentaire',
            ])
            ->add('reference', null, [
                'label' => 'Référence de la transaction',
            ])
            ->add('budget_line', EntityType::class, [
                'label' => 'Recettes ou dépenses correspondantes <span class="text-danger">*</span>',
                'label_html' => true,
                'class' => BudgetLine::class,
                'query_builder' => function (EntityRepository $er) use ($budget) {
                    return $er->createQueryBuilder('bl')
                        ->where('bl.budget = :budget')
                        ->setParameter('budget', $budget)
                        ->orderBy('bl.name', 'ASC');
                },
                'choice_label' => function (BudgetLine $budgetLine) {
                    return $budgetLine->getName() . ' - ' . number_format($budgetLine->getAmount(), 2, ',', ' ') . ' €';
                },
                'multiple' => true,
                'required' => false,
                'constraints' => [
                    new Assert\Count(min: 1, minMessage: 'Sélectionnez au moins une recette ou dépense.'),
                ],

                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('attachment', FileType::class, [
                'label' => 'Ajouter une pièce-jointe',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File(
                        mimeTypes: ['application/pdf', 'image/jpeg', 'image/png'],
                        mimeTypesMessage: 'Format non autorisé.',
                    )
                ],
                'attr' => [
                    'accept' => '.pdf, .jpg, .png',
                ]
            ])
        ;

        // Champ affiché seulement quand la protection des recettes bloque la
        // modification : un admin y indique pourquoi il passe outre.
        if ($options['allow_force']) {
            $builder->add('force_reason', TextareaType::class, [
                'label' => 'Motif pour passer outre <span class="text-danger">*</span>',
                'label_html' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => 255,
                    'rows' => 3,
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
            'budget' => null,
            'allow_force' => false,
        ]);
        $resolver->setAllowedTypes('budget', ['null', Budget::class]);
        $resolver->setAllowedTypes('allow_force', 'bool');
    }
}
