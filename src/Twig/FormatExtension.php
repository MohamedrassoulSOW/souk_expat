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
     * Lien WhatsApp (wa.me) à partir d’un numéro (+212…, 00212… ou 06… Maroc).
     */
    public function whatsappUrl(?string $phone, ?string $prefill = null): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return '#';
        }

        // 00indicatif… → indicatif…
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Mobile marocain local 06/07… → 2126/2127…
        if (preg_match('/^0([67]\d{8})$/', $digits, $m)) {
            $digits = '212' . $m[1];
        }

        // Toujours un indicatif pays (pas de numéro local restant)
        if ($digits === '' || str_starts_with($digits, '0') || \strlen($digits) < 10) {
            return '#';
        }

        $url = 'https://wa.me/' . $digits;
        if ($prefill !== null && trim($prefill) !== '') {
            $url .= '?text=' . rawurlencode($prefill);
        }

        return $url;
    }
}
