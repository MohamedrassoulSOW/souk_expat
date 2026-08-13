<?php

declare(strict_types=1);

namespace App\Twig;

use App\Mail\SiteContact;
use App\Service\GoogleOAuthService;
use App\Service\SiteSettingsService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class SiteExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SiteSettingsService $settingsService,
        private readonly GoogleOAuthService $googleOAuth,
    ) {
    }

    public function getGlobals(): array
    {
        try {
            $site = $this->settingsService->get();
        } catch (\Throwable) {
            return [
                'site' => null,
                'contact_email' => SiteContact::EMAIL,
                'google_oauth_enabled' => $this->googleOAuth->isConfigured(),
            ];
        }

        return [
            'site' => $site,
            // Adresse unique pour tout le site
            'contact_email' => SiteContact::EMAIL,
            'google_oauth_enabled' => $this->googleOAuth->isConfigured(),
        ];
    }
}
