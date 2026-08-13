<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Service\PlatformMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function contact(
        Request $request,
        EntityManagerInterface $entityManager,
        PlatformMailer $platformMailer,
    ): Response {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Bots : honeypot rempli → faux succès silencieux
            $honeypot = trim((string) $form->get('website')->getData());
            if ($honeypot !== '') {
                $this->addFlash('success', 'Message envoyé ! Nous vous répondrons rapidement.');

                return $this->redirectToRoute('app_contact');
            }

            // Anti-spam simple : max 3 envois / 10 min par session
            $session = $request->getSession();
            $bucket = $session->get('contact_submit_times', []);
            $now = time();
            $bucket = array_values(array_filter(
                is_array($bucket) ? $bucket : [],
                static fn ($t): bool => is_int($t) && ($now - $t) < 600
            ));
            if (\count($bucket) >= 3) {
                $this->addFlash('warning', 'Trop de messages envoyés. Réessayez dans quelques minutes.');

                return $this->redirectToRoute('app_contact');
            }

            $contact->setCreatedAt(new \DateTimeImmutable());
            $contact->setIsProcessed(false);
            $entityManager->persist($contact);
            $entityManager->flush();

            $bucket[] = $now;
            $session->set('contact_submit_times', $bucket);

            $sent = $platformMailer->sendContactToInbox($contact);
            $from = $platformMailer->contactEmail();

            if ($sent) {
                $this->addFlash('success', sprintf(
                    'Message envoyé ! Nous vous répondrons à partir de %s.',
                    $from
                ));
            } else {
                $this->addFlash('warning', 'Votre message a été enregistré, mais la notification e-mail a échoué. Notre équipe le traitera tout de même.');
            }

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(),
        ]);
    }
}
