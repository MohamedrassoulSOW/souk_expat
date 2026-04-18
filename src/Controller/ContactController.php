<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\Contact;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use App\Service\NotificationService;

final class ContactController extends AbstractController
{
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

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(),
        ]);
    }

    #[Route('/admin/contact/reply/{id}', name: 'admin_contact_reply', methods: ['POST'])]
    public function reply(
        Contact $contact,
        Request $request,
        UserRepository $userRepository,
        NotificationService $notifService,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $replyContent = $request->request->get('reply_message');

        $user = $userRepository->findOneBy(['email' => $contact->getEmail()]);

        if ($user && $replyContent) {
            $notifService->notifyUser(
                $user,
                'Réponse à votre message : ' . $contact->getSubject(),
                $replyContent
            );

            $contact->setIsProcessed(true);
            $em->flush();

            $this->addFlash('success', 'Réponse envoyée avec succès à ' . $contact->getName());
        } else {
            $this->addFlash('error', 'Erreur : Aucun compte utilisateur trouvé pour l\'adresse ' . $contact->getEmail());
        }

        return $this->redirectToRoute('app_admin_contact_index');
    }
}

