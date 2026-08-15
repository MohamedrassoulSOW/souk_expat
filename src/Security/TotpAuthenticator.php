<?php

declare(strict_types=1);

namespace App\Security;

/**
 * TOTP RFC 6238 (Google Authenticator, Authy, etc.).
 */
final class TotpAuthenticator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $secret, string $email, string $issuer = 'SoukExpat'): string
    {
        $label = rawurlencode($issuer.':'.$email);

        return sprintf(
            'otpauth://totp/%s?%s',
            $label,
            http_build_query([
                'secret' => $secret,
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => 6,
                'period' => 30,
            ], '', '&', PHP_QUERY_RFC3986)
        );
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $slice = intdiv(time(), 30);
        for ($i = -$window; $i <= $window; ++$i) {
            if (hash_equals($this->hotp($secret, $slice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private function hotp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $bin = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $binary = '';
        $length = strlen($data);
        for ($i = 0; $i < $length; ++$i) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0');
            $out .= self::ALPHABET[bindec($chunk)];
        }

        return $out;
    }

    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper((string) preg_replace('/[^A-Z2-7]/', '', $b32));
        $map = array_flip(str_split(self::ALPHABET));
        $binary = '';
        $length = strlen($b32);
        for ($i = 0; $i < $length; ++$i) {
            $char = $b32[$i];
            if (!isset($map[$char])) {
                continue;
            }
            $binary .= str_pad(decbin($map[$char]), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $out .= chr(bindec($byte));
        }

        return $out;
    }
}
