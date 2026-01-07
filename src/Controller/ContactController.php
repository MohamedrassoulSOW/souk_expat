<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\Contact;
use App\Entity\Notification;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(): Response
    {
        return $this->render('contact/index.html.twig', [
            'controller_name' => 'ContactController',
        ]);
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact->setCreatedAt(new \DateTimeImmutable());
            $contact->setIsProcessed(false);
            $entityManager->persist($contact);
            $entityManager->flush();

            $this->addFlash('success', 'Message envoyé !');
            return $this->redirectToRoute('app_contact');
        }

        // C'EST CETTE LIGNE QUI RÈGLE VOTRE ERREUR :
        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(), // On envoie la variable à Twig
        ]);
    }

    #[Route('/admin/contact/reply/{id}', name: 'admin_contact_reply', methods: ['POST'])]
public function reply(
    Contact $contact, // Symfony fait le find($id) automatiquement ici
    Request $request, 
    UserRepository $userRepository, 
    NotificationService $notifService,
    EntityManagerInterface $em
): Response {
    $replyContent = $request->request->get('reply_message');

    // On cherche l'utilisateur par l'email stocké dans l'entité Contact
    $user = $userRepository->findOneBy(['email' => $contact->getEmail()]);

    if ($user && $replyContent) {
        // Envoi de la notification
        $notifService->notifyUser(
            $user, 
            "Réponse à votre message : " . $contact->getSubject(), 
            $replyContent
        );

        // On marque le contact comme traité (isProcessed)
        $contact->setIsProcessed(true);
        $em->flush();

        $this->addFlash('success', 'Réponse envoyée avec succès à ' . $contact->getName());
    } else {
        $this->addFlash('error', 'Erreur : Aucun compte utilisateur trouvé pour l\'adresse ' . $contact->getEmail());
    }

    return $this->redirectToRoute('app_admin_contact_index');
}

#[Route('/notification/read/{id}', name: 'app_notification_read')]
    public function read(Notification $notification, EntityManagerInterface $em): Response
    {
        // Sécurité : on vérifie que la notif appartient bien à l'utilisateur connecté
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $notification->setIsRead(true);
        $em->flush();

        // On redirige vers la page d'accueil ou une page spécifique
        return $this->redirectToRoute('app_home');
    }
    
}


