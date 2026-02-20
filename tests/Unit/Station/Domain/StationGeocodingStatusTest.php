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

namespace App\Tests\Unit\Station\Domain;

use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Domain\Station;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StationGeocodingStatusTest extends TestCase
{
    public function testCreateWithoutCoordinatesStartsPending(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', null, null);

        self::assertSame(GeocodingStatus::PENDING, $station->geocodingStatus());
        self::assertNotNull($station->geocodingRequestedAt());
        self::assertNull($station->geocodedAt());
        self::assertNull($station->geocodingFailedAt());
        self::assertNull($station->geocodingLastError());
    }

    public function testCreateWithCoordinatesStartsSuccess(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', 48856600, 2352200);

        self::assertSame(GeocodingStatus::SUCCESS, $station->geocodingStatus());
        self::assertNotNull($station->geocodedAt());
        self::assertNull($station->geocodingRequestedAt());
        self::assertNull($station->geocodingLastError());
    }

    public function testStatusTransitionsAreTracked(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', null, null);
        $failedAt = new DateTimeImmutable('2026-02-20T10:00:00+00:00');
        $successAt = new DateTimeImmutable('2026-02-20T10:05:00+00:00');

        $station->markGeocodingFailed('timeout', $failedAt);

        self::assertSame(GeocodingStatus::FAILED, $station->geocodingStatus());
        self::assertSame($failedAt, $station->geocodingFailedAt());
        self::assertSame('timeout', $station->geocodingLastError());

        $station->markGeocodingSuccess(48856600, 2352200, $successAt);

        self::assertSame(GeocodingStatus::SUCCESS, $station->geocodingStatus());
        self::assertSame($successAt, $station->geocodedAt());
        self::assertSame(48856600, $station->latitudeMicroDegrees());
        self::assertSame(2352200, $station->longitudeMicroDegrees());
        self::assertNull($station->geocodingFailedAt());
        self::assertNull($station->geocodingLastError());
    }
}
