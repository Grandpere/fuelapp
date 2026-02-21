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
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Maintenance\UI\Api\Resource\Output\MaintenanceEventOutput;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MaintenanceEventOutput>
 */
final readonly class MaintenanceEventStateProvider implements ProviderInterface
{
    public function __construct(
        private MaintenanceEventRepository $repository,
        private AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $ownerId = $this->currentUserId();
        if (null === $ownerId) {
            return null;
        }

        $id = $uriVariables['id'] ?? null;
        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                return null;
            }

            $event = $this->repository->get($id);
            if (!$event instanceof MaintenanceEvent || $event->ownerId() !== $ownerId) {
                return null;
            }

            return $this->toOutput($event);
        }

        $vehicleId = $this->readUuidFilter($context, 'vehicleId');
        $eventType = $this->readEventTypeFilter($context, 'eventType');
        $events = [];
        foreach ($this->repository->allForOwner($ownerId) as $event) {
            if (null !== $vehicleId && $event->vehicleId() !== $vehicleId) {
                continue;
            }
            if (null !== $eventType && $event->eventType() !== $eventType) {
                continue;
            }

            $events[] = $this->toOutput($event);
        }

        return $events;
    }

    private function toOutput(MaintenanceEvent $event): MaintenanceEventOutput
    {
        return new MaintenanceEventOutput(
            $event->id()->toString(),
            $event->ownerId(),
            $event->vehicleId(),
            $event->eventType()->value,
            $event->occurredAt(),
            $event->description(),
            $event->odometerKilometers(),
            $event->totalCostCents(),
            $event->currencyCode(),
            $event->createdAt(),
            $event->updatedAt(),
        );
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
    private function readEventTypeFilter(array $context, string $name): ?MaintenanceEventType
    {
        $value = $this->readFilter($context, $name);
        if (null === $value) {
            return null;
        }

        return MaintenanceEventType::tryFrom($value);
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

    private function currentUserId(): ?string
    {
        return $this->authenticatedUserIdProvider->getAuthenticatedUserId();
    }
}
