<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Repository\ThreadRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprime les messages de discussion plus anciens que la durée de rétention.
 */
final class MessageRetentionService
{
    public const RETENTION_DAYS = 30;

    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ThreadRepository $threadRepository,
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{messages: int, files: int, threads: int, cutoff: \DateTimeImmutable}
     */
    public function purgeExpired(int $retentionDays = self::RETENTION_DAYS, bool $dryRun = false): array
    {
        $cutoff = new \DateTimeImmutable(sprintf('-%d days', max(1, $retentionDays)));
        $messages = $this->messageRepository->findCreatedBefore($cutoff);

        $deletedMessages = 0;
        $deletedFiles = 0;
        $uploadRoot = $this->projectDir . '/public/uploads/messages';

        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                continue;
            }

            $filename = $message->getImageFilename();
            if ($filename) {
                $path = $uploadRoot . '/' . $filename;
                if (is_file($path)) {
                    if (!$dryRun && @unlink($path)) {
                        ++$deletedFiles;
                    } elseif ($dryRun) {
                        ++$deletedFiles;
                    }
                }
            }

            if (!$dryRun) {
                $this->em->remove($message);
            }
            ++$deletedMessages;
        }

        if (!$dryRun && $deletedMessages > 0) {
            $this->em->flush();
        }

        $deletedThreads = 0;
        $emptyThreads = $this->threadRepository->findEmptyThreads();
        foreach ($emptyThreads as $thread) {
            if (!$dryRun) {
                $this->em->remove($thread);
            }
            ++$deletedThreads;
        }

        if (!$dryRun && $deletedThreads > 0) {
            $this->em->flush();
        }

        return [
            'messages' => $deletedMessages,
            'files' => $deletedFiles,
            'threads' => $deletedThreads,
            'cutoff' => $cutoff,
        ];
    }

    /**
     * Compteurs pour l’UI admin (sans suppression).
     *
     * @return array<int, int> days => message count
     */
    public function countByAgeBuckets(array $daysList = [7, 14, 30, 60, 90]): array
    {
        $counts = [];
        foreach ($daysList as $days) {
            $days = (int) $days;
            $cutoff = new \DateTimeImmutable(sprintf('-%d days', max(1, $days)));
            $counts[$days] = $this->messageRepository->countCreatedBefore($cutoff);
        }

        return $counts;
    }

    /**
     * Supprime une conversation entière (messages + fichiers legacy + thread).
     */
    public function deleteThreadCompletely(\App\Entity\Thread $thread): int
    {
        $uploadRoot = $this->projectDir . '/public/uploads/messages';
        $deleted = 0;

        foreach ($thread->getMessagesAsThread()->toArray() as $message) {
            if (!$message instanceof Message) {
                continue;
            }
            $filename = $message->getImageFilename();
            if ($filename) {
                $path = $uploadRoot . '/' . $filename;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $this->em->remove($message);
            ++$deleted;
        }

        $this->em->remove($thread);
        $this->em->flush();

        return $deleted;
    }
}
