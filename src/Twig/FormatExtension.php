<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FormatExtension extends AbstractExtension
{
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
     * Lien WhatsApp (wa.me) à partir d’un numéro international (+indicatif…).
     */
    public function whatsappUrl(?string $phone, ?string $prefill = null): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '' || str_starts_with($digits, '0')) {
            return '#';
        }

        $url = 'https://wa.me/' . $digits;
        if ($prefill !== null && trim($prefill) !== '') {
            $url .= '?text=' . rawurlencode($prefill);
        }

        return $url;
    }
}
