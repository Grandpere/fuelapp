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
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ExportReceiptsController extends AbstractController
{
    private const FORMAT_CSV = 'csv';
    private const FORMAT_XLSX = 'xlsx';
    private const EXPORT_CHUNK_SIZE = 1000;

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
        'fuel_type',
        'quantity_milli_liters',
        'unit_price_deci_cents_per_liter',
        'vat_rate_percent',
        'total_cents',
        'vat_amount_cents',
    ];

    /** @var array<string, string> */
    private const COLUMN_LABELS = [
        'id' => 'receipt_id',
        'issued_at' => 'issued_at',
        'station_name' => 'station_name',
        'station_street_name' => 'station_street_name',
        'station_postal_code' => 'station_postal_code',
        'station_city' => 'station_city',
        'fuel_type' => 'fuel_type',
        'quantity_milli_liters' => 'quantity_milli_liters',
        'unit_price_deci_cents_per_liter' => 'unit_price_deci_cents_per_liter',
        'vat_rate_percent' => 'vat_rate_percent',
        'total_cents' => 'total_cents',
        'vat_amount_cents' => 'vat_amount_cents',
    ];

    /** @var array<string, list<string>> */
    private const PRESET_COLUMNS = [
        self::PRESET_COMPACT => [
            'issued_at',
            'station_name',
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
            'fuel_type',
            'quantity_milli_liters',
            'unit_price_deci_cents_per_liter',
            'vat_rate_percent',
            'total_cents',
            'vat_amount_cents',
        ],
    ];

    public function __construct(private readonly ReceiptRepository $receiptRepository)
    {
    }

    #[Route('/ui/receipts/export', name: 'ui_receipt_export', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $stationId = $this->nullableString($request->query->get('station_id'));
        $issuedFrom = $this->parseDate($request->query->get('issued_from'));
        $issuedTo = $this->parseDate($request->query->get('issued_to'));
        $columnsPreset = $this->parsePreset($request->query->get('columns_preset'));
        $columns = $this->resolveColumns($request->query->all('columns'), $columnsPreset);
        $fuelType = $this->parseFuelType($request->query->get('fuel_type'));
        $quantityMilliLitersMin = $this->parseInt($request->query->get('quantity_min'));
        $quantityMilliLitersMax = $this->parseInt($request->query->get('quantity_max'));
        $unitPriceDeciCentsPerLiterMin = $this->parseInt($request->query->get('unit_price_min'));
        $unitPriceDeciCentsPerLiterMax = $this->parseInt($request->query->get('unit_price_max'));
        $vatRatePercent = $this->parseInt($request->query->get('vat_rate'));
        $format = $this->parseFormat($request->query->get('format'));
        $sortBy = in_array((string) $request->query->get('sort_by'), ['date', 'total', 'fuel_type', 'quantity', 'unit_price', 'vat_rate'], true)
            ? (string) $request->query->get('sort_by')
            : 'date';
        $sortDirection = 'asc' === strtolower((string) $request->query->get('sort_direction')) ? 'asc' : 'desc';

        $generatedAt = new DateTimeImmutable();
        $metadataRows = $this->buildMetadataRows(
            $generatedAt,
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
            $columns,
            $format,
        );

        $filenameBase = sprintf('receipts-export-%s', $generatedAt->format('Ymd-His'));

        if (self::FORMAT_XLSX === $format) {
            return $this->xlsxResponse(
                $filenameBase.'.xlsx',
                $metadataRows,
                $columns,
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
        }

        return $this->csvResponse(
            $filenameBase.'.csv',
            $metadataRows,
            $columns,
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
    }

    /**
     * @param list<array{0:string,1:string}> $metadataRows
     * @param list<string>                   $columns
     */
    private function csvResponse(
        string $filename,
        array $metadataRows,
        array $columns,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType,
        ?int $quantityMilliLitersMin,
        ?int $quantityMilliLitersMax,
        ?int $unitPriceDeciCentsPerLiterMin,
        ?int $unitPriceDeciCentsPerLiterMax,
        ?int $vatRatePercent,
    ): Response {
        $response = new StreamedResponse(function () use (
            $metadataRows,
            $columns,
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
        ): void {
            $stream = fopen('php://output', 'w');
            if (false === $stream) {
                throw new RuntimeException('Cannot create export stream.');
            }

            foreach ($metadataRows as $metadataRow) {
                fputcsv($stream, $metadataRow);
            }
            fputcsv($stream, []);

            $headers = [];
            foreach ($columns as $column) {
                $headers[] = self::COLUMN_LABELS[$column];
            }
            fputcsv($stream, $headers);

            foreach ($this->iterateRowsForExport(
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
            ) as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $line[] = $this->mapColumnValue($column, $row);
                }

                fputcsv($stream, $line);
            }

            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    /**
     * @param list<array{0:string,1:string}> $metadataRows
     * @param list<string>                   $columns
     */
    private function xlsxResponse(
        string $filename,
        array $metadataRows,
        array $columns,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType,
        ?int $quantityMilliLitersMin,
        ?int $quantityMilliLitersMax,
        ?int $unitPriceDeciCentsPerLiterMin,
        ?int $unitPriceDeciCentsPerLiterMax,
        ?int $vatRatePercent,
    ): Response {
        $response = new StreamedResponse(function () use (
            $metadataRows,
            $columns,
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
        ): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Receipts export');

            $rowIndex = 1;
            foreach ($metadataRows as [$key, $value]) {
                $sheet->setCellValue(sprintf('A%d', $rowIndex), $key);
                $sheet->setCellValue(sprintf('B%d', $rowIndex), $value);
                ++$rowIndex;
            }
            ++$rowIndex;

            $columnIndex = 1;
            foreach ($columns as $column) {
                $sheet->setCellValue([$columnIndex, $rowIndex], self::COLUMN_LABELS[$column]);
                ++$columnIndex;
            }
            ++$rowIndex;

            foreach ($this->iterateRowsForExport(
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
            ) as $row) {
                $columnIndex = 1;
                foreach ($columns as $column) {
                    $sheet->setCellValue([$columnIndex, $rowIndex], $this->mapColumnValue($column, $row));
                    ++$columnIndex;
                }
                ++$rowIndex;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    /**
     * @param list<string> $columns
     *
     * @return list<array{0:string,1:string}>
     */
    private function buildMetadataRows(
        DateTimeImmutable $generatedAt,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType,
        ?int $quantityMilliLitersMin,
        ?int $quantityMilliLitersMax,
        ?int $unitPriceDeciCentsPerLiterMin,
        ?int $unitPriceDeciCentsPerLiterMax,
        ?int $vatRatePercent,
        array $columns,
        string $format,
    ): array {
        return [
            ['generated_at', $generatedAt->format(DATE_ATOM)],
            ['format', $format],
            ['filter_station_id', $stationId ?? ''],
            ['filter_issued_from', $issuedFrom?->format('Y-m-d') ?? ''],
            ['filter_issued_to', $issuedTo?->format('Y-m-d') ?? ''],
            ['filter_fuel_type', $fuelType ?? ''],
            ['filter_quantity_min', null === $quantityMilliLitersMin ? '' : (string) $quantityMilliLitersMin],
            ['filter_quantity_max', null === $quantityMilliLitersMax ? '' : (string) $quantityMilliLitersMax],
            ['filter_unit_price_min', null === $unitPriceDeciCentsPerLiterMin ? '' : (string) $unitPriceDeciCentsPerLiterMin],
            ['filter_unit_price_max', null === $unitPriceDeciCentsPerLiterMax ? '' : (string) $unitPriceDeciCentsPerLiterMax],
            ['filter_vat_rate', null === $vatRatePercent ? '' : (string) $vatRatePercent],
            ['sort_by', $sortBy],
            ['sort_direction', $sortDirection],
            ['columns', implode(',', $columns)],
        ];
    }

    /**
     * @return iterable<array{
     *     id: string,
     *     issuedAt: DateTimeImmutable,
     *     totalCents: int,
     *     vatAmountCents: int,
     *     stationName: ?string,
     *     stationStreetName: ?string,
     *     stationPostalCode: ?string,
     *     stationCity: ?string,
     *     fuelType: ?string,
     *     quantityMilliLiters: ?int,
     *     unitPriceDeciCentsPerLiter: ?int,
     *     vatRatePercent: ?int
     * }>
     */
    private function iterateRowsForExport(
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType,
        ?int $quantityMilliLitersMin,
        ?int $quantityMilliLitersMax,
        ?int $unitPriceDeciCentsPerLiterMin,
        ?int $unitPriceDeciCentsPerLiterMax,
        ?int $vatRatePercent,
    ): iterable {
        $page = 1;
        while (true) {
            $rows = $this->receiptRepository->paginateFilteredListRows(
                $page,
                self::EXPORT_CHUNK_SIZE,
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

            if ([] === $rows) {
                return;
            }

            foreach ($rows as $row) {
                yield $row;
            }

            if (count($rows) < self::EXPORT_CHUNK_SIZE) {
                return;
            }

            ++$page;
        }
    }

    /**
     * @param array{
     *     id: string,
     *     issuedAt: DateTimeImmutable,
     *     totalCents: int,
     *     vatAmountCents: int,
     *     stationName: ?string,
     *     stationStreetName: ?string,
     *     stationPostalCode: ?string,
     *     stationCity: ?string,
     *     fuelType: ?string,
     *     quantityMilliLiters: ?int,
     *     unitPriceDeciCentsPerLiter: ?int,
     *     vatRatePercent: ?int
     * } $row
     */
    private function mapColumnValue(string $column, array $row): string
    {
        return match ($column) {
            'id' => $row['id'],
            'issued_at' => $row['issuedAt']->format('Y-m-d H:i:s'),
            'station_name' => $row['stationName'] ?? '',
            'station_street_name' => $row['stationStreetName'] ?? '',
            'station_postal_code' => $row['stationPostalCode'] ?? '',
            'station_city' => $row['stationCity'] ?? '',
            'fuel_type' => $row['fuelType'] ?? '',
            'quantity_milli_liters' => null === $row['quantityMilliLiters'] ? '' : (string) $row['quantityMilliLiters'],
            'unit_price_deci_cents_per_liter' => null === $row['unitPriceDeciCentsPerLiter'] ? '' : (string) $row['unitPriceDeciCentsPerLiter'],
            'vat_rate_percent' => null === $row['vatRatePercent'] ? '' : (string) $row['vatRatePercent'],
            'total_cents' => (string) $row['totalCents'],
            'vat_amount_cents' => (string) $row['vatAmountCents'],
            default => '',
        };
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

    private function parseFormat(mixed $value): string
    {
        $format = strtolower($this->nullableString($value) ?? self::FORMAT_CSV);

        return in_array($format, [self::FORMAT_CSV, self::FORMAT_XLSX], true) ? $format : self::FORMAT_CSV;
    }
}
