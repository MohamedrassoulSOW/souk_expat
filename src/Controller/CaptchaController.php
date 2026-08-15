<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\ImageCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CaptchaController extends AbstractController
{
    #[Route('/captcha', name: 'app_captcha', methods: ['GET'])]
    public function image(Request $request, ImageCaptcha $captcha): Response
    {
        $session = $request->getSession();
        $session->start();
        if ($request->query->get('refresh')) {
            $code = $captcha->refresh($session);
        } else {
            $code = $captcha->ensure($session);
        }

        return new Response($captcha->renderPng($code), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
