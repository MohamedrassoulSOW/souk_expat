<?php

namespace App\Controller\Admin;

use App\Entity\Annonce;
use App\Repository\AnnonceRepository;
use App\Service\AnnonceDeletionService;
use App\Service\NotificationService;
use App\Service\PlatformMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/annonces')]
class AdminAnnonceController extends AbstractController
{
    #[Route('/pending', name: 'admin_annonces_pending')]
    public function pending(AnnonceRepository $annonceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $annonces = $annonceRepository->findBy(
            ['status' => Annonce::STATUS_PENDING],
            ['createdAt' => 'DESC']
        );

        return $this->render('admin/annonce/pending.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/approved', name: 'admin_annonces_approved')]
    public function approved(AnnonceRepository $annonceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        // Plus anciennes d’abord (pratique pour nettoyer le catalogue)
        $annonces = $annonceRepository->findBy(
            ['status' => Annonce::STATUS_APPROVED],
            ['approvedAt' => 'ASC', 'createdAt' => 'ASC']
        );

        return $this->render('admin/annonce/approved.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/rejected', name: 'admin_annonces_rejected')]
    public function rejected(AnnonceRepository $annonceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $annonces = $annonceRepository->findBy(
            ['status' => Annonce::STATUS_REJECTED],
            ['createdAt' => 'ASC']
        );

        return $this->render('admin/annonce/rejected.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_annonce_approve', methods: ['POST'])]
    public function approve(
        ?Annonce $annonce,
        Request $request,
        EntityManagerInterface $em,
        PlatformMailer $platformMailer,
        NotificationService $notificationService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        if (!$this->isCsrfTokenValid('approve'.$annonce->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide');

            return $this->redirectToRoute('admin_annonces_pending');
        }

        $annonce->setStatus(Annonce::STATUS_APPROVED);
        $annonce->setApprovedAt(new \DateTimeImmutable());
        $em->flush();

        $owner = $annonce->getUser();
        if ($owner) {
            $notificationService->notifyUser(
                $owner,
                'Annonce approuvée',
                'Votre annonce « ' . $annonce->getTitle() . ' » est maintenant en ligne sur SoukExpat.',
            );
            $platformMailer->sendAnnonceApproved($annonce);
        }

        $this->addFlash('success', 'Annonce validée — e-mail envoyé depuis contact@soukexpat.com.');

        return $this->redirectToRoute('admin_annonces_pending');
    }

    #[Route('/{id}/reject', name: 'admin_annonce_reject', methods: ['POST'])]
    public function reject(
        ?Annonce $annonce,
        Request $request,
        EntityManagerInterface $em,
        PlatformMailer $platformMailer,
        NotificationService $notificationService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        if (!$this->isCsrfTokenValid('reject'.$annonce->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide');

            return $this->redirectToRoute('admin_annonces_pending');
        }

        $annonce->setStatus(Annonce::STATUS_REJECTED);
        $em->flush();

        $owner = $annonce->getUser();
        if ($owner) {
            $notificationService->notifyUser(
                $owner,
                'Annonce non validée',
                'Votre annonce « ' . $annonce->getTitle() . ' » n’a pas été validée. Vous pouvez la modifier et la soumettre à nouveau.',
            );
            $platformMailer->sendAnnonceRejected($annonce);
        }

        $this->addFlash('warning', 'Annonce rejetée — e-mail envoyé depuis contact@soukexpat.com.');

        return $this->redirectToRoute('admin_annonces_pending');
    }

    #[Route('/{id}/delete', name: 'admin_annonce_delete', methods: ['POST'])]
    public function delete(
        ?Annonce $annonce,
        Request $request,
        EntityManagerInterface $em,
        AnnonceDeletionService $annonceDeletionService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $redirectRoute = match ($annonce->getStatus()) {
            Annonce::STATUS_APPROVED => 'admin_annonces_approved',
            Annonce::STATUS_REJECTED => 'admin_annonces_rejected',
            default => 'admin_annonces_pending',
        };

        if (!$this->isCsrfTokenValid('admin_delete'.$annonce->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide');

            return $this->redirectToRoute($redirectRoute);
        }

        $annonceDeletionService->removeCompletely($em, $annonce);
        $em->flush();

        $this->addFlash('success', 'Annonce supprimée définitivement.');

        return $this->redirectToRoute($redirectRoute);
    }
}
