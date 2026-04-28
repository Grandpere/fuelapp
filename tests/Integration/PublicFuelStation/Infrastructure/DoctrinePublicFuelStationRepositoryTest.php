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

use App\PublicFuelStation\Application\Import\ParsedPublicFuelStation;
use App\PublicFuelStation\Application\Repository\PublicFuelStationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePublicFuelStationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PublicFuelStationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $repository = self::getContainer()->get(PublicFuelStationRepository::class);
        if (!$repository instanceof PublicFuelStationRepository) {
            throw new RuntimeException('PublicFuelStationRepository service not found.');
        }
        $this->repository = $repository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE public_fuel_stations RESTART IDENTITY CASCADE');
    }

    public function testItUpsertsPublicFuelStationsBySourceId(): void
    {
        $this->repository->upsert($this->station('1000001', 'Rue A'));
        $this->repository->upsert($this->station('1000001', 'Rue B'));

        self::assertSame(1, $this->repository->countAll());

        $row = $this->em->getConnection()->fetchAssociative('SELECT source_id, address, fuels FROM public_fuel_stations WHERE source_id = ?', ['1000001']);
        self::assertIsArray($row);
        self::assertSame('1000001', $row['source_id']);
        self::assertSame('Rue B', $row['address']);
        self::assertIsString($row['fuels']);
        self::assertStringContainsString('priceMilliEurosPerLiter', $row['fuels']);
    }

    private function station(string $sourceId, string $address): ParsedPublicFuelStation
    {
        return new ParsedPublicFuelStation(
            $sourceId,
            4956900,
            364600,
            $address,
            '01000',
            'Bourg',
            'R',
            'Ain',
            '01',
            'Auvergne-Rhône-Alpes',
            '84',
            true,
            ['Boutique'],
            [
                'gazole' => [
                    'available' => true,
                    'priceMilliEurosPerLiter' => 1789,
                    'priceUpdatedAt' => '2026-04-28T09:15:00+02:00',
                    'ruptureType' => null,
                    'ruptureStartedAt' => null,
                ],
            ],
            new DateTimeImmutable('2026-04-28T09:15:00+02:00'),
        );
    }
}
