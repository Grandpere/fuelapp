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

namespace App\Maintenance\Application\MessageHandler;

use App\Maintenance\Application\Message\EvaluateMaintenanceRemindersMessage;
use App\Maintenance\Application\Notification\MaintenanceReminderNotifier;
use App\Maintenance\Application\Reminder\ReminderDueCalculator;
use App\Maintenance\Application\Reminder\ReminderDueState;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\MaintenanceReminder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EvaluateMaintenanceRemindersMessageHandler
{
    public function __construct(
        private MaintenanceReminderRuleRepository $ruleRepository,
        private MaintenanceEventRepository $eventRepository,
        private ReminderDueCalculator $dueCalculator,
        private MaintenanceReminderRepository $reminderRepository,
        private MaintenanceReminderNotifier $notifier,
    ) {
    }

    public function __invoke(EvaluateMaintenanceRemindersMessage $message): void
    {
        $pairs = $this->collectOwnerVehiclePairs();

        foreach ($pairs as $pair) {
            $ownerId = $pair['ownerId'];
            $vehicleId = $pair['vehicleId'];
            $currentOdometer = $this->resolveCurrentOdometer($ownerId, $vehicleId);

            $states = $this->dueCalculator->computeForVehicle($ownerId, $vehicleId, $currentOdometer);
            foreach ($states as $state) {
                if (!$state->isDue) {
                    continue;
                }

                $reminder = $this->toReminder($ownerId, $vehicleId, $state);
                $created = $this->reminderRepository->saveIfNew($reminder);
                if ($created) {
                    $this->notifier->notifyCreated($reminder);
                }
            }
        }
    }

    /** @return list<array{ownerId: string, vehicleId: string}> */
    private function collectOwnerVehiclePairs(): array
    {
        $pairs = [];
        foreach ($this->ruleRepository->allForSystem() as $rule) {
            $key = sprintf('%s:%s', $rule->ownerId(), $rule->vehicleId());
            $pairs[$key] = [
                'ownerId' => $rule->ownerId(),
                'vehicleId' => $rule->vehicleId(),
            ];
        }

        return array_values($pairs);
    }

    private function resolveCurrentOdometer(string $ownerId, string $vehicleId): ?int
    {
        $max = null;
        foreach ($this->eventRepository->allForOwnerAndVehicle($ownerId, $vehicleId) as $event) {
            $odometer = $event->odometerKilometers();
            if (null === $odometer) {
                continue;
            }

            $max = null === $max ? $odometer : max($max, $odometer);
        }

        return $max;
    }

    private function toReminder(string $ownerId, string $vehicleId, ReminderDueState $state): MaintenanceReminder
    {
        return MaintenanceReminder::create(
            $ownerId,
            $vehicleId,
            $state->ruleId,
            $state->dueAtDate,
            $state->dueAtOdometerKilometers,
            $state->dueByDate,
            $state->dueByOdometer,
        );
    }
}
