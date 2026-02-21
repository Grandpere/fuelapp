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
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenancePlannedCost;
use App\Maintenance\UI\Api\Resource\Input\MaintenancePlannedCostInput;
use App\Maintenance\UI\Api\Resource\Output\MaintenancePlannedCostOutput;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Vehicle\Application\Repository\VehicleRepository;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, MaintenancePlannedCostOutput>
 */
final readonly class MaintenancePlannedCostStateProcessor implements ProcessorInterface
{
    public function __construct(
        private MaintenancePlannedCostRepository $repository,
        private VehicleRepository $vehicleRepository,
        private AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenancePlannedCostOutput
    {
        if (!$data instanceof MaintenancePlannedCostInput) {
            throw new InvalidArgumentException('Invalid planned cost input.');
        }

        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        if (!$this->vehicleRepository->belongsToOwner($data->vehicleId, $ownerId)) {
            throw new NotFoundHttpException('Vehicle not found.');
        }

        $eventType = null === $data->eventType ? null : MaintenanceEventType::from($data->eventType);
        $id = $uriVariables['id'] ?? null;
        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $item = $this->repository->get($id);
            if (!$item instanceof MaintenancePlannedCost || $item->ownerId() !== $ownerId) {
                throw new NotFoundHttpException();
            }

            $item->update(
                $data->vehicleId,
                $data->label,
                $eventType,
                $data->plannedFor,
                $data->plannedCostCents,
                $data->currencyCode,
                $data->notes,
            );
        } else {
            $item = MaintenancePlannedCost::create(
                $ownerId,
                $data->vehicleId,
                $data->label,
                $eventType,
                $data->plannedFor,
                $data->plannedCostCents,
                $data->currencyCode,
                $data->notes,
            );
        }

        $this->repository->save($item);

        return new MaintenancePlannedCostOutput(
            $item->id()->toString(),
            $item->ownerId(),
            $item->vehicleId(),
            $item->label(),
            $item->eventType()?->value,
            $item->plannedFor(),
            $item->plannedCostCents(),
            $item->currencyCode(),
            $item->notes(),
            $item->createdAt(),
            $item->updatedAt(),
        );
    }
}
