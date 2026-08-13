<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Connexion Google OAuth 2.0 (OpenID Connect) sans bundle tiers.
 */
final class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/userinfo';
    private const SESSION_STATE = '_google_oauth_state';

    private readonly string $clientId;
    private readonly string $clientSecret;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        #[Autowire('%env(default::GOOGLE_CLIENT_ID)%')]
        ?string $clientId = null,
        #[Autowire('%env(default::GOOGLE_CLIENT_SECRET)%')]
        ?string $clientSecret = null,
    ) {
        $this->clientId = trim((string) $clientId);
        $this->clientSecret = trim((string) $clientSecret);
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function getAuthorizationUrl(): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google OAuth non configuré (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).');
        }

        $state = bin2hex(random_bytes(16));
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_STATE, $state);

        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return self::AUTH_URL . '?' . $query;
    }

    /**
     * @return array{sub: string, email: string, email_verified: bool, given_name: string, family_name: string, picture: ?string, name: string}
     */
    public function fetchUserFromCallback(?string $code, ?string $state): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google OAuth non configuré.');
        }

        if ($code === null || $code === '' || $state === null || $state === '') {
            throw new \InvalidArgumentException('Réponse Google invalide (code/state manquant).');
        }

        $session = $this->requestStack->getSession();
        $expected = (string) $session->get(self::SESSION_STATE, '');
        $session->remove(self::SESSION_STATE);

        if ($expected === '' || !hash_equals($expected, $state)) {
            throw new \InvalidArgumentException('État OAuth invalide. Réessayez la connexion Google.');
        }

        $tokenResponse = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->getRedirectUri(),
                'grant_type' => 'authorization_code',
            ],
        ]);

        $tokenData = $tokenResponse->toArray(false);
        if (($tokenResponse->getStatusCode() >= 400) || empty($tokenData['access_token'])) {
            $err = $tokenData['error_description'] ?? $tokenData['error'] ?? 'échange de token échoué';
            throw new \RuntimeException('Google token : ' . (string) $err);
        }

        $userResponse = $this->httpClient->request('GET', self::USERINFO_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
            ],
        ]);
        $user = $userResponse->toArray(false);
        if ($userResponse->getStatusCode() >= 400 || empty($user['sub']) || empty($user['email'])) {
            throw new \RuntimeException('Impossible de récupérer le profil Google.');
        }

        return [
            'sub' => (string) $user['sub'],
            'email' => mb_strtolower(trim((string) $user['email'])),
            'email_verified' => (bool) ($user['email_verified'] ?? false),
            'given_name' => trim((string) ($user['given_name'] ?? '')),
            'family_name' => trim((string) ($user['family_name'] ?? '')),
            'name' => trim((string) ($user['name'] ?? '')),
            'picture' => isset($user['picture']) ? (string) $user['picture'] : null,
        ];
    }

    public function getRedirectUri(): string
    {
        return $this->urlGenerator->generate('connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
