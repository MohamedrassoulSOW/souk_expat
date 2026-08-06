#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Génère logo-souk-expat-blanc.png + icônes PWA / favicon.
 * Fond icônes : #4B79A1 — logo blanc net sur fond bleu marque.
 *
 * Usage : php bin/generate-pwa-icons.php
 */

$root = dirname(__DIR__);
$srcPath = $root . '/public/logo-souk-expat.png';
$whiteLogoPath = $root . '/public/logo-souk-expat-blanc.png';
$outDir = $root . '/public/icons';

if (!is_file($srcPath)) {
    fwrite(STDERR, "Logo introuvable : {$srcPath}\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Extension GD requise.\n");
    exit(1);
}

$src = imagecreatefrompng($srcPath);
if ($src === false) {
    fwrite(STDERR, "Impossible de lire le logo PNG.\n");
    exit(1);
}

$sw = imagesx($src);
$sh = imagesy($src);

/** Pixel de fond (noir PNG, papier crème, blanc). */
$isBackground = static function (int $r, int $g, int $b, int $opacity): bool {
    if ($opacity < 24) {
        return true;
    }
    if ($r < 32 && $g < 32 && $b < 32) {
        return true;
    }
    if ($r > 238 && $g > 238 && $b > 238) {
        return true;
    }
    // Papier crème / beige du logo officiel
    if ($r > 210 && $g > 200 && $b > 175 && abs($r - $g) < 30) {
        return true;
    }

    return false;
};

/** Logo complet blanc, fond transparent. */
$whiteLogo = imagecreatetruecolor($sw, $sh);
imagesavealpha($whiteLogo, true);
$transparent = imagecolorallocatealpha($whiteLogo, 0, 0, 0, 127);
imagefill($whiteLogo, 0, 0, $transparent);

for ($y = 0; $y < $sh; ++$y) {
    for ($x = 0; $x < $sw; ++$x) {
        $rgba = imagecolorat($src, $x, $y);
        $alpha = ($rgba >> 24) & 0x7F;
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        $opacity = 127 - $alpha;

        if ($isBackground($r, $g, $b, $opacity)) {
            continue;
        }

        $white = imagecolorallocatealpha($whiteLogo, 255, 255, 255, $alpha);
        imagesetpixel($whiteLogo, $x, $y, $white);
    }
}

imagedestroy($src);
imagepng($whiteLogo, $whiteLogoPath, 6);
echo "[OK] {$whiteLogoPath}\n";

if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Impossible de créer {$outDir}\n");
    exit(1);
}

/** Fond bleu marque (#4B79A1). */
$bgR = 0x4B;
$bgG = 0x79;
$bgB = 0xA1;

$make = static function (int $size, string $path) use ($whiteLogo, $sw, $sh, $bgR, $bgG, $bgB): void {
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $bg = imagecolorallocate($im, $bgR, $bgG, $bgB);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $bg);

    // Logo blanc complet — zone sûre ~78 %
    $maxW = (int) round($size * 0.78);
    $maxH = (int) round($size * 0.78);
    $ratio = min($maxW / $sw, $maxH / $sh);
    $dw = (int) round($sw * $ratio);
    $dh = (int) round($sh * $ratio);
    $dx = (int) (($size - $dw) / 2);
    $dy = (int) (($size - $dh) / 2);

    imagecopyresampled($im, $whiteLogo, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    imagepng($im, $path, 6);
    imagedestroy($im);
    echo "[OK] {$path}\n";
};

$sizes = [32, 72, 96, 128, 144, 152, 180, 192, 384, 512];
foreach ($sizes as $size) {
    if ($size === 32) {
        $make(32, $root . '/public/favicon.png');
        continue;
    }
    if ($size === 180) {
        $make(180, $outDir . '/apple-touch-icon.png');
        continue;
    }
    $make($size, $outDir . '/icon-' . $size . '.png');
}

imagedestroy($whiteLogo);
echo "Icônes PWA générées (logo blanc officiel, fond #4B79A1).\n";
