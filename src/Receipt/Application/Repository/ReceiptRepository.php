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

namespace App\Receipt\Application\Repository;

use App\Receipt\Domain\Receipt;
use DateTimeImmutable;

interface ReceiptRepository
{
    public function save(Receipt $receipt): void;

    public function get(string $id): ?Receipt;

    public function delete(string $id): void;

    /** @return iterable<Receipt> */
    public function all(): iterable;

    /** @return iterable<Receipt> */
    public function paginate(int $page, int $perPage): iterable;

    public function countAll(): int;

    /** @return iterable<Receipt> */
    public function paginateFiltered(
        int $page,
        int $perPage,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): iterable;

    public function countFiltered(
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): int;

    /** @return list<array{
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
     * }> */
    public function paginateFilteredListRows(
        int $page,
        int $perPage,
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): array;

    /** @return list<array{
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
     * }> */
    public function listFilteredRowsForExport(
        ?string $stationId,
        ?DateTimeImmutable $issuedFrom,
        ?DateTimeImmutable $issuedTo,
        string $sortBy,
        string $sortDirection,
        ?string $fuelType = null,
        ?int $quantityMilliLitersMin = null,
        ?int $quantityMilliLitersMax = null,
        ?int $unitPriceDeciCentsPerLiterMin = null,
        ?int $unitPriceDeciCentsPerLiterMax = null,
        ?int $vatRatePercent = null,
    ): array;
}
