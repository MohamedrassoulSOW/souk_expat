<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 429 : JSON pour l’API, flash + redirection pour le web.
 */
final class TooManyRequestsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 16],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof TooManyRequestsHttpException) {
            return;
        }

        $request = $event->getRequest();
        $retryAfter = (string) ($exception->getHeaders()['Retry-After'] ?? 60);

        if ($request->getRequestFormat() === 'json' || str_starts_with($request->getPathInfo(), '/api/')) {
            $response = new JsonResponse([
                'error' => 'too_many_requests',
                'message' => 'Trop de tentatives. Réessayez plus tard.',
            ], 429);
            $response->headers->set('Retry-After', $retryAfter);
            $event->setResponse($response);

            return;
        }

        $session = $this->requestStack->getSession();
        $session->getFlashBag()->add('warning', 'Trop de tentatives. Réessayez dans quelques minutes.');

        $target = $request->getUri();
        $referer = $request->headers->get('referer');
        if (\is_string($referer) && $referer !== '') {
            $refHost = parse_url($referer, PHP_URL_HOST);
            if ($refHost === $request->getHost()) {
                $target = $referer;
            }
        }

        $event->setResponse(new RedirectResponse($target));
    }
}
