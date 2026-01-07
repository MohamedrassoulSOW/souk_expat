<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/contact')]
final class ContactController extends AbstractController
{
    #[Route('/', name: 'app_admin_contact_index', methods: ['GET'])]
    public function index(ContactRepository $contactRepository): Response
    {
        return $this->render('admin/contact/index.html.twig', [
            // On récupère les messages du plus récent au plus ancien
            'contacts' => $contactRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_contact_show', methods: ['GET'])]
    public function show(Contact $contact): Response
    {
        return $this->render('admin/contact/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_contact_toggle', methods: ['POST'])]
    public function toggleProcessed(Contact $contact, EntityManagerInterface $entityManager): Response
    {
        // On inverse le statut actuel
        $contact->setIsProcessed(!$contact->isProcessed());
        $entityManager->flush();

        $this->addFlash('success', 'Le statut du message a été mis à jour.');

        return $this->redirectToRoute('app_admin_contact_index');
    }

    #[Route('/{id}/delete', name: 'app_admin_contact_delete', methods: ['POST'])]
    public function delete(Contact $contact, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($contact);
        $entityManager->flush();

        $this->addFlash('danger', 'Le message a été supprimé.');

        return $this->redirectToRoute('app_admin_contact_index');
    }

        // Dans votre Controller de réponse
public function reply(Contact $contact, Request $request, UserRepository $userRepo, NotificationService $notifService, EntityManagerInterface $em): Response 
{
    // On récupère le contenu du textarea (le nom doit être identique au 'name' dans Twig)
    $replyContent = $request->request->get('reply_message');

    // On cherche l'utilisateur par l'email du contact
    $user = $userRepo->findOneBy(['email' => $contact->getEmail()]);

    if ($user && !empty($replyContent)) {
        // C'est ici qu'on fait la "traduction" :
        // Le 'subject' du contact devient le 'title' de la notification
        $titleForNotif = "Réponse à votre message : " . $contact->getSubject();

        $notifService->notifyUser(
            $user, 
            $titleForNotif, // Remplira le champ 'title' en base
            $replyContent    // Remplira le champ 'message' en base
        );

        $contact->setIsProcessed(true);
        $em->flush();

        $this->addFlash('success', 'La notification a été envoyée à ' . $user->getFirstName());
    } else {
        $this->addFlash('error', 'Impossible d\'envoyer : utilisateur non trouvé ou message vide.');
    }

    return $this->redirectToRoute('app_admin_contact_show', ['id' => $contact->getId()]);
}

}