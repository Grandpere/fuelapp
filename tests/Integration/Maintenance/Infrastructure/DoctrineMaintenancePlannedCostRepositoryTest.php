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

use App\Maintenance\Domain\MaintenancePlannedCost;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Repository\DoctrineMaintenancePlannedCostRepository;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineMaintenancePlannedCostRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DoctrineMaintenancePlannedCostRepository $repository;
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
        $this->repository = new DoctrineMaintenancePlannedCostRepository($this->em);

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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, users RESTART IDENTITY CASCADE');
    }

    public function testRepositoryPersistsAndSumsPlannedCosts(): void
    {
        $owner = new UserEntity();
        $owner->setId(Uuid::v7());
        $owner->setEmail('maintenance.plan.owner@example.com');
        $owner->setRoles(['ROLE_USER']);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'test1234'));
        $this->em->persist($owner);
        $this->em->flush();

        $vehicle = Vehicle::create($owner->getId()->toRfc4122(), 'Captur', 'ab-999-cd');
        $this->vehicleRepository->save($vehicle);

        $item = MaintenancePlannedCost::create(
            $owner->getId()->toRfc4122(),
            $vehicle->id()->toString(),
            'Planned clutch replacement',
            null,
            new DateTimeImmutable('2026-09-01 10:00:00'),
            70000,
        );
        $this->repository->save($item);

        $loaded = $this->repository->get($item->id()->toString());
        self::assertNotNull($loaded);
        self::assertSame('Planned clutch replacement', $loaded->label());

        $sum = $this->repository->sumPlannedCostsForOwner(
            $vehicle->id()->toString(),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
            $owner->getId()->toRfc4122(),
        );
        self::assertSame(70000, $sum);
    }
}
