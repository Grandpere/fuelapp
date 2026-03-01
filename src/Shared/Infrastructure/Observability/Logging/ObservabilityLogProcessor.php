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

namespace App\Shared\Infrastructure\Observability\Logging;

use App\Shared\Infrastructure\Observability\CorrelationIdContext;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class ObservabilityLogProcessor
{
    public function __construct(
        private CorrelationIdContext $correlationContext,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;
        $context = $record->context;

        $correlationId = $this->correlationContext->current();
        if (is_string($correlationId) && '' !== trim($correlationId)) {
            $extra['correlation_id'] = trim($correlationId);
            $extra['request_id'] = trim($correlationId);
        }

        $token = $this->tokenStorage->getToken();
        if (null !== $token) {
            $user = $token->getUser();
            if ($user instanceof UserEntity) {
                $extra['user_id'] = $user->getId()->toRfc4122();
                $extra['user_email'] = $user->getEmail();
            }
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            $extra['http_method'] = $request->getMethod();
            $extra['http_path'] = $request->getPathInfo();
            $route = $request->attributes->get('_route');
            if (is_string($route) && '' !== trim($route)) {
                $extra['http_route'] = trim($route);
            }
        }

        $jobId = $context['job_id'] ?? $context['import_job_id'] ?? $context['station_id'] ?? null;
        if (is_scalar($jobId) && '' !== trim((string) $jobId)) {
            $extra['job_id'] = trim((string) $jobId);
        }

        return $record->with(extra: $extra);
    }
}
