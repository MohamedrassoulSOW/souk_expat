<?php

namespace App\Service;

use App\Entity\Annonce;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Suppression complète d’une annonce (fichiers images + fils de discussion).
 */
final class AnnonceDeletionService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function removeCompletely(EntityManagerInterface $em, Annonce $annonce): void
    {
        $uploadAnnonces = $this->projectDir . '/public/uploads/annonces/';
        $uploadMessages = $this->projectDir . '/public/uploads/messages/';

        foreach ($annonce->getThreadsAsAnnonce()->toArray() as $thread) {
            foreach ($thread->getMessagesAsThread()->toArray() as $message) {
                $mf = $message->getImageFilename();
                if ($mf !== null && $mf !== '') {
                    $path = $uploadMessages . $mf;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
                $em->remove($message);
            }
            $em->remove($thread);
        }

        foreach ($annonce->getAnnonceImages()->toArray() as $image) {
            $path = $uploadAnnonces . $image->getImadeName();
            if (is_file($path)) {
                unlink($path);
            }
        }

        $em->remove($annonce);
    }
}
