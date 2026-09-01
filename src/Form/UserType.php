<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Organization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Validator\Constraints as Assert;


class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ->add('email', EmailType::class, [
            //     'label' => 'Adresse e-mail <span class="text-danger">*</span>',
            //     'label_html' => true,
            // ])
            // ->add('password')
            ->add('firstname', null, [
                'label' => 'Prénom<span class="text-danger">*</span>',
                'label_html' => true,
            ])
            ->add('lastname', null, [
                'label' => 'Nom<span class="text-danger">*</span>',
                'label_html' => true,
            ])
            ->add('picture', FileType::class, [
                'label' => 'Avatar',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File(
                        mimeTypes: ['image/jpeg', 'image/png', 'image/avif'],
                        mimeTypesMessage: 'Format non autorisé.',
                    )
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
            'data_class' => User::class,
        ]);
    }
}
