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
use App\Admin\Application\Security\SecurityActivityEntry;
use App\Admin\Application\Security\SecurityActivityReader;
use App\Admin\UI\Api\Resource\Output\AdminSecurityActivityOutput;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminSecurityActivityOutput>
 */
final readonly class AdminSecurityActivityStateProvider implements ProviderInterface
{
    public function __construct(private SecurityActivityReader $reader)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;
        if (null !== $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $entry = $this->reader->get($id);
            if (!$entry instanceof SecurityActivityEntry) {
                throw new NotFoundHttpException();
            }

            return $this->toOutput($entry);
        }

        $rawFilters = $context['filters'] ?? null;
        $filters = [];
        if (is_array($rawFilters)) {
            foreach ($rawFilters as $key => $value) {
                if (is_string($key)) {
                    $filters[$key] = $value;
                }
            }
        }

        $action = $this->readStringFilter($filters, 'action');
        $actorId = $this->readUuidFilter($filters, 'actorId');
        $from = $this->readDateFilter($filters, 'from');
        $to = $this->readDateFilter($filters, 'to');

        $items = [];
        foreach ($this->reader->search($action, $actorId, $from, $to) as $entry) {
            $items[] = $this->toOutput($entry);
        }

        return $items;
    }

    private function toOutput(SecurityActivityEntry $entry): AdminSecurityActivityOutput
    {
        return new AdminSecurityActivityOutput(
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

    /** @param array<string, mixed> $filters */
    private function readStringFilter(array $filters, string $name): ?string
    {
        $value = $filters[$name] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /** @param array<string, mixed> $filters */
    private function readUuidFilter(array $filters, string $name): ?string
    {
        $value = $this->readStringFilter($filters, $name);
        if (null === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $filters */
    private function readDateFilter(array $filters, string $name): ?DateTimeImmutable
    {
        $value = $this->readStringFilter($filters, $name);
        if (null === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
