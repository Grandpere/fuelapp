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

namespace App\Tests\Unit\Receipt\Application\Command;

use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Application\Command\UpdateReceiptLinesCommand;
use App\Receipt\Application\Command\UpdateReceiptLinesHandler;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\Domain\Receipt;
use App\Receipt\Domain\ReceiptLine;
use App\Receipt\Domain\ValueObject\ReceiptId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UpdateReceiptLinesHandlerTest extends TestCase
{
    public function testItUpdatesOwnedReceiptLines(): void
    {
        $receipt = Receipt::reconstitute(
            ReceiptId::fromString('019ca6c0-f40f-7097-8ddf-c96d189a3220'),
            new DateTimeImmutable('2026-02-22 10:00:00'),
            [ReceiptLine::create(FuelType::DIESEL, 10000, 1800, 20)],
            null,
            null,
        );

        $repository = new InMemoryUpdateReceiptRepository($receipt, 'owner-1');
        $handler = new UpdateReceiptLinesHandler($repository);

        $updated = $handler(new UpdateReceiptLinesCommand(
            $receipt->id()->toString(),
            [new CreateReceiptLineCommand(FuelType::SP95, 12000, 1750, 20)],
        ));

        self::assertNotNull($updated);
        self::assertSame(FuelType::SP95, $updated->lines()[0]->fuelType());
        self::assertSame(0, $repository->saveForOwnerCount);
        self::assertSame(1, $repository->saveCount);
    }

    public function testItUpdatesSystemReceiptWhenAllowed(): void
    {
        $receipt = Receipt::reconstitute(
            ReceiptId::fromString('019ca6c0-f40f-7097-8ddf-c96d189a3221'),
            new DateTimeImmutable('2026-02-22 10:00:00'),
            [ReceiptLine::create(FuelType::DIESEL, 10000, 1800, 20)],
            null,
            null,
        );

        $repository = new InMemoryUpdateReceiptRepository($receipt, 'owner-2');
        $repository->forceScopedMiss = true;
        $handler = new UpdateReceiptLinesHandler($repository);

        $updated = $handler(new UpdateReceiptLinesCommand(
            $receipt->id()->toString(),
            [new CreateReceiptLineCommand(FuelType::SP98, 9000, 1900, 20)],
            true,
        ));

        self::assertNotNull($updated);
        self::assertSame(FuelType::SP98, $updated->lines()[0]->fuelType());
        self::assertSame(0, $repository->saveCount);
        self::assertSame(1, $repository->saveForOwnerCount);
        self::assertSame('owner-2', $repository->lastOwnerId);
    }
}

final class InMemoryUpdateReceiptRepository implements ReceiptRepository
{
    public bool $forceScopedMiss = false;
    public int $saveCount = 0;
    public int $saveForOwnerCount = 0;
    public ?string $lastOwnerId = null;

    public function __construct(
        private ?Receipt $receipt,
        private readonly string $ownerId,
    ) {
    }

    public function save(Receipt $receipt): void
    {
        ++$this->saveCount;
        $this->receipt = $receipt;
    }

    public function saveForOwner(Receipt $receipt, string $ownerId): void
    {
        ++$this->saveForOwnerCount;
        $this->lastOwnerId = $ownerId;
        $this->receipt = $receipt;
    }

    public function get(string $id): ?Receipt
    {
        if ($this->forceScopedMiss) {
            return null;
        }

        if (null === $this->receipt) {
            return null;
        }

        return $this->receipt->id()->toString() === $id ? $this->receipt : null;
    }

    public function getForSystem(string $id): ?Receipt
    {
        if (null === $this->receipt) {
            return null;
        }

        return $this->receipt->id()->toString() === $id ? $this->receipt : null;
    }

    public function ownerIdForSystem(string $id): ?string
    {
        if (null === $this->receipt || $this->receipt->id()->toString() !== $id) {
            return null;
        }

        return $this->ownerId;
    }

    public function delete(string $id): void
    {
    }

    public function deleteForSystem(string $id): void
    {
    }

    public function all(): iterable
    {
        return $this->receipt ? [$this->receipt] : [];
    }

    public function allForSystem(): iterable
    {
        return $this->all();
    }

    public function paginate(int $page, int $perPage): iterable
    {
        return $this->all();
    }

    public function countAll(): int
    {
        return $this->receipt ? 1 : 0;
    }

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
    ): iterable {
        return $this->all();
    }

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
    ): int {
        return $this->countAll();
    }

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
    ): array {
        return [];
    }

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
    ): array {
        return [];
    }
}
