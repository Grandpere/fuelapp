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

namespace App\Receipt\Domain;

use App\Receipt\Domain\ValueObject\ReceiptId;
use App\Station\Domain\ValueObject\StationId;
use App\Vehicle\Domain\ValueObject\VehicleId;
use DateTimeImmutable;
use InvalidArgumentException;

final class Receipt
{
    private ReceiptId $id;
    private DateTimeImmutable $issuedAt;
    /** @var array<ReceiptLine> */
    private array $lines;
    private ?StationId $stationId;
    private ?VehicleId $vehicleId;

    /** @param array<ReceiptLine> $lines */
    private function __construct(ReceiptId $id, DateTimeImmutable $issuedAt, array $lines, ?StationId $stationId, ?VehicleId $vehicleId)
    {
        $this->id = $id;
        $this->issuedAt = $issuedAt;
        $this->lines = $lines;
        $this->stationId = $stationId;
        $this->vehicleId = $vehicleId;
    }

    /** @param array<ReceiptLine> $lines */
    public static function create(DateTimeImmutable $issuedAt, array $lines, ?StationId $stationId, ?VehicleId $vehicleId = null): self
    {
        return new self(ReceiptId::new(), $issuedAt, self::assertLines($lines), $stationId, $vehicleId);
    }

    /** @param array<ReceiptLine> $lines */
    public static function reconstitute(
        ReceiptId $id,
        DateTimeImmutable $issuedAt,
        array $lines,
        ?StationId $stationId,
        ?VehicleId $vehicleId = null,
    ): self {
        return new self($id, $issuedAt, self::assertLines($lines), $stationId, $vehicleId);
    }

    public function id(): ReceiptId
    {
        return $this->id;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    /** @return array<ReceiptLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function stationId(): ?StationId
    {
        return $this->stationId;
    }

    public function vehicleId(): ?VehicleId
    {
        return $this->vehicleId;
    }

    public function totalCents(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->lineTotalCents();
        }

        return $total;
    }

    public function vatAmountCents(): int
    {
        $totalVat = 0;
        foreach ($this->lines as $line) {
            $totalVat += $line->vatAmountCents();
        }

        return $totalVat;
    }

    /** @param array<ReceiptLine> $lines
     * @return array<ReceiptLine>
     */
    private static function assertLines(array $lines): array
    {
        if ([] === $lines) {
            throw new InvalidArgumentException('Receipt must have at least one line');
        }

        return $lines;
    }
}
