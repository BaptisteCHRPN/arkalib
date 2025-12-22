<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Entity\Transaction;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('is_expense')
            ->add('descrption')
            ->add('amount')
            ->add('created_at', null, [
                'widget' => 'single_text',
            ])
            ->add('is_active')
            ->add('budget', EntityType::class, [
                'class' => Budget::class,
                'choice_label' => 'id',
            ])
            ->add('transactions', EntityType::class, [
                'class' => Transaction::class,
                'choice_label' => 'id',
                'multiple' => true,
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
