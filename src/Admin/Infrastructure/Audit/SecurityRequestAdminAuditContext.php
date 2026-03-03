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

namespace App\Admin\Infrastructure\Audit;

use App\Admin\Application\Audit\AdminAuditContext;
use App\Shared\Infrastructure\Observability\CorrelationIdContext;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SecurityRequestAdminAuditContext implements AdminAuditContext
{
    private const int MAX_CORRELATION_ID_LENGTH = 80;

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private CorrelationIdContext $correlationIdContext,
    ) {
    }

    public function actorId(): ?string
    {
        $user = $this->currentUser();

        return $user?->getId()->toRfc4122();
    }

    public function actorEmail(): ?string
    {
        return $this->currentUser()?->getEmail();
    }

    public function correlationId(): string
    {
        $current = $this->correlationIdContext->current();
        if (is_string($current) && '' !== trim($current)) {
            return $this->normalizeCorrelationId($current);
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return Uuid::v7()->toRfc4122();
        }

        $existing = $request->attributes->get('_admin_audit_correlation_id');
        if (is_string($existing) && '' !== trim($existing)) {
            return $this->normalizeCorrelationId($existing);
        }

        $header = $request->headers->get('X-Correlation-Id') ?? $request->headers->get('X-Request-Id');
        $correlationId = is_string($header) && '' !== trim($header)
            ? $this->normalizeCorrelationId($header)
            : Uuid::v7()->toRfc4122();

        $request->attributes->set('_admin_audit_correlation_id', $correlationId);

        return $correlationId;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return [];
        }

        return [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'route' => $request->attributes->get('_route'),
        ];
    }

    private function currentUser(): ?UserEntity
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof UserEntity ? $user : null;
    }

    private function normalizeCorrelationId(string $value): string
    {
        return mb_substr(trim($value), 0, self::MAX_CORRELATION_ID_LENGTH);
    }
}
