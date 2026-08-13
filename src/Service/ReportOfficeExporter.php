<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;

/**
 * Construction de fichiers Office (.xlsx, .docx, .pptx) sans dépendance externe.
 */
final class ReportOfficeExporter
{
    /**
     * @param list<array{title: string, rows: list<list<scalar|null>>}> $sheets
     */
    public function buildXlsx(array $sheets): string
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
     * @param list<array{title: string, png: string}> $charts
     */
    public function buildDocx(string $htmlBody, string $documentTitle, array $charts = []): string
    {
        $body = $this->htmlToWordMl($htmlBody);
        $rels = '';
        $contentTypesExtra = '<Default Extension="png" ContentType="image/png"/>';

        if ($charts !== []) {
            $body .= '<w:p><w:r><w:rPr><w:b/><w:sz w:val="28"/></w:rPr><w:t>Diagrammes</w:t></w:r></w:p>';
            foreach ($charts as $i => $chart) {
                $imgId = $i + 1;
                $rId = 'rIdImg' . $imgId;
                $cx = 5486400;
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
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
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
            . '<dc:title>' . htmlspecialchars($documentTitle, ENT_XML1, 'UTF-8') . '</dc:title>'
            . '<dc:creator>SoukExpat</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
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

    /**
     * @param list<array{title: string, lines: list<string>}> $textSlides
     * @param list<array{title: string, png: string}> $chartSlides
     */
    public function buildPptx(string $presentationTitle, array $textSlides, array $chartSlides = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'spptx');
        if ($tmp === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire.');
        }
        $zipPath = $tmp . '.pptx';
        @unlink($tmp);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier PowerPoint.');
        }

        $slides = [];
        $slides[] = ['title' => $presentationTitle, 'lines' => ['SoukExpat — Rapport généré automatiquement', date('d/m/Y H:i')]];
        foreach ($textSlides as $slide) {
            $slides[] = $slide;
        }

        $slideCount = \count($slides) + \count($chartSlides);
        $presentationRels = '';
        $contentTypesSlides = '';
        $contentTypesOverrides = '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
            . '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>'
            . '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>'
            . '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>';

        for ($i = 1; $i <= $slideCount; ++$i) {
            $contentTypesOverrides .= sprintf(
                '<Override PartName="/ppt/slides/slide%d.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>',
                $i
            );
            $presentationRels .= sprintf(
                '<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide%d.xml"/>',
                $i + 2,
                $i
            );
        }

        $imageContentTypes = '';
        foreach ($chartSlides as $ci => $chart) {
            $imageContentTypes .= sprintf(
                '<Override PartName="/ppt/media/image%d.png" ContentType="image/png"/>',
                $ci + 1
            );
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . $contentTypesOverrides
            . $imageContentTypes
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>'
            . '</Relationships>');

        $slideIdList = '';
        for ($i = 1; $i <= $slideCount; ++$i) {
            $slideIdList .= sprintf('<p:sldId id="%d" r:id="rId%d"/>', 255 + $i, $i + 2);
        }

        $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" saveSubsetFonts="1">'
            . '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>'
            . '<p:sldIdLst>' . $slideIdList . '</p:sldIdLst>'
            . '<p:sldSz cx="9144000" cy="6858000" type="screen4x3"/>'
            . '<p:notesSz cx="6858000" cy="9144000"/>'
            . '</p:presentation>');

        $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>'
            . $presentationRels
            . '</Relationships>');

        $this->addPptxMaster($zip);

        $slideIndex = 1;
        foreach ($slides as $slide) {
            $zip->addFromString(
                'ppt/slides/slide' . $slideIndex . '.xml',
                $this->pptxTextSlide((string) $slide['title'], $slide['lines'])
            );
            $zip->addFromString(
                'ppt/slides/_rels/slide' . $slideIndex . '.xml.rels',
                '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
                . '</Relationships>'
            );
            ++$slideIndex;
        }

        foreach ($chartSlides as $ci => $chart) {
            $imgNum = $ci + 1;
            $zip->addFromString('ppt/media/image' . $imgNum . '.png', $chart['png']);
            $zip->addFromString(
                'ppt/slides/slide' . $slideIndex . '.xml',
                $this->pptxImageSlide((string) $chart['title'], $imgNum)
            );
            $zip->addFromString(
                'ppt/slides/_rels/slide' . $slideIndex . '.xml.rels',
                '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image' . $imgNum . '.png"/>'
                . '</Relationships>'
            );
            ++$slideIndex;
        }

        $zip->close();
        $binary = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return $binary;
    }

    public function download(string $binary, string $filename, string $mime): Response
    {
        return new Response($binary, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    public function filename(string $prefix, string $periodLabel, string $ext): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $periodLabel) ?? 'periode';
        $slug = trim(strtolower($slug), '-');

        return sprintf('%s-%s-%s.%s', $prefix, $slug, (new \DateTimeImmutable())->format('Ymd-Hi'), $ext);
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

    private function addPptxMaster(\ZipArchive $zip): void
    {
        $zip->addFromString('ppt/theme/theme1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="SoukExpat">'
            . '<a:themeElements><a:clrScheme name="SoukExpat">'
            . '<a:dk1><a:srgbClr val="1B2E4B"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>'
            . '<a:accent1><a:srgbClr val="4B79A1"/></a:accent1>'
            . '</a:clrScheme><a:fontScheme name="Office"/><a:fmtScheme name="Office"/></a:themeElements></a:theme>');

        $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">'
            . '<p:cSld name="Blank"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr/></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>');

        $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'
            . '</Relationships>');

        $zip->addFromString('ppt/slideMasters/slideMaster1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/></p:spTree></p:cSld>'
            . '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>'
            . '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst></p:sldMaster>');

        $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>'
            . '</Relationships>');
    }

    /**
     * @param list<string> $lines
     */
    private function pptxTextSlide(string $title, array $lines): string
    {
        $shapes = $this->pptxTitleShape($title, 400000, 300000, 8200000, 900000, 4400, true);
        $y = 1400000;
        foreach ($lines as $line) {
            $shapes .= $this->pptxTitleShape($line, 400000, $y, 8200000, 500000, 2400, false);
            $y += 550000;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
            . $shapes
            . '</p:spTree></p:cSld></p:sld>';
    }

    private function pptxImageSlide(string $title, int $imageNum): string
    {
        $titleShape = $this->pptxTitleShape($title, 400000, 200000, 8200000, 700000, 3200, true);
        $pic = '<p:pic><p:nvPicPr><p:cNvPr id="10" name="Chart"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>'
            . '<p:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>'
            . '<p:spPr><a:xfrm><a:off x="400000" y="1000000"/><a:ext cx="8200000" cy="5200000"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
            . $titleShape . $pic
            . '</p:spTree></p:cSld></p:sld>';
    }

    private function pptxTitleShape(string $text, int $x, int $y, int $cx, int $cy, int $fontSize, bool $bold): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1, 'UTF-8');
        $boldTag = $bold ? '<a:b/>' : '';

        return '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Text"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>'
            . '<p:txBody><a:bodyPr wrap="square"/><a:lstStyle/>'
            . '<a:p><a:r><a:rPr lang="fr-FR" sz="' . $fontSize . '" dirty="0">' . $boldTag . '</a:rPr>'
            . '<a:t xml:space="preserve">' . $escaped . '</a:t></a:r></a:p></p:txBody></p:sp>';
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
}
