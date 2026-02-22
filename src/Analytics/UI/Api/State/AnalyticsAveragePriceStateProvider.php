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
use App\Analytics\UI\Api\Resource\Output\AnalyticsAveragePriceOutput;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;

/** @implements ProviderInterface<object> */
final readonly class AnalyticsAveragePriceStateProvider implements ProviderInterface
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

        $vehicleId = AnalyticsFilterReader::readVehicleId($context);
        $stationId = AnalyticsFilterReader::readStationId($context);
        $fuelType = AnalyticsFilterReader::readFuelType($context);
        $from = AnalyticsFilterReader::readDateFilter($context, 'from');
        $to = AnalyticsFilterReader::readDateFilter($context, 'to');
        $kpi = $this->kpiReader->readAveragePrice($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        return [new AnalyticsAveragePriceOutput(
            'average',
            $vehicleId,
            $from,
            $to,
            $kpi->totalCostCents,
            $kpi->totalQuantityMilliLiters,
            $kpi->averagePriceDeciCentsPerLiter,
            'deci_cent_per_liter',
        )];
    }
}
