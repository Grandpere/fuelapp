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

namespace App\Receipt\UI\Web\Controller;

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\UI\Realtime\ReceiptStreamPublisher;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListReceiptsController extends AbstractController
{
    private const PRESET_CUSTOM = 'custom';
    private const PRESET_COMPACT = 'compact';
    private const PRESET_MOBILE = 'mobile';
    private const PRESET_FULL = 'full';
    private const PRESET_EXPORT_ACCOUNTING = 'export_accounting';

    /** @var list<string> */
    private const DEFAULT_COLUMNS = [
        'issued_at',
        'station_name',
        'odometer_kilometers',
        'fuel_type',
        'quantity_milli_liters',
        'total_cents',
    ];

    /** @var array<string, string> */
    private const COLUMN_LABELS = [
        'id' => 'Receipt ID',
        'issued_at' => 'Date',
        'station_name' => 'Station name',
        'station_street_name' => 'Station street',
        'station_postal_code' => 'Station postal code',
        'station_city' => 'Station city',
        'odometer_kilometers' => 'Odometer (km)',
        'fuel_type' => 'Fuel type',
        'quantity_milli_liters' => 'Quantity (mL)',
        'unit_price_deci_cents_per_liter' => 'Unit price (deci-cents/L)',
        'vat_rate_percent' => 'VAT rate (%)',
        'total_cents' => 'Total (cents)',
        'vat_amount_cents' => 'VAT amount (cents)',
    ];

    /** @var array<string, list<string>> */
    private const PRESET_COLUMNS = [
        self::PRESET_COMPACT => [
            'issued_at',
            'station_name',
            'odometer_kilometers',
            'fuel_type',
            'quantity_milli_liters',
            'total_cents',
        ],
        self::PRESET_MOBILE => [
            'issued_at',
            'station_name',
            'total_cents',
        ],
        self::PRESET_FULL => [
            'id',
            'issued_at',
            'station_name',
            'station_street_name',
            'station_postal_code',
            'station_city',
            'odometer_kilometers',
            'fuel_type',
            'quantity_milli_liters',
            'unit_price_deci_cents_per_liter',
            'vat_rate_percent',
            'total_cents',
            'vat_amount_cents',
        ],
        self::PRESET_EXPORT_ACCOUNTING => [
            'id',
            'issued_at',
            'station_name',
            'odometer_kilometers',
            'fuel_type',
            'quantity_milli_liters',
            'unit_price_deci_cents_per_liter',
            'vat_rate_percent',
            'total_cents',
            'vat_amount_cents',
        ],
    ];

    /** @var array<string, string> */
    private const PRESET_LABELS = [
        self::PRESET_CUSTOM => 'Custom',
        self::PRESET_COMPACT => 'Compact',
        self::PRESET_MOBILE => 'Mobile',
        self::PRESET_FULL => 'Full',
        self::PRESET_EXPORT_ACCOUNTING => 'Export accounting',
    ];

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly StationRepository $stationRepository,
        private readonly VehicleRepository $vehicleRepository,
    ) {
    }

    #[Route('/ui/receipts', name: 'ui_receipt_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('per_page', 25)));
        $vehicleId = $this->nullableString($request->query->get('vehicle_id'));
        $stationId = $this->nullableString($request->query->get('station_id'));
        $issuedFrom = $this->parseDate($request->query->get('issued_from'));
        $issuedTo = $this->parseDate($request->query->get('issued_to'));
        $columnsPreset = $this->parsePreset($request->query->get('columns_preset'));
        $visibleColumns = $this->resolveColumns($request->query->all('columns'), $columnsPreset);
        $selectedColumnsPreset = $columnsPreset ?? $this->detectPreset($visibleColumns);
        $fuelType = $this->parseFuelType($request->query->get('fuel_type'));
        $quantityMilliLitersMin = $this->parseInt($request->query->get('quantity_min'));
        $quantityMilliLitersMax = $this->parseInt($request->query->get('quantity_max'));
        $unitPriceDeciCentsPerLiterMin = $this->parseInt($request->query->get('unit_price_min'));
        $unitPriceDeciCentsPerLiterMax = $this->parseInt($request->query->get('unit_price_max'));
        $vatRatePercent = $this->parseInt($request->query->get('vat_rate'));

        $sortBy = in_array((string) $request->query->get('sort_by'), ['date', 'total', 'fuel_type', 'quantity', 'unit_price', 'vat_rate'], true)
            ? (string) $request->query->get('sort_by')
            : 'date';
        $sortDirection = 'asc' === strtolower((string) $request->query->get('sort_direction')) ? 'asc' : 'desc';

        $total = $this->receiptRepository->countFiltered(
            $vehicleId,
            $stationId,
            $issuedFrom,
            $issuedTo,
            $fuelType,
            $quantityMilliLitersMin,
            $quantityMilliLitersMax,
            $unitPriceDeciCentsPerLiterMin,
            $unitPriceDeciCentsPerLiterMax,
            $vatRatePercent,
        );
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $rows = $this->receiptRepository->paginateFilteredListRows(
            $page,
            $perPage,
            $vehicleId,
            $stationId,
            $issuedFrom,
            $issuedTo,
            $sortBy,
            $sortDirection,
            $fuelType,
            $quantityMilliLitersMin,
            $quantityMilliLitersMax,
            $unitPriceDeciCentsPerLiterMin,
            $unitPriceDeciCentsPerLiterMax,
            $vatRatePercent,
        );

        $stationOptions = [];
        $vehicleOptions = [];
        foreach ($this->stationRepository->all() as $stationOption) {
            $stationOptions[] = [
                'id' => $stationOption->id()->toString(),
                'label' => sprintf(
                    '%s - %s, %s %s',
                    $stationOption->name(),
                    $stationOption->streetName(),
                    $stationOption->postalCode(),
                    $stationOption->city(),
                ),
            ];
        }
        foreach ($this->vehicleRepository->all() as $vehicleOption) {
            $vehicleOptions[] = [
                'id' => $vehicleOption->id()->toString(),
                'label' => sprintf('%s (%s)', $vehicleOption->name(), $vehicleOption->plateNumber()),
            ];
        }

        usort(
            $stationOptions,
            static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']),
        );
        usort(
            $vehicleOptions,
            static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']),
        );

        $selectedVehicle = $this->findOption($vehicleOptions, $vehicleId);
        $selectedStation = $this->findOption($stationOptions, $stationId);

        $queryParams = [
            'per_page' => $perPage,
            'vehicle_id' => $vehicleId,
            'station_id' => $stationId,
            'issued_from' => $issuedFrom?->format('Y-m-d'),
            'issued_to' => $issuedTo?->format('Y-m-d'),
            'fuel_type' => $fuelType,
            'columns_preset' => $selectedColumnsPreset,
            'columns' => $visibleColumns,
            'quantity_min' => $quantityMilliLitersMin,
            'quantity_max' => $quantityMilliLitersMax,
            'unit_price_min' => $unitPriceDeciCentsPerLiterMin,
            'unit_price_max' => $unitPriceDeciCentsPerLiterMax,
            'vat_rate' => $vatRatePercent,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $enableRealtime = 1 === $page
            && null === $vehicleId
            && null === $stationId
            && null === $issuedFrom
            && null === $issuedTo
            && null === $fuelType
            && null === $quantityMilliLitersMin
            && null === $quantityMilliLitersMax
            && null === $unitPriceDeciCentsPerLiterMin
            && null === $unitPriceDeciCentsPerLiterMax
            && null === $vatRatePercent
            && self::DEFAULT_COLUMNS === $visibleColumns
            && 'date' === $sortBy
            && 'desc' === $sortDirection;

        return $this->render('receipt/index.html.twig', [
            'receipts' => $rows,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => $lastPage,
            'vehicleOptions' => $vehicleOptions,
            'stationOptions' => $stationOptions,
            'filters' => [
                'vehicleId' => $vehicleId,
                'stationId' => $stationId,
                'issuedFrom' => $issuedFrom?->format('Y-m-d'),
                'issuedTo' => $issuedTo?->format('Y-m-d'),
                'fuelType' => $fuelType,
                'columnsPreset' => $selectedColumnsPreset,
                'columns' => $visibleColumns,
                'quantityMin' => $quantityMilliLitersMin,
                'quantityMax' => $quantityMilliLitersMax,
                'unitPriceMin' => $unitPriceDeciCentsPerLiterMin,
                'unitPriceMax' => $unitPriceDeciCentsPerLiterMax,
                'vatRate' => $vatRatePercent,
                'sortBy' => $sortBy,
                'sortDirection' => $sortDirection,
            ],
            'queryParams' => $queryParams,
            'exportQueryParams' => $queryParams,
            'columnOptions' => self::COLUMN_LABELS,
            'columnPresetOptions' => self::PRESET_LABELS,
            'visibleColumns' => $visibleColumns,
            'fuelTypeChoices' => array_map(static fn (FuelType $fuelType): string => $fuelType->value, FuelType::cases()),
            'enableRealtime' => $enableRealtime,
            'mercureTopic' => ReceiptStreamPublisher::TOPIC,
            'selectedVehicle' => $selectedVehicle,
            'selectedStation' => $selectedStation,
            'activeFilters' => $this->buildActiveFilters($selectedVehicle, $selectedStation, $issuedFrom, $issuedTo, $fuelType, $vatRatePercent),
            'dateShortcutLinks' => $this->buildDateShortcutLinks(
                $perPage,
                $vehicleId,
                $stationId,
                $fuelType,
                $selectedColumnsPreset,
                $visibleColumns,
                $quantityMilliLitersMin,
                $quantityMilliLitersMax,
                $unitPriceDeciCentsPerLiterMin,
                $unitPriceDeciCentsPerLiterMax,
                $vatRatePercent,
                $sortBy,
                $sortDirection,
                $issuedFrom,
                $issuedTo,
            ),
            'newReceiptParams' => array_filter([
                'vehicle_id' => $vehicleId,
                'station_id' => $stationId,
            ], static fn (mixed $value): bool => null !== $value && '' !== $value),
        ]);
    }

    /** @param list<array{id: string, label: string}> $options
     * @return array{id: string, label: string}|null
     */
    private function findOption(array $options, ?string $id): ?array
    {
        if (null === $id) {
            return null;
        }

        foreach ($options as $option) {
            if ($option['id'] === $id) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param array{id: string, label: string}|null $selectedVehicle
     * @param array{id: string, label: string}|null $selectedStation
     *
     * @return list<array{label: string, value: string}>
     */
    private function buildActiveFilters(
        ?array $selectedVehicle,
        ?array $selectedStation,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        ?string $fuelType,
        ?int $vatRatePercent,
    ): array {
        $filters = [];

        if (null !== $selectedVehicle) {
            $filters[] = ['label' => 'Vehicle', 'value' => $selectedVehicle['label']];
        }

        if (null !== $selectedStation) {
            $filters[] = ['label' => 'Station', 'value' => $selectedStation['label']];
        }

        if ($issuedFrom instanceof DateTimeImmutable || $issuedTo instanceof DateTimeImmutable) {
            $filters[] = [
                'label' => 'Issued window',
                'value' => sprintf('%s -> %s', $issuedFrom?->format('d/m/Y') ?? '...', $issuedTo?->format('d/m/Y') ?? '...'),
            ];
        }

        if (null !== $fuelType) {
            $filters[] = ['label' => 'Fuel', 'value' => strtoupper($fuelType)];
        }

        if (null !== $vatRatePercent) {
            $filters[] = ['label' => 'VAT', 'value' => sprintf('%d%%', $vatRatePercent)];
        }

        return $filters;
    }

    /**
     * @param list<string> $visibleColumns
     *
     * @return list<array{label: string, params: array<string, mixed>, isActive: bool}>
     */
    private function buildDateShortcutLinks(
        int $perPage,
        ?string $vehicleId,
        ?string $stationId,
        ?string $fuelType,
        string $selectedColumnsPreset,
        array $visibleColumns,
        ?int $quantityMilliLitersMin,
        ?int $quantityMilliLitersMax,
        ?int $unitPriceDeciCentsPerLiterMin,
        ?int $unitPriceDeciCentsPerLiterMax,
        ?int $vatRatePercent,
        string $sortBy,
        string $sortDirection,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
    ): array {
        return [
            [
                'label' => 'Last 30 days',
                'params' => $this->buildShortcutQueryParams('-30 days', 'today', $perPage, $vehicleId, $stationId, $fuelType, $selectedColumnsPreset, $visibleColumns, $quantityMilliLitersMin, $quantityMilliLitersMax, $unitPriceDeciCentsPerLiterMin, $unitPriceDeciCentsPerLiterMax, $vatRatePercent, $sortBy, $sortDirection),
                'isActive' => $this->isSameDateShortcut($issuedFrom, $issuedTo, '-30 days', 'today'),
            ],
            [
                'label' => 'Last 90 days',
                'params' => $this->buildShortcutQueryParams('-90 days', 'today', $perPage, $vehicleId, $stationId, $fuelType, $selectedColumnsPreset, $visibleColumns, $quantityMilliLitersMin, $quantityMilliLitersMax, $unitPriceDeciCentsPerLiterMin, $unitPriceDeciCentsPerLiterMax, $vatRatePercent, $sortBy, $sortDirection),
                'isActive' => $this->isSameDateShortcut($issuedFrom, $issuedTo, '-90 days', 'today'),
            ],
            [
                'label' => 'This month',
                'params' => $this->buildShortcutQueryParams('first day of this month', 'last day of this month', $perPage, $vehicleId, $stationId, $fuelType, $selectedColumnsPreset, $visibleColumns, $quantityMilliLitersMin, $quantityMilliLitersMax, $unitPriceDeciCentsPerLiterMin, $unitPriceDeciCentsPerLiterMax, $vatRatePercent, $sortBy, $sortDirection),
                'isActive' => $this->isSameDateShortcut($issuedFrom, $issuedTo, 'first day of this month', 'last day of this month'),
            ],
        ];
    }

    /**
     * @param list<string> $visibleColumns
     *
     * @return array<string, mixed>
     */
    private function buildShortcutQueryParams(
        string $fromExpression,
        string $toExpression,
        int $perPage,
        ?string $vehicleId,
        ?string $stationId,
        ?string $fuelType,
        string $selectedColumnsPreset,
        array $visibleColumns,
        ?int $quantityMilliLitersMin,
        ?int $quantityMilliLitersMax,
        ?int $unitPriceDeciCentsPerLiterMin,
        ?int $unitPriceDeciCentsPerLiterMax,
        ?int $vatRatePercent,
        string $sortBy,
        string $sortDirection,
    ): array {
        return [
            'per_page' => $perPage,
            'vehicle_id' => $vehicleId,
            'station_id' => $stationId,
            'issued_from' => new DateTimeImmutable($fromExpression)->format('Y-m-d'),
            'issued_to' => new DateTimeImmutable($toExpression)->format('Y-m-d'),
            'fuel_type' => $fuelType,
            'columns_preset' => $selectedColumnsPreset,
            'columns' => $visibleColumns,
            'quantity_min' => $quantityMilliLitersMin,
            'quantity_max' => $quantityMilliLitersMax,
            'unit_price_min' => $unitPriceDeciCentsPerLiterMin,
            'unit_price_max' => $unitPriceDeciCentsPerLiterMax,
            'vat_rate' => $vatRatePercent,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];
    }

    private function isSameDateShortcut(?DateTimeImmutable $issuedFrom, ?DateTimeImmutable $issuedTo, string $fromExpression, string $toExpression): bool
    {
        if (!$issuedFrom instanceof DateTimeImmutable || !$issuedTo instanceof DateTimeImmutable) {
            return false;
        }

        return $issuedFrom->format('Y-m-d') === new DateTimeImmutable($fromExpression)->format('Y-m-d')
            && $issuedTo->format('Y-m-d') === new DateTimeImmutable($toExpression)->format('Y-m-d');
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $stringValue = trim((string) $value);

        return '' === $stringValue ? null : $stringValue;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_scalar($value)) {
            return null;
        }

        $date = trim((string) $value);
        if ('' === $date) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (false === $parsed) {
            return null;
        }

        if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        if ($parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $parsed;
    }

    private function parseInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $stringValue = trim((string) $value);
        if ('' === $stringValue) {
            return null;
        }

        $intValue = filter_var($stringValue, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return null === $intValue ? null : $intValue;
    }

    private function parseFuelType(mixed $value): ?string
    {
        $fuelType = $this->nullableString($value);
        if (null === $fuelType) {
            return null;
        }

        $choices = array_map(static fn (FuelType $type): string => $type->value, FuelType::cases());

        return in_array($fuelType, $choices, true) ? $fuelType : null;
    }

    /**
     * @return list<string>
     */
    private function parseColumns(mixed $value): array
    {
        if (!is_array($value)) {
            return self::DEFAULT_COLUMNS;
        }

        $allowed = array_keys(self::COLUMN_LABELS);
        $columns = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $column = trim((string) $item);
            if (in_array($column, $allowed, true) && !in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }

        return [] === $columns ? self::DEFAULT_COLUMNS : $columns;
    }

    private function parsePreset(mixed $value): ?string
    {
        $preset = $this->nullableString($value);
        if (null === $preset) {
            return null;
        }

        if (!array_key_exists($preset, self::PRESET_COLUMNS)) {
            return null;
        }

        return $preset;
    }

    /**
     * @return list<string>
     */
    private function resolveColumns(mixed $columnsValue, ?string $preset): array
    {
        if (null !== $preset) {
            return self::PRESET_COLUMNS[$preset];
        }

        return $this->parseColumns($columnsValue);
    }

    /** @param list<string> $columns */
    private function detectPreset(array $columns): string
    {
        foreach (self::PRESET_COLUMNS as $preset => $presetColumns) {
            if ($columns === $presetColumns) {
                return $preset;
            }
        }

        return self::PRESET_CUSTOM;
    }
}
