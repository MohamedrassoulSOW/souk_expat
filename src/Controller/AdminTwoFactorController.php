<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\AbuseLimiter;
use App\Security\AdminTwoFactor;
use App\Security\TotpAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/2fa')]
#[IsGranted('ROLE_ADMIN')]
final class AdminTwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TotpAuthenticator $totp,
        private readonly AdminTwoFactor $twoFactor,
        private readonly EntityManagerInterface $em,
        private readonly AbuseLimiter $abuseLimiter,
    ) {
    }

    #[Route('/setup', name: 'app_2fa_setup', methods: ['GET', 'POST'])]
    public function setup(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->isTotpEnabled()) {
            if ($this->twoFactor->isVerified($request->getSession(), $user)) {
                return $this->render('security/2fa_setup.html.twig', [
                    'otpauthUri' => null,
                    'secret' => null,
                    'alreadyEnabled' => true,
                ]);
            }

            return $this->redirectToRoute('app_2fa_challenge');
        }

        $session = $request->getSession();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_2fa_setup', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $this->abuseLimiter->assertTotpChallenge($request, (string) $user->getId());
            $secret = (string) $session->get(AdminTwoFactor::SESSION_SETUP_SECRET, '');
            $code = $request->request->getString('code');
            if ($secret === '' || !$this->totp->verify($secret, $code)) {
                $this->addFlash('danger', 'Code invalide. Scannez le QR et saisissez le code à 6 chiffres.');

                return $this->redirectToRoute('app_2fa_setup');
            }

            $user->setTotpSecret($secret);
            $this->em->flush();
            $this->twoFactor->markVerified($request, $user);
            $this->addFlash('success', 'La double authentification est activée pour votre compte admin.');

            return $this->redirectToRoute('app_dashboard');
        }

        $secret = (string) $session->get(AdminTwoFactor::SESSION_SETUP_SECRET, '');
        if ($secret === '') {
            $secret = $this->totp->generateSecret();
            $session->set(AdminTwoFactor::SESSION_SETUP_SECRET, $secret);
        }

        return $this->render('security/2fa_setup.html.twig', [
            'otpauthUri' => $this->totp->provisioningUri($secret, (string) $user->getEmail()),
            'secret' => $secret,
            'alreadyEnabled' => $user->isTotpEnabled(),
        ]);
    }

    #[Route('/challenge', name: 'app_2fa_challenge', methods: ['GET', 'POST'])]
    public function challenge(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $session = $request->getSession();

        if ($this->twoFactor->isVerified($session, $user)) {
            return $this->redirectToRoute('app_dashboard');
        }

        if (!$user->isTotpEnabled()) {
            return $this->redirectToRoute('app_2fa_setup');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_2fa_challenge', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $this->abuseLimiter->assertTotpChallenge($request, (string) $user->getId());
            if ($this->totp->verify((string) $user->getTotpSecret(), $request->request->getString('code'))) {
                $this->twoFactor->markVerified($request, $user);

                return $this->redirectToRoute('app_dashboard');
            }

            $this->addFlash('danger', 'Code invalide. Réessayez.');
        }

        return $this->render('security/2fa_challenge.html.twig');
    }
}
