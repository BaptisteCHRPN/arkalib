<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Organization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;

class OrganizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom de l\'oraginsation'
            ])
            // ->add('created_at', null, [
            //     'widget' => 'single_text',
            // ])
            // ->add('is_active')
            ->add('description', null, [
                'label' => 'Présentatiopn rapide'
            ])
            // ->add('users', EntityType::class, [
            //     'class' => User::class,
            //     'choice_label' => 'id',
            //     'multiple' => true,
            // ])
            ->add('picture', FileType::class, [
                'label' => 'Logo de l\'organisation',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File([
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/avif'],
                        'mimeTypesMessage' => 'Format non autorisé.',
                    ])
                ],
                'attr' => [
                    'accept' => '.jpg, .png, .avif',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organization::class,
        ]);
    }
}
