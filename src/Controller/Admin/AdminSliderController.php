<?php

namespace App\Controller\Admin;

use App\Entity\Slider;
use App\Form\SliderType;
use App\Repository\SliderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/slider')]
class AdminSliderController extends AbstractController
{
    // src/Controller/HomeController.php

    #[Route('', name: 'admin_slider_index', methods: ['GET'])]
    public function index(SliderRepository $repo): Response
    {
        // C'est cette ligne qui définit "sliders" pour Twig
        return $this->render('admin/slider/index.html.twig', [
            'sliders' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_slider_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $slider = new Slider();
        $form = $this->createForm(SliderType::class, $slider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/sliders',
                    $filename
                );
                $slider->setImageName($filename);
            }

            $em->persist($slider);
            $em->flush();

            $this->addFlash('success', 'Slide ajoutée.');
            return $this->redirectToRoute('admin_slider_index');
        }

        return $this->render('admin/slider/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_slider_delete', methods: ['POST'])]
    public function delete(Request $request, Slider $slider, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $slider->getId(), $request->request->get('_token'))) {
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/sliders/' . $slider->getImageName();
            if (file_exists($imagePath)) {
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

        // On inverse le statut actuel (si vrai devient faux, et inversement)
        $slider->setIsActive(!$slider->isActive());
        
        $em->flush();

        $statusLabel = $slider->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Le slide a été $statusLabel avec succès.");

        return $this->redirectToRoute('admin_slider_index');
    }
}