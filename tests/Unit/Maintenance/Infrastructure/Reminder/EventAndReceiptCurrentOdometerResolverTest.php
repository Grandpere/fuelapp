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

namespace App\Tests\Unit\Maintenance\Infrastructure\Reminder;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Maintenance\Infrastructure\Reminder\EventAndReceiptCurrentOdometerResolver;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Receipt;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class EventAndReceiptCurrentOdometerResolverTest extends TestCase
{
    public function testItReturnsMaxAcrossEventsAndReceipts(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $event = MaintenanceEvent::create(
            $ownerId,
            $vehicleId,
            MaintenanceEventType::SERVICE,
            new DateTimeImmutable('2026-01-01 10:00:00'),
            'service',
            110000,
            10000,
        );

        $resolver = new EventAndReceiptCurrentOdometerResolver(
            new ResolverEventRepository([$event]),
            new ResolverReceiptRepository(120500),
        );

        self::assertSame(120500, $resolver->resolve($ownerId, $vehicleId));
    }

    public function testItFallsBackToEventOdometerWhenNoReceiptValue(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $event = MaintenanceEvent::create(
            $ownerId,
            $vehicleId,
            MaintenanceEventType::SERVICE,
            new DateTimeImmutable('2026-01-01 10:00:00'),
            'service',
            101000,
            10000,
        );

        $resolver = new EventAndReceiptCurrentOdometerResolver(
            new ResolverEventRepository([$event]),
            new ResolverReceiptRepository(null),
        );

        self::assertSame(101000, $resolver->resolve($ownerId, $vehicleId));
    }
}

final readonly class ResolverEventRepository implements MaintenanceEventRepository
{
    /** @param list<MaintenanceEvent> $events */
    public function __construct(private array $events)
    {
    }

    public function save(MaintenanceEvent $event): void
    {
    }

    public function get(string $id): ?MaintenanceEvent
    {
        return null;
    }

    public function delete(string $id): void
    {
    }

    public function allForOwner(string $ownerId): iterable
    {
        return [];
    }

    public function allForOwnerAndVehicle(string $ownerId, string $vehicleId): iterable
    {
        foreach ($this->events as $event) {
            if ($event->ownerId() === $ownerId && $event->vehicleId() === $vehicleId) {
                yield $event;
            }
        }
    }

    public function allForSystem(): iterable
    {
        return [];
    }

    public function sumActualCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int
    {
        return 0;
    }
}

final readonly class ResolverReceiptRepository implements ReceiptRepository
{
    public function __construct(private ?int $maxOdometer)
    {
    }

    public function save(Receipt $receipt): void
    {
    }

    public function saveForOwner(Receipt $receipt, string $ownerId): void
    {
    }

    public function get(string $id): ?Receipt
    {
        return null;
    }

    public function getForSystem(string $id): ?Receipt
    {
        return null;
    }

    public function ownerIdForSystem(string $id): ?string
    {
        return null;
    }

    public function delete(string $id): void
    {
    }

    public function deleteForSystem(string $id): void
    {
    }

    public function all(): iterable
    {
        return [];
    }

    public function allForSystem(): iterable
    {
        return [];
    }

    public function paginate(int $page, int $perPage): iterable
    {
        return [];
    }

    public function countAll(): int
    {
        return 0;
    }

    public function paginateFiltered(
        int $page,
        int $perPage,
        ?string $vehicleId,
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
        return [];
    }

    public function countFiltered(
        ?string $vehicleId,
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
        return 0;
    }

    public function paginateFilteredListRows(
        int $page,
        int $perPage,
        ?string $vehicleId,
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
        ?string $vehicleId,
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

    public function maxOdometerKilometersForOwnerAndVehicle(string $ownerId, string $vehicleId): ?int
    {
        return $this->maxOdometer;
    }
}
