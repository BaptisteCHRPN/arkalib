<?php

namespace App\Form;

use App\Entity\BudgetLine;
use App\Entity\Transaction;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date')
            ->add('amount')
            ->add('payment_method')
            ->add('created_at', null, [
                'widget' => 'single_text',
            ])
            ->add('comment')
            ->add('is_active')
            ->add('reference')
            ->add('budget_line', EntityType::class, [
                'class' => BudgetLine::class,
                'choice_label' => 'id',
                'multiple' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
        ]);
    }
}
