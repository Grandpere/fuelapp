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

namespace App\Tests\Integration\Station\Application;

use App\Station\Application\Geocoding\GeocodedAddress;
use App\Station\Application\Geocoding\Geocoder;
use App\Station\Application\Message\GeocodeStationAddressMessage;
use App\Station\Application\MessageHandler\GeocodeStationAddressMessageHandler;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Domain\Station;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GeocodeStationAddressMessageHandlerIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StationRepository $stationRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $repository = self::getContainer()->get(StationRepository::class);
        if (!$repository instanceof StationRepository) {
            throw new RuntimeException('StationRepository service not found.');
        }
        $this->stationRepository = $repository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE stations RESTART IDENTITY CASCADE');
    }

    public function testHandlerPersistsSuccessStatusAndCoordinates(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', null, null);
        $this->stationRepository->save($station);

        $handler = new GeocodeStationAddressMessageHandler(
            $this->stationRepository,
            new StaticGeocoder(new GeocodedAddress(48856600, 2352200)),
            new NullLogger(),
        );

        $handler(new GeocodeStationAddressMessage($station->id()->toString()));

        $saved = $this->stationRepository->getForSystem($station->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(GeocodingStatus::SUCCESS, $saved->geocodingStatus());
        self::assertSame(48856600, $saved->latitudeMicroDegrees());
        self::assertSame(2352200, $saved->longitudeMicroDegrees());
    }

    public function testHandlerPersistsFailedStatusWhenNoResult(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', null, null);
        $this->stationRepository->save($station);

        $handler = new GeocodeStationAddressMessageHandler(
            $this->stationRepository,
            new StaticGeocoder(null),
            new NullLogger(),
        );

        $handler(new GeocodeStationAddressMessage($station->id()->toString()));

        $saved = $this->stationRepository->getForSystem($station->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(GeocodingStatus::FAILED, $saved->geocodingStatus());
        self::assertSame('provider_no_result', $saved->geocodingLastError());
    }
}

final class StaticGeocoder implements Geocoder
{
    public function __construct(private readonly ?GeocodedAddress $result)
    {
    }

    public function geocode(string $name, string $streetName, string $postalCode, string $city): ?GeocodedAddress
    {
        return $this->result;
    }
}
