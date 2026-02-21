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

    public function sumActualCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int
    {
        return 0;
    }
}
