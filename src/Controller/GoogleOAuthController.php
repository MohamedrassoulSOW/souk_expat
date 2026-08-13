<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\GoogleOAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleOAuthController extends AbstractController
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuth,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/connect/google', name: 'connect_google_start', methods: ['GET'])]
    public function start(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if (!$this->googleOAuth->isConfigured()) {
            $this->addFlash('danger', 'La connexion Google n’est pas encore configurée. Contactez l’administrateur.');

            return $this->redirectToRoute('app_login');
        }

        return $this->redirect($this->googleOAuth->getAuthorizationUrl());
    }

    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function check(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->query->get('error')) {
            $this->addFlash('warning', 'Connexion Google annulée.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $profile = $this->googleOAuth->fetchUserFromCallback(
                $request->query->getString('code') ?: null,
                $request->query->getString('state') ?: null,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Google OAuth failed: {message}', ['message' => $e->getMessage()]);
            $this->addFlash('danger', 'Connexion Google impossible. Réessayez.');

            return $this->redirectToRoute('app_login');
        }

        if (!$profile['email_verified']) {
            $this->addFlash('danger', 'Votre e-mail Google n’est pas vérifié.');

            return $this->redirectToRoute('app_login');
        }

        $user = $this->users->findOneBy(['googleId' => $profile['sub']])
            ?? $this->users->findOneBy(['email' => $profile['email']]);

        if ($user && $user->isBlocked()) {
            $this->addFlash('danger', 'Votre compte est bloqué par un administrateur.');

            return $this->redirectToRoute('app_login');
        }

        $isNew = false;
        if (!$user) {
            $user = new User();
            $user->setEmail($profile['email']);
            $user->setFirstName($profile['given_name'] !== '' ? $profile['given_name'] : ($profile['name'] !== '' ? $profile['name'] : 'Utilisateur'));
            $user->setLastName($profile['family_name'] !== '' ? $profile['family_name'] : 'Google');
            $user->setPassword(null);
            $user->setRoles([]);
            $isNew = true;
            $this->em->persist($user);
        }

        $user->setGoogleId($profile['sub']);

        if ($isNew || !$user->getAvatar()) {
            $avatar = $this->storeGoogleAvatar($profile['picture'] ?? null);
            if ($avatar) {
                $user->setAvatar($avatar);
            }
        }

        // Compléter prénom/nom si vides
        if (!$user->getFirstName() && $profile['given_name'] !== '') {
            $user->setFirstName($profile['given_name']);
        }
        if (!$user->getLastName() && $profile['family_name'] !== '') {
            $user->setLastName($profile['family_name']);
        }

        $this->em->flush();

        $this->addFlash('success', $isNew
            ? 'Bienvenue sur SoukExpat ! Votre compte Google a été créé.'
            : 'Connexion Google réussie. Bon retour !'
        );

        return $this->security->login($user, 'form_login', 'main') ?? $this->redirectToRoute('app_home');
    }

    private function storeGoogleAvatar(?string $pictureUrl): ?string
    {
        if ($pictureUrl === null || $pictureUrl === '') {
            return null;
        }

        try {
            $data = file_get_contents($pictureUrl, false, stream_context_create([
                'http' => ['timeout' => 8, 'header' => "User-Agent: SoukExpat\r\n"],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]));
            if ($data === false || $data === '') {
                return null;
            }

            $dir = $this->projectDir . '/public/uploads/avatars';
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                return null;
            }

            $filename = 'g_' . bin2hex(random_bytes(8)) . '.jpg';
            if (file_put_contents($dir . '/' . $filename, $data) === false) {
                return null;
            }

            return $filename;
        } catch (\Throwable) {
            return null;
        }
    }
}
