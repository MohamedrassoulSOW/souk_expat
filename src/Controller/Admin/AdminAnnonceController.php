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
    public function approved(Request $request, AnnonceRepository $annonceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $periodUnit = (string) $request->query->get('unit', 'days');
        if (!\in_array($periodUnit, ['days', 'months'], true)) {
            $periodUnit = 'days';
        }

        $periodValue = $request->query->getInt('value', 0);
        $olderThanDays = $request->query->getInt('olderThan', 0);

        if ($periodValue > 0) {
            $olderThanDays = $periodUnit === 'months'
                ? max(1, min(120, $periodValue)) * 30
                : max(1, min(3650, $periodValue));
        } elseif ($olderThanDays > 0) {
            $olderThanDays = max(1, min(3650, $olderThanDays));
            $periodUnit = 'days';
            $periodValue = $olderThanDays;
        }

        $countsByAge = [
            30 => $annonceRepository->countApprovedOlderThan(new \DateTimeImmutable('-30 days')),
            60 => $annonceRepository->countApprovedOlderThan(new \DateTimeImmutable('-60 days')),
            90 => $annonceRepository->countApprovedOlderThan(new \DateTimeImmutable('-90 days')),
            180 => $annonceRepository->countApprovedOlderThan(new \DateTimeImmutable('-180 days')),
            365 => $annonceRepository->countApprovedOlderThan(new \DateTimeImmutable('-365 days')),
        ];

        if ($olderThanDays > 0) {
            $annonces = $annonceRepository->findApprovedOlderThan(
                new \DateTimeImmutable(sprintf('-%d days', $olderThanDays))
            );
            $customCount = \count($annonces);
        } else {
            $annonces = $annonceRepository->findBy(
                ['status' => Annonce::STATUS_APPROVED],
                ['approvedAt' => 'ASC', 'createdAt' => 'ASC']
            );
            $customCount = 0;
        }

        return $this->render('admin/annonce/approved.html.twig', [
            'annonces' => $annonces,
            'olderThanDays' => $olderThanDays,
            'periodUnit' => $periodUnit,
            'periodValue' => $periodValue > 0 ? $periodValue : 90,
            'customCount' => $customCount,
            'countsByAge' => $countsByAge,
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

    #[Route('/{id}', name: 'admin_annonce_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(?Annonce $annonce): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        return $this->render('admin/annonce/show.html.twig', [
            'annonce' => $annonce,
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
        $mailSent = false;
        if ($owner) {
            $notificationService->notifyUser(
                $owner,
                'Annonce validée',
                'Votre annonce « ' . $annonce->getTitle() . ' » est maintenant en ligne.',
            );
            $mailSent = $platformMailer->sendAnnonceApproved($annonce);
        }

        if ($mailSent) {
            $this->addFlash('success', sprintf(
                'Annonce validée — e-mail envoyé à %s depuis %s.',
                $owner->getEmail(),
                $platformMailer->contactEmail()
            ));
        } elseif ($owner) {
            $this->addFlash('warning', 'Annonce validée, mais l’e-mail à l’annonceur n’a pas pu être envoyé. Vérifiez MAILER_DSN.');
        } else {
            $this->addFlash('success', 'Annonce validée.');
        }

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
        $annonce->setApprovedAt(null);
        $em->flush();

        $owner = $annonce->getUser();
        $mailSent = false;
        if ($owner) {
            $notificationService->notifyUser(
                $owner,
                'Annonce refusée',
                'Votre annonce « ' . $annonce->getTitle() . ' » a été refusée. Vous pouvez la modifier et la soumettre à nouveau.',
            );
            $mailSent = $platformMailer->sendAnnonceRejected($annonce);
        }

        if ($mailSent) {
            $this->addFlash('warning', sprintf(
                'Annonce refusée — e-mail envoyé à %s depuis %s.',
                $owner->getEmail(),
                $platformMailer->contactEmail()
            ));
        } elseif ($owner) {
            $this->addFlash('danger', 'Annonce refusée, mais l’e-mail à l’annonceur n’a pas pu être envoyé. Vérifiez MAILER_DSN.');
        } else {
            $this->addFlash('warning', 'Annonce refusée.');
        }

        return $this->redirectToRoute('admin_annonces_pending');
    }

    #[Route('/purge-old-approved', name: 'admin_annonces_purge_old_approved', methods: ['POST'])]
    public function purgeOldApproved(
        Request $request,
        EntityManagerInterface $em,
        AnnonceRepository $annonceRepository,
        AnnonceDeletionService $annonceDeletionService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$this->isCsrfTokenValid('admin_purge_old_approved', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_annonces_approved');
        }

        $days = $this->resolvePeriodToDays(
            (string) $request->request->get('unit', 'days'),
            $request->request->getInt('value', $request->request->getInt('days', 90)),
            90
        );

        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $days));
        $annonces = $annonceRepository->findApprovedOlderThan($cutoff);
        foreach ($annonces as $annonce) {
            $annonceDeletionService->removeCompletely($em, $annonce);
        }
        $em->flush();

        $this->addFlash('success', sprintf(
            '%d annonce(s) validée(s) de plus de %d jours supprimée(s).',
            \count($annonces),
            $days
        ));

        return $this->redirectToRoute('admin_annonces_approved', [
            'unit' => 'days',
            'value' => $days,
            'olderThan' => $days,
        ]);
    }

    #[Route('/bulk-delete-approved', name: 'admin_annonces_bulk_delete_approved', methods: ['POST'])]
    public function bulkDeleteApproved(
        Request $request,
        EntityManagerInterface $em,
        AnnonceRepository $annonceRepository,
        AnnonceDeletionService $annonceDeletionService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$this->isCsrfTokenValid('admin_bulk_delete_approved', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_annonces_approved');
        }

        $ids = $request->request->all('ids');
        if (!\is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        $annonces = $annonceRepository->findApprovedByIds($ids);
        foreach ($annonces as $annonce) {
            $annonceDeletionService->removeCompletely($em, $annonce);
        }
        $em->flush();

        $this->addFlash('success', sprintf('%d annonce(s) validée(s) sélectionnée(s) supprimée(s).', \count($annonces)));

        $olderThan = $request->request->getInt('olderThan', 0);

        return $this->redirectToRoute('admin_annonces_approved', $olderThan > 0 ? ['olderThan' => $olderThan] : []);
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

    private function resolvePeriodToDays(string $unit, int $value, int $defaultDays): int
    {
        if ($value <= 0) {
            return $defaultDays;
        }

        if ($unit === 'months') {
            return max(1, min(120, $value)) * 30;
        }

        return max(1, min(3650, $value));
    }
}
