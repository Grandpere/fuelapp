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

use App\Maintenance\Domain\MaintenancePlannedCost;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MaintenancePlannedCostTest extends TestCase
{
    public function testCreateNormalizesCurrencyAndNotes(): void
    {
        $item = MaintenancePlannedCost::create(
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            'Brake service',
            null,
            new DateTimeImmutable('2026-03-01 10:00:00'),
            25000,
            'eur',
            '  expected quote  ',
        );

        self::assertSame('EUR', $item->currencyCode());
        self::assertSame('expected quote', $item->notes());
        self::assertSame(25000, $item->plannedCostCents());
    }

    public function testCreateRejectsNonPositiveCost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('plannedCostCents must be a positive integer.');

        MaintenancePlannedCost::create(
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            'Annual service',
            null,
            new DateTimeImmutable('2026-03-01 10:00:00'),
            0,
        );
    }
}
