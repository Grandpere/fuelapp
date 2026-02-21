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
use ApiPlatform\State\ProcessorInterface;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Maintenance\UI\Api\Resource\Input\MaintenanceEventInput;
use App\Maintenance\UI\Api\Resource\Output\MaintenanceEventOutput;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Vehicle\Application\Repository\VehicleRepository;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, MaintenanceEventOutput>
 */
final readonly class MaintenanceEventStateProcessor implements ProcessorInterface
{
    public function __construct(
        private MaintenanceEventRepository $repository,
        private VehicleRepository $vehicleRepository,
        private AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenanceEventOutput
    {
        if (!$data instanceof MaintenanceEventInput) {
            throw new InvalidArgumentException('Invalid maintenance event input.');
        }

        $ownerId = $this->currentUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        if (!$this->vehicleRepository->belongsToOwner($data->vehicleId, $ownerId)) {
            throw new NotFoundHttpException('Vehicle not found.');
        }

        $eventType = MaintenanceEventType::from($data->eventType);
        $id = $uriVariables['id'] ?? null;

        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $event = $this->repository->get($id);
            if (!$event instanceof MaintenanceEvent || $event->ownerId() !== $ownerId) {
                throw new NotFoundHttpException();
            }

            $event->update(
                $data->vehicleId,
                $eventType,
                $data->occurredAt,
                $data->description,
                $data->odometerKilometers,
                $data->totalCostCents,
                $data->currencyCode,
            );
        } else {
            $event = MaintenanceEvent::create(
                $ownerId,
                $data->vehicleId,
                $eventType,
                $data->occurredAt,
                $data->description,
                $data->odometerKilometers,
                $data->totalCostCents,
                $data->currencyCode,
            );
        }

        $this->repository->save($event);

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

    private function currentUserId(): ?string
    {
        return $this->authenticatedUserIdProvider->getAuthenticatedUserId();
    }
}
