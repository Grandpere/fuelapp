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

namespace App\Maintenance\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Maintenance\Application\Cost\MaintenanceCostVarianceReader;
use App\Maintenance\UI\Api\Resource\Output\MaintenanceCostVarianceOutput;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/** @implements ProviderInterface<object> */
final readonly class MaintenanceCostVarianceStateProvider implements ProviderInterface
{
    public function __construct(
        private MaintenanceCostVarianceReader $varianceReader,
        private AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            return [];
        }

        $vehicleId = $this->readVehicleIdFilter($context, 'vehicleId');
        $from = $this->readDateFilter($context, 'from');
        $to = $this->readDateFilter($context, 'to');
        $variance = $this->varianceReader->read($ownerId, $vehicleId, $from, $to);

        return [new MaintenanceCostVarianceOutput(
            $variance->vehicleId,
            $from,
            $to,
            $variance->plannedCostCents,
            $variance->actualCostCents,
            $variance->varianceCents,
        )];
    }

    /** @param array<string, mixed> $context */
    private function readVehicleIdFilter(array $context, string $name): ?string
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
        if (false !== $parsed) {
            return $parsed;
        }

        $date = date_create_immutable($value);

        return $date instanceof DateTimeImmutable ? $date : null;
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
}
