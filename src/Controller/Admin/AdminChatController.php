<?php

namespace App\Controller\Admin;

use App\Entity\Thread;
use App\Repository\ThreadRepository;
use App\Service\MessageRetentionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class AdminChatController extends AbstractController
{
    #[Route('/admin/chats', name: 'app_admin_chats_index', methods: ['GET'])]
    public function index(
        ThreadRepository $threadRepository,
        MessageRetentionService $retentionService,
    ): Response {
        return $this->render('admin/chat/index.html.twig', [
            'threads' => $threadRepository->findAllForAdminOrderedByNewest(0),
            'countsByAge' => $retentionService->countByAgeBuckets([7, 14, 30, 60, 90]),
            'emptyThreadsCount' => \count($threadRepository->findEmptyThreads()),
        ]);
    }

    #[Route('/admin/chats/purge-old-messages', name: 'app_admin_chats_purge_old', methods: ['POST'])]
    public function purgeOldMessages(
        Request $request,
        MessageRetentionService $retentionService,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_purge_old_messages', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_admin_chats_index');
        }

        $days = $this->resolvePeriodToDays(
            (string) $request->request->get('unit', 'days'),
            $request->request->getInt('value', $request->request->getInt('days', 30)),
            30
        );

        $result = $retentionService->purgeExpired($days, false);

        $this->addFlash('success', sprintf(
            '%d message(s) de plus de %d jours supprimé(s)%s.',
            $result['messages'],
            $days,
            $result['threads'] > 0
                ? sprintf(', %d conversation(s) vide(s) retirée(s)', $result['threads'])
                : ''
        ));

        return $this->redirectToRoute('app_admin_chats_index');
    }

    #[Route('/admin/chats/{id}/delete', name: 'app_admin_chats_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteThread(
        Thread $thread,
        Request $request,
        MessageRetentionService $retentionService,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_delete_thread_' . $thread->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_admin_chats_index');
        }

        $threadId = $thread->getId();
        $deleted = $retentionService->deleteThreadCompletely($thread);

        $this->addFlash('success', sprintf(
            'Conversation #%d supprimée (%d message(s)).',
            $threadId ?? 0,
            $deleted
        ));

        return $this->redirectToRoute('app_admin_chats_index');
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
