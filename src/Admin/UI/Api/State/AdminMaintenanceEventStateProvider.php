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
use App\Admin\UI\Api\Resource\Output\AdminMaintenanceEventOutput;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenanceEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use ValueError;

/**
 * @implements ProviderInterface<AdminMaintenanceEventOutput>
 */
final readonly class AdminMaintenanceEventStateProvider implements ProviderInterface
{
    public function __construct(private MaintenanceEventRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;
        if (null !== $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $event = $this->repository->get($id);
            if (!$event instanceof MaintenanceEvent) {
                throw new NotFoundHttpException();
            }

            return $this->toOutput($event);
        }

        $ownerId = $this->readUuidFilter($context, 'ownerId');
        $vehicleId = $this->readUuidFilter($context, 'vehicleId');
        $eventType = $this->readEnumFilter($context, 'eventType');

        $resources = [];
        foreach ($this->repository->allForSystem() as $event) {
            if (null !== $ownerId && $event->ownerId() !== $ownerId) {
                continue;
            }
            if (null !== $vehicleId && $event->vehicleId() !== $vehicleId) {
                continue;
            }
            if (null !== $eventType && $event->eventType() !== $eventType) {
                continue;
            }

            $resources[] = $this->toOutput($event);
        }

        return $resources;
    }

    private function toOutput(MaintenanceEvent $event): AdminMaintenanceEventOutput
    {
        return new AdminMaintenanceEventOutput(
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
    private function readEnumFilter(array $context, string $name): ?MaintenanceEventType
    {
        $value = $this->readFilter($context, $name);
        if (null === $value) {
            return null;
        }

        try {
            return MaintenanceEventType::from($value);
        } catch (ValueError) {
            return null;
        }
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
