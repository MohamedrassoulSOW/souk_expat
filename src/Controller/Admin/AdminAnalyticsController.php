<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\AnalyticsReportExporter;
use App\Service\SiteAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class AdminAnalyticsController extends AbstractController
{
    #[Route('/admin/analytics', name: 'admin_analytics', methods: ['GET'])]
    public function index(Request $request, SiteAnalyticsService $analytics): Response
    {
        [$value, $unit] = $this->resolvePeriod($request);
        $report = $analytics->buildReport($value, $unit);

        return $this->render('admin/analytics/index.html.twig', [
            'report' => $report,
            'periodValue' => $value,
            'periodUnit' => $unit,
            'periodLabel' => $report['periodLabel'],
        ]);
    }

    #[Route('/admin/analytics/export/{format}', name: 'admin_analytics_export', methods: ['GET'], requirements: ['format' => 'excel|word|pdf'])]
    public function export(
        string $format,
        Request $request,
        SiteAnalyticsService $analytics,
        AnalyticsReportExporter $exporter,
    ): Response {
        [$value, $unit] = $this->resolvePeriod($request);
        $report = $analytics->buildReport($value, $unit);

        return match ($format) {
            'excel' => $exporter->excel($report),
            'word' => $exporter->word($report),
            default => $exporter->pdf($report),
        };
    }

    /**
     * @return array{0: int, 1: 'days'|'months'}
     */
    private function resolvePeriod(Request $request): array
    {
        $unit = (string) $request->query->get('unit', 'months');
        if (!\in_array($unit, ['days', 'months'], true)) {
            $unit = 'months';
        }

        $value = $request->query->getInt('value', $unit === 'days' ? 90 : 12);
        if ($unit === 'days') {
            $value = max(1, min(3650, $value));
        } else {
            $value = max(1, min(60, $value));
        }

        if ($request->query->has('months') && !$request->query->has('value')) {
            $unit = 'months';
            $value = max(1, min(60, $request->query->getInt('months', 12)));
        }

        return [$value, $unit];
    }
}
