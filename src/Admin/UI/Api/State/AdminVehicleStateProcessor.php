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
use App\Admin\Application\Audit\AdminAuditTrail;
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
    public function __construct(
        private VehicleRepository $repository,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminVehicleOutput
    {
        if (!$data instanceof AdminVehicleInput) {
            throw new InvalidArgumentException('Invalid vehicle input.');
        }

        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $vehicle = $this->repository->get($id);
        if (!$vehicle instanceof Vehicle) {
            throw new NotFoundHttpException();
        }

        $before = $this->snapshot($vehicle);

        if (!$this->repository->ownerExists($data->ownerId)) {
            throw new NotFoundHttpException('Owner not found.');
        }

        $existing = $this->repository->findByOwnerAndPlateNumber($data->ownerId, $data->plateNumber);
        if ($existing instanceof Vehicle && $existing->id()->toString() !== $vehicle->id()->toString()) {
            throw new ConflictHttpException('A vehicle with this plate number already exists for this owner.');
        }

        $vehicle->update($data->ownerId, $data->name, $data->plateNumber);
        $this->repository->save($vehicle);
        $after = $this->snapshot($vehicle);
        $this->auditTrail->record(
            'admin.vehicle.updated',
            'vehicle',
            $vehicle->id()->toString(),
            [
                'before' => $before,
                'after' => $after,
                'changed' => $this->diff($before, $after),
            ],
        );

        return new AdminVehicleOutput(
            $vehicle->id()->toString(),
            $vehicle->ownerId(),
            $vehicle->name(),
            $vehicle->plateNumber(),
            $vehicle->createdAt(),
            $vehicle->updatedAt(),
        );
    }

    /** @return array<string, mixed> */
    private function snapshot(Vehicle $vehicle): array
    {
        return [
            'ownerId' => $vehicle->ownerId(),
            'name' => $vehicle->name(),
            'plateNumber' => $vehicle->plateNumber(),
        ];
    }

    /** @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;
            if ($previous !== $value) {
                $changed[$key] = ['before' => $previous, 'after' => $value];
            }
        }

        return $changed;
    }
}
