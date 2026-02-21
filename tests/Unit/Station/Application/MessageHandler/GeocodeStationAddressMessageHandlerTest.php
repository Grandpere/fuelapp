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

namespace App\Tests\Unit\Station\Application\MessageHandler;

use App\Station\Application\Geocoding\GeocodedAddress;
use App\Station\Application\Geocoding\Geocoder;
use App\Station\Application\Message\GeocodeStationAddressMessage;
use App\Station\Application\MessageHandler\GeocodeStationAddressMessageHandler;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Domain\Station;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeocodeStationAddressMessageHandlerTest extends TestCase
{
    public function testItSkipsUnknownStation(): void
    {
        $repository = new InMemoryStationRepository([]);
        $geocoder = new StubGeocoder();
        $handler = new GeocodeStationAddressMessageHandler($repository, $geocoder);

        $handler(new GeocodeStationAddressMessage('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'));

        self::assertSame(0, $repository->saveCount);
    }

    public function testItIsIdempotentForAlreadySuccessfulStation(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', 48856600, 2352200);
        $repository = new InMemoryStationRepository([$station]);
        $geocoder = new StubGeocoder();
        $handler = new GeocodeStationAddressMessageHandler($repository, $geocoder);

        $handler(new GeocodeStationAddressMessage($station->id()->toString()));

        self::assertSame(0, $geocoder->calls);
        self::assertSame(0, $repository->saveCount);
    }

    public function testItMarksStationAsSuccessWhenGeocodingResolvesCoordinates(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', null, null);
        $repository = new InMemoryStationRepository([$station]);
        $geocoder = new StubGeocoder(new GeocodedAddress(48856600, 2352200));
        $handler = new GeocodeStationAddressMessageHandler($repository, $geocoder);

        $handler(new GeocodeStationAddressMessage($station->id()->toString()));

        $saved = $repository->getForSystem($station->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(GeocodingStatus::SUCCESS, $saved->geocodingStatus());
        self::assertSame(48856600, $saved->latitudeMicroDegrees());
        self::assertSame(2352200, $saved->longitudeMicroDegrees());
    }

    public function testItMarksStationAsFailedWhenProviderReturnsNoResult(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', null, null);
        $repository = new InMemoryStationRepository([$station]);
        $geocoder = new StubGeocoder(null);
        $handler = new GeocodeStationAddressMessageHandler($repository, $geocoder);

        $handler(new GeocodeStationAddressMessage($station->id()->toString()));

        $saved = $repository->getForSystem($station->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(GeocodingStatus::FAILED, $saved->geocodingStatus());
        self::assertSame('provider_no_result', $saved->geocodingLastError());
    }

    public function testItMarksStationAsFailedAndRethrowsProviderException(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', null, null);
        $repository = new InMemoryStationRepository([$station]);
        $geocoder = new ThrowingGeocoder();
        $handler = new GeocodeStationAddressMessageHandler($repository, $geocoder);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider down');

        try {
            $handler(new GeocodeStationAddressMessage($station->id()->toString()));
        } finally {
            $saved = $repository->getForSystem($station->id()->toString());
            self::assertNotNull($saved);
            self::assertSame(GeocodingStatus::FAILED, $saved->geocodingStatus());
            self::assertStringContainsString('provider_exception', (string) $saved->geocodingLastError());
        }
    }
}

final class StubGeocoder implements Geocoder
{
    public int $calls = 0;

    public function __construct(private readonly ?GeocodedAddress $result = null)
    {
    }

    public function geocode(string $name, string $streetName, string $postalCode, string $city): ?GeocodedAddress
    {
        ++$this->calls;

        return $this->result;
    }
}

final class ThrowingGeocoder implements Geocoder
{
    public function geocode(string $name, string $streetName, string $postalCode, string $city): ?GeocodedAddress
    {
        throw new RuntimeException('provider down');
    }
}

final class InMemoryStationRepository implements StationRepository
{
    /** @var array<string, Station> */
    private array $items = [];

    public int $saveCount = 0;

    /** @param list<Station> $stations */
    public function __construct(array $stations)
    {
        foreach ($stations as $station) {
            $this->items[$station->id()->toString()] = $station;
        }
    }

    public function save(Station $station): void
    {
        ++$this->saveCount;
        $this->items[$station->id()->toString()] = $station;
    }

    public function get(string $id): ?Station
    {
        return $this->items[$id] ?? null;
    }

    public function getForSystem(string $id): ?Station
    {
        return $this->get($id);
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }

    public function getByIds(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            if (isset($this->items[$id])) {
                $results[$id] = $this->items[$id];
            }
        }

        return $results;
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        foreach ($this->items as $station) {
            if (
                $station->name() === $name
                && $station->streetName() === $streetName
                && $station->postalCode() === $postalCode
                && $station->city() === $city
            ) {
                return $station;
            }
        }

        return null;
    }

    public function all(): iterable
    {
        return array_values($this->items);
    }
}
