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

namespace App\Analytics\UI\Web\Controller;

use App\Analytics\Application\Kpi\AnalyticsKpiReader;
use App\Analytics\Application\Kpi\MonthlyConsumptionKpi;
use App\Analytics\Application\Kpi\MonthlyCostKpi;
use App\Receipt\Domain\Enum\FuelType;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AnalyticsDashboardController extends AbstractController
{
    public function __construct(
        private readonly AnalyticsKpiReader $kpiReader,
        private readonly VehicleRepository $vehicleRepository,
        private readonly StationRepository $stationRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    #[Route('/ui/analytics', name: 'ui_analytics_dashboard', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $from = $this->readDateFilter($request, 'from');
        $to = $this->readDateFilter($request, 'to');
        $vehicleId = $this->readVehicleFilter($request, $ownerId);
        $stationId = $this->readStationFilter($request);
        $fuelType = $this->readFuelTypeFilter($request);

        $costPerMonth = $this->kpiReader->readCostPerMonth($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
        $consumptionPerMonth = $this->kpiReader->readConsumptionPerMonth($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
        $averagePrice = $this->kpiReader->readAveragePrice($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        return $this->render('analytics/index.html.twig', [
            'vehicleOptions' => $this->vehicleOptions($ownerId),
            'stationOptions' => $this->stationOptions(),
            'fuelTypeChoices' => array_map(static fn (FuelType $case): string => $case->value, FuelType::cases()),
            'vehicleFilter' => $vehicleId,
            'stationFilter' => $stationId,
            'fuelTypeFilter' => $fuelType,
            'fromFilter' => $from?->format('Y-m-d'),
            'toFilter' => $to?->format('Y-m-d'),
            'costPerMonth' => $costPerMonth,
            'consumptionPerMonth' => $consumptionPerMonth,
            'costTrend' => $this->costTrend($costPerMonth),
            'consumptionTrend' => $this->consumptionTrend($consumptionPerMonth),
            'totalCostCents' => $averagePrice->totalCostCents,
            'totalQuantityMilliLiters' => $averagePrice->totalQuantityMilliLiters,
            'averagePriceDeciCentsPerLiter' => $averagePrice->averagePriceDeciCentsPerLiter,
            'exportQueryParams' => [
                'issued_from' => $from?->format('Y-m-d'),
                'issued_to' => $to?->format('Y-m-d'),
                'station_id' => $stationId,
                'fuel_type' => $fuelType,
            ],
        ]);
    }

    /** @return array<string, string> */
    private function vehicleOptions(string $ownerId): array
    {
        $options = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $options[$vehicle->id()->toString()] = sprintf('%s (%s)', $vehicle->name(), $vehicle->plateNumber());
        }

        return $options;
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private function stationOptions(): array
    {
        $options = [];
        foreach ($this->stationRepository->all() as $station) {
            $options[] = [
                'id' => $station->id()->toString(),
                'label' => sprintf(
                    '%s - %s, %s %s',
                    $station->name(),
                    $station->streetName(),
                    $station->postalCode(),
                    $station->city(),
                ),
            ];
        }

        usort(
            $options,
            static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']),
        );

        return $options;
    }

    private function readDateFilter(Request $request, string $name): ?DateTimeImmutable
    {
        $raw = $request->query->get($name);
        if (!is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        if ('' === $value) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false !== $parsed) {
            return $parsed;
        }

        $date = date_create_immutable($value);

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private function readVehicleFilter(Request $request, string $ownerId): ?string
    {
        $raw = $request->query->get('vehicle_id');
        if (!is_scalar($raw)) {
            return null;
        }

        $vehicleId = trim((string) $raw);
        if ('' === $vehicleId || !Uuid::isValid($vehicleId)) {
            return null;
        }

        return $this->vehicleRepository->belongsToOwner($vehicleId, $ownerId) ? $vehicleId : null;
    }

    private function readStationFilter(Request $request): ?string
    {
        $raw = $request->query->get('station_id');
        if (!is_scalar($raw)) {
            return null;
        }

        $stationId = trim((string) $raw);
        if ('' === $stationId || !Uuid::isValid($stationId)) {
            return null;
        }

        return $stationId;
    }

    private function readFuelTypeFilter(Request $request): ?string
    {
        $raw = $request->query->get('fuel_type');
        if (!is_scalar($raw)) {
            return null;
        }

        $fuelType = trim((string) $raw);
        if ('' === $fuelType) {
            return null;
        }

        $choices = array_map(static fn (FuelType $case): string => $case->value, FuelType::cases());

        return in_array($fuelType, $choices, true) ? $fuelType : null;
    }

    /**
     * @param list<MonthlyCostKpi> $items
     *
     * @return list<array{month:string,value:int,ratio:int}>
     */
    private function costTrend(array $items): array
    {
        $max = 0;
        foreach ($items as $item) {
            $max = max($max, $item->totalCostCents);
        }

        $trend = [];
        foreach ($items as $item) {
            $trend[] = [
                'month' => $item->month,
                'value' => $item->totalCostCents,
                'ratio' => $max > 0 ? max(8, (int) round(($item->totalCostCents / $max) * 100, 0, PHP_ROUND_HALF_UP)) : 0,
            ];
        }

        return $trend;
    }

    /**
     * @param list<MonthlyConsumptionKpi> $items
     *
     * @return list<array{month:string,value:int,ratio:int}>
     */
    private function consumptionTrend(array $items): array
    {
        $max = 0;
        foreach ($items as $item) {
            $max = max($max, $item->totalQuantityMilliLiters);
        }

        $trend = [];
        foreach ($items as $item) {
            $trend[] = [
                'month' => $item->month,
                'value' => $item->totalQuantityMilliLiters,
                'ratio' => $max > 0 ? max(8, (int) round(($item->totalQuantityMilliLiters / $max) * 100, 0, PHP_ROUND_HALF_UP)) : 0,
            ];
        }

        return $trend;
    }
}
