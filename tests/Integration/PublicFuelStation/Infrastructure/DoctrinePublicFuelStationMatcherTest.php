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

use App\PublicFuelStation\Application\Matching\PublicFuelStationMatcher;
use App\PublicFuelStation\Application\Matching\VisitedStationPublicMatchQuery;
use App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity\PublicFuelStationEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePublicFuelStationMatcherTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PublicFuelStationMatcher $matcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $matcher = self::getContainer()->get(PublicFuelStationMatcher::class);
        if (!$matcher instanceof PublicFuelStationMatcher) {
            throw new RuntimeException('PublicFuelStationMatcher service not found.');
        }
        $this->matcher = $matcher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE public_fuel_stations RESTART IDENTITY CASCADE');
    }

    public function testItFindsClosestPublicStationCandidateWithoutMerging(): void
    {
        $this->persistPublicStation('near-public-station', 44569010, -579010, '5 PLACE CENTRALE', '33000', 'BORDEAUX');
        $this->persistPublicStation('far-public-station', 44800000, -600000, '99 ROUTE LOINTAINE', '33000', 'BORDEAUX');
        $this->em->flush();

        $matches = $this->matcher->findCandidates(new VisitedStationPublicMatchQuery(
            44569000,
            -579000,
            '5 Place Centrale',
            '33000',
            'Bordeaux',
        ));

        self::assertNotEmpty($matches);
        self::assertSame('near-public-station', $matches[0]->sourceId);
        self::assertSame('high', $matches[0]->confidence);
        self::assertNotNull($matches[0]->distanceMeters);
        self::assertSame(1789, $matches[0]->fuels['gazole']['priceMilliEurosPerLiter']);
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
