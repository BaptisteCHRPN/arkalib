<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Complétion du profil, imposée à la première connexion.
 *
 * Les contraintes sont portées par le formulaire et non par l'entité : une
 * contrainte sur User::$firstname rendrait invalides tous les comptes créés
 * avant cette règle, et le back-office ne pourrait plus enregistrer la moindre
 * modification sur eux sans inventer un prénom que l'exploitant ne connaît pas.
 * Ici, l'exigence ne pèse que sur la personne concernée, au seul moment où elle
 * est en mesure d'y répondre.
 */
class ProfileCompletionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', null, [
                'label' => 'Prénom ou pseudo <span class="text-danger">*</span>',
                'label_html' => true,
                'help' => 'C\'est sous ce nom que les autres membres vous verront. Un pseudo convient très bien.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez renseigner un prénom ou un pseudo.'),
                    new Assert\Length(max: 255, maxMessage: 'Ce nom ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('lastname', null, [
                'label' => 'Nom',
                'required' => false,
                'help' => 'Facultatif, vous pourrez l\'ajouter plus tard.',
                'constraints' => [
                    new Assert\Length(max: 255, maxMessage: 'Ce nom ne peut pas dépasser {{ limit }} caractères.'),
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
