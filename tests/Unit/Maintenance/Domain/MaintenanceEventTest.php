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

namespace App\Tests\Unit\Maintenance\Domain;

use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenanceEvent;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MaintenanceEventTest extends TestCase
{
    public function testCreateNormalizesCurrencyAndDescription(): void
    {
        $now = new DateTimeImmutable('2026-02-22 10:00:00');
        $event = MaintenanceEvent::create(
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            MaintenanceEventType::SERVICE,
            new DateTimeImmutable('2026-02-21 08:00:00'),
            '  Oil + filters  ',
            123456,
            12999,
            'eur',
            $now,
        );

        self::assertSame('EUR', $event->currencyCode());
        self::assertSame('Oil + filters', $event->description());
        self::assertSame(123456, $event->odometerKilometers());
        self::assertSame(12999, $event->totalCostCents());
        self::assertSame($now, $event->createdAt());
        self::assertSame($now, $event->updatedAt());
    }

    public function testCreateRejectsNegativeCost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('totalCostCents must be a non-negative integer.');

        MaintenanceEvent::create(
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            MaintenanceEventType::REPAIR,
            new DateTimeImmutable(),
            null,
            null,
            -1,
        );
    }
}
