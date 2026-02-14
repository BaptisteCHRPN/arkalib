<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use Assert\File;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BudgetLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $budget = $options['budget'];
        
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la ligne',
                'attr' => [
                    'placeholder' => 'Ex: Loyer, subventions...',
                    'class' => 'form-control'
                ],
                'required' => true
            ])
            ->add('is_expense', ChoiceType::class, [
                'label' => 'Dépense ou recette',
                'placeholder' => '-- Type de ligne budgétaire --',
                'required' => false,
                'choices' => [
                    'Dépense' => true,
                    'Recette' => false
                ]
            ])
            ->add('descrption', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description optionnelle...',
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'EUR',
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control'
                ],
                'required' => true
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'label' => 'Catégorie',
                'placeholder' => '-- Sélectionner une catégorie --',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ],
                'choices' => $budget->getCategories(),
                'choice_label' => function(?Category $category) {
                    if (!$category) {
                        return '';
                    }
                    
                    if ($category->getParentCategory()) {
                        return $category->getParentCategory()->getName() . ' > ' . $category->getName();
                    }
                    
                    return $category->getName();
                },
                'group_by' => function(?Category $category) {
                    if (!$category) {
                        return null;
                    }
                    
                    if ($category->getParentCategory()) {
                        return $category->getParentCategory()->getName();
                    }
                    
                    return 'Catégories principales';
                },
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
            'data_class' => BudgetLine::class,
        ]);
        
        $resolver->setRequired('budget');
        
        $resolver->setAllowedTypes('budget', Budget::class);
    }
}