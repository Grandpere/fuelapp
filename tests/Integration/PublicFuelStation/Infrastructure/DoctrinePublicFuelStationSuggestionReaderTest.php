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

use App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity\PublicFuelStationEntity;
use App\PublicFuelStation\Infrastructure\Search\DoctrinePublicFuelStationSuggestionReader;
use App\Station\Application\Suggestion\StationSuggestionQuery;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePublicFuelStationSuggestionReaderTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DoctrinePublicFuelStationSuggestionReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = self::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service is invalid.');
        }
        $this->em = $em;

        $this->reader = new DoctrinePublicFuelStationSuggestionReader($this->em->getConnection());

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE public_fuel_stations CASCADE');
    }

    public function testSearchReturnsPublicStationsMatchingPostalCodeAndText(): void
    {
        $this->persistPublicStation('public-1', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000);
        $this->persistPublicStation('public-2', '10 Avenue de Paris', '75001', 'PARIS', 48856000, 2352000);
        $this->em->flush();

        $results = $this->reader->search(new StationSuggestionQuery('frisange', null, null, '5751', 'FRISANGE'), 5);

        self::assertCount(1, $results);
        self::assertSame('public-1', $results[0]->sourceId);
        self::assertSame('40 Rue Robert Schuman', $results[0]->name);
    }

    public function testSearchRejectsNoisyCrossBorderFreeTextMatches(): void
    {
        $this->persistPublicStation('public-1', '3 Rue Nationale', '57350', 'STIRING-WENDEL', 49202000, 6880000);
        $this->persistPublicStation('public-2', '1 Rue Philippe Duclercq', '80100', 'ABBEVILLE', 50105000, 1850000);
        $this->persistPublicStation('public-3', 'Rue Emile Zola', '59215', 'ABSCON', 50337000, 3360000);
        $this->em->flush();

        $results = $this->reader->search(new StationSuggestionQuery(
            'TOTAL 40 Rue Robert Schuman L-5751 FRISANGE',
            null,
            null,
            null,
            null,
        ), 10);

        self::assertSame([], $results);
    }

    public function testSearchScoresAllFilteredMatchesBeforeApplyingLimit(): void
    {
        for ($i = 0; $i < 60; ++$i) {
            $this->persistPublicStation(
                sprintf('public-%d', $i),
                sprintf('Alpha %d Avenue', $i),
                '59000',
                'LILLE',
                50500000 + $i,
                3000000 + $i,
            );
        }

        $this->persistPublicStation('public-best', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000);
        $this->em->flush();

        $results = $this->reader->search(new StationSuggestionQuery(
            '40 Robert Schuman',
            null,
            null,
            null,
            null,
            1,
        ), 1);

        self::assertCount(1, $results);
        self::assertSame('public-best', $results[0]->sourceId);
    }

    private function persistPublicStation(string $sourceId, string $address, string $postalCode, string $city, int $latitudeMicroDegrees, int $longitudeMicroDegrees): void
    {
        $station = new PublicFuelStationEntity();
        $station->setSourceId($sourceId);
        $station->setAddress($address);
        $station->setPostalCode($postalCode);
        $station->setCity($city);
        $station->setLatitudeMicroDegrees($latitudeMicroDegrees);
        $station->setLongitudeMicroDegrees($longitudeMicroDegrees);
        $station->setAutomate24(false);
        $station->setServices([]);
        $station->setFuels([]);
        $station->setSourceUpdatedAt(new DateTimeImmutable('2026-04-29 12:00:00'));
        $this->em->persist($station);
    }
}
