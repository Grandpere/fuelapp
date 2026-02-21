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

namespace App\Tests\Unit\Maintenance\Application\Cost;

use App\Maintenance\Application\Cost\MaintenanceCostVarianceReader;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MaintenanceCostVarianceReaderTest extends TestCase
{
    public function testReadComputesVarianceAsActualMinusPlanned(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $from = new DateTimeImmutable('2026-01-01');
        $to = new DateTimeImmutable('2026-12-31');

        $reader = new MaintenanceCostVarianceReader(
            new StubPlannedCostRepository(40000),
            new StubEventRepository(55000),
        );

        $variance = $reader->read($ownerId, $vehicleId, $from, $to);

        self::assertSame(40000, $variance->plannedCostCents);
        self::assertSame(55000, $variance->actualCostCents);
        self::assertSame(15000, $variance->varianceCents);
    }
}

final readonly class StubPlannedCostRepository implements MaintenancePlannedCostRepository
{
    public function __construct(private int $sum)
    {
    }

    public function save(\App\Maintenance\Domain\MaintenancePlannedCost $item): void
    {
    }

    public function get(string $id): ?\App\Maintenance\Domain\MaintenancePlannedCost
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

    public function sumPlannedCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int
    {
        return $this->sum;
    }
}

final readonly class StubEventRepository implements MaintenanceEventRepository
{
    public function __construct(private int $sum)
    {
    }

    public function save(\App\Maintenance\Domain\MaintenanceEvent $event): void
    {
    }

    public function get(string $id): ?\App\Maintenance\Domain\MaintenanceEvent
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
        return [];
    }

    public function sumActualCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int
    {
        return $this->sum;
    }
}
