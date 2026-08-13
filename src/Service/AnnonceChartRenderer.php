<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Diagrammes PNG (GD) pour l’export du rapport annonces.
 */
final class AnnonceChartRenderer
{
    private const BRAND = [75, 121, 161];
    private const BRAND_DARK = [27, 46, 75];
    private const GREEN = [25, 135, 84];
    private const ORANGE = [253, 126, 20];
    private const RED = [220, 53, 69];
    private const TEAL = [13, 148, 136];
    private const GRAY = [148, 163, 184];

    public function __construct(
        private readonly AnalyticsChartRenderer $analyticsCharts,
    ) {
    }

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
            'title' => 'Volume d’annonces',
            'png' => $this->analyticsCharts->renderLineChartPublic($monthLabels, [
                ['label' => 'Créées', 'values' => $c['createdByMonth'] ?? [], 'color' => self::BRAND],
                ['label' => 'Validées', 'values' => $c['approvedByMonth'] ?? [], 'color' => self::GREEN],
                ['label' => 'Refusées', 'values' => $c['rejectedByMonth'] ?? [], 'color' => self::RED],
            ], 720, 320),
        ];

        $charts[] = [
            'title' => 'Répartition par statut',
            'png' => $this->analyticsCharts->renderPieChartPublic(
                $c['statusLabels'] ?? [],
                $c['statusValues'] ?? [],
                [self::GREEN, self::ORANGE, self::RED, self::GRAY],
                420,
                320
            ),
        ];

        $charts[] = [
            'title' => 'Top catégories (validées)',
            'png' => $this->analyticsCharts->renderBarChartPublic(
                $c['categories']['labels'] ?? [],
                $c['categories']['values'] ?? [],
                self::BRAND,
                560,
                300,
                false
            ),
        ];

        $charts[] = [
            'title' => 'Top villes (validées)',
            'png' => $this->analyticsCharts->renderBarChartPublic(
                $c['cities']['labels'] ?? [],
                $c['cities']['values'] ?? [],
                self::BRAND_DARK,
                560,
                300,
                false
            ),
        ];

        $charts[] = [
            'title' => 'Fourchettes de prix (MAD)',
            'png' => $this->analyticsCharts->renderBarChartPublic(
                $c['priceBuckets']['labels'] ?? [],
                $c['priceBuckets']['values'] ?? [],
                self::TEAL,
                560,
                300,
                false
            ),
        ];

        $charts[] = [
            'title' => 'Annonces avec photo',
            'png' => $this->analyticsCharts->renderPieChartPublic(
                $c['photos']['labels'] ?? [],
                $c['photos']['values'] ?? [],
                [self::BRAND, self::GRAY],
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
}
