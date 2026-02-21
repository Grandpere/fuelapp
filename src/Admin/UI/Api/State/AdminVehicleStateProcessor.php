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
use ApiPlatform\State\ProcessorInterface;
use App\Admin\UI\Api\Resource\Input\AdminVehicleInput;
use App\Admin\UI\Api\Resource\Output\AdminVehicleOutput;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminVehicleOutput>
 */
final readonly class AdminVehicleStateProcessor implements ProcessorInterface
{
    public function __construct(private VehicleRepository $repository)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminVehicleOutput
    {
        if (!$data instanceof AdminVehicleInput) {
            throw new InvalidArgumentException('Invalid vehicle input.');
        }

        $id = $uriVariables['id'] ?? null;
        $vehicle = null;

        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $vehicle = $this->repository->get($id);
            if (!$vehicle instanceof Vehicle) {
                throw new NotFoundHttpException();
            }
        } else {
            $vehicle = Vehicle::create($data->ownerId, $data->name, $data->plateNumber);
        }

        if (!$this->repository->ownerExists($data->ownerId)) {
            throw new NotFoundHttpException('Owner not found.');
        }

        $existing = $this->repository->findByOwnerAndPlateNumber($data->ownerId, $data->plateNumber);
        if ($existing instanceof Vehicle && $existing->id()->toString() !== $vehicle->id()->toString()) {
            throw new ConflictHttpException('A vehicle with this plate number already exists for this owner.');
        }

        $vehicle->update($data->ownerId, $data->name, $data->plateNumber);
        $this->repository->save($vehicle);

        return new AdminVehicleOutput(
            $vehicle->id()->toString(),
            $vehicle->ownerId(),
            $vehicle->name(),
            $vehicle->plateNumber(),
            $vehicle->createdAt(),
            $vehicle->updatedAt(),
        );
    }
}
