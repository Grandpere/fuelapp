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

use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\MaintenanceReminderRule;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Repository\DoctrineMaintenanceReminderRuleRepository;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineMaintenanceReminderRuleRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MaintenanceReminderRuleRepository $repository;
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

        $this->repository = new DoctrineMaintenanceReminderRuleRepository($this->em);

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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_reminder_rules, maintenance_events, vehicles, users RESTART IDENTITY CASCADE');
    }

    public function testRepositoryPersistsAndFindsRuleByOwnerAndVehicle(): void
    {
        $owner = new UserEntity();
        $owner->setId(Uuid::v7());
        $owner->setEmail('maintenance.rule.owner@example.com');
        $owner->setRoles(['ROLE_USER']);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'test1234'));
        $this->em->persist($owner);
        $this->em->flush();

        $vehicle = Vehicle::create($owner->getId()->toRfc4122(), 'Megane', 'ab-456-cd');
        $this->vehicleRepository->save($vehicle);

        $rule = MaintenanceReminderRule::create(
            $owner->getId()->toRfc4122(),
            $vehicle->id()->toString(),
            'Oil Service',
            ReminderRuleTriggerMode::WHICHEVER_FIRST,
            MaintenanceEventType::SERVICE,
            365,
            10000,
        );
        $this->repository->save($rule);

        $loaded = $this->repository->get($rule->id()->toString());
        self::assertNotNull($loaded);
        self::assertSame('Oil Service', $loaded->name());
        self::assertSame(ReminderRuleTriggerMode::WHICHEVER_FIRST, $loaded->triggerMode());
        self::assertSame(365, $loaded->intervalDays());
        self::assertSame(10000, $loaded->intervalKilometers());
        self::assertSame(MaintenanceEventType::SERVICE, $loaded->eventType());

        $list = iterator_to_array($this->repository->allForOwnerAndVehicle($owner->getId()->toRfc4122(), $vehicle->id()->toString()));
        self::assertCount(1, $list);
        self::assertSame($rule->id()->toString(), $list[0]->id()->toString());
    }
}
