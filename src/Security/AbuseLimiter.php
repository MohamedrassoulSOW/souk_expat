<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Limite les endpoints publics sensibles (API auth, reset mot de passe, contact).
 */
final class AbuseLimiter
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $apiLoginLimiter,
        private readonly RateLimiterFactoryInterface $apiRegisterLimiter,
        private readonly RateLimiterFactoryInterface $passwordResetLimiter,
        private readonly RateLimiterFactoryInterface $contactFormLimiter,
    ) {
    }

    public function assertApiLogin(Request $request): void
    {
        $this->consume($this->apiLoginLimiter, $this->clientKey($request));
    }

    public function assertApiRegister(Request $request): void
    {
        $this->consume($this->apiRegisterLimiter, $this->clientKey($request));
    }

    public function assertPasswordReset(Request $request): void
    {
        $this->consume($this->passwordResetLimiter, $this->clientKey($request));
    }

    public function assertContactForm(Request $request): void
    {
        $this->consume($this->contactFormLimiter, $this->clientKey($request));
    }

    private function consume(RateLimiterFactoryInterface $factory, string $key): void
    {
        $limit = $factory->create($key)->consume(1);
        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter();
        $seconds = max(1, $retryAfter->getTimestamp() - time());

        throw new TooManyRequestsHttpException($seconds, 'Trop de tentatives. Réessayez plus tard.');
    }

    private function clientKey(Request $request): string
    {
        return $request->getClientIp() ?: 'unknown';
    }
}
