<?php

namespace App\Form;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class InvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email de la personne à inviter',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un email.'),
                    new Email(message: 'Veuillez saisir un email valide.'),
                ],
            ],)
            ->add('role', EnumType::class, [
                'class' => OrganizationRole::class,
                'label' => 'Rôle dans l\'organisation',
                'choice_label' => fn (OrganizationRole $role) => $role->label(),
                'data' => OrganizationRole::READER,
                'help' => 'Lecteur : consultation seule. Trésorier : saisie des écritures et clôture des budgets. Administrateur : gestion de l\'organisation et de ses membres.',
            ]);
    }

    // public function configureOptions(OptionsResolver $resolver): void
    // {
    //     $resolver->setDefaults([
    //         'data_class' => Invitation::class,
    //     ]);
    // }
}
