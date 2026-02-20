<?php

declare(strict_types=1);

/*
 * This file is part of a FuelApp project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Security;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiLoginController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;
        if (!is_string($email) || !is_string($password) || '' === trim($email) || '' === $password) {
            return $this->json(['message' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var UserEntity|null $user */
        $user = $this->em->getRepository(UserEntity::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (null === $user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtTokenManager->create($user);
        $claims = $this->jwtTokenManager->parseAndValidate($token);

        return $this->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $claims['exp'],
        ]);
    }
}
