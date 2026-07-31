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
            $contact->setCreatedAt(new \DateTimeImmutable());
            $contact->setIsProcessed(false);
            $entityManager->persist($contact);
            $entityManager->flush();

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
