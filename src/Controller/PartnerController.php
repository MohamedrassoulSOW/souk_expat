<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PartnerController extends AbstractController
{
    private function partnerDocsDir(): string
    {
        return $this->getParameter('kernel.project_dir').\DIRECTORY_SEPARATOR.'docs'.\DIRECTORY_SEPARATOR.'partner-pitch';
    }

    #[Route('/partenaires', name: 'app_partners')]
    #[IsGranted('ROLE_EDITOR')]
    public function index(CategoryRepository $categoryRepository, CityRepository $cityRepository): Response
    {
        $docsDir = $this->partnerDocsDir();
        $pdfPath = $docsDir.\DIRECTORY_SEPARATOR.'SoukExpat-Dossier-Partenaires.pdf';
        $pptxPath = $docsDir.\DIRECTORY_SEPARATOR.'SoukExpat-Presentation-Partenaires.pptx';

        return $this->render('page/partners.html.twig', [
            'categories' => $categoryRepository->findAllOrderedByName(),
            'cities' => $cityRepository->findAllOrderedByName(),
            'can_export_docs' => true,
            'has_pdf' => is_file($pdfPath),
            'has_pptx' => is_file($pptxPath),
            'auto_print' => false,
        ]);
    }

    #[Route('/partenaires/imprimer', name: 'app_partners_print')]
    #[IsGranted('ROLE_EDITOR')]
    public function printPage(CategoryRepository $categoryRepository, CityRepository $cityRepository): Response
    {
        $docsDir = $this->partnerDocsDir();

        return $this->render('page/partners.html.twig', [
            'categories' => $categoryRepository->findAllOrderedByName(),
            'cities' => $cityRepository->findAllOrderedByName(),
            'can_export_docs' => true,
            'has_pdf' => is_file($docsDir.\DIRECTORY_SEPARATOR.'SoukExpat-Dossier-Partenaires.pdf'),
            'has_pptx' => is_file($docsDir.\DIRECTORY_SEPARATOR.'SoukExpat-Presentation-Partenaires.pptx'),
            'auto_print' => true,
        ]);
    }

    #[Route('/partenaires/dossier.pdf', name: 'app_partners_pdf')]
    #[IsGranted('ROLE_EDITOR')]
    public function downloadPdf(): BinaryFileResponse
    {
        return $this->downloadDoc(
            'SoukExpat-Dossier-Partenaires.pdf',
            'application/pdf',
            'SoukExpat-Dossier-Partenaires.pdf'
        );
    }

    #[Route('/partenaires/presentation.pptx', name: 'app_partners_pptx')]
    #[IsGranted('ROLE_EDITOR')]
    public function downloadPptx(): BinaryFileResponse
    {
        return $this->downloadDoc(
            'SoukExpat-Presentation-Partenaires.pptx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'SoukExpat-Presentation-Partenaires.pptx'
        );
    }


    private function downloadDoc(string $filename, string $mime, string $downloadName): BinaryFileResponse
    {
        $path = $this->partnerDocsDir().\DIRECTORY_SEPARATOR.$filename;
        if (!is_file($path)) {
            throw new NotFoundHttpException('Document partenaires introuvable. Générez-le via docs/partner-pitch.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $downloadName
        );

        return $response;
    }
}
