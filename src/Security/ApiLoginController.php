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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ApiLoginController extends AbstractController
{
    private const int AUDIT_TARGET_ID_MAX_LENGTH = 120;
    private const int RATE_LIMITER_KEY_MAX_LENGTH = 200;
    private const int MAX_LOGIN_JSON_BODY_BYTES = 4096;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly AdminAuditTrail $auditTrail,
        #[Autowire(service: 'limiter.api_login')]
        private readonly RateLimiterFactory $apiLoginLimiter,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $rawContent = $request->getContent();
        if (strlen($rawContent) > self::MAX_LOGIN_JSON_BODY_BYTES) {
            $this->recordLoginFailure('payload_too_large', 'Login payload too large.');

            return $this->json(['message' => 'Request payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $payload = json_decode($rawContent, true);
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
        $rateLimitedResponse = $this->consumeRateLimitOrBuildResponse($normalizedEmail, $request->getClientIp());
        if (null !== $rateLimitedResponse) {
            $this->recordLoginFailure($normalizedEmail, 'Too many login attempts.');

            return $rateLimitedResponse;
        }

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

    private function consumeRateLimitOrBuildResponse(string $normalizedEmail, ?string $clientIp): ?JsonResponse
    {
        $limiter = $this->apiLoginLimiter->create($this->buildRateLimiterKey($normalizedEmail, $clientIp));
        $limit = $limiter->consume(1);
        if ($limit->isAccepted()) {
            return null;
        }

        $retryAfter = $limit->getRetryAfter();
        $retryAfterSeconds = max(1, $retryAfter->getTimestamp() - time());

        $response = $this->json(['message' => 'Too many login attempts. Please try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
        $response->headers->set('Retry-After', (string) $retryAfterSeconds);

        return $response;
    }

    private function buildRateLimiterKey(string $normalizedEmail, ?string $clientIp): string
    {
        $ip = is_string($clientIp) && '' !== trim($clientIp) ? trim($clientIp) : 'unknown-ip';
        $rawKey = sprintf('api-login:%s|%s', $normalizedEmail, $ip);

        return mb_substr($rawKey, 0, self::RATE_LIMITER_KEY_MAX_LENGTH);
    }
}
