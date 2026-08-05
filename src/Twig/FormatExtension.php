<?php

namespace App\Twig;

use App\Service\WhatsAppLinkBuilder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FormatExtension extends AbstractExtension
{
    public function __construct(
        private readonly WhatsAppLinkBuilder $whatsAppLinkBuilder,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('cap999', [$this, 'cap999']),
            new TwigFilter('whatsapp_url', [$this, 'whatsappUrl']),
        ];
    }

    /**
     * Affiche le nombre tel quel sous 999, sinon "+999".
     */
    public function cap999(int|float|string|null $value): string
    {
        $n = (int) $value;

        return $n >= 999 ? '+999' : (string) $n;
    }

    /**
     * Lien WhatsApp (wa.me) à partir d’un numéro (+212…, 00212… ou 06… Maroc).
     */
    public function whatsappUrl(?string $phone, ?string $prefill = null): string
    {
        return $this->whatsAppLinkBuilder->url($phone, $prefill) ?? '#';
    }
}
