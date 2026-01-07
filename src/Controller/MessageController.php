<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Thread;
use App\Entity\Message;
use App\Form\MessageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\ThreadRepository;
use App\Entity\Annonce;

    #[Route('/chat')]
class MessageController extends AbstractController
{
    #[Route('/chat/check/{id}', name: 'app_chat_check')]
    public function check(Annonce $annonce, ThreadRepository $threadRepo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        if ($user === $annonce->getUser()) {
            $this->addFlash('warning', 'Action impossible sur votre propre annonce.');
            return $this->redirectToRoute('app_annonce_index');
        }

        $thread = $threadRepo->findOneBy(['annonce' => $annonce, 'buyer' => $user]);

        if (!$thread) {
            $thread = new Thread();
            $thread->setAnnonce($annonce);
            $thread->setBuyer($user);
            $thread->setSeller($annonce->getUser());
            $em->persist($thread);
            $em->flush();
        }

        return $this->redirectToRoute('app_chat_view', ['id' => $thread->getId()]);
    }

    #[Route('/chat/thread/{id}', name: 'app_chat_view')]
public function view(Thread $thread, Request $request, EntityManagerInterface $em): Response 
{
    $message = new Message();
    $form = $this->createForm(MessageType::class, $message);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
        if ($form->isValid()) {
            $message->setThread($thread);
            $message->setSender($this->getUser());
            
            $em->persist($message);
            $em->flush();

            return $this->redirectToRoute('app_chat_view', ['id' => $thread->getId()]);
        } else {
            // Cela affichera l'erreur précise en haut de ta page
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        }
    }

    return $this->render('message/view.html.twig', [
        'thread' => $thread,
        'form' => $form->createView(),
    ]);
}

    #[Route('/messages', name: 'app_messages_list')]
    public function index(ThreadRepository $threadRepo): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        // On récupère les discussions triées par ID (ou par date de dernier message si tu as un champ updatedAt)
        $threads = $threadRepo->createQueryBuilder('t')
            ->where('t.buyer = :user OR t.seller = :user')
            ->setParameter('user', $user)
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('message/index.html.twig', [
            'threads' => $threads,
        ]);
    }
}