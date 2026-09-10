<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création d'un compte depuis le back-office.
 *
 * Distinct de UserType, qui sert à l'édition : l'adresse e-mail ne se saisit
 * qu'ici. La changer ensuite passe par le circuit de confirmation porté par
 * `pendingEmail`, pas par un champ d'administration.
 *
 * Aucun champ mot de passe : l'exploitant n'a pas à connaître celui de ses
 * clients. Le compte naît avec un secret inutilisable et la personne reçoit un
 * lien pour choisir le sien.
 */
class AdminUserCreationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail <span class="text-danger">*</span>',
                'label_html' => true,
                'help' => 'C\'est à cette adresse que sera envoyé le lien d\'initialisation du mot de passe.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez renseigner une adresse e-mail.'),
                    new Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.'),
                ],
            ])
            ->add('firstname', null, [
                'label' => 'Prénom',
                'required' => false,
            ])
            ->add('lastname', null, [
                'label' => 'Nom',
                'required' => false,
            ])
            ->add('picture', FileType::class, [
                'label' => 'Avatar',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File(
                        mimeTypes: ['image/jpeg', 'image/png', 'image/avif'],
                        mimeTypesMessage: 'Format non autorisé.',
                    ),
                ],
                'attr' => [
                    'accept' => '.jpg, .png, .avif',
                ],
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
