<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\AdminTwoFactor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Un administrateur doit valider un TOTP à chaque session.
 */
final class AdminTwoFactorSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'app_2fa_setup',
        'app_2fa_challenge',
        'app_logout',
        'app_login',
        'connect_google_start',
        'connect_google_check',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly AdminTwoFactor $twoFactor,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 6],
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof User || !$this->twoFactor->isAdmin($user)) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (\in_array($route, self::ALLOWED_ROUTES, true) || str_starts_with($route, '_wdt') || str_starts_with($route, '_profiler')) {
            return;
        }

        if ($this->twoFactor->isVerified($request->getSession(), $user)) {
            return;
        }

        $target = $user->isTotpEnabled() ? 'app_2fa_challenge' : 'app_2fa_setup';
        $event->setResponse(new RedirectResponse($this->urls->generate($target)));
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User || !$this->twoFactor->isAdmin($user)) {
            return;
        }

        $this->twoFactor->clear($event->getRequest()->getSession());
    }

    public function onLogout(LogoutEvent $event): void
    {
        $session = $event->getRequest()->getSession();
        $this->twoFactor->clear($session);
    }
}
