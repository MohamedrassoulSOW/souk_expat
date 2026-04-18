<?php

namespace App\Controller\Admin;

use App\Repository\ThreadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class AdminChatController extends AbstractController
{
    #[Route('/admin/chats', name: 'app_admin_chats_index', methods: ['GET'])]
    public function index(ThreadRepository $threadRepository): Response
    {

        return $this->render('admin/chat/index.html.twig', [
            'threads' => $threadRepository->findAllForAdminOrderedByNewest(0),
        ]);
    }
}
