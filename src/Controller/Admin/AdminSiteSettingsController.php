<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\SiteSettingsType;
use App\Mail\SiteContact;
use App\Service\SiteSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings')]
#[IsGranted('ROLE_EDITOR')]
class AdminSiteSettingsController extends AbstractController
{
    #[Route('', name: 'admin_site_settings', methods: ['GET', 'POST'])]
    public function edit(Request $request, SiteSettingsService $settingsService): Response
    {
        $settings = $settingsService->get();
        $settings->setContactEmail(SiteContact::EMAIL);
        $form = $this->createForm(SiteSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->setContactEmail(SiteContact::EMAIL);
            $settingsService->save($settings);
            $this->addFlash('success', 'Les informations du site ont été enregistrées.');

            return $this->redirectToRoute('admin_site_settings');
        }

        return $this->render('admin/settings/edit.html.twig', [
            'form' => $form,
            'settings' => $settings,
        ]);
    }
}
