<?php

namespace App\Controller\Admin;

use App\Entity\Annonce;
use App\Repository\AnnonceRepository;
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

        $annonces = $annonceRepository->findBy(['status' => Annonce::STATUS_APPROVED]);

        return $this->render('admin/annonce/approved.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/rejected', name: 'admin_annonces_rejected')]
    public function rejected(AnnonceRepository $annonceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $annonces = $annonceRepository->findBy(['status' => Annonce::STATUS_REJECTED]);

        return $this->render('admin/annonce/rejected.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_annonce_approve', methods: ['POST'])]
    public function approve(?Annonce $annonce, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('approve'.$annonce->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_annonces_pending');
        }

        $annonce->setStatus(Annonce::STATUS_APPROVED);
        $annonce->setApprovedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Annonce validée avec succès');
        return $this->redirectToRoute('admin_annonces_pending');
    }

    #[Route('/{id}/reject', name: 'admin_annonce_reject', methods: ['POST'])]
    public function reject(?Annonce $annonce, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('reject'.$annonce->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_annonces_pending');
        }

        $annonce->setStatus(Annonce::STATUS_REJECTED);
        $em->flush();

        $this->addFlash('warning', 'Annonce rejetée avec succès');
        return $this->redirectToRoute('admin_annonces_pending');
    }
}
