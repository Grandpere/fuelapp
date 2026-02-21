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

namespace App\Tests\Integration\Maintenance\Application;

use App\Maintenance\Application\Message\EvaluateMaintenanceRemindersMessage;
use App\Maintenance\Application\MessageHandler\EvaluateMaintenanceRemindersMessageHandler;
use App\Maintenance\Application\Notification\MaintenanceReminderNotifier;
use App\Maintenance\Application\Reminder\ReminderDueCalculator;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\MaintenanceReminder;
use App\Maintenance\Domain\MaintenanceReminderRule;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Repository\DoctrineMaintenanceEventRepository;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Repository\DoctrineMaintenanceReminderRepository;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Repository\DoctrineMaintenanceReminderRuleRepository;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class EvaluateMaintenanceRemindersMessageHandlerIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
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

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, users RESTART IDENTITY CASCADE');
    }

    public function testHandlerGeneratesSingleReminderAndPreventsDuplicates(): void
    {
        $owner = new UserEntity();
        $owner->setId(Uuid::v7());
        $owner->setEmail('maintenance.scheduler.owner@example.com');
        $owner->setRoles(['ROLE_USER']);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'test1234'));
        $this->em->persist($owner);
        $this->em->flush();

        $vehicle = Vehicle::create($owner->getId()->toRfc4122(), 'Scenic', 'xy-123-zz');
        $this->vehicleRepository->save($vehicle);

        $ruleRepository = new DoctrineMaintenanceReminderRuleRepository($this->em);
        $rule = MaintenanceReminderRule::create(
            $owner->getId()->toRfc4122(),
            $vehicle->id()->toString(),
            'Annual check',
            ReminderRuleTriggerMode::DATE,
            null,
            365,
            null,
        );
        $ruleRepository->save($rule);

        $eventRepository = new DoctrineMaintenanceEventRepository($this->em);
        $reminderRepository = new DoctrineMaintenanceReminderRepository($this->em);
        $calculator = new ReminderDueCalculator($ruleRepository, $eventRepository);
        $notifier = new SpyNotifier();
        $handler = new EvaluateMaintenanceRemindersMessageHandler(
            $ruleRepository,
            $eventRepository,
            $calculator,
            $reminderRepository,
            $notifier,
        );

        $handler(new EvaluateMaintenanceRemindersMessage());
        $handler(new EvaluateMaintenanceRemindersMessage());

        $rawCount = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM maintenance_reminders');
        self::assertTrue(is_int($rawCount) || (is_string($rawCount) && ctype_digit($rawCount)));
        $count = (int) $rawCount;
        self::assertSame(1, $count);
        self::assertCount(1, $notifier->notifiedReminderIds);
    }
}

final class SpyNotifier implements MaintenanceReminderNotifier
{
    /** @var list<string> */
    public array $notifiedReminderIds = [];

    public function notifyCreated(MaintenanceReminder $reminder): void
    {
        $this->notifiedReminderIds[] = $reminder->id()->toString();
    }
}
