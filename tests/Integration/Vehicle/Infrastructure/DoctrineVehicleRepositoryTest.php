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

namespace App\Tests\Integration\Vehicle\Infrastructure;

use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineVehicleRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private VehicleRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $repository = self::getContainer()->get(VehicleRepository::class);
        if (!$repository instanceof VehicleRepository) {
            throw new RuntimeException('VehicleRepository service not found.');
        }
        $this->repository = $repository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE vehicles RESTART IDENTITY CASCADE');
    }

    public function testRepositoryPersistsAndFindsVehicleByIdAndPlate(): void
    {
        $vehicle = Vehicle::create('Peugeot 208', 'aa-123-bb');
        $this->repository->save($vehicle);

        $loadedById = $this->repository->get($vehicle->id()->toString());
        self::assertNotNull($loadedById);
        self::assertSame('Peugeot 208', $loadedById->name());
        self::assertSame('AA-123-BB', $loadedById->plateNumber());

        $loadedByPlate = $this->repository->findByPlateNumber('aa-123-bb');
        self::assertNotNull($loadedByPlate);
        self::assertSame($vehicle->id()->toString(), $loadedByPlate->id()->toString());
    }
}
