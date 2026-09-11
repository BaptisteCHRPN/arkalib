<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Édition d'un compte depuis le back-office.
 *
 * Ni e-mail ni mot de passe ici, volontairement : changer l'adresse passe par
 * le circuit de confirmation porté par `pendingEmail`, et le mot de passe par
 * un lien envoyé à la personne. La création utilise AdminUserCreationType.
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', null, [
                'label' => 'Prénom<span class="text-danger">*</span>',
                'label_html' => true,
            ])
            ->add('lastname', null, [
                'label' => 'Nom',
                'required' => false,
                'help' => 'Facultatif.',
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
