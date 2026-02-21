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
use App\Admin\UI\Api\Resource\Output\AdminVehicleOutput;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminVehicleOutput>
 */
final readonly class AdminVehicleStateProvider implements ProviderInterface
{
    public function __construct(private VehicleRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $id = $uriVariables['id'] ?? null;
        if (null !== $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                throw new NotFoundHttpException();
            }

            $vehicle = $this->repository->get($id);
            if (!$vehicle instanceof Vehicle) {
                throw new NotFoundHttpException();
            }

            return $this->toOutput($vehicle);
        }

        $query = $this->readFilter($context, 'q');
        $ownerId = $this->readOwnerIdFilter($context, 'ownerId');
        $resources = [];
        foreach ($this->repository->all() as $vehicle) {
            if (null !== $query && !$this->matchesQuery($vehicle, $query)) {
                continue;
            }
            if (null !== $ownerId && $vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $resources[] = $this->toOutput($vehicle);
        }

        return $resources;
    }

    private function toOutput(Vehicle $vehicle): AdminVehicleOutput
    {
        return new AdminVehicleOutput(
            $vehicle->id()->toString(),
            $vehicle->ownerId(),
            $vehicle->name(),
            $vehicle->plateNumber(),
            $vehicle->createdAt(),
            $vehicle->updatedAt(),
        );
    }

    private function matchesQuery(Vehicle $vehicle, string $query): bool
    {
        $haystack = mb_strtolower(sprintf('%s %s', $vehicle->name(), $vehicle->plateNumber()));

        return str_contains($haystack, mb_strtolower($query));
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
    private function readOwnerIdFilter(array $context, string $name): ?string
    {
        $value = $this->readFilter($context, $name);
        if (null === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $value;
    }
}
