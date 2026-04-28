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

use App\Analytics\Application\Aggregation\ReceiptAnalyticsProjectionRefresher;
use App\Analytics\Application\Kpi\AnalyticsKpiReader;
use App\Analytics\Application\Kpi\FuelDashboardSnapshotKpi;
use App\Analytics\Application\Kpi\MonthlyComparedCostKpi;
use App\Analytics\Application\Kpi\MonthlyConsumptionKpi;
use App\Analytics\Application\Kpi\MonthlyCostKpi;
use App\Analytics\Application\Kpi\MonthlyFuelPriceKpi;
use App\Analytics\Application\Kpi\VisitedStationPointKpi;
use App\Analytics\Application\Map\AnalyticsStationMapBuilder;
use App\Receipt\Application\Repository\ReceiptRepository;
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
        private readonly ReceiptRepository $receiptRepository,
        private readonly ReceiptAnalyticsProjectionRefresher $receiptAnalyticsProjectionRefresher,
        private readonly VehicleRepository $vehicleRepository,
        private readonly StationRepository $stationRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly AnalyticsStationMapBuilder $analyticsStationMapBuilder,
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
        [
            'fuelSnapshot' => $fuelSnapshot,
            'comparedCostPerMonth' => $comparedCostPerMonth,
            'visitedStations' => $visitedStations,
        ] = $this->readDashboardData($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        $costPerMonth = $fuelSnapshot->costPerMonth;
        $consumptionPerMonth = $fuelSnapshot->consumptionPerMonth;
        $averagePrice = $fuelSnapshot->averagePrice;
        $fuelPricePerMonth = $fuelSnapshot->fuelPricePerMonth;
        $vehicleOptions = $this->vehicleOptions($ownerId);
        $stationOptions = $this->stationOptions();
        $stationMap = $this->analyticsStationMapBuilder->build($visitedStations);
        $analyticsQueryParams = [
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'vehicle_id' => $vehicleId,
            'station_id' => $stationId,
            'fuel_type' => $fuelType,
        ];
        $receiptQueryParams = [
            'vehicle_id' => $vehicleId,
            'station_id' => $stationId,
            'fuel_type' => $fuelType,
            'issued_from' => $from?->format('Y-m-d'),
            'issued_to' => $to?->format('Y-m-d'),
        ];

        return $this->render('analytics/index.html.twig', [
            'vehicleOptions' => $vehicleOptions,
            'stationOptions' => $stationOptions,
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
            'stationMap' => $stationMap,
            'dateShortcuts' => $this->dateShortcuts($analyticsQueryParams),
            'activeFilterBadges' => $this->activeFilterBadges($vehicleOptions, $stationOptions, $vehicleId, $stationId, $fuelType, $from, $to),
            'selectedVehicleLabel' => null !== $vehicleId ? ($vehicleOptions[$vehicleId] ?? null) : null,
            'selectedStationLabel' => $this->selectedStationLabel($stationOptions, $stationId),
            'analyticsQueryParams' => $analyticsQueryParams,
            'receiptQueryParams' => $receiptQueryParams,
            'exportQueryParams' => [
                'vehicle_id' => $vehicleId,
                'issued_from' => $from?->format('Y-m-d'),
                'issued_to' => $to?->format('Y-m-d'),
                'station_id' => $stationId,
                'fuel_type' => $fuelType,
            ],
        ]);
    }

    /**
     * @return array{
     *     fuelSnapshot: FuelDashboardSnapshotKpi,
     *     comparedCostPerMonth: list<MonthlyComparedCostKpi>,
     *     visitedStations: list<VisitedStationPointKpi>
     * }
     */
    private function readDashboardData(
        string $ownerId,
        ?string $vehicleId,
        ?string $stationId,
        ?string $fuelType,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
    ): array {
        $fuelSnapshot = $this->kpiReader->readFuelDashboardSnapshot($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
        $comparedCostPerMonth = $this->kpiReader->readComparedCostPerMonth($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
        $visitedStations = $this->kpiReader->readVisitedStations($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);

        if ($this->shouldRefreshProjection($fuelSnapshot, $vehicleId, $stationId, $fuelType, $from, $to)) {
            $this->receiptAnalyticsProjectionRefresher->refresh();
            $fuelSnapshot = $this->kpiReader->readFuelDashboardSnapshot($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
            $comparedCostPerMonth = $this->kpiReader->readComparedCostPerMonth($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
            $visitedStations = $this->kpiReader->readVisitedStations($ownerId, $vehicleId, $stationId, $fuelType, $from, $to);
        }

        return [
            'fuelSnapshot' => $fuelSnapshot,
            'comparedCostPerMonth' => $comparedCostPerMonth,
            'visitedStations' => $visitedStations,
        ];
    }

    private function shouldRefreshProjection(
        FuelDashboardSnapshotKpi $fuelSnapshot,
        ?string $vehicleId,
        ?string $stationId,
        ?string $fuelType,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
    ): bool {
        if ($fuelSnapshot->averagePrice->totalCostCents > 0 || $fuelSnapshot->averagePrice->totalQuantityMilliLiters > 0) {
            return false;
        }

        return $this->receiptRepository->countFiltered(
            $vehicleId,
            $stationId,
            $from,
            $to,
            $fuelType,
        ) > 0;
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

    /** @param array<string, ?string> $baseParams
     * @return list<array{label:string,params:array<string, ?string>,isActive:bool}>
     */
    private function dateShortcuts(array $baseParams): array
    {
        $today = new DateTimeImmutable('today');
        $thisMonthStart = $today->modify('first day of this month');
        $thisMonthEnd = $today->modify('last day of this month');

        $shortcuts = [
            [
                'label' => 'Last 30 days',
                'params' => array_merge($baseParams, [
                    'from' => $today->modify('-29 days')->format('Y-m-d'),
                    'to' => $today->format('Y-m-d'),
                ]),
                'isActive' => false,
            ],
            [
                'label' => 'Last 90 days',
                'params' => array_merge($baseParams, [
                    'from' => $today->modify('-89 days')->format('Y-m-d'),
                    'to' => $today->format('Y-m-d'),
                ]),
                'isActive' => false,
            ],
            [
                'label' => 'This month',
                'params' => array_merge($baseParams, [
                    'from' => $thisMonthStart->format('Y-m-d'),
                    'to' => $thisMonthEnd->format('Y-m-d'),
                ]),
                'isActive' => false,
            ],
        ];

        foreach ($shortcuts as &$shortcut) {
            $shortcut['isActive'] = ($baseParams['from'] ?? null) === $shortcut['params']['from']
                && ($baseParams['to'] ?? null) === $shortcut['params']['to'];
        }

        return $shortcuts;
    }

    /**
     * @param array<string, string>               $vehicleOptions
     * @param list<array{id:string,label:string}> $stationOptions
     *
     * @return list<string>
     */
    private function activeFilterBadges(array $vehicleOptions, array $stationOptions, ?string $vehicleId, ?string $stationId, ?string $fuelType, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $badges = [];

        if (null !== $from || null !== $to) {
            $badges[] = sprintf(
                'Period: %s -> %s',
                $from?->format('d/m/Y') ?? 'start',
                $to?->format('d/m/Y') ?? 'today',
            );
        }

        if (null !== $vehicleId && isset($vehicleOptions[$vehicleId])) {
            $badges[] = 'Vehicle: '.$vehicleOptions[$vehicleId];
        }

        $stationLabel = $this->selectedStationLabel($stationOptions, $stationId);
        if (null !== $stationLabel) {
            $badges[] = 'Station: '.$stationLabel;
        }

        if (null !== $fuelType) {
            $badges[] = 'Fuel: '.strtoupper($fuelType);
        }

        return $badges;
    }

    /**
     * @param list<array{id:string,label:string}> $stationOptions
     */
    private function selectedStationLabel(array $stationOptions, ?string $stationId): ?string
    {
        if (null === $stationId) {
            return null;
        }

        foreach ($stationOptions as $option) {
            if ($option['id'] === $stationId) {
                return $option['label'];
            }
        }

        return null;
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
