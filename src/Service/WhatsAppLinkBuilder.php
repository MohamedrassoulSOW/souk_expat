<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Annonce;

/**
 * Liens WhatsApp (wa.me) pour contact acheteur ↔ vendeur.
 */
final class WhatsAppLinkBuilder
{
    public function contactPhoneForAnnonce(Annonce $annonce): ?string
    {
        $phone = trim($annonce->getPhone());
        if ($phone !== '') {
            return $phone;
        }

        $sellerPhone = $annonce->getUser()?->getWhatsappPhone();

        return $sellerPhone !== null && trim($sellerPhone) !== '' ? trim($sellerPhone) : null;
    }

    public function url(?string $phone, ?string $prefill = null): ?string
    {
        $digits = $this->normalizeDigits($phone);
        if ($digits === null) {
            return null;
        }

        $url = 'https://wa.me/' . $digits;
        if ($prefill !== null && trim($prefill) !== '') {
            $url .= '?text=' . rawurlencode($prefill);
        }

        return $url;
    }

    public function urlForAnnonce(Annonce $annonce, ?string $prefill = null): ?string
    {
        $phone = $this->contactPhoneForAnnonce($annonce);
        if ($phone === null) {
            return null;
        }

        if ($prefill === null || trim($prefill) === '') {
            $prefill = sprintf(
                'Bonjour, je suis intéressé(e) par votre annonce « %s » sur SoukExpat.',
                $annonce->getTitle()
            );
        }

        return $this->url($phone, $prefill);
    }

    public function normalizeDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^0([67]\d{8})$/', $digits, $m)) {
            $digits = '212' . $m[1];
        }

        if ($digits === '' || str_starts_with($digits, '0') || \strlen($digits) < 10) {
            return null;
        }

        return $digits;
    }
}
