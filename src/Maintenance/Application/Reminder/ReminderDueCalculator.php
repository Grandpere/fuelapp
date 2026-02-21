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

namespace App\Maintenance\Application\Reminder;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Maintenance\Domain\MaintenanceReminderRule;
use DateTimeImmutable;

final readonly class ReminderDueCalculator
{
    public function __construct(
        private MaintenanceReminderRuleRepository $ruleRepository,
        private MaintenanceEventRepository $eventRepository,
    ) {
    }

    /** @return list<ReminderDueState> */
    public function computeForVehicle(
        string $ownerId,
        string $vehicleId,
        ?int $currentOdometerKilometers = null,
        ?DateTimeImmutable $now = null,
    ): array {
        $now ??= new DateTimeImmutable();
        $states = [];

        $events = [];
        foreach ($this->eventRepository->allForOwnerAndVehicle($ownerId, $vehicleId) as $event) {
            $events[] = $event;
        }

        foreach ($this->ruleRepository->allForOwnerAndVehicle($ownerId, $vehicleId) as $rule) {
            $lastEvent = $this->findLatestMatchingEvent($events, $rule);
            $dueAtDate = $this->computeDueDate($rule, $lastEvent, $now);
            $dueAtOdometer = $this->computeDueOdometer($rule, $lastEvent, $currentOdometerKilometers);

            $dueByDate = null !== $dueAtDate && $now >= $dueAtDate;
            $dueByOdometer = null !== $dueAtOdometer && null !== $currentOdometerKilometers && $currentOdometerKilometers >= $dueAtOdometer;

            $isDue = match ($rule->triggerMode()) {
                ReminderRuleTriggerMode::DATE => $dueByDate,
                ReminderRuleTriggerMode::ODOMETER => $dueByOdometer,
                ReminderRuleTriggerMode::WHICHEVER_FIRST => $dueByDate || $dueByOdometer,
            };

            $states[] = new ReminderDueState(
                $rule->id()->toString(),
                $rule->name(),
                $rule->triggerMode(),
                $dueAtDate,
                $dueAtOdometer,
                $dueByDate,
                $dueByOdometer,
                $isDue,
                $lastEvent?->occurredAt(),
                $lastEvent?->odometerKilometers(),
            );
        }

        return $states;
    }

    /** @param list<MaintenanceEvent> $events */
    private function findLatestMatchingEvent(array $events, MaintenanceReminderRule $rule): ?MaintenanceEvent
    {
        $latest = null;
        foreach ($events as $event) {
            if (null !== $rule->eventType() && $event->eventType() !== $rule->eventType()) {
                continue;
            }

            if (null === $latest || $event->occurredAt() > $latest->occurredAt()) {
                $latest = $event;
            }
        }

        return $latest;
    }

    private function computeDueDate(MaintenanceReminderRule $rule, ?MaintenanceEvent $lastEvent, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $intervalDays = $rule->intervalDays();
        if (null === $intervalDays) {
            return null;
        }

        if (null === $lastEvent) {
            return $now;
        }

        return $lastEvent->occurredAt()->modify(sprintf('+%d days', $intervalDays));
    }

    private function computeDueOdometer(MaintenanceReminderRule $rule, ?MaintenanceEvent $lastEvent, ?int $currentOdometerKilometers): ?int
    {
        $intervalKilometers = $rule->intervalKilometers();
        if (null === $intervalKilometers) {
            return null;
        }

        if (null === $lastEvent || null === $lastEvent->odometerKilometers()) {
            return $currentOdometerKilometers;
        }

        return $lastEvent->odometerKilometers() + $intervalKilometers;
    }
}
