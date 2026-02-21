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

namespace App\Tests\Integration\Maintenance\Infrastructure;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Repository\DoctrineMaintenanceEventRepository;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineMaintenanceEventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MaintenanceEventRepository $repository;
    private VehicleRepository $vehicleRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $this->repository = new DoctrineMaintenanceEventRepository($this->em);

        $vehicleRepository = self::getContainer()->get(VehicleRepository::class);
        if (!$vehicleRepository instanceof VehicleRepository) {
            throw new RuntimeException('VehicleRepository service not found.');
        }
        $this->vehicleRepository = $vehicleRepository;

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service not found.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_events, vehicles, users RESTART IDENTITY CASCADE');
    }

    public function testRepositoryPersistsAndReadsEventsByOwnerAndVehicle(): void
    {
        $owner = new UserEntity();
        $owner->setId(Uuid::v7());
        $owner->setEmail('maintenance.repo.owner@example.com');
        $owner->setRoles(['ROLE_USER']);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'test1234'));
        $this->em->persist($owner);
        $this->em->flush();

        $vehicle = Vehicle::create($owner->getId()->toRfc4122(), 'Renault Clio', 'ab-123-cd');
        $this->vehicleRepository->save($vehicle);

        $event = MaintenanceEvent::create(
            $owner->getId()->toRfc4122(),
            $vehicle->id()->toString(),
            MaintenanceEventType::SERVICE,
            new DateTimeImmutable('2026-02-22 09:00:00'),
            'Annual service',
            98500,
            18990,
        );
        $this->repository->save($event);

        $loaded = $this->repository->get($event->id()->toString());
        self::assertNotNull($loaded);
        self::assertSame($owner->getId()->toRfc4122(), $loaded->ownerId());
        self::assertSame($vehicle->id()->toString(), $loaded->vehicleId());
        self::assertSame(MaintenanceEventType::SERVICE, $loaded->eventType());
        self::assertSame(98500, $loaded->odometerKilometers());
        self::assertSame(18990, $loaded->totalCostCents());

        $ownerEvents = iterator_to_array($this->repository->allForOwner($owner->getId()->toRfc4122()));
        self::assertCount(1, $ownerEvents);
        self::assertSame($event->id()->toString(), $ownerEvents[0]->id()->toString());

        $vehicleEvents = iterator_to_array($this->repository->allForOwnerAndVehicle($owner->getId()->toRfc4122(), $vehicle->id()->toString()));
        self::assertCount(1, $vehicleEvents);
        self::assertSame($event->id()->toString(), $vehicleEvents[0]->id()->toString());
    }
}
