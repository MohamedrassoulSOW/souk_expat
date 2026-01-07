<?php

namespace App\Twig;

use App\Repository\ThreadRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MessageExtension extends AbstractExtension
{
    public function __construct(
        private Security $security,
        private ThreadRepository $threadRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_messages_count', [$this, 'getUnreadCount']),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if (!$user) return 0;

        // Ici, on compte simplement le nombre de discussions actives. 
        // Si tu ajoutes un champ 'isRead' dans ton entité Message plus tard, 
        // tu pourras affiner ce calcul.
        return count($this->threadRepository->findByUser($user));
    }
}