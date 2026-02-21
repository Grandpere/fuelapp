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
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class AdminVehicleDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private VehicleRepository $repository,
        private AdminAuditTrail $auditTrail,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? null;
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $vehicle = $this->repository->get($id);
        if (!$vehicle instanceof Vehicle) {
            throw new NotFoundHttpException();
        }

        $this->repository->delete($id);
        $this->auditTrail->record(
            'admin.vehicle.deleted',
            'vehicle',
            $id,
            [
                'before' => [
                    'ownerId' => $vehicle->ownerId(),
                    'name' => $vehicle->name(),
                    'plateNumber' => $vehicle->plateNumber(),
                ],
            ],
        );
    }
}
