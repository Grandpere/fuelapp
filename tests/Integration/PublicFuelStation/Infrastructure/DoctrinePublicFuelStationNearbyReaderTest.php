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

namespace App\Tests\Integration\PublicFuelStation\Infrastructure;

use App\PublicFuelStation\Application\Nearby\NearbyPublicFuelStationQuery;
use App\PublicFuelStation\Application\Nearby\PublicFuelStationNearbyReader;
use App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity\PublicFuelStationEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePublicFuelStationNearbyReaderTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PublicFuelStationNearbyReader $reader;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $reader = self::getContainer()->get(PublicFuelStationNearbyReader::class);
        if (!$reader instanceof PublicFuelStationNearbyReader) {
            throw new RuntimeException('PublicFuelStationNearbyReader service not found.');
        }
        $this->reader = $reader;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE public_fuel_stations RESTART IDENTITY CASCADE');
    }

    public function testItFindsNearbyPublicStationsAndExcludesDirectMatches(): void
    {
        $this->persistPublicStation('matched-public', 48856120, 2352210, '5 PUBLIC ROAD', '75001', 'PARIS');
        $this->persistPublicStation('nearby-public', 48857120, 2352310, '7 NEARBY ROAD', '75001', 'PARIS');
        $this->persistPublicStation('too-far-public', 48950000, 2400000, '99 FAR AWAY', '75001', 'PARIS');
        $this->em->flush();

        $results = $this->reader->findNearby([
            'station-1' => new NearbyPublicFuelStationQuery(48856100, 2352200),
        ], ['matched-public'], 2, 1500);

        self::assertCount(1, $results);
        self::assertCount(1, $results['station-1']);
        self::assertSame('nearby-public', $results['station-1'][0]->sourceId);
        self::assertSame('7 NEARBY ROAD', $results['station-1'][0]->address);
        self::assertSame(['Gazole'], $results['station-1'][0]->availableFuelLabels);
    }

    public function testItScalesPrefilterBoundsWhenCallerRequestsLargerRadius(): void
    {
        $this->persistPublicStation('wide-radius-public', 48856100, 2402200, '50 WIDE RADIUS ROAD', '75001', 'PARIS');
        $this->em->flush();

        $results = $this->reader->findNearby([
            'station-1' => new NearbyPublicFuelStationQuery(48856100, 2352200),
        ], [], 2, 5000);

        self::assertCount(1, $results);
        self::assertCount(1, $results['station-1']);
        self::assertSame('wide-radius-public', $results['station-1'][0]->sourceId);
    }

    private function persistPublicStation(string $sourceId, int $latitudeMicroDegrees, int $longitudeMicroDegrees, string $address, string $postalCode, string $city): void
    {
        $station = new PublicFuelStationEntity();
        $station->setSourceId($sourceId);
        $station->setLatitudeMicroDegrees($latitudeMicroDegrees);
        $station->setLongitudeMicroDegrees($longitudeMicroDegrees);
        $station->setAddress($address);
        $station->setPostalCode($postalCode);
        $station->setCity($city);
        $station->setAutomate24(true);
        $station->setServices(['Boutique alimentaire']);
        $station->setFuels([
            'gazole' => [
                'available' => true,
                'priceMilliEurosPerLiter' => 1789,
                'priceUpdatedAt' => '2026-04-28T09:15:00+02:00',
                'ruptureType' => null,
                'ruptureStartedAt' => null,
            ],
        ]);
        $station->setSourceUpdatedAt(new DateTimeImmutable('2026-04-28 09:15:00'));
        $station->setImportedAt(new DateTimeImmutable('2026-04-28 09:20:00'));
        $this->em->persist($station);
    }
}
