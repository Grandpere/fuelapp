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

namespace App\Analytics\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Analytics\Application\Kpi\AnalyticsKpiReader;
use App\Analytics\UI\Api\Resource\Output\AnalyticsComparedCostPerMonthOutput;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;

/** @implements ProviderInterface<object> */
final readonly class AnalyticsComparedCostPerMonthStateProvider implements ProviderInterface
{
    public function __construct(
        private AnalyticsKpiReader $kpiReader,
        private AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            return [];
        }

        $items = $this->kpiReader->readComparedCostPerMonth(
            $ownerId,
            AnalyticsFilterReader::readVehicleId($context),
            AnalyticsFilterReader::readStationId($context),
            AnalyticsFilterReader::readFuelType($context),
            AnalyticsFilterReader::readDateFilter($context, 'from'),
            AnalyticsFilterReader::readDateFilter($context, 'to'),
        );

        $result = [];
        foreach ($items as $item) {
            $result[] = new AnalyticsComparedCostPerMonthOutput(
                $item->month,
                $item->fuelCostCents,
                $item->maintenanceCostCents,
                $item->totalCostCents,
                'EUR',
            );
        }

        return $result;
    }
}
