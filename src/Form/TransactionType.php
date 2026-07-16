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
                'label' => 'Date de la transaction',
                'attr' => [
                    'data-controller' => 'date-paste',
                    'autocomplete' => 'off',
                ],
            ])

            ->add('amount', null, [
                'label' => 'Montant',
            ])
            ->add('payment_method', null, [
                'label' => 'Moyen de paiement',
            ])
            ->add('comment', null, [
                'label' => 'Information complémentaire',
            ])
            ->add('reference', null, [
                'label' => 'Référence de la transaction',
            ])
            ->add('budget_line', EntityType::class, [
                'label' => 'Recettes ou dépenses correspondantes',
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
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('attachment', FileType::class, [
                'label' => 'Ajouter une pièce-jointe',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File([
                        'mimeTypes' => ['application/pdf', 'image/jpeg', 'image/png'],
                        'mimeTypesMessage' => 'Format non autorisé.',
                    ])
                ],
                'attr' => [
                    'accept' => '.pdf, .jpg, .png',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
            'budget' => null,
        ]);
        $resolver->setAllowedTypes('budget', ['null', Budget::class]);
    }
}
