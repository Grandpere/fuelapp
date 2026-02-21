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

namespace App\Tests\Unit\Maintenance\Application\MessageHandler;

use App\Maintenance\Application\Message\EvaluateMaintenanceRemindersMessage;
use App\Maintenance\Application\MessageHandler\EvaluateMaintenanceRemindersMessageHandler;
use App\Maintenance\Application\Notification\MaintenanceReminderNotifier;
use App\Maintenance\Application\Reminder\ReminderDueCalculator;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\MaintenanceReminder;
use App\Maintenance\Domain\MaintenanceReminderRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class EvaluateMaintenanceRemindersMessageHandlerTest extends TestCase
{
    public function testHandlerCreatesReminderOnceAndPreventsDuplicates(): void
    {
        $ownerId = Uuid::v7()->toRfc4122();
        $vehicleId = Uuid::v7()->toRfc4122();
        $rule = MaintenanceReminderRule::create(
            $ownerId,
            $vehicleId,
            'Oil service',
            ReminderRuleTriggerMode::DATE,
            null,
            365,
            null,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $ruleRepository = new InMemoryReminderRuleRepository([$rule]);
        $eventRepository = new NoopEventRepository();
        $reminderRepository = new InMemoryReminderRepository();
        $notifier = new SpyNotifier();
        $calculator = new ReminderDueCalculator($ruleRepository, $eventRepository);

        $handler = new EvaluateMaintenanceRemindersMessageHandler(
            $ruleRepository,
            $eventRepository,
            $calculator,
            $reminderRepository,
            $notifier,
        );

        $handler(new EvaluateMaintenanceRemindersMessage());
        $handler(new EvaluateMaintenanceRemindersMessage());

        self::assertCount(1, $reminderRepository->items);
        self::assertCount(1, $notifier->notifiedReminderIds);
    }
}

final readonly class InMemoryReminderRuleRepository implements MaintenanceReminderRuleRepository
{
    /** @param list<MaintenanceReminderRule> $items */
    public function __construct(private array $items)
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
        foreach ($this->items as $item) {
            if ($item->ownerId() === $ownerId && $item->vehicleId() === $vehicleId) {
                yield $item;
            }
        }
    }

    public function allForSystem(): iterable
    {
        yield from $this->items;
    }
}

final class NoopEventRepository implements MaintenanceEventRepository
{
    public function save(\App\Maintenance\Domain\MaintenanceEvent $event): void
    {
    }

    public function get(string $id): ?\App\Maintenance\Domain\MaintenanceEvent
    {
        return null;
    }

    public function delete(string $id): void
    {
    }

    public function allForOwner(string $ownerId): iterable
    {
        return [];
    }

    public function allForOwnerAndVehicle(string $ownerId, string $vehicleId): iterable
    {
        return [];
    }
}

final class InMemoryReminderRepository implements MaintenanceReminderRepository
{
    /** @var list<MaintenanceReminder> */
    public array $items = [];

    public function saveIfNew(MaintenanceReminder $reminder): bool
    {
        foreach ($this->items as $item) {
            if ($item->dedupKey() === $reminder->dedupKey()) {
                return false;
            }
        }

        $this->items[] = $reminder;

        return true;
    }

    public function allForOwner(string $ownerId): iterable
    {
        return [];
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
