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
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Maintenance\Domain\MaintenancePlannedCost;
use App\Maintenance\UI\Api\Resource\Output\MaintenancePlannedCostOutput;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MaintenancePlannedCostOutput>
 */
final readonly class MaintenancePlannedCostStateProvider implements ProviderInterface
{
    public function __construct(
        private MaintenancePlannedCostRepository $repository,
        private AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            return null;
        }

        $id = $uriVariables['id'] ?? null;
        if (is_string($id)) {
            if (!Uuid::isValid($id)) {
                return null;
            }

            $item = $this->repository->get($id);
            if (!$item instanceof MaintenancePlannedCost || $item->ownerId() !== $ownerId) {
                return null;
            }

            return $this->toOutput($item);
        }

        $resources = [];
        foreach ($this->repository->allForOwner($ownerId) as $item) {
            $resources[] = $this->toOutput($item);
        }

        return $resources;
    }

    private function toOutput(MaintenancePlannedCost $item): MaintenancePlannedCostOutput
    {
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
