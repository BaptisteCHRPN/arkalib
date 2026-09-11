<?php

namespace App\Controller\Member;

use App\Entity\User;
use App\Form\ProfileCompletionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran imposé à la première connexion, tant que le compte n'a pas de prénom.
 *
 * C'est ProfileCompletionSubscriber qui y conduit ; ce contrôleur se contente
 * de l'afficher et de l'enregistrer. Il reste accessible ensuite : qui y revient
 * volontairement peut corriger son nom, et le subscriber cesse simplement de
 * l'y renvoyer une fois le prénom rempli.
 */
#[IsGranted('ROLE_USER')]
final class ProfileCompletionController extends AbstractController
{
    #[Route('/profil/completer', name: 'app_profile_completion', methods: ['GET', 'POST'])]
    public function complete(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileCompletionType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Bienvenue %s !', $user->getDisplayName()));

            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/user/complete_profile.html.twig', [
            'form' => $form,
        ]);
    }
}
