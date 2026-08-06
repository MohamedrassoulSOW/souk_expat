<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Génère des diagrammes PNG (GD) pour l’export / impression du rapport analytics.
 */
final class AnalyticsChartRenderer
{
    private const BRAND = [75, 121, 161];
    private const BRAND_DARK = [27, 46, 75];
    private const GREEN = [25, 135, 84];
    private const ORANGE = [253, 126, 20];
    private const RED = [220, 53, 69];
    private const TEAL = [13, 148, 136];
    private const GRAY = [148, 163, 184];

    /**
     * @param array<string, mixed> $report
     * @return list<array{title: string, png: string}>
     */
    public function renderAll(array $report): array
    {
        if (!\function_exists('imagecreatetruecolor')) {
            return [];
        }

        /** @var array<string, mixed> $c */
        $c = $report['charts'];
        $charts = [];

        $monthLabels = array_map(
            static fn ($ym) => self::monthPretty((string) $ym),
            $c['monthLabels'] ?? []
        );

        $charts[] = [
            'title' => 'Tendances d’activité',
            'png' => $this->lineChart($monthLabels, [
                ['label' => 'Annonces', 'values' => $c['annoncesByMonth'] ?? [], 'color' => self::BRAND],
                ['label' => 'Validations', 'values' => $c['approvalsByMonth'] ?? [], 'color' => self::GREEN],
                ['label' => 'Messages', 'values' => $c['messagesByMonth'] ?? [], 'color' => self::TEAL],
                ['label' => 'Contacts', 'values' => $c['contactsByMonth'] ?? [], 'color' => self::ORANGE],
            ], 720, 320),
        ];

        $charts[] = [
            'title' => 'Annonces par statut',
            'png' => $this->pieChart(
                $c['statusLabels'] ?? [],
                $c['statusValues'] ?? [],
                [self::GREEN, self::ORANGE, self::RED, self::GRAY],
                420,
                320
            ),
        ];

        $charts[] = [
            'title' => 'Top catégories',
            'png' => $this->barChart(
                $c['categories']['labels'] ?? [],
                $c['categories']['values'] ?? [],
                self::BRAND,
                560,
                300,
                false
            ),
        ];

        $charts[] = [
            'title' => 'Top villes',
            'png' => $this->barChart(
                $c['cities']['labels'] ?? [],
                $c['cities']['values'] ?? [],
                self::BRAND_DARK,
                560,
                300,
                false
            ),
        ];

        $charts[] = [
            'title' => 'Top vendeurs',
            'png' => $this->barChart(
                $c['topSellers']['labels'] ?? [],
                $c['topSellers']['values'] ?? [],
                self::TEAL,
                560,
                340,
                true
            ),
        ];

        $charts[] = [
            'title' => 'Messages contact',
            'png' => $this->pieChart(
                $c['contactsSplit']['labels'] ?? [],
                $c['contactsSplit']['values'] ?? [],
                [self::ORANGE, self::GREEN],
                420,
                300
            ),
        ];

        return array_values(array_filter($charts, static fn (array $chart) => $chart['png'] !== ''));
    }

    private static function monthPretty(string $ym): string
    {
        $parts = explode('-', $ym);
        if (\count($parts) < 2) {
            return $ym;
        }
        $names = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        $m = (int) $parts[1];

        return ($names[$m - 1] ?? $parts[1]) . ' ' . substr($parts[0], -2);
    }

    /**
     * @param list<string> $labels
     * @param list<array{label: string, values: list<int|float>, color: array{0:int,1:int,2:int}}> $series
     */
    private function lineChart(array $labels, array $series, int $width, int $height): string
    {
        if ($labels === []) {
            return '';
        }

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return '';
        }
        imagealphablending($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        $axis = imagecolorallocate($img, 148, 163, 184);
        $text = imagecolorallocate($img, 51, 65, 85);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $padL = 48;
        $padR = 20;
        $padT = 28;
        $padB = 56;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;

        $max = 1.0;
        foreach ($series as $s) {
            foreach ($s['values'] as $v) {
                $max = max($max, (float) $v);
            }
        }

        imageline($img, $padL, $padT, $padL, $padT + $plotH, $axis);
        imageline($img, $padL, $padT + $plotH, $padL + $plotW, $padT + $plotH, $axis);

        $n = \count($labels);
        $stepX = $n > 1 ? $plotW / ($n - 1) : $plotW;

        foreach ($series as $s) {
            $color = imagecolorallocate($img, $s['color'][0], $s['color'][1], $s['color'][2]);
            $points = [];
            foreach ($labels as $i => $_) {
                $v = (float) ($s['values'][$i] ?? 0);
                $x = (int) round($padL + $i * $stepX);
                $y = (int) round($padT + $plotH - ($v / $max) * $plotH);
                $points[] = [$x, $y];
                imagefilledellipse($img, $x, $y, 6, 6, $color);
            }
            for ($i = 0; $i < \count($points) - 1; ++$i) {
                imageline($img, $points[$i][0], $points[$i][1], $points[$i + 1][0], $points[$i + 1][1], $color);
            }
        }

        foreach ($labels as $i => $label) {
            $x = (int) round($padL + $i * $stepX - 12);
            imagestring($img, 2, max(0, $x), $padT + $plotH + 8, $this->latin($label), $text);
        }

        $legendX = $padL;
        foreach ($series as $s) {
            $color = imagecolorallocate($img, $s['color'][0], $s['color'][1], $s['color'][2]);
            imagefilledrectangle($img, $legendX, 8, $legendX + 10, 18, $color);
            imagestring($img, 2, $legendX + 14, 6, $this->latin($s['label']), $text);
            $legendX += 110;
        }

        return $this->png($img);
    }

    /**
     * @param list<string> $labels
     * @param list<int|float> $values
     * @param array{0:int,1:int,2:int} $rgb
     */
    private function barChart(array $labels, array $values, array $rgb, int $width, int $height, bool $horizontal): string
    {
        if ($labels === []) {
            return '';
        }

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return '';
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        $axis = imagecolorallocate($img, 148, 163, 184);
        $text = imagecolorallocate($img, 51, 65, 85);
        $bar = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $n = \count($labels);
        $max = max(1.0, ...array_map(static fn ($v) => (float) $v, $values ?: [0]));

        if ($horizontal) {
            $padL = 110;
            $padR = 24;
            $padT = 16;
            $padB = 24;
            $plotW = $width - $padL - $padR;
            $plotH = $height - $padT - $padB;
            $rowH = $plotH / max(1, $n);

            for ($i = 0; $i < $n; ++$i) {
                $v = (float) ($values[$i] ?? 0);
                $bw = (int) round(($v / $max) * $plotW);
                $y = (int) round($padT + $i * $rowH + $rowH * 0.2);
                $h = (int) max(8, $rowH * 0.6);
                imagefilledrectangle($img, $padL, $y, $padL + $bw, $y + $h, $bar);
                $label = $this->latin(mb_substr((string) $labels[$i], 0, 16));
                imagestring($img, 2, 8, $y + (int) ($h / 4), $label, $text);
            }
            imageline($img, $padL, $padT, $padL, $padT + $plotH, $axis);
        } else {
            $padL = 36;
            $padR = 16;
            $padT = 20;
            $padB = 48;
            $plotW = $width - $padL - $padR;
            $plotH = $height - $padT - $padB;
            $colW = $plotW / max(1, $n);

            imageline($img, $padL, $padT + $plotH, $padL + $plotW, $padT + $plotH, $axis);

            for ($i = 0; $i < $n; ++$i) {
                $v = (float) ($values[$i] ?? 0);
                $bh = (int) round(($v / $max) * $plotH);
                $x = (int) round($padL + $i * $colW + $colW * 0.15);
                $w = (int) max(8, $colW * 0.7);
                $y = $padT + $plotH - $bh;
                imagefilledrectangle($img, $x, $y, $x + $w, $padT + $plotH, $bar);
                $label = $this->latin(mb_substr((string) $labels[$i], 0, 10));
                imagestring($img, 1, $x, $padT + $plotH + 8, $label, $text);
            }
        }

        return $this->png($img);
    }

    /**
     * @param list<string> $labels
     * @param list<int|float> $values
     * @param list<array{0:int,1:int,2:int}> $colors
     */
    private function pieChart(array $labels, array $values, array $colors, int $width, int $height): string
    {
        if ($labels === []) {
            return '';
        }

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return '';
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        $text = imagecolorallocate($img, 51, 65, 85);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $total = array_sum(array_map(static fn ($v) => (float) $v, $values));
        if ($total <= 0) {
            imagestring($img, 3, 20, (int) ($height / 2), 'Aucune donnee', $text);

            return $this->png($img);
        }

        $cx = (int) ($width * 0.38);
        $cy = (int) ($height / 2);
        $radius = (int) min($cx - 20, $cy - 20);
        $start = 0.0;

        foreach ($values as $i => $value) {
            $v = (float) $value;
            if ($v <= 0) {
                continue;
            }
            $angle = ($v / $total) * 360.0;
            $rgb = $colors[$i % \count($colors)];
            $color = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            imagefilledarc(
                $img,
                $cx,
                $cy,
                $radius * 2,
                $radius * 2,
                (int) round($start),
                (int) round($start + $angle),
                $color,
                IMG_ARC_PIE
            );
            $start += $angle;
        }

        // trou doughnut
        $hole = imagecolorallocate($img, 255, 255, 255);
        imagefilledellipse($img, $cx, $cy, (int) ($radius * 0.9), (int) ($radius * 0.9), $hole);

        $legendX = (int) ($width * 0.68);
        $legendY = 40;
        foreach ($labels as $i => $label) {
            $rgb = $colors[$i % \count($colors)];
            $color = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            imagefilledrectangle($img, $legendX, $legendY, $legendX + 12, $legendY + 12, $color);
            $line = $this->latin(sprintf('%s (%s)', mb_substr((string) $label, 0, 18), $values[$i] ?? 0));
            imagestring($img, 2, $legendX + 18, $legendY - 1, $line, $text);
            $legendY += 22;
        }

        return $this->png($img);
    }

    /** @param \GdImage $img */
    private function png(\GdImage $img): string
    {
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private function latin(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return $converted;
    }
}
