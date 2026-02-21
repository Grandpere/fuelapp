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

namespace App\Tests\Unit\Maintenance\Application\Reminder;

use App\Maintenance\Application\Reminder\ReminderDueCalculator;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Maintenance\Domain\MaintenanceReminderRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ReminderDueCalculatorTest extends TestCase
{
    public function testWhicheverFirstIsDueWhenOdometerThresholdReached(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $rule = MaintenanceReminderRule::create(
            $ownerId,
            $vehicleId,
            'Oil Service',
            ReminderRuleTriggerMode::WHICHEVER_FIRST,
            MaintenanceEventType::SERVICE,
            365,
            10000,
            new DateTimeImmutable('2026-01-01 10:00:00'),
        );
        $event = MaintenanceEvent::create(
            $ownerId,
            $vehicleId,
            MaintenanceEventType::SERVICE,
            new DateTimeImmutable('2026-01-01 10:00:00'),
            'previous service',
            100000,
            12990,
        );

        $calculator = new ReminderDueCalculator(
            new InMemoryRuleRepository([$rule]),
            new InMemoryEventRepository([$event]),
        );

        $states = $calculator->computeForVehicle(
            $ownerId,
            $vehicleId,
            110100,
            new DateTimeImmutable('2026-06-01 10:00:00'),
        );

        self::assertCount(1, $states);
        self::assertSame(110000, $states[0]->dueAtOdometerKilometers);
        self::assertFalse($states[0]->dueByDate);
        self::assertTrue($states[0]->dueByOdometer);
        self::assertTrue($states[0]->isDue);
    }

    public function testDateRuleWithoutHistoryIsImmediatelyDue(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $now = new DateTimeImmutable('2026-06-01 10:00:00');

        $rule = MaintenanceReminderRule::create(
            $ownerId,
            $vehicleId,
            'Technical Inspection',
            ReminderRuleTriggerMode::DATE,
            null,
            365,
            null,
            new DateTimeImmutable('2026-01-01 10:00:00'),
        );

        $calculator = new ReminderDueCalculator(
            new InMemoryRuleRepository([$rule]),
            new InMemoryEventRepository([]),
        );

        $states = $calculator->computeForVehicle($ownerId, $vehicleId, null, $now);

        self::assertCount(1, $states);
        self::assertSame($now, $states[0]->dueAtDate);
        self::assertTrue($states[0]->dueByDate);
        self::assertTrue($states[0]->isDue);
    }

    public function testWhicheverFirstIsDueWhenDateThresholdReachedBeforeOdometer(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $rule = MaintenanceReminderRule::create(
            $ownerId,
            $vehicleId,
            'Comprehensive Service',
            ReminderRuleTriggerMode::WHICHEVER_FIRST,
            MaintenanceEventType::SERVICE,
            30,
            10000,
            new DateTimeImmutable('2026-01-01 10:00:00'),
        );
        $event = MaintenanceEvent::create(
            $ownerId,
            $vehicleId,
            MaintenanceEventType::SERVICE,
            new DateTimeImmutable('2026-01-01 10:00:00'),
            'service baseline',
            100000,
            22990,
        );

        $calculator = new ReminderDueCalculator(
            new InMemoryRuleRepository([$rule]),
            new InMemoryEventRepository([$event]),
        );

        $states = $calculator->computeForVehicle(
            $ownerId,
            $vehicleId,
            105000,
            new DateTimeImmutable('2026-02-05 10:00:00'),
        );

        self::assertCount(1, $states);
        self::assertSame('2026-01-31 10:00', $states[0]->dueAtDate?->format('Y-m-d H:i'));
        self::assertSame(110000, $states[0]->dueAtOdometerKilometers);
        self::assertTrue($states[0]->dueByDate);
        self::assertFalse($states[0]->dueByOdometer);
        self::assertTrue($states[0]->isDue);
    }

    public function testWhicheverFirstIsNotDueWhenNoThresholdReached(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $rule = MaintenanceReminderRule::create(
            $ownerId,
            $vehicleId,
            'Major Check',
            ReminderRuleTriggerMode::WHICHEVER_FIRST,
            MaintenanceEventType::SERVICE,
            365,
            20000,
            new DateTimeImmutable('2026-01-01 10:00:00'),
        );
        $event = MaintenanceEvent::create(
            $ownerId,
            $vehicleId,
            MaintenanceEventType::SERVICE,
            new DateTimeImmutable('2026-01-01 10:00:00'),
            'service baseline',
            100000,
            22990,
        );

        $calculator = new ReminderDueCalculator(
            new InMemoryRuleRepository([$rule]),
            new InMemoryEventRepository([$event]),
        );

        $states = $calculator->computeForVehicle(
            $ownerId,
            $vehicleId,
            110000,
            new DateTimeImmutable('2026-06-01 10:00:00'),
        );

        self::assertCount(1, $states);
        self::assertFalse($states[0]->dueByDate);
        self::assertFalse($states[0]->dueByOdometer);
        self::assertFalse($states[0]->isDue);
    }

    public function testDateRuleIgnoresEventsOfDifferentTypeWhenSelectingBaseline(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $rule = MaintenanceReminderRule::create(
            $ownerId,
            $vehicleId,
            'Service cadence',
            ReminderRuleTriggerMode::DATE,
            MaintenanceEventType::SERVICE,
            30,
            null,
            new DateTimeImmutable('2026-01-01 10:00:00'),
        );

        $serviceEvent = MaintenanceEvent::create(
            $ownerId,
            $vehicleId,
            MaintenanceEventType::SERVICE,
            new DateTimeImmutable('2026-01-10 08:00:00'),
            'service event',
            100500,
            15000,
        );
        $repairEvent = MaintenanceEvent::create(
            $ownerId,
            $vehicleId,
            MaintenanceEventType::REPAIR,
            new DateTimeImmutable('2026-02-10 08:00:00'),
            'repair event',
            101000,
            9000,
        );

        $calculator = new ReminderDueCalculator(
            new InMemoryRuleRepository([$rule]),
            new InMemoryEventRepository([$repairEvent, $serviceEvent]),
        );

        $states = $calculator->computeForVehicle(
            $ownerId,
            $vehicleId,
            101000,
            new DateTimeImmutable('2026-02-05 08:00:00'),
        );

        self::assertCount(1, $states);
        self::assertSame('2026-02-09 08:00', $states[0]->dueAtDate?->format('Y-m-d H:i'));
        self::assertFalse($states[0]->isDue);
    }
}

final readonly class InMemoryRuleRepository implements MaintenanceReminderRuleRepository
{
    /** @param list<MaintenanceReminderRule> $rules */
    public function __construct(private array $rules)
    {
    }

    public function save(MaintenanceReminderRule $rule): void
    {
    }

    public function get(string $id): ?MaintenanceReminderRule
    {
        return null;
    }

    public function delete(string $id): void
    {
    }

    public function allForOwnerAndVehicle(string $ownerId, string $vehicleId): iterable
    {
        foreach ($this->rules as $rule) {
            if ($rule->ownerId() === $ownerId && $rule->vehicleId() === $vehicleId) {
                yield $rule;
            }
        }
    }

    public function allForSystem(): iterable
    {
        yield from $this->rules;
    }
}

final readonly class InMemoryEventRepository implements MaintenanceEventRepository
{
    /** @param list<MaintenanceEvent> $events */
    public function __construct(private array $events)
    {
    }

    public function save(MaintenanceEvent $event): void
    {
    }

    public function get(string $id): ?MaintenanceEvent
    {
        return null;
    }

    public function delete(string $id): void
    {
    }

    public function allForOwner(string $ownerId): iterable
    {
        foreach ($this->events as $event) {
            if ($event->ownerId() === $ownerId) {
                yield $event;
            }
        }
    }

    public function allForOwnerAndVehicle(string $ownerId, string $vehicleId): iterable
    {
        foreach ($this->events as $event) {
            if ($event->ownerId() === $ownerId && $event->vehicleId() === $vehicleId) {
                yield $event;
            }
        }
    }

    public function allForSystem(): iterable
    {
        yield from $this->events;
    }

    public function sumActualCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int
    {
        return 0;
    }
}
