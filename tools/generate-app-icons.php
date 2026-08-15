<?php

declare(strict_types=1);

/**
 * Génère le favicon et les icônes PWA (desktop + mobile) à partir du logo
 * original, centré sur un fond blanc opaque.
 *
 * Usage : php tools/generate-app-icons.php
 */

$root = \dirname(__DIR__);
$logoPath = $root . '/public/logo-souk-expat.png';
$iconsDir = $root . '/public/icons';

if (!is_file($logoPath)) {
    fwrite(STDERR, "Logo introuvable : {$logoPath}\n");
    exit(1);
}

if (!is_dir($iconsDir) && !mkdir($iconsDir, 0775, true) && !is_dir($iconsDir)) {
    fwrite(STDERR, "Impossible de créer {$iconsDir}\n");
    exit(1);
}

$logo = imagecreatefrompng($logoPath);
if (!$logo instanceof \GdImage) {
    fwrite(STDERR, "Logo illisible.\n");
    exit(1);
}

$logo = trimTransparent($logo);

// Icônes « any » : logo large, la totalité de l'image reste visible.
// Icônes « maskable » : Android/Windows rognent en cercle, le logo doit tenir
// dans la zone de sécurité centrale (80 % du côté) — d'où la marge renforcée.
$targets = [
    $iconsDir . '/icon-72.png' => [72, 0.08],
    $iconsDir . '/icon-96.png' => [96, 0.08],
    $iconsDir . '/icon-128.png' => [128, 0.08],
    $iconsDir . '/icon-144.png' => [144, 0.08],
    $iconsDir . '/icon-152.png' => [152, 0.08],
    $iconsDir . '/icon-192.png' => [192, 0.08],
    $iconsDir . '/icon-384.png' => [384, 0.08],
    $iconsDir . '/icon-512.png' => [512, 0.08],
    $iconsDir . '/apple-touch-icon.png' => [180, 0.08],
    $iconsDir . '/icon-maskable-192.png' => [192, 0.19],
    $iconsDir . '/icon-maskable-512.png' => [512, 0.19],
    $root . '/public/favicon.png' => [96, 0.04],
];

foreach ($targets as $path => [$size, $paddingRatio]) {
    $icon = renderIcon($logo, $size, $paddingRatio);
    imagepng($icon, $path, 9);
    imagedestroy($icon);
    echo 'OK ', str_replace($root . '/', '', $path), ' (', $size, 'px)', PHP_EOL;
}

imagedestroy($logo);
echo 'Terminé : ', count($targets), " icônes générées.\n";

/**
 * Logo centré sur un carré blanc opaque.
 */
function renderIcon(\GdImage $logo, int $size, float $paddingRatio): \GdImage
{
    $canvas = imagecreatetruecolor($size, $size);
    imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, 255, 255, 255));

    $available = (int) round($size * (1 - 2 * $paddingRatio));
    $srcWidth = imagesx($logo);
    $srcHeight = imagesy($logo);
    $scale = min($available / $srcWidth, $available / $srcHeight);

    $drawWidth = max(1, (int) round($srcWidth * $scale));
    $drawHeight = max(1, (int) round($srcHeight * $scale));

    imagealphablending($canvas, true);
    imagecopyresampled(
        $canvas,
        $logo,
        (int) round(($size - $drawWidth) / 2),
        (int) round(($size - $drawHeight) / 2),
        0,
        0,
        $drawWidth,
        $drawHeight,
        $srcWidth,
        $srcHeight
    );

    return $canvas;
}

/**
 * Retire les bordures entièrement transparentes pour maximiser le logo.
 */
function trimTransparent(\GdImage $image): \GdImage
{
    $width = imagesx($image);
    $height = imagesy($image);

    $minX = $width;
    $minY = $height;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
            if ($alpha < 120) {
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
    }

    if ($maxX < 0 || ($minX === 0 && $minY === 0 && $maxX === $width - 1 && $maxY === $height - 1)) {
        return $image;
    }

    $cropped = imagecrop($image, [
        'x' => $minX,
        'y' => $minY,
        'width' => $maxX - $minX + 1,
        'height' => $maxY - $minY + 1,
    ]);

    if (!$cropped instanceof \GdImage) {
        return $image;
    }

    imagedestroy($image);

    return $cropped;
}
