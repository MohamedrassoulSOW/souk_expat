<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\AbuseLimiter;
use App\Security\ImageCaptcha;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
        ImageCaptcha $captcha,
        AbuseLimiter $abuseLimiter,
    ): Response {
        $session = $request->getSession();
        $captcha->ensure($session);

        if ($request->isMethod('POST')) {
            $abuseLimiter->assertApiRegister($request);
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $honeypot = trim((string) $form->get('website')->getData());
            if ($honeypot !== '') {
                $this->addFlash('success', 'Compte créé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();
            $captcha->consume($session);

            return $security->login($user, 'form_login', 'main', [new RememberMeBadge()]);
        }

        if ($form->isSubmitted()) {
            $captcha->refresh($session);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
