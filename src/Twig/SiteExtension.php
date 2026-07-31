<?php

declare(strict_types=1);

namespace App\Twig;

use App\Mail\SiteContact;
use App\Service\SiteSettingsService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class SiteExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SiteSettingsService $settingsService,
    ) {
    }

    public function getGlobals(): array
    {
        try {
            $site = $this->settingsService->get();
            $email = $site->getContactEmail() ?: SiteContact::EMAIL;
        } catch (\Throwable) {
            // Evite de casser le rendu si la table n’existe pas encore
            return [
                'site' => null,
                'contact_email' => SiteContact::EMAIL,
            ];
        }

        return [
            'site' => $site,
            'contact_email' => $email,
        ];
    }
}
