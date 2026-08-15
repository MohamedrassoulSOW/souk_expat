<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CORS pour /api/* : origines autorisées uniquement (jamais *).
 * Les apps natives sans en-tête Origin ne sont pas concernées.
 */
final class CorsSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private readonly array $allowedOrigins;

    public function __construct(
        #[Autowire('%env(default::DEFAULT_URI)%')]
        ?string $defaultUri = '',
        #[Autowire('%env(default::CORS_ALLOW_ORIGINS)%')]
        ?string $extraOrigins = '',
    ) {
        $origins = [];
        foreach ([ (string) $defaultUri, ...explode(',', (string) $extraOrigins)] as $raw) {
            $origin = $this->normalizeOrigin(trim($raw));
            if ($origin !== null) {
                $origins[] = $origin;
            }
        }

        $this->allowedOrigins = array_values(array_unique($origins));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 250],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response('', Response::HTTP_NO_CONTENT);
            $this->applyCorsHeaders($request, $response);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $this->applyCorsHeaders($request, $event->getResponse());
    }

    private function applyCorsHeaders(Request $request, Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept');
        $response->headers->set('Access-Control-Expose-Headers', 'Authorization');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->headers->set('Vary', 'Origin', false);

        $allowed = $this->matchingOrigin($request);
        if ($allowed !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $allowed);
        }
    }

    private function matchingOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '') {
            return null;
        }

        $normalized = $this->normalizeOrigin($origin);
        if ($normalized === null) {
            return null;
        }

        $sameHost = $this->normalizeOrigin($request->getSchemeAndHttpHost());
        if ($normalized === $sameHost || \in_array($normalized, $this->allowedOrigins, true)) {
            return $origin;
        }

        return null;
    }

    private function normalizeOrigin(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $parts = parse_url($value);
        if (!isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = strtolower($parts['scheme']).'://'.strtolower($parts['host']);
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
