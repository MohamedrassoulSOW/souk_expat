<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MessageExtension extends AbstractExtension
{
    private const COUNT_TTL = 45;

    public function __construct(
        private Security $security,
        private MessageRepository $messageRepository,
        private NotificationRepository $notificationRepository,
        private CacheInterface $cache,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_messages_count', [$this, 'getUnreadCount']),
            new TwigFunction('unread_notifications_count', [$this, 'getUnreadNotificationsCount']),
            new TwigFunction('latest_notifications', [$this, 'getLatestNotifications']),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return 0;
        }

        $userId = $user->getId();

        return (int) $this->cache->get('nav.unread_msg.'.$userId, function (ItemInterface $item) use ($user): int {
            $item->expiresAfter(self::COUNT_TTL);

            return $this->messageRepository->countUnreadMessages($user);
        });
    }

    public function getUnreadNotificationsCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return 0;
        }

        $userId = $user->getId();

        return (int) $this->cache->get('nav.unread_notif.'.$userId, function (ItemInterface $item) use ($user): int {
            $item->expiresAfter(self::COUNT_TTL);

            return $this->notificationRepository->countUnreadForUser($user);
        });
    }

    /**
     * @return list<\App\Entity\Notification>
     */
    public function getLatestNotifications(int $limit = 8): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->notificationRepository->findLatestForUser($user, $limit);
    }
}
