<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\User;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * JWT HS256 pour l’API mobile (/api/v1).
 */
final class JwtTokenManager
{
    private Configuration $config;

    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $ttlSeconds = 2_592_000,
        private readonly ?ClockInterface $clock = null,
    ) {
        $key = InMemory::plainText($this->normalizeSecret($secret));
        $this->config = Configuration::forSymmetricSigner(new Sha256(), $key);
    }

    /**
     * @return array{token: string, expires_at: string, expires_in: int}
     */
    public function createToken(User $user): array
    {
        $now = $this->clock()->now();
        $expiresAt = $now->modify('+' . $this->ttlSeconds . ' seconds');

        $token = $this->config->builder()
            ->issuedBy($this->issuer)
            ->relatedTo((string) $user->getUserIdentifier())
            ->identifiedBy(bin2hex(random_bytes(8)))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($expiresAt)
            ->withClaim('uid', $user->getId())
            ->withClaim('roles', $user->getRoles())
            ->getToken($this->config->signer(), $this->config->signingKey());

        return [
            'token' => $token->toString(),
            'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
            'expires_in' => $this->ttlSeconds,
        ];
    }

    public function getUserEmailFromToken(string $jwt): ?string
    {
        try {
            $token = $this->config->parser()->parse($jwt);
            if (!$token instanceof Plain) {
                return null;
            }

            $constraints = [
                new SignedWith($this->config->signer(), $this->config->verificationKey()),
                new IssuedBy($this->issuer),
                new StrictValidAt($this->clock()),
            ];

            if (!$this->config->validator()->validate($token, ...$constraints)) {
                return null;
            }

            $email = $token->claims()->get('sub');

            return \is_string($email) && $email !== '' ? $email : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function clock(): ClockInterface
    {
        return $this->clock ?? new NativeClock();
    }

    private function normalizeSecret(string $secret): string
    {
        // HS256 exige une clé suffisamment longue.
        if (\strlen($secret) >= 32) {
            return $secret;
        }

        return hash('sha256', $secret);
    }
}
