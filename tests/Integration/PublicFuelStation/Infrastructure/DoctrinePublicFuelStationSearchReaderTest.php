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

use App\PublicFuelStation\Application\Search\PublicFuelStationSearchFilters;
use App\PublicFuelStation\Application\Search\PublicFuelStationSearchReader;
use App\PublicFuelStation\Domain\Enum\PublicFuelType;
use App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity\PublicFuelStationEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePublicFuelStationSearchReaderTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PublicFuelStationSearchReader $reader;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $reader = self::getContainer()->get(PublicFuelStationSearchReader::class);
        if (!$reader instanceof PublicFuelStationSearchReader) {
            throw new RuntimeException('PublicFuelStationSearchReader service not found.');
        }
        $this->reader = $reader;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE public_fuel_stations RESTART IDENTITY CASCADE');
    }

    public function testItFiltersCachedStationsByQueryFuelAndAvailability(): void
    {
        $this->persistPublicStation('1000001', '596 AVENUE DE TREVOUX', '01000', 'SAINT-DENIS-LÈS-BOURG', true, 1789);
        $this->persistPublicStation('2000002', '8 ROUTE SANS GAZOLE', '69000', 'LYON', false, 1799);
        $this->em->flush();

        $result = $this->reader->search(new PublicFuelStationSearchFilters('01000', PublicFuelType::DIESEL, true));

        self::assertSame(1, $result->totalCount);
        self::assertCount(1, $result->items);
        self::assertSame('1000001', $result->items[0]->sourceId);
        self::assertSame(49.569, $result->items[0]->latitude);
        self::assertTrue($result->items[0]->fuels['gazole']['available']);
        self::assertSame(1789, $result->items[0]->fuels['gazole']['priceMilliEurosPerLiter']);
    }

    private function persistPublicStation(string $sourceId, string $address, string $postalCode, string $city, bool $available, int $priceMilliEurosPerLiter): void
    {
        $station = new PublicFuelStationEntity();
        $station->setSourceId($sourceId);
        $station->setLatitudeMicroDegrees(49569000);
        $station->setLongitudeMicroDegrees(3646000);
        $station->setAddress($address);
        $station->setPostalCode($postalCode);
        $station->setCity($city);
        $station->setPopulationKind('R');
        $station->setDepartment('Ain');
        $station->setDepartmentCode('01');
        $station->setRegion('Auvergne-Rhône-Alpes');
        $station->setRegionCode('84');
        $station->setAutomate24(true);
        $station->setServices(['Boutique alimentaire']);
        $station->setFuels([
            'gazole' => [
                'available' => $available,
                'priceMilliEurosPerLiter' => $priceMilliEurosPerLiter,
                'priceUpdatedAt' => '2026-04-28T09:15:00+02:00',
                'ruptureType' => $available ? null : 'temporaire',
                'ruptureStartedAt' => $available ? null : '2026-04-28T10:00:00+02:00',
            ],
        ]);
        $station->setSourceUpdatedAt(new DateTimeImmutable('2026-04-28 09:15:00'));
        $station->setImportedAt(new DateTimeImmutable('2026-04-28 09:20:00'));
        $this->em->persist($station);
    }
}
