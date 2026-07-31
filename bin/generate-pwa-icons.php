#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Génère les icônes PWA (192, 512) + apple-touch depuis le logo.
 * Usage : php bin/generate-pwa-icons.php
 */

$root = dirname(__DIR__);
$srcPath = $root . '/public/logo-souk-expat.png';
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

if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Impossible de créer {$outDir}\n");
    exit(1);
}

$make = static function (int $size, string $path) use ($src, $sw, $sh): void {
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $bg = imagecolorallocate($im, 0x1B, 0x2E, 0x4B);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $bg);

    // Zone sûre ~72 % (maskable / adaptive icons)
    $maxW = (int) round($size * 0.72);
    $maxH = (int) round($size * 0.72);
    $ratio = min($maxW / $sw, $maxH / $sh);
    $dw = (int) round($sw * $ratio);
    $dh = (int) round($sh * $ratio);
    $dx = (int) (($size - $dw) / 2);
    $dy = (int) (($size - $dh) / 2);

    imagecopyresampled($im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    imagepng($im, $path, 6);
    imagedestroy($im);
    echo "[OK] {$path}\n";
};

$make(192, $outDir . '/icon-192.png');
$make(512, $outDir . '/icon-512.png');
$make(180, $outDir . '/apple-touch-icon.png');

imagedestroy($src);
echo "Icônes PWA générées.\n";
