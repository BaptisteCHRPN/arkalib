<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Entity\Transaction;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Nom de la dépense / recette'
                ]
            ])
            ->add('is_expense', ChoiceType::class, [
                'label' => 'Dépense ou recette',
                'choices' => [
                    'Dépense' => true,
                    'Recette' => false
                ]
            ])
            ->add('descrption', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'montant',

            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BudgetLine::class,
        ]);
    }
}
