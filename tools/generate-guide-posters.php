<?php

declare(strict_types=1);

/**
 * Génère les images de couverture (posters) des vidéos du guide,
 * aux couleurs SoukExpat, dans public/videos/guide/posters/.
 *
 * Usage : php tools/generate-guide-posters.php
 */

const WIDTH = 1080;
const HEIGHT = 1920;

const NAVY = [0x1B, 0x2E, 0x4B];
const CYAN = [0x4B, 0x79, 0xA1];

$root = \dirname(__DIR__);
$outputDir = $root . '/public/videos/guide/posters';
$logoPath = $root . '/public/logo-souk-expat-blanc.png';

$font = null;
foreach (['C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'] as $candidate) {
    if (is_file($candidate)) {
        $font = $candidate;
        break;
    }
}

$posters = [
    'soukexpat-01-story-idee' => ['num' => 1, 'kicker' => 'Le projet', 'title' => "L’idée\ngénérale", 'footer' => 'PRÉSENTATION'],
    'soukexpat-02-story-impact' => ['num' => 2, 'kicker' => 'Communauté', 'title' => "Impact au\nquotidien", 'footer' => 'PRÉSENTATION'],
    'soukexpat-03-story-social' => ['num' => 3, 'kicker' => 'Réseaux sociaux', 'title' => "Présentation\nSoukExpat", 'footer' => 'PRÉSENTATION'],
    'soukexpat-04-tutoriel-mobile' => ['num' => 4, 'kicker' => 'Pour commencer', 'title' => 'Découvrir SoukExpat'],
    'soukexpat-05-guide-user' => ['num' => 5, 'kicker' => 'Guide utilisateur', 'title' => "Parcourir &\nvendre"],
    'soukexpat-06-guide-messaging' => ['num' => 6, 'kicker' => 'Messagerie', 'title' => "Validation &\nmessages"],
    'soukexpat-07-guide-profile' => ['num' => 7, 'kicker' => 'Compte', 'title' => "Profil &\nmot de passe"],
    'soukexpat-08-guide-pwa' => ['num' => 8, 'kicker' => 'Application', 'title' => "Responsive\n& PWA"],
    'soukexpat-09-guide-editor' => ['num' => 9, 'kicker' => 'Guide éditeur', 'title' => "Modérer\nles annonces"],
    'soukexpat-10-guide-admin' => ['num' => 10, 'kicker' => 'Guide admin', 'title' => "Gérer la\nplateforme"],
];

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Impossible de créer {$outputDir}\n");
    exit(1);
}

foreach ($posters as $slug => $meta) {
    $image = imagecreatetruecolor(WIDTH, HEIGHT);
    imagesavealpha($image, true);

    drawGradient($image);
    drawDecorations($image);
    drawLogo($image, $logoPath);
    drawPlayButton($image);
    drawTexts($image, $font, $meta);

    $target = $outputDir . '/' . $slug . '.jpg';
    imagejpeg($image, $target, 88);
    imagedestroy($image);

    echo 'OK ', basename($target), PHP_EOL;
}

echo 'Terminé : ', count($posters), " couvertures générées.\n";

/**
 * Dégradé diagonal navy → cyan.
 */
function drawGradient(\GdImage $image): void
{
    for ($y = 0; $y < HEIGHT; $y++) {
        $ratio = $y / (HEIGHT - 1);
        // Courbe douce pour garder le haut sombre et lisible
        $eased = $ratio ** 1.35;
        $color = imagecolorallocate(
            $image,
            (int) round(NAVY[0] + (CYAN[0] - NAVY[0]) * $eased),
            (int) round(NAVY[1] + (CYAN[1] - NAVY[1]) * $eased),
            (int) round(NAVY[2] + (CYAN[2] - NAVY[2]) * $eased)
        );
        imagefilledrectangle($image, 0, $y, WIDTH, $y, $color);
    }
}

/**
 * Cercles concentriques translucides (rappel des cartes arrondies du site).
 */
function drawDecorations(\GdImage $image): void
{
    $layer = imagecreatetruecolor(WIDTH, HEIGHT);
    imagealphablending($layer, false);
    imagefilledrectangle($layer, 0, 0, WIDTH, HEIGHT, imagecolorallocatealpha($layer, 0, 0, 0, 127));
    imagealphablending($layer, true);

    $soft = imagecolorallocatealpha($layer, 255, 255, 255, 118);
    foreach ([820, 1180, 1560] as $diameter) {
        imagesetthickness($layer, 4);
        imageellipse($layer, (int) (WIDTH * 0.5), (int) (HEIGHT * 0.42), $diameter, $diameter, $soft);
    }

    $glow = imagecolorallocatealpha($layer, 66, 177, 206, 112);
    imagefilledellipse($layer, (int) (WIDTH * 0.18), (int) (HEIGHT * 0.12), 620, 620, $glow);
    imagefilledellipse($layer, (int) (WIDTH * 0.9), (int) (HEIGHT * 0.86), 520, 520, $glow);

    imagecopy($image, $layer, 0, 0, 0, 0, WIDTH, HEIGHT);
    imagedestroy($layer);
}

function drawLogo(\GdImage $image, string $logoPath): void
{
    if (!is_file($logoPath)) {
        return;
    }
    $logo = @imagecreatefrompng($logoPath);
    if (!$logo instanceof \GdImage) {
        return;
    }

    $targetWidth = 460;
    $scale = $targetWidth / imagesx($logo);
    $targetHeight = (int) round(imagesy($logo) * $scale);

    imagealphablending($image, true);
    imagecopyresampled(
        $image,
        $logo,
        (int) ((WIDTH - $targetWidth) / 2),
        210,
        0,
        0,
        $targetWidth,
        $targetHeight,
        imagesx($logo),
        imagesy($logo)
    );
    imagedestroy($logo);
}

/**
 * Cercle de lecture central avec triangle blanc.
 */
function drawPlayButton(\GdImage $image): void
{
    $cx = (int) (WIDTH / 2);
    $cy = (int) (HEIGHT * 0.44);

    $halo = imagecolorallocatealpha($image, 255, 255, 255, 104);
    imagefilledellipse($image, $cx, $cy, 380, 380, $halo);

    $disc = imagecolorallocate($image, 255, 255, 255);
    imagefilledellipse($image, $cx, $cy, 280, 280, $disc);

    $triangle = imagecolorallocate($image, NAVY[0], NAVY[1], NAVY[2]);
    imagefilledpolygon(
        $image,
        [$cx - 45, $cy - 72, $cx - 45, $cy + 72, $cx + 82, $cy],
        $triangle
    );
}

/**
 * @param array{num: int, kicker: string, title: string, footer?: string} $meta
 */
function drawTexts(\GdImage $image, ?string $font, array $meta): void
{
    $white = imagecolorallocate($image, 255, 255, 255);
    $soft = imagecolorallocate($image, 214, 231, 245);

    if ($font === null) {
        imagestring($image, 5, 60, (int) (HEIGHT * 0.7), $meta['title'], $white);

        return;
    }

    // Pastille numérotée
    $badgeY = (int) (HEIGHT * 0.62);
    $badge = imagecolorallocatealpha($image, 255, 255, 255, 96);
    imagefilledellipse($image, (int) (WIDTH / 2), $badgeY, 108, 108, $badge);
    centeredText($image, $font, 44, $badgeY + 16, (string) $meta['num'], $white);

    // Sur-titre
    centeredText($image, $font, 34, (int) (HEIGHT * 0.685), mb_strtoupper($meta['kicker']), $soft, 6);

    // Titre principal (multi-lignes)
    $lines = explode("\n", $meta['title']);
    $y = (int) (HEIGHT * 0.755);
    foreach ($lines as $line) {
        centeredText($image, $font, 68, $y, $line, $white);
        $y += 96;
    }

    // Pied de page
    centeredText($image, $font, 30, (int) (HEIGHT * 0.93), $meta['footer'] ?? 'VIDÉO TUTORIEL', $soft, 8);
}

function centeredText(
    \GdImage $image,
    string $font,
    int $size,
    int $y,
    string $text,
    int $color,
    int $letterSpacing = 0
): void {
    if ($letterSpacing > 0) {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $totalWidth = 0;
        $widths = [];
        foreach ($chars as $char) {
            $box = imagettfbbox($size, 0, $font, $char);
            $charWidth = $box[2] - $box[0];
            $widths[] = $charWidth;
            $totalWidth += $charWidth + $letterSpacing;
        }
        $totalWidth -= $letterSpacing;
        $x = (int) ((WIDTH - $totalWidth) / 2);
        foreach ($chars as $index => $char) {
            imagettftext($image, $size, 0, $x, $y, $color, $font, $char);
            $x += $widths[$index] + $letterSpacing;
        }

        return;
    }

    $box = imagettfbbox($size, 0, $font, $text);
    $x = (int) ((WIDTH - ($box[2] - $box[0])) / 2);
    imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
}
