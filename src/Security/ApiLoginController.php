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

use App\Admin\Application\Audit\AdminAuditTrail;
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
    private const int AUDIT_TARGET_ID_MAX_LENGTH = 120;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $this->recordLoginFailure('invalid_payload', 'Invalid JSON payload.');

            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;
        if (!is_string($email) || !is_string($password) || '' === trim($email) || '' === $password) {
            $this->recordLoginFailure('invalid_payload', 'Email and password are required.');

            return $this->json(['message' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }
        $normalizedEmail = mb_strtolower(trim($email));

        /** @var UserEntity|null $user */
        $user = $this->em->getRepository(UserEntity::class)->findOneBy(['email' => $normalizedEmail]);
        if (null === $user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->recordLoginFailure($normalizedEmail, 'Invalid credentials.');

            return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$user->isActive()) {
            $this->recordLoginFailure($normalizedEmail, 'Account disabled.');

            return $this->json(['message' => 'Account disabled.'], Response::HTTP_FORBIDDEN);
        }

        $token = $this->jwtTokenManager->create($user);
        $claims = $this->jwtTokenManager->parseAndValidate($token);
        $this->auditTrail->record(
            'security.login.success',
            'user',
            $user->getId()->toRfc4122(),
            [],
            ['channel' => 'api_password'],
        );

        return $this->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $claims['exp'],
        ]);
    }

    private function recordLoginFailure(string $targetId, string $reason): void
    {
        $this->auditTrail->record(
            'security.login.failure',
            'credential',
            $this->normalizeAuditCredentialTargetId($targetId),
            [],
            ['channel' => 'api_password', 'reason' => $reason],
        );
    }

    private function normalizeAuditCredentialTargetId(string $targetId): string
    {
        $normalized = mb_strtolower(trim($targetId));
        if ('' === $normalized) {
            return 'anonymous';
        }

        return mb_substr($normalized, 0, self::AUDIT_TARGET_ID_MAX_LENGTH);
    }
}
