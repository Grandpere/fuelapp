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

namespace App\Tests\Integration\Station\Infrastructure;

use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineStationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StationRepository $repository;

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
        $this->repository = $repository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE stations RESTART IDENTITY CASCADE');
    }

    public function testItPersistsPublicSourceId(): void
    {
        $station = Station::create(
            'TOTAL',
            '40 Rue Robert Schuman',
            '5751',
            'FRISANGE',
            49569000,
            4230000,
            'public-1',
        );

        $this->repository->save($station);
        $reloaded = $this->repository->getForSystem($station->id()->toString());

        self::assertNotNull($reloaded);
        self::assertSame('public-1', $reloaded->publicSourceId());
    }
}
