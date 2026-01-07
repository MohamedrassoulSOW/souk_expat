<?php

namespace App\Controller;

use App\Form\AvatarType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class AvatarController extends AbstractController
{

    #[Route('/profile/update-avatar', name: 'app_profile_avatar_update', methods: ['POST'])]
    public function updateAvatar(Request $request, SluggerInterface $slugger, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $form = $this->createForm(AvatarType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                // 1. Supprimer l'ancien avatar physiquement
                if ($user->getAvatar()) {
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars/' . $user->getAvatar();
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                // 2. Préparer le nouveau nom
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                // 3. Déplacer le fichier
                try {
                    $avatarFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/avatars',
                        $newFilename
                    );
                    $user->setAvatar($newFilename);
                    $entityManager->flush();
                    
                    $this->addFlash('success', 'Photo de profil mise à jour !');
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload.');
                }
            }
        }

        return $this->redirectToRoute('app_profile');
    }
}