<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Api\ApiResourceFactory;
use App\Api\JwtTokenManager;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly ApiResourceFactory $resources,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/login', name: 'api_v1_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = $this->jsonBody($request);
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json([
                'error' => 'validation_error',
                'message' => 'Email et mot de passe requis.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'error' => 'invalid_credentials',
                'message' => 'Identifiants incorrects.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isBlocked()) {
            return $this->json([
                'error' => 'account_blocked',
                'message' => 'Votre compte est bloqué.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->tokenResponse($user);
    }

    #[Route('/register', name: 'api_v1_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->jsonBody($request);

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $firstName = trim((string) ($payload['firstName'] ?? ''));
        $lastName = trim((string) ($payload['lastName'] ?? ''));
        $acceptTerms = filter_var($payload['acceptTerms'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $violations = $this->validator->validate(
            [
                'email' => $email,
                'password' => $password,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'acceptTerms' => $acceptTerms,
            ],
            new Assert\Collection([
                'email' => [new Assert\NotBlank(), new Assert\Email()],
                'password' => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 72)],
                'firstName' => [new Assert\NotBlank(), new Assert\Length(max: 191)],
                'lastName' => [new Assert\NotBlank(), new Assert\Length(max: 191)],
                'acceptTerms' => [new Assert\IsTrue(message: 'Vous devez accepter les conditions d’utilisation.')],
            ])
        );

        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[trim($violation->getPropertyPath(), '[]')] = $violation->getMessage();
            }

            return $this->json([
                'error' => 'validation_error',
                'message' => 'Données invalides.',
                'fields' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->userRepository->findOneBy(['email' => $email])) {
            return $this->json([
                'error' => 'email_taken',
                'message' => 'Un compte existe déjà avec cet e-mail.',
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        return $this->tokenResponse($user, Response::HTTP_CREATED);
    }

    private function tokenResponse(User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        $token = $this->jwtTokenManager->createToken($user);

        return $this->json([
            'tokenType' => 'Bearer',
            'accessToken' => $token['token'],
            'expiresAt' => $token['expires_at'],
            'expiresIn' => $token['expires_in'],
            'user' => $this->resources->user($user, true),
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        $data = json_decode($request->getContent() ?: '{}', true);

        return \is_array($data) ? $data : [];
    }
}
