<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom',
            ])
            ->add('start_date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début'
            ])
            ->add('end_date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin'
            ])

            // ->add('organization', EntityType::class, [
            //     'class' => Organization::class,
            //     'choice_label' => 'id',
            // ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Budget::class,
        ]);
    }
}
