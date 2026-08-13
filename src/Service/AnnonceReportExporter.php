<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Export du rapport annonces en Excel, Word et PowerPoint.
 */
final class AnnonceReportExporter
{
    public function __construct(
        private readonly Environment $twig,
        private readonly AnnonceChartRenderer $chartRenderer,
        private readonly ReportOfficeExporter $office,
    ) {
    }

    /**
     * @param array<string, mixed> $report
     */
    public function excel(array $report): Response
    {
        return $this->office->download(
            $this->office->buildXlsx($this->buildSheets($report)),
            $this->office->filename('soukexpat-rapport-annonces', (string) $report['periodLabel'], 'xlsx'),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /**
     * @param array<string, mixed> $report
     */
    public function word(array $report): Response
    {
        $html = $this->twig->render('admin/annonce/export_document.html.twig', [
            'report' => $report,
            'periodLabel' => $report['periodLabel'],
        ]);
        $charts = $this->chartRenderer->renderAll($report);
        $binary = $this->office->buildDocx(
            $html,
            'SoukExpat — Rapport annonces — ' . $report['periodLabel'],
            $charts
        );

        return $this->office->download(
            $binary,
            $this->office->filename('soukexpat-rapport-annonces', (string) $report['periodLabel'], 'docx'),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
    }

    /**
     * @param array<string, mixed> $report
     */
    public function powerpoint(array $report): Response
    {
        $charts = $this->chartRenderer->renderAll($report);
        /** @var array<string, mixed> $k */
        $k = $report['kpis'];
        $textSlides = [
            [
                'title' => 'Indicateurs clés',
                'lines' => [
                    'Total annonces : ' . $k['total'],
                    'En ligne (validées) : ' . $k['approved'] . ' — En attente : ' . $k['pending'],
                    'Refusées : ' . $k['rejected'] . ' — Brouillons : ' . $k['draft'],
                    'Créées sur la période : ' . $k['createdInPeriod'],
                    'Avec photo : ' . $k['withPhotos'] . ' / sans photo : ' . $k['withoutPhotos'],
                    'Prix moyen (validées) : ' . ($k['avgPrice'] !== null ? number_format((float) $k['avgPrice'], 0, ',', ' ') . ' MAD' : '—'),
                    'Taux validation : ' . ($k['approvalRate'] !== null ? $k['approvalRate'] . ' %' : '—'),
                    'Délai modération moyen : ' . ($k['avgModerationHours'] !== null ? $k['avgModerationHours'] . ' h' : '—'),
                ],
            ],
        ];

        foreach (array_slice($report['insights'], 0, 6) as $insight) {
            if (!isset($textSlides[1])) {
                $textSlides[] = ['title' => 'Points clés', 'lines' => []];
            }
            $textSlides[1]['lines'][] = (string) $insight;
        }

        $binary = $this->office->buildPptx(
            'SoukExpat — Rapport annonces (' . $report['periodLabel'] . ')',
            $textSlides,
            $charts
        );

        return $this->office->download(
            $binary,
            $this->office->filename('soukexpat-rapport-annonces', (string) $report['periodLabel'], 'pptx'),
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        );
    }

    /**
     * @param array<string, mixed> $report
     * @return list<array{title: string, rows: list<list<scalar|null>>}>
     */
    private function buildSheets(array $report): array
    {
        /** @var array<string, mixed> $k */
        $k = $report['kpis'];
        /** @var array<string, mixed> $c */
        $c = $report['charts'];
        /** @var array<string, mixed> $t */
        $t = $report['tables'];
        /** @var array<string, string> $x */
        $x = $report['explanations'] ?? [];

        $meta = [
            ['Rapport annonces SoukExpat', ''],
            ['Periode', $report['periodLabel']],
            ['Depuis', $this->fmtDate($report['since'])],
            ['Genere le', $this->fmtDateTime($report['generatedAt'])],
            [''],
            ['Guide', ''],
        ];
        foreach ($x as $label => $text) {
            if ($text !== '') {
                $meta[] = [ucfirst($label), $text];
            }
        }
        $meta[] = [''];
        $meta[] = ['Indicateur', 'Valeur', 'Detail'];
        $meta[] = ['Total annonces', $k['total'], 'tous statuts'];
        $meta[] = ['Validées', $k['approved'], $k['pending'] . ' en attente'];
        $meta[] = ['Refusées', $k['rejected'], $k['draft'] . ' brouillon(s)'];
        $meta[] = ['Créées (période)', $k['createdInPeriod'], ''];
        $meta[] = ['Taux validation (%)', $k['approvalRate'], ''];
        $meta[] = ['Avec photo', $k['withPhotos'], $k['withoutPhotos'] . ' sans photo'];
        $meta[] = ['Prix moyen (MAD)', $k['avgPrice'], ''];
        $meta[] = ['Prix min (MAD)', $k['minPrice'], ''];
        $meta[] = ['Prix max (MAD)', $k['maxPrice'], ''];
        $meta[] = ['Délai modération (h)', $k['avgModerationHours'], 'moyenne période'];

        $insights = [['Points clés']];
        foreach ($report['insights'] as $insight) {
            $insights[] = [$insight];
        }

        $trends = [['Mois', 'Créées', 'Validées', 'Refusées']];
        foreach ($c['monthLabels'] as $i => $label) {
            $trends[] = [
                $label,
                $c['createdByMonth'][$i] ?? 0,
                $c['approvedByMonth'][$i] ?? 0,
                $c['rejectedByMonth'][$i] ?? 0,
            ];
        }

        $status = [['Statut', 'Nombre']];
        foreach ($c['statusLabels'] as $i => $label) {
            $status[] = [$label, $c['statusValues'][$i] ?? 0];
        }

        $prices = [['Fourchette (MAD)', 'Annonces']];
        foreach ($c['priceBuckets']['labels'] as $i => $label) {
            $prices[] = [$label, $c['priceBuckets']['values'][$i] ?? 0];
        }

        $categories = [['Catégorie', 'Total', 'Validées', 'Attente', 'Refusées']];
        foreach ($t['categoriesBreakdown'] as $row) {
            $categories[] = [$row['name'], $row['total'], $row['approved'], $row['pending'], $row['rejected']];
        }

        $cities = [['Ville', 'Total', 'En ligne']];
        foreach ($t['citiesBreakdown'] as $row) {
            $cities[] = [$row['name'], $row['total'], $row['approved']];
        }

        $sellers = [['Vendeur', 'Email', 'Publiées', 'Validées', 'Attente', 'Refusées', 'Prix moy. MAD']];
        foreach ($t['sellers'] ?? [] as $row) {
            $sellers[] = [
                $row['name'],
                $row['email'],
                $row['total'],
                $row['approved'],
                $row['pending'],
                $row['rejected'],
                $row['avgPrice'],
            ];
        }

        $annonces = [['ID', 'Titre', 'Statut', 'Prix MAD', 'Ville', 'Catégorie', 'Vendeur', 'Email', 'Date', 'Photo']];
        foreach ($t['recent'] as $row) {
            $annonces[] = [
                $row['id'],
                $row['title'],
                $row['status'],
                $row['price'],
                $row['city'],
                $row['category'],
                $row['seller'],
                $row['sellerEmail'],
                $row['createdAt'],
                $row['hasPhoto'],
            ];
        }

        $pending = [['ID', 'Titre', 'Prix MAD', 'Ville', 'Vendeur', 'Email', 'Date']];
        foreach ($t['pending'] ?? [] as $row) {
            $pending[] = [$row['id'], $row['title'], $row['price'], $row['city'], $row['seller'], $row['sellerEmail'], $row['createdAt']];
        }

        return [
            ['title' => 'Synthèse', 'rows' => $meta],
            ['title' => 'Insights', 'rows' => $insights],
            ['title' => 'Tendances', 'rows' => $trends],
            ['title' => 'Statuts', 'rows' => $status],
            ['title' => 'Prix', 'rows' => $prices],
            ['title' => 'Catégories', 'rows' => $categories],
            ['title' => 'Villes', 'rows' => $cities],
            ['title' => 'Vendeurs', 'rows' => $sellers],
            ['title' => 'En attente', 'rows' => $pending],
            ['title' => 'Toutes annonces', 'rows' => $annonces],
        ];
    }

    private function fmtDate(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('d/m/Y') : (string) $value;
    }

    private function fmtDateTime(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('d/m/Y H:i') : (string) $value;
    }
}
