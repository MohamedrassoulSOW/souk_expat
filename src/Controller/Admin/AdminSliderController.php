<?php

namespace App\Controller\Admin;

use App\Entity\Slider;
use App\Form\SliderType;
use App\Repository\SliderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/slider')]
class AdminSliderController extends AbstractController
{
    private const UPLOAD_DIR = '/public/uploads/sliders';

    #[Route('', name: 'admin_slider_index', methods: ['GET'])]
    public function index(SliderRepository $repo): Response
    {
        return $this->render('admin/slider/index.html.twig', [
            'sliders' => $repo->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_slider_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $slider = new Slider();
        $slider->setIsActive(true);
        $slider->setTitle('');
        $slider->setImageName('');
        $form = $this->createForm(SliderType::class, $slider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<UploadedFile>|UploadedFile|null $files */
            $files = $form->get('mediaFiles')->getData();
            if (!\is_array($files)) {
                $files = $files ? [$files] : [];
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . self::UPLOAD_DIR;
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                $this->addFlash('danger', 'Impossible de créer le dossier d’upload.');

                return $this->redirectToRoute('admin_slider_new');
            }

            $baseTitle = trim((string) $form->get('title')->getData());
            $isActive = (bool) $form->get('isActive')->getData();
            $created = 0;

            foreach ($files as $index => $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $mediaType = $this->resolveMediaType($file);
                $ext = $file->guessExtension() ?: ($mediaType === Slider::TYPE_VIDEO ? 'mp4' : 'jpg');
                $filename = uniqid('slider_', true) . '.' . $ext;
                $file->move($uploadDir, $filename);

                $slide = new Slider();
                $slide->setImageName($filename);
                $slide->setMediaType($mediaType);
                $slide->setIsActive($isActive);
                $title = $baseTitle !== ''
                    ? ($created > 0 ? sprintf('%s (%d)', $baseTitle, $created + 1) : $baseTitle)
                    : ($mediaType === Slider::TYPE_VIDEO ? 'Vidéo' : 'Image') . ' ' . ($index + 1);
                $slide->setTitle($title);

                $em->persist($slide);
                ++$created;
            }

            if ($created === 0) {
                $this->addFlash('danger', 'Aucun fichier valide n’a été uploadé.');

                return $this->redirectToRoute('admin_slider_new');
            }

            $em->flush();
            $this->addFlash('success', sprintf('%d média(s) ajouté(s) au slider.', $created));

            return $this->redirectToRoute('admin_slider_index');
        }

        return $this->render('admin/slider/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_slider_delete', methods: ['POST'])]
    public function delete(Request $request, Slider $slider, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $slider->getId(), $request->request->get('_token'))) {
            $imagePath = $this->getParameter('kernel.project_dir') . self::UPLOAD_DIR . '/' . $slider->getImageName();
            if (is_file($imagePath)) {
                unlink($imagePath);
            }

            $em->remove($slider);
            $em->flush();
            $this->addFlash('success', 'Slide supprimée.');
        }

        return $this->redirectToRoute('admin_slider_index');
    }

    #[Route('/toggle/{id}', name: 'admin_slider_toggle', methods: ['POST'])]
    public function toggleStatus(Request $request, Slider $slider, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('toggle_slider_' . $slider->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide ou expiré.');

            return $this->redirectToRoute('admin_slider_index');
        }

        $slider->setIsActive(!$slider->isActive());
        $em->flush();

        $statusLabel = $slider->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Le slide a été $statusLabel avec succès.");

        return $this->redirectToRoute('admin_slider_index');
    }

    private function resolveMediaType(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if (str_starts_with($mime, 'video/')) {
            return Slider::TYPE_VIDEO;
        }

        return Slider::TYPE_IMAGE;
    }
}
