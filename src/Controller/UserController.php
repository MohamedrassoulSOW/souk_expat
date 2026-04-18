<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserController extends AbstractController
{
    #[Route('/admin/user', name: 'app_user')]
    public function index(UserRepository $userRepository): Response
    {
        // Compter le nombre total d'utilisateurs
        $userCount = $userRepository->count([]);
        // Restreindre l'accès à cette page aux administrateurs uniquement
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        // Récupérer tous les utilisateurs
        $users = $userRepository->findAll();

        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
            'users' => $users,
            'userCount' => $userCount,
        ]);
    }

    #[Route('/admin/user/{id}/editor', name: 'admin_user_editor')]
    public function toggleRole(User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Vérifie si l'utilisateur a déjà le rôle ADMIN
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            // Retirer le rôle ADMIN, ne garder que ROLE_USER
            $user->setRoles(['ROLE_USER']);
            $this->addFlash('success', 'Le rôle ADMIN a été retiré.');
        } else {
            // Ajouter le rôle ADMIN
            $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
            $this->addFlash('success', 'Le rôle ADMIN a été ajouté.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * Ajoute ou retire le rôle stocké ROLE_EDITOR (sans impacter le rôle admin).
     */
    #[Route('/admin/user/{id}/toggle-editor', name: 'admin_user_toggle_editor')]
    public function toggleEditorRole(User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->getUser() === $user) {
            $this->addFlash('danger', 'Modifiez le rôle éditeur depuis un autre compte super-admin, ou gérez-le en base.');

            return $this->redirectToRoute('app_dashboard');
        }

        $noUser = static fn (string $r): bool => $r !== 'ROLE_USER';
        $roles = array_values(array_filter($user->getRoles(), $noUser));
        $hadEditor = \in_array(User::ROLE_EDITOR, $roles, true);
        if ($hadEditor) {
            $next = array_values(
                array_filter(
                    $roles,
                    static fn (string $r) => $r !== User::ROLE_EDITOR
                )
            );
        } else {
            $next = $roles;
            if (!\in_array(User::ROLE_EDITOR, $next, true)) {
                $next[] = User::ROLE_EDITOR;
            }
        }

        $hasAfter = \in_array(User::ROLE_EDITOR, $next, true);
        $user->setRoles($next);
        $entityManager->flush();

        if ($hasAfter && !$hadEditor) {
            $this->addFlash('success', 'Rôle éditeur donné (back-office, hors gestion des comptes).');
        } elseif (!$hasAfter && $hadEditor) {
            $this->addFlash('success', 'Rôle éditeur retiré.');
        } else {
            $this->addFlash('success', 'Rôles mis à jour.');
        }

        return $this->redirectToRoute('app_dashboard');
    }


    #[Route('/admin/user/{id}/delete', name: 'admin_user_delete')]
    public function delete(User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->getUser() === $user) {
            $this->addFlash('danger', 'Impossible de se supprimer soi-même.');
            return $this->redirectToRoute('app_dashboard');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès.');

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/admin/user/{id}/toggle', name: 'admin_user_toggle')]
    public function toggle(User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->getUser() === $user) {
            $this->addFlash('danger', 'Impossible de se bloquer soi-même.');
            return $this->redirectToRoute('app_dashboard');
        }

        $user->setIsBlocked(!$user->isBlocked());
        $em->flush();

        $this->addFlash(
            'success',
            $user->isBlocked() ? 'Utilisateur bloqué' : 'Utilisateur débloqué'
        );

        return $this->redirectToRoute('app_dashboard');
    }

}

