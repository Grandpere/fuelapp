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
        self::PRESET_FULL => self::DEFAULT_COLUMNS,
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
    ) {
    }

    #[Route('/ui/receipts', name: 'ui_receipt_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('per_page', 25)));
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

        usort(
            $stationOptions,
            static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']),
        );

        $queryParams = [
            'per_page' => $perPage,
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
            'stationOptions' => $stationOptions,
            'filters' => [
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
        ]);
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
