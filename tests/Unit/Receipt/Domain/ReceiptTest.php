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

namespace App\Tests\Unit\Receipt\Domain;

use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\Domain\Receipt;
use App\Receipt\Domain\ReceiptLine;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReceiptTest extends TestCase
{
    public function testReceiptAcceptsOptionalOdometerKilometers(): void
    {
        $receipt = Receipt::create(
            new DateTimeImmutable('2026-03-01 12:00:00'),
            [ReceiptLine::create(FuelType::DIESEL, 10_000, 1_800, 20)],
            null,
            odometerKilometers: 123_456,
        );

        self::assertSame(123_456, $receipt->odometerKilometers());
    }

    public function testReceiptRejectsNegativeOdometerKilometers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Receipt odometer must be non-negative.');

        Receipt::create(
            new DateTimeImmutable('2026-03-01 12:00:00'),
            [ReceiptLine::create(FuelType::DIESEL, 10_000, 1_800, 20)],
            null,
            odometerKilometers: -1,
        );
    }
}
