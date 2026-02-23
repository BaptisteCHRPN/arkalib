<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Récupérer le budget passé en option
        $budget = $options['budget'];
        
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr' => [
                    'placeholder' => 'Ex: Salaire, Loyer, Cotisations...',
                    'class' => 'form-control'
                ],
                'required' => true
            ])
            ->add('parentCategory', EntityType::class, [
                'class' => Category::class,
                'label' => 'Catégorie parente (optionnel)',
                'required' => false,
                'placeholder' => '-- Aucune (catégorie principale) --',
                'attr' => [
                    'class' => 'form-select'
                ],
                // Afficher seulement les catégories principales du même budget
                'choices' => $budget->getCategories()->filter(
                    fn(Category $cat) => $cat->getParentCategory() === null
                ),
                'choice_label' => 'name',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
        
        // IMPORTANT : Déclarer l'option "budget" comme requise
        $resolver->setRequired('budget');
        
        // IMPORTANT : Définir le type attendu pour l'option "budget"
        $resolver->setAllowedTypes('budget', 'App\Entity\Budget');
    }
}