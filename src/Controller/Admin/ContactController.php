<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\PlatformMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/contact')]
final class ContactController extends AbstractController
{
    #[Route('/', name: 'app_admin_contact_index', methods: ['GET'])]
    public function index(ContactRepository $contactRepository): Response
    {
        return $this->render('admin/contact/index.html.twig', [
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

    #[Route('/{id}/reply', name: 'app_admin_contact_reply', methods: ['POST'])]
    public function reply(
        Contact $contact,
        Request $request,
        UserRepository $userRepo,
        NotificationService $notifService,
        PlatformMailer $platformMailer,
        EntityManagerInterface $em,
    ): Response {
        $replyContent = trim((string) $request->request->get('reply_message', ''));

        if ($replyContent === '') {
            $this->addFlash('error', 'Le message de réponse est vide.');

            return $this->redirectToRoute('app_admin_contact_show', ['id' => $contact->getId()]);
        }

        $sent = $platformMailer->sendContactReply(
            (string) $contact->getEmail(),
            (string) $contact->getName(),
            (string) $contact->getSubject(),
            $replyContent,
        );

        $user = $userRepo->findOneBy(['email' => $contact->getEmail()]);
        if ($user) {
            $notifService->notifyUser(
                $user,
                'Réponse à votre message : ' . $contact->getSubject(),
                $replyContent,
            );
        }

        $contact->setIsProcessed(true);
        $em->flush();

        if ($sent) {
            $this->addFlash('success', 'Réponse envoyée par e-mail depuis contact@soukexpat.com.');
        } else {
            $this->addFlash('warning', 'Réponse enregistrée, mais l’e-mail n’a pas pu être envoyé. Vérifiez MAILER_DSN.');
        }

        return $this->redirectToRoute('app_admin_contact_show', ['id' => $contact->getId()]);
    }
}
