<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Captcha image (session) pour l’inscription.
 */
final class ImageCaptcha
{
    public const SESSION_CODE = '_reg_captcha_code';
    public const SESSION_EXPIRES = '_reg_captcha_exp';
    private const TTL = 600;
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function ensure(SessionInterface $session): string
    {
        $code = (string) $session->get(self::SESSION_CODE, '');
        $expires = (int) $session->get(self::SESSION_EXPIRES, 0);
        if ($code === '' || $expires < time()) {
            return $this->refresh($session);
        }

        return $code;
    }

    public function refresh(SessionInterface $session): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 5; ++$i) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        $session->set(self::SESSION_CODE, $code);
        $session->set(self::SESSION_EXPIRES, time() + self::TTL);

        return $code;
    }

    public function isValid(SessionInterface $session, string $submitted): bool
    {
        $expected = (string) $session->get(self::SESSION_CODE, '');
        $expires = (int) $session->get(self::SESSION_EXPIRES, 0);
        if ($expected === '' || $expires < time()) {
            return false;
        }

        $ok = hash_equals(strtoupper($expected), strtoupper(trim($submitted)));

        return $ok;
    }

    public function consume(SessionInterface $session): void
    {
        $session->remove(self::SESSION_CODE);
        $session->remove(self::SESSION_EXPIRES);
    }

    public function renderPng(string $code): string
    {
        $width = 200;
        $height = 64;
        $im = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($im, 0x1B, 0x2E, 0x4B);
        $fg = imagecolorallocate($im, 0xF8, 0xFA, 0xFC);
        $noise = imagecolorallocate($im, 0x4B, 0x79, 0xA1);
        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        for ($i = 0; $i < 18; ++$i) {
            imageline(
                $im,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $noise
            );
        }

        $font = $this->fontPath();
        $len = strlen($code);
        for ($i = 0; $i < $len; ++$i) {
            $x = 16 + ($i * 36);
            $y = random_int(42, 52);
            $char = $code[$i];
            if ($font !== null && \function_exists('imagettftext')) {
                imagettftext($im, 22, random_int(-12, 12), $x, $y, $fg, $font, $char);
            } else {
                imagestring($im, 5, $x, $y - 28, $char, $fg);
            }
        }

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    public function fromRequest(Request $request): SessionInterface
    {
        return $request->getSession();
    }

    private function fontPath(): ?string
    {
        $candidates = [
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
        ];
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
