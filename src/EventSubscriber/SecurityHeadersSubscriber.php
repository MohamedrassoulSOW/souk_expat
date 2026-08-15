<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * En-têtes HTTP de durcissement (CSP, HSTS, clickjacking, MIME sniffing).
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        #[Autowire('%env(default::MERCURE_PUBLIC_URL)%')]
        private readonly ?string $mercurePublicUrl = '',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        $request = $event->getRequest();
        if ($this->kernel->getEnvironment() === 'prod' && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    private function contentSecurityPolicy(): string
    {
        $connectSrc = ["'self'"];
        $mercure = trim((string) $this->mercurePublicUrl);
        if ($mercure !== '' && filter_var($mercure, FILTER_VALIDATE_URL)) {
            $parts = parse_url($mercure);
            if (isset($parts['scheme'], $parts['host'])) {
                $origin = $parts['scheme'].'://'.$parts['host'];
                if (isset($parts['port'])) {
                    $origin .= ':'.$parts['port'];
                }
                $connectSrc[] = $origin;
                if ($parts['scheme'] === 'https') {
                    $connectSrc[] = 'wss://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
                }
            }
        }

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self' https://accounts.google.com",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "frame-src 'self' https://accounts.google.com",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            // Scripts inline existants (thème, vendors) + CDN de secours
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://accounts.google.com",
            'connect-src '.implode(' ', array_unique($connectSrc)),
        ];

        if ($this->kernel->getEnvironment() === 'prod') {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
