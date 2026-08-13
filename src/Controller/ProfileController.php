<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Form\ChangePasswordType;
use App\Form\AvatarType;


final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // 1. On prépare le petit formulaire d'avatar pour Twig
        $formAvatar = $this->createForm(AvatarType::class);

        // 2. On envoie TOUTES les variables nécessaires au template
        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'formAvatar' => $formAvatar->createView(), // <-- C'est cette ligne qui manque !
        ]);
    }

    #[Route('/profile/avatar/update', name: 'app_profile_avatar_update', methods: ['POST'])]
    public function updateAvatar(
        Request $request, 
        EntityManagerInterface $entityManager, 
        \Symfony\Component\String\Slugger\SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(AvatarType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                // Suppression de l'ancien fichier
                if ($user->getAvatar()) {
                    $oldPath = $this->getParameter('kernel.project_dir').'/public/uploads/avatars/'.$user->getAvatar();
                    if (file_exists($oldPath)) { unlink($oldPath); }
                }

                // Upload du nouveau fichier
                $newFilename = uniqid().'-'.$slugger->slug($user->getFirstName()).'.'.$avatarFile->guessExtension();
                $avatarFile->move(
                    $this->getParameter('kernel.project_dir').'/public/uploads/avatars',
                    $newFilename
                );

                $user->setAvatar($newFilename);
                $entityManager->flush();
                $this->addFlash('success', 'Photo de profil mise à jour !');
            }
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/edit', name: 'app_profile_edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

            /** @var User $user */
            $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Gestion avatar (optionnel)
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                $filename = uniqid().'.'.$avatarFile->guessExtension();

                $avatarFile->move(
                    $this->getParameter('kernel.project_dir').'/public/uploads/avatars',
                    $filename
                );
                $user->setAvatar($filename);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès');

            return $this->redirectToRoute('app_profile');
        }

        // ✅ Passer la variable user au template
        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user, // ← c’est important !
        ]);
    }


    #[Route('/profile/avatar/delete', name: 'app_profile_avatar_delete', methods: ['POST'])]
    public function deleteAvatar(
        Request $request,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('delete_avatar', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Supprimer le fichier physique
        if ($user->getAvatar()) {
            $avatarPath = $this->getParameter('kernel.project_dir')
                . '/public/uploads/avatars/' . $user->getAvatar();

            if (file_exists($avatarPath)) {
                unlink($avatarPath);
            }

            // Supprimer en base
            $user->setAvatar(null);
            $entityManager->flush();
        }

        $this->addFlash('success', 'Photo de profil supprimée');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/mon-profil/password', name: 'app_profile_password')]
    public function changePassword(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordType::class, null, [
            'require_current_password' => $user->hasPassword(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();

            // Compte Google sans mot de passe : on autorise la création d’un mot de passe local
            if (!$user->hasPassword()) {
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $entityManager->flush();
                $this->addFlash('success', 'Mot de passe créé. Vous pouvez aussi vous connecter par e-mail.');

                return $this->redirectToRoute('app_profile_password');
            }

            $oldPassword = $form->get('oldPassword')->getData();

            // Vérification de l'ancien mot de passe
            if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
                $this->addFlash('danger', 'Votre mot de passe actuel est incorrect.');
                // On redirige pour "fixer" le flash en session
                return $this->redirectToRoute('app_profile_password');
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');

            return $this->redirectToRoute('app_profile_password');
        }

        return $this->render('profile/password.html.twig', [
            'passwordForm' => $form->createView(),
            'isGoogleOnly' => !$user->hasPassword() && $user->isGoogleAccount(),
        ]);
    }

}