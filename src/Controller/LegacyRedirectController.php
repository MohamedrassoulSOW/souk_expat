<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Redirections 301 pour anciennes URLs.
 */
final class LegacyRedirectController extends AbstractController
{
    #[Route('/messages', name: 'app_messages_legacy')]
    public function messages(): Response
    {
        return $this->redirectToRoute('app_messages_list', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
