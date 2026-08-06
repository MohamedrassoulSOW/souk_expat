<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Export du rapport analytics en Excel (.xlsx), Word (.docx) et PDF (sans libs externes).
 */
final class AnalyticsReportExporter
{
    public function __construct(
        private readonly Environment $twig,
        private readonly AnalyticsChartRenderer $chartRenderer,
    ) {
    }

    /**
     * @param array<string, mixed> $report
     */
    public function excel(array $report): Response
    {
        $binary = $this->buildXlsx($this->buildSheets($report));

        return $this->download(
            $binary,
            $this->filename($report, 'xlsx'),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /**
     * @param array<string, mixed> $report
     */
    public function word(array $report): Response
    {
        $htmlBody = $this->twig->render('admin/analytics/export_document.html.twig', [
            'report' => $report,
            'periodLabel' => $report['periodLabel'],
        ]);
        $charts = $this->chartRenderer->renderAll($report);
        $binary = $this->buildDocx($htmlBody, (string) $report['periodLabel'], $charts);

        return $this->download(
            $binary,
            $this->filename($report, 'docx'),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
    }

    /**
     * @param array<string, mixed> $report
     */
    public function pdf(array $report): Response
    {
        $charts = $this->chartRenderer->renderAll($report);
        $binary = $this->buildPdf($report, $charts);

        return $this->download(
            $binary,
            $this->filename($report, 'pdf'),
            'application/pdf'
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

        $meta = [
            ['Rapport SoukExpat', ''],
            ['Periode', $report['periodLabel']],
            ['Depuis', $this->fmtDate($report['since'])],
            ['Genere le', $this->fmtDateTime($report['generatedAt'])],
            [''],
            ['Guide de lecture', ''],
        ];
        /** @var array<string, string> $x */
        $x = $report['explanations'] ?? [];
        foreach ([
            'Presentation' => $x['intro'] ?? '',
            'Comment lire' => $x['howToRead'] ?? '',
            'Indicateurs' => $x['kpis'] ?? '',
            'Tendances' => $x['trends'] ?? '',
            'Statuts' => $x['status'] ?? '',
            'Categories' => $x['categories'] ?? '',
            'Villes' => $x['cities'] ?? '',
            'Vendeurs' => $x['sellers'] ?? '',
            'Contacts' => $x['contacts'] ?? '',
            'Conversations' => $x['threads'] ?? '',
        ] as $label => $text) {
            if ($text !== '') {
                $meta[] = [$label, $text];
            }
        }
        $meta[] = [''];
        $meta[] = ['Indicateur', 'Valeur', 'Detail', 'Explication'];
        $meta[] = ['Utilisateurs', $k['users'], $k['sellersActive'] . ' vendeurs actifs', $x['kpiUsers'] ?? ''];
        $meta[] = ['Annonces validees', $k['approved'], $k['pending'] . ' en attente', $x['kpiApproved'] ?? ''];
        $meta[] = ['Taux validation (%)', $k['approvalRate'], $k['rejected'] . ' refusee(s)', $x['kpiApprovalRate'] ?? ''];
        $meta[] = ['Messages chat', $k['messages'], $k['threads'] . ' conversation(s)', $x['kpiMessages'] ?? ''];
        $meta[] = ['Contacts ouverts', $k['contactsOpen'], '', $x['kpiContacts'] ?? ''];
        $meta[] = ['Contacts traites', $k['contactsDone'], '', $x['kpiContacts'] ?? ''];
        $meta[] = ['WhatsApp profil', $k['usersWithWhatsapp'], '', $x['kpiWhatsapp'] ?? ''];
        $meta[] = ['Prix moyen (MAD)', $k['avgPrice'], '', $x['kpiAvgPrice'] ?? ''];
        $meta[] = ['Prix min (MAD)', $k['minPrice'], '', $x['kpiPriceRange'] ?? ''];
        $meta[] = ['Prix max (MAD)', $k['maxPrice'], '', ''];
        $meta[] = ['Categories', $k['categories'], $k['cities'] . ' ville(s)', $x['kpiCategories'] ?? ''];
        $meta[] = ['Total annonces', $k['annoncesTotal'], 'tous statuts', $x['kpiTotal'] ?? ''];

        $insights = [['Points cles / insights']];
        if (!empty($x['insights'])) {
            $insights[] = [$x['insights']];
        }
        foreach ($report['insights'] as $insight) {
            $insights[] = [$insight];
        }

        $trends = [['Mois', 'Annonces', 'Messages', 'Contacts', 'Validations']];
        foreach ($c['monthLabels'] as $i => $label) {
            $trends[] = [
                $label,
                $c['annoncesByMonth'][$i] ?? 0,
                $c['messagesByMonth'][$i] ?? 0,
                $c['contactsByMonth'][$i] ?? 0,
                $c['approvalsByMonth'][$i] ?? 0,
            ];
        }

        $status = [['Statut', 'Nombre']];
        foreach ($c['statusLabels'] as $i => $label) {
            $status[] = [$label, $c['statusValues'][$i] ?? 0];
        }

        $categories = [['Categorie', 'Annonces']];
        foreach ($c['categories']['labels'] as $i => $label) {
            $categories[] = [$label, $c['categories']['values'][$i] ?? 0];
        }

        $cities = [['Ville', 'Annonces']];
        foreach ($c['cities']['labels'] as $i => $label) {
            $cities[] = [$label, $c['cities']['values'][$i] ?? 0];
        }

        $sellers = [['Vendeur', 'Email', 'Annonces', 'Prix moyen (MAD)']];
        foreach ($t['topSellers'] as $row) {
            $sellers[] = [$row['name'], $row['email'], $row['total'], $row['avgPrice']];
        }

        $threads = [['Annonce', 'Acheteur', 'Vendeur', 'Messages']];
        foreach ($t['busiestThreads'] as $row) {
            $threads[] = [$row['annonce'], $row['buyer'], $row['seller'], $row['messages']];
        }

        $annonces = [['Titre', 'Vendeur', 'Statut', 'Prix (MAD)', 'Date']];
        foreach ($t['recentAnnonces'] as $row) {
            $annonces[] = [$row['title'], $row['seller'], $row['status'], $row['price'], $row['createdAt']];
        }

        return [
            ['title' => 'Synthese', 'rows' => $meta],
            ['title' => 'Tendances', 'rows' => $trends],
            ['title' => 'Statuts', 'rows' => $status],
            ['title' => 'Categories', 'rows' => $categories],
            ['title' => 'Villes', 'rows' => $cities],
            ['title' => 'Vendeurs', 'rows' => $sellers],
            ['title' => 'Conversations', 'rows' => $threads],
            ['title' => 'Annonces', 'rows' => $annonces],
            ['title' => 'Insights', 'rows' => $insights],
        ];
    }

    /**
     * @param list<array{title: string, rows: list<list<scalar|null>>}> $sheets
     */
    private function buildXlsx(array $sheets): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sxlsx');
        if ($tmp === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire.');
        }
        $zipPath = $tmp . '.xlsx';
        @unlink($tmp);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier Excel.');
        }

        $contentTypesOverrides = '';
        $workbookSheets = '';
        $workbookRels = '';
        $sheetXmls = [];

        foreach ($sheets as $i => $sheet) {
            $sheetId = $i + 1;
            $sheetFile = 'sheet' . $sheetId . '.xml';
            $sheetXmls[$sheetFile] = $this->sheetXml($sheet['rows']);
            $safeTitle = $this->safeSheetName($sheet['title'], $sheetId);
            $workbookSheets .= sprintf(
                '<sheet name="%s" sheetId="%d" r:id="rId%d"/>',
                htmlspecialchars($safeTitle, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                $sheetId,
                $sheetId
            );
            $workbookRels .= sprintf(
                '<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/%s"/>',
                $sheetId,
                $sheetFile
            );
            $contentTypesOverrides .= sprintf(
                '<Override PartName="/xl/worksheets/%s" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
                $sheetFile
            );
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $contentTypesOverrides
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $workbookSheets . '</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $workbookRels
            . '</Relationships>');

        foreach ($sheetXmls as $file => $xml) {
            $zip->addFromString('xl/worksheets/' . $file, $xml);
        }

        $zip->close();
        $binary = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return $binary;
    }

    /**
     * @param list<list<scalar|null>> $rows
     */
    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rIndex => $row) {
            $xml .= '<row r="' . ($rIndex + 1) . '">';
            foreach ($row as $cIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $col = $this->colLetter($cIndex) . ($rIndex + 1);
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $col . '"><v>' . $value . '</v></c>';
                } else {
                    $text = htmlspecialchars((string) $value, ENT_XML1, 'UTF-8');
                    $xml .= '<c r="' . $col . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    /**
     * @param list<array{title: string, png: string}> $charts
     */
    private function buildDocx(string $htmlBody, string $periodLabel, array $charts = []): string
    {
        $body = $this->htmlToWordMl($htmlBody);
        $rels = '';
        $contentTypesExtra = '<Default Extension="png" ContentType="image/png"/>';

        if ($charts !== []) {
            $body .= '<w:p><w:r><w:rPr><w:b/><w:sz w:val="28"/></w:rPr><w:t>Diagrammes</w:t></w:r></w:p>';
            foreach ($charts as $i => $chart) {
                $imgId = $i + 1;
                $rId = 'rIdImg' . $imgId;
                $cx = 5486400; // ~6"
                $size = @getimagesizefromstring($chart['png']);
                $ratio = ($size && $size[0] > 0) ? ($size[1] / $size[0]) : 0.5;
                $cy = (int) round($cx * $ratio);

                $body .= '<w:p><w:r><w:rPr><w:b/><w:sz w:val="24"/></w:rPr><w:t>'
                    . htmlspecialchars($chart['title'], ENT_XML1, 'UTF-8')
                    . '</w:t></w:r></w:p>';
                $body .= $this->wordImageDrawing($rId, $imgId, $cx, $cy);
                $rels .= sprintf(
                    '<Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image%d.png"/>',
                    $rId,
                    $imgId
                );
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sdocx');
        if ($tmp === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire.');
        }
        $zipPath = $tmp . '.docx';
        @unlink($tmp);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier Word.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . $contentTypesExtra
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>');

        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>');

        foreach ($charts as $i => $chart) {
            $zip->addFromString('word/media/image' . ($i + 1) . '.png', $chart['png']);
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Rapport SoukExpat — ' . htmlspecialchars($periodLabel, ENT_XML1, 'UTF-8') . '</dc:title>'
            . '<dc:creator>SoukExpat</dc:creator>'
            . '<cp:lastModifiedBy>SoukExpat</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>');

        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
            . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<w:body>' . $body
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
            . '</w:body></w:document>');

        $zip->close();
        $binary = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return $binary;
    }

    private function wordImageDrawing(string $rId, int $docPrId, int $cx, int $cy): string
    {
        return '<w:p><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:docPr id="' . $docPrId . '" name="Chart' . $docPrId . '"/>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic><pic:nvPicPr><pic:cNvPr id="' . $docPrId . '" name="Chart' . $docPrId . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rId . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            . '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
            . '<w:p><w:r><w:t></w:t></w:r></w:p>';
    }

    private function htmlToWordMl(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $out = '';
        $root = $dom->getElementById('root') ?? $dom->documentElement;
        if (!$root) {
            return '<w:p><w:r><w:t>Rapport SoukExpat</w:t></w:r></w:p>';
        }

        foreach ($root->childNodes as $node) {
            $out .= $this->nodeToWordMl($node);
        }

        return $out !== '' ? $out : '<w:p><w:r><w:t>Rapport SoukExpat</w:t></w:r></w:p>';
    }

    private function nodeToWordMl(\DOMNode $node): string
    {
        if (!$node instanceof \DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        if ($tag === 'div' || $tag === 'section') {
            $out = '';
            foreach ($node->childNodes as $child) {
                $out .= $this->nodeToWordMl($child);
            }

            return $out;
        }

        if ($tag === 'table') {
            return $this->tableToWordMl($node);
        }

        if ($tag === 'ul') {
            $out = '';
            foreach ($node->getElementsByTagName('li') as $li) {
                $text = htmlspecialchars(trim(preg_replace('/\s+/u', ' ', $li->textContent) ?? ''), ENT_XML1, 'UTF-8');
                $out .= '<w:p><w:r><w:t xml:space="preserve">- ' . $text . '</w:t></w:r></w:p>';
            }

            return $out;
        }

        if (!\in_array($tag, ['h1', 'h2', 'h3', 'p'], true)) {
            return '';
        }

        $text = htmlspecialchars(trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? ''), ENT_XML1, 'UTF-8');
        if ($text === '') {
            return '';
        }

        $size = match ($tag) {
            'h1' => 32,
            'h2' => 26,
            'h3' => 22,
            default => 20,
        };
        $bold = \in_array($tag, ['h1', 'h2', 'h3'], true) ? '<w:b/>' : '';

        return '<w:p><w:r><w:rPr>' . $bold . '<w:sz w:val="' . $size . '"/></w:rPr>'
            . '<w:t xml:space="preserve">' . $text . '</w:t></w:r></w:p>';
    }

    private function tableToWordMl(\DOMElement $table): string
    {
        $out = '';
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $out .= '<w:tr>';
            foreach ($tr->childNodes as $cell) {
                if (!$cell instanceof \DOMElement) {
                    continue;
                }
                if (!\in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    continue;
                }
                $text = htmlspecialchars(trim(preg_replace('/\s+/u', ' ', $cell->textContent) ?? ''), ENT_XML1, 'UTF-8');
                $bold = strtolower($cell->tagName) === 'th' ? '<w:b/>' : '';
                $out .= '<w:tc><w:tcPr><w:tcW w:w="2200" w:type="dxa"/></w:tcPr>'
                    . '<w:p><w:r><w:rPr>' . $bold . '</w:rPr><w:t xml:space="preserve">' . $text . '</w:t></w:r></w:p></w:tc>';
            }
            $out .= '</w:tr>';
        }

        return '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/>'
            . '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            . '<w:left w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            . '<w:right w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            . '</w:tblBorders></w:tblPr>' . $out . '</w:tbl>'
            . '<w:p><w:r><w:t></w:t></w:r></w:p>';
    }

    /**
     * @param array<string, mixed> $report
     * @param list<array{title: string, png: string}> $charts
     */
    private function buildPdf(array $report, array $charts = []): string
    {
        /** @var list<array{0: string, 1: int, 2: bool}|array{type: 'image', title: string, jpeg: string, width: int, height: int}> $blocks */
        $blocks = [];
        $blocks[] = ['SoukExpat — Rapport analytics', 16, true];
        $blocks[] = ['Periode : ' . $report['periodLabel'], 11, false];
        $blocks[] = [
            'Du ' . $this->fmtDate($report['since']) . ' — genere le ' . $this->fmtDateTime($report['generatedAt']),
            10,
            false,
        ];
        $blocks[] = ['', 10, false];

        /** @var array<string, string> $x */
        $x = $report['explanations'] ?? [];
        if ($x !== []) {
            $blocks[] = ['Comprendre ce rapport', 13, true];
            foreach (['intro', 'howToRead'] as $key) {
                if (!empty($x[$key])) {
                    foreach ($this->wrapText((string) $x[$key], 95) as $chunk) {
                        $blocks[] = [$chunk, 9, false];
                    }
                    $blocks[] = ['', 6, false];
                }
            }
        }

        /** @var array<string, mixed> $k */
        $k = $report['kpis'];
        $blocks[] = ['Indicateurs cles', 13, true];
        if (!empty($x['kpis'])) {
            foreach ($this->wrapText((string) $x['kpis'], 95) as $chunk) {
                $blocks[] = [$chunk, 9, false];
            }
            $blocks[] = ['', 6, false];
        }
        foreach ([
            'Utilisateurs' => $k['users'] . ' (' . $k['sellersActive'] . ' vendeurs actifs)',
            'Annonces validees' => $k['approved'] . ' / ' . $k['annoncesTotal'] . ' total',
            'En attente / refusees' => $k['pending'] . ' / ' . $k['rejected'],
            'Taux validation' => ($k['approvalRate'] !== null ? $k['approvalRate'] . ' %' : '—'),
            'Messages / conversations' => $k['messages'] . ' / ' . $k['threads'],
            'Contacts' => $k['contactsOpen'] . ' ouverts, ' . $k['contactsDone'] . ' traites',
            'WhatsApp profil' => $k['usersWithWhatsapp'],
            'Prix moyen' => ($k['avgPrice'] !== null ? number_format((float) $k['avgPrice'], 0, ',', ' ') . ' MAD' : '—'),
            'Fourchette prix' => ($k['minPrice'] !== null
                ? number_format((float) $k['minPrice'], 0, ',', ' ') . ' – ' . number_format((float) $k['maxPrice'], 0, ',', ' ') . ' MAD'
                : '—'),
        ] as $label => $value) {
            $blocks[] = [$label . ' : ' . $value, 10, false];
        }

        $blocks[] = ['', 10, false];
        $blocks[] = ['Points cles', 13, true];
        if (!empty($x['insights'])) {
            foreach ($this->wrapText((string) $x['insights'], 95) as $chunk) {
                $blocks[] = [$chunk, 9, false];
            }
        }
        foreach ($report['insights'] as $insight) {
            foreach ($this->wrapText('- ' . (string) $insight, 95) as $chunk) {
                $blocks[] = [$chunk, 10, false];
            }
        }

        if ($charts !== []) {
            $blocks[] = ['', 10, false];
            $blocks[] = ['Diagrammes', 14, true];
            $chartNotes = [
                'Tendances d’activité' => $x['trends'] ?? null,
                'Annonces par statut' => $x['status'] ?? null,
                'Top catégories' => $x['categories'] ?? null,
                'Top villes' => $x['cities'] ?? null,
                'Top vendeurs' => $x['sellers'] ?? null,
                'Messages contact' => $x['contacts'] ?? null,
            ];
            foreach ($charts as $chart) {
                $jpeg = $this->pngToJpeg($chart['png']);
                if ($jpeg === null) {
                    continue;
                }
                $blocks[] = [$chart['title'], 12, true];
                foreach ($chartNotes as $titlePrefix => $note) {
                    if ($note && str_starts_with($chart['title'], $titlePrefix)) {
                        foreach ($this->wrapText((string) $note, 95) as $chunk) {
                            $blocks[] = [$chunk, 8, false];
                        }
                        break;
                    }
                }
                $blocks[] = [
                    'type' => 'image',
                    'title' => $chart['title'],
                    'jpeg' => $jpeg['data'],
                    'width' => $jpeg['width'],
                    'height' => $jpeg['height'],
                ];
                $blocks[] = ['', 8, false];
            }
        }

        /** @var array<string, mixed> $c */
        $c = $report['charts'];
        $blocks[] = ['', 10, false];
        $blocks[] = ['Tendances (mensuelles)', 13, true];
        $blocks[] = [sprintf('%-10s %10s %10s %10s %12s', 'Mois', 'Annonces', 'Messages', 'Contacts', 'Validations'), 9, true];
        foreach ($c['monthLabels'] as $i => $label) {
            $blocks[] = [sprintf(
                '%-10s %10s %10s %10s %12s',
                mb_substr((string) $label, 0, 10),
                (string) ($c['annoncesByMonth'][$i] ?? 0),
                (string) ($c['messagesByMonth'][$i] ?? 0),
                (string) ($c['contactsByMonth'][$i] ?? 0),
                (string) ($c['approvalsByMonth'][$i] ?? 0)
            ), 9, false];
        }

        $blocks[] = ['', 10, false];
        $blocks[] = ['Top vendeurs', 13, true];
        foreach ($report['tables']['topSellers'] as $row) {
            $avg = $row['avgPrice'] !== null ? number_format((float) $row['avgPrice'], 0, ',', ' ') . ' MAD' : '—';
            foreach ($this->wrapText(sprintf('%s (%s) — %s annonces — moy. %s', $row['name'], $row['email'], $row['total'], $avg), 95) as $chunk) {
                $blocks[] = [$chunk, 9, false];
            }
        }

        $blocks[] = ['', 10, false];
        $blocks[] = ['Dernieres annonces', 13, true];
        foreach ($report['tables']['recentAnnonces'] as $row) {
            foreach ($this->wrapText(sprintf(
                '%s — %s — %s — %s MAD — %s',
                $row['title'],
                $row['seller'],
                $row['status'],
                number_format((float) $row['price'], 0, ',', ' '),
                $row['createdAt']
            ), 95) as $chunk) {
                $blocks[] = [$chunk, 9, false];
            }
        }

        return $this->renderPdfBlocks($blocks);
    }

    /**
     * @return array{data: string, width: int, height: int}|null
     */
    private function pngToJpeg(string $png): ?array
    {
        $img = @imagecreatefromstring($png);
        if ($img === false) {
            return null;
        }
        $width = imagesx($img);
        $height = imagesy($img);
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($img);

            return null;
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $img, 0, 0, 0, 0, $width, $height);
        imagedestroy($img);
        ob_start();
        imagejpeg($canvas, null, 85);
        imagedestroy($canvas);
        $data = (string) ob_get_clean();

        return ['data' => $data, 'width' => $width, 'height' => $height];
    }

    /**
     * @param list<array{0: string, 1: int, 2: bool}|array{type: 'image', title: string, jpeg: string, width: int, height: int}> $blocks
     */
    private function renderPdfBlocks(array $blocks): string
    {
        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $margin = 40;
        $contentWidth = $pageWidth - (2 * $margin);
        $yStart = $pageHeight - $margin;
        $pages = [];
        $current = [];
        $y = $yStart;

        foreach ($blocks as $block) {
            if (isset($block['type']) && $block['type'] === 'image') {
                $drawW = $contentWidth;
                $drawH = $drawW * ($block['height'] / max(1, $block['width']));
                if ($drawH > 280) {
                    $drawH = 280.0;
                    $drawW = $drawH * ($block['width'] / max(1, $block['height']));
                }
                if ($y - $drawH < $margin) {
                    $pages[] = $current;
                    $current = [];
                    $y = $yStart;
                }
                $current[] = [
                    'kind' => 'image',
                    'jpeg' => $block['jpeg'],
                    'imgW' => $block['width'],
                    'imgH' => $block['height'],
                    'drawW' => $drawW,
                    'drawH' => $drawH,
                    'x' => $margin,
                    'y' => $y - $drawH,
                ];
                $y -= $drawH + 10;
                continue;
            }

            [$text, $size, $bold] = $block;
            $lineHeight = $size + 6;
            if ($y - $lineHeight < $margin) {
                $pages[] = $current;
                $current = [];
                $y = $yStart;
            }
            $current[] = ['kind' => 'text', 'text' => $text, 'size' => $size, 'bold' => $bold, 'y' => $y];
            $y -= $lineHeight;
        }
        $pages[] = $current;

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $objectId = 3;

        $fontRegularId = $objectId++;
        $fontBoldId = $objectId++;
        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($pages as $pageItems) {
            $pageId = $objectId++;
            $contentId = $objectId++;
            $kids[] = $pageId . ' 0 R';

            $xObjects = '';
            $stream = '';
            $imgIndex = 0;

            foreach ($pageItems as $item) {
                if (($item['kind'] ?? '') === 'image') {
                    $imgObjId = $objectId++;
                    ++$imgIndex;
                    $imgName = 'Im' . $imgIndex;
                    $jpeg = $item['jpeg'];
                    $objects[$imgObjId] = sprintf(
                        "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                        $item['imgW'],
                        $item['imgH'],
                        strlen($jpeg),
                        $jpeg
                    );
                    $xObjects .= sprintf('/%s %d 0 R ', $imgName, $imgObjId);
                    $stream .= sprintf(
                        "q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ\n",
                        $item['drawW'],
                        $item['drawH'],
                        $item['x'],
                        $item['y'],
                        $imgName
                    );
                    continue;
                }

                $fontRef = $item['bold'] ? $fontBoldId : $fontRegularId;
                $escaped = $this->pdfEscape($this->toWinAnsi((string) $item['text']));
                $stream .= "BT\n";
                $stream .= sprintf("/F%d %d Tf\n1 0 0 1 %.2F %.2F Tm (%s) Tj\n", $fontRef, $item['size'], $margin, $item['y'], $escaped);
                $stream .= "ET\n";
            }

            $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
            $xObjectDict = $xObjects !== '' ? '/XObject << ' . $xObjects . '>>' : '';
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Contents %d 0 R /Resources << /Font << /F%d %d 0 R /F%d %d 0 R >> %s >> >>',
                $pageWidth,
                $pageHeight,
                $contentId,
                $fontRegularId,
                $fontRegularId,
                $fontBoldId,
                $fontBoldId,
                $xObjectDict
            );
        }

        $objects[2] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', $kids),
            \count($kids)
        );

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; ++$i) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, int $width): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return [''];
        }

        $wrapped = wordwrap($text, $width, "\n", true);

        return explode("\n", $wrapped);
    }

    private function toWinAnsi(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return $converted;
    }

    private function pdfEscape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $n = $index;
        do {
            $letter = chr(65 + ($n % 26)) . $letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }

    private function safeSheetName(string $title, int $fallbackId): string
    {
        $title = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title) ?? $title;
        $title = trim($title);
        if ($title === '') {
            $title = 'Feuille' . $fallbackId;
        }

        return mb_substr($title, 0, 31);
    }

    private function fmtDate(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('d/m/Y') : (string) $value;
    }

    private function fmtDateTime(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('d/m/Y H:i') : (string) $value;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function filename(array $report, string $ext): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', (string) $report['periodLabel']) ?? 'periode';
        $slug = trim(strtolower($slug), '-');

        return sprintf('soukexpat-rapport-%s-%s.%s', $slug, (new \DateTimeImmutable())->format('Ymd-Hi'), $ext);
    }

    private function download(string $binary, string $filename, string $mime): Response
    {
        return new Response($binary, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'private, no-cache',
        ]);
    }
}
