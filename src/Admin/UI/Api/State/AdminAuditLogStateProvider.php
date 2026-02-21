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

namespace App\Admin\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Admin\Application\Audit\AdminAuditLogEntry;
use App\Admin\Application\Audit\AdminAuditLogReader;
use App\Admin\UI\Api\Resource\Output\AdminAuditLogOutput;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminAuditLogOutput>
 */
final readonly class AdminAuditLogStateProvider implements ProviderInterface
{
    public function __construct(private AdminAuditLogReader $reader)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;
        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $entry = $this->reader->get($id);
            if (null !== $entry) {
                return $this->map($entry);
            }

            throw new NotFoundHttpException();
        }

        $action = $this->readFilter($context, 'action');
        $actorId = $this->readUuidFilter($context, 'actorId');
        $targetType = $this->readFilter($context, 'targetType');
        $targetId = $this->readFilter($context, 'targetId');
        $correlationId = $this->readFilter($context, 'correlationId');
        $from = $this->readDateFilter($context, 'from');
        $to = $this->readDateFilter($context, 'to');

        $outputs = [];
        foreach ($this->reader->search($action, $actorId, $targetType, $targetId, $correlationId, $from, $to) as $entry) {
            $outputs[] = $this->map($entry);
        }

        return $outputs;
    }

    private function map(AdminAuditLogEntry $entry): AdminAuditLogOutput
    {
        return new AdminAuditLogOutput(
            $entry->id,
            $entry->actorId,
            $entry->actorEmail,
            $entry->action,
            $entry->targetType,
            $entry->targetId,
            $entry->diffSummary,
            $entry->metadata,
            $entry->correlationId,
            $entry->createdAt,
        );
    }

    /** @param array<string, mixed> $context */
    private function readFilter(array $context, string $name): ?string
    {
        $filters = $context['filters'] ?? null;
        if (!is_array($filters)) {
            return null;
        }

        $value = $filters[$name] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /** @param array<string, mixed> $context */
    private function readUuidFilter(array $context, string $name): ?string
    {
        $value = $this->readFilter($context, $name);
        if (null === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $context */
    private function readDateFilter(array $context, string $name): ?DateTimeImmutable
    {
        $value = $this->readFilter($context, $name);
        if (null === $value) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false === $parsed) {
            return null;
        }

        return $parsed;
    }
}
