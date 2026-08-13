<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\AnnonceReportExporter;
use App\Service\AnnonceReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class AdminAnnonceReportController extends AbstractController
{
    #[Route('/admin/annonces/rapport', name: 'admin_annonces_rapport', methods: ['GET'])]
    public function index(Request $request, AnnonceReportService $reportService): Response
    {
        [$value, $unit] = $this->resolvePeriod($request);
        $report = $reportService->buildReport($value, $unit);

        return $this->render('admin/annonce/rapport.html.twig', [
            'report' => $report,
            'periodValue' => $value,
            'periodUnit' => $unit,
            'periodLabel' => $report['periodLabel'],
        ]);
    }

    #[Route('/admin/annonces/rapport/export/{format}', name: 'admin_annonces_rapport_export', methods: ['GET'], requirements: ['format' => 'excel|word|powerpoint'])]
    public function export(
        string $format,
        Request $request,
        AnnonceReportService $reportService,
        AnnonceReportExporter $exporter,
    ): Response {
        [$value, $unit] = $this->resolvePeriod($request);
        $report = $reportService->buildExportReport($value, $unit);

        return match ($format) {
            'excel' => $exporter->excel($report),
            'word' => $exporter->word($report),
            default => $exporter->powerpoint($report),
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

        return [$value, $unit];
    }
}
