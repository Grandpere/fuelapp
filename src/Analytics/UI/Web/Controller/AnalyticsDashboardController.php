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
use App\Analytics\Application\Kpi\MonthlyComparedCostKpi;
use App\Analytics\Application\Kpi\MonthlyConsumptionKpi;
use App\Analytics\Application\Kpi\MonthlyCostKpi;
use App\Analytics\Application\Kpi\MonthlyFuelPriceKpi;
use App\Analytics\Application\Kpi\VisitedStationPointKpi;
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
        $fuelSnapshot = $this->kpiReader->readFuelDashboardSnapshot($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
        $costPerMonth = $fuelSnapshot->costPerMonth;
        $consumptionPerMonth = $fuelSnapshot->consumptionPerMonth;
        $averagePrice = $fuelSnapshot->averagePrice;
        $fuelPricePerMonth = $fuelSnapshot->fuelPricePerMonth;
        $comparedCostPerMonth = $this->kpiReader->readComparedCostPerMonth($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
        $visitedStations = $this->kpiReader->readVisitedStations($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

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
            'fuelPriceTrend' => $this->fuelPriceTrend($fuelPricePerMonth),
            'comparedCostTrend' => $this->comparedCostTrend($comparedCostPerMonth),
            'totalCostCents' => $averagePrice->totalCostCents,
            'totalQuantityMilliLiters' => $averagePrice->totalQuantityMilliLiters,
            'averagePriceDeciCentsPerLiter' => $averagePrice->averagePriceDeciCentsPerLiter,
            'visitedStations' => $visitedStations,
            'stationMapPoints' => $this->stationMapPoints($visitedStations),
            'exportQueryParams' => [
                'vehicle_id' => $vehicleId,
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

    /**
     * @param list<VisitedStationPointKpi> $items
     *
     * @return list<array{
     *     stationId:string,
     *     stationName:string,
     *     address:string,
     *     latitude:float,
     *     longitude:float,
     *     receiptCount:int,
     *     totalCostCents:int,
     *     totalQuantityMilliLiters:int
     * }>
     */
    private function stationMapPoints(array $items): array
    {
        $points = [];
        foreach ($items as $item) {
            $points[] = [
                'stationId' => $item->stationId,
                'stationName' => $item->stationName,
                'address' => sprintf('%s, %s %s', $item->streetName, $item->postalCode, $item->city),
                'latitude' => $item->latitudeMicroDegrees / 1_000_000,
                'longitude' => $item->longitudeMicroDegrees / 1_000_000,
                'receiptCount' => $item->receiptCount,
                'totalCostCents' => $item->totalCostCents,
                'totalQuantityMilliLiters' => $item->totalQuantityMilliLiters,
            ];
        }

        return $points;
    }

    /**
     * @param list<MonthlyFuelPriceKpi> $items
     *
     * @return list<array{
     *     month:string,
     *     fuelType:string,
     *     averagePriceDeciCentsPerLiter:?int,
     *     totalCostCents:int,
     *     totalQuantityMilliLiters:int,
     *     ratio:int
     * }>
     */
    private function fuelPriceTrend(array $items): array
    {
        $max = 0;
        foreach ($items as $item) {
            $max = max($max, $item->averagePriceDeciCentsPerLiter ?? 0);
        }

        $trend = [];
        foreach ($items as $item) {
            $average = $item->averagePriceDeciCentsPerLiter;
            $trend[] = [
                'month' => $item->month,
                'fuelType' => $item->fuelType,
                'averagePriceDeciCentsPerLiter' => $average,
                'totalCostCents' => $item->totalCostCents,
                'totalQuantityMilliLiters' => $item->totalQuantityMilliLiters,
                'ratio' => null !== $average && $max > 0 ? max(8, (int) round(($average / $max) * 100, 0, PHP_ROUND_HALF_UP)) : 0,
            ];
        }

        return $trend;
    }

    /**
     * @param list<MonthlyComparedCostKpi> $items
     *
     * @return list<array{
     *     month:string,
     *     fuelCostCents:int,
     *     maintenanceCostCents:int,
     *     totalCostCents:int,
     *     fuelRatio:int,
     *     maintenanceRatio:int,
     *     totalRatio:int
     * }>
     */
    private function comparedCostTrend(array $items): array
    {
        $max = 0;
        foreach ($items as $item) {
            $max = max($max, $item->totalCostCents);
        }

        $trend = [];
        foreach ($items as $item) {
            $trend[] = [
                'month' => $item->month,
                'fuelCostCents' => $item->fuelCostCents,
                'maintenanceCostCents' => $item->maintenanceCostCents,
                'totalCostCents' => $item->totalCostCents,
                'fuelRatio' => $max > 0 ? max(8, (int) round(($item->fuelCostCents / $max) * 100, 0, PHP_ROUND_HALF_UP)) : 0,
                'maintenanceRatio' => $max > 0 ? max(8, (int) round(($item->maintenanceCostCents / $max) * 100, 0, PHP_ROUND_HALF_UP)) : 0,
                'totalRatio' => $max > 0 ? max(8, (int) round(($item->totalCostCents / $max) * 100, 0, PHP_ROUND_HALF_UP)) : 0,
            ];
        }

        return $trend;
    }
}
