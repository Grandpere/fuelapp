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

namespace App\Maintenance\UI\Web\Controller;

use App\Maintenance\Application\Cost\MaintenanceCostVarianceReader;
use App\Maintenance\Application\Reminder\ReminderDueCalculator;
use App\Maintenance\Application\Reminder\ReminderDueState;
use App\Maintenance\Application\Reminder\VehicleCurrentOdometerResolver;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\MaintenanceReminderRule;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MaintenanceDashboardController extends AbstractController
{
    public function __construct(
        private readonly MaintenanceEventRepository $eventRepository,
        private readonly MaintenancePlannedCostRepository $plannedCostRepository,
        private readonly MaintenanceReminderRepository $reminderRepository,
        private readonly MaintenanceReminderRuleRepository $ruleRepository,
        private readonly ReminderDueCalculator $dueCalculator,
        private readonly VehicleCurrentOdometerResolver $odometerResolver,
        private readonly MaintenanceCostVarianceReader $varianceReader,
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/ui/maintenance', name: 'ui_maintenance_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $vehicles = $this->ownerVehicles($ownerId);
        $vehicleMap = [];
        foreach ($vehicles as $vehicle) {
            $vehicleMap[$vehicle->id()->toString()] = sprintf('%s (%s)', $vehicle->name(), $vehicle->plateNumber());
        }

        $vehicleId = $this->readVehicleFilter($request, $ownerId);
        $events = null === $vehicleId
            ? iterator_to_array($this->eventRepository->allForOwner($ownerId))
            : iterator_to_array($this->eventRepository->allForOwnerAndVehicle($ownerId, $vehicleId));
        usort(
            $events,
            static fn ($a, $b): int => $b->occurredAt() <=> $a->occurredAt(),
        );

        $plannedCosts = array_values(array_filter(
            iterator_to_array($this->plannedCostRepository->allForOwner($ownerId)),
            static fn (mixed $plan): bool => null === $vehicleId || $plan->vehicleId() === $vehicleId,
        ));

        usort(
            $plannedCosts,
            static fn ($a, $b): int => $a->plannedFor() <=> $b->plannedFor(),
        );

        $today = new DateTimeImmutable('today')->setTime(0, 0, 0);
        $recentEventCutoff = $today->modify('-30 days');
        $soonPlanCutoff = $today->modify('+14 days');
        $upcomingPlans = array_values(array_filter(
            $plannedCosts,
            static fn ($plan): bool => $plan->plannedFor() >= $today,
        ));

        $reminders = array_values(array_filter(
            iterator_to_array($this->reminderRepository->allForOwner($ownerId)),
            static fn (mixed $reminder): bool => null === $vehicleId || $reminder->vehicleId() === $vehicleId,
        ));

        $ruleNames = [];
        $ruleDetails = [];
        $reminderRules = [];
        $reminderStates = [];
        $currentOdometers = [];
        $ruleInsights = [];
        $ruleLifecycle = [];
        $dueRuleCount = 0;
        $watchingRuleCount = 0;
        $dueSoonRuleCount = 0;
        $configuredRuleCount = 0;
        foreach ($vehicles as $vehicle) {
            if (null !== $vehicleId && $vehicle->id()->toString() !== $vehicleId) {
                continue;
            }

            $resolvedOdometer = $this->odometerResolver->resolve($ownerId, $vehicle->id()->toString());
            $currentOdometers[$vehicle->id()->toString()] = $resolvedOdometer;

            foreach ($this->ruleRepository->allForOwnerAndVehicle($ownerId, $vehicle->id()->toString()) as $rule) {
                $reminderRules[] = $rule;
                $ruleNames[$rule->id()->toString()] = $rule->name();
                $ruleDetails[$rule->id()->toString()] = [
                    'name' => $rule->name(),
                    'eventType' => $rule->eventType()?->value,
                    'triggerMode' => $rule->triggerMode()->value,
                    'intervalDays' => $rule->intervalDays(),
                    'intervalKilometers' => $rule->intervalKilometers(),
                    'vehicleId' => $rule->vehicleId(),
                ];
            }

            foreach ($this->dueCalculator->computeForVehicle($ownerId, $vehicle->id()->toString(), $resolvedOdometer) as $state) {
                $reminderStates[$state->ruleId] = $state;
                if ($state->isDue) {
                    ++$dueRuleCount;
                }
            }

            foreach ($this->ruleRepository->allForOwnerAndVehicle($ownerId, $vehicle->id()->toString()) as $rule) {
                $state = $reminderStates[$rule->id()->toString()] ?? null;
                $ruleInsights[$rule->id()->toString()] = $this->buildRuleInsight($rule, $state, $resolvedOdometer);
                $ruleLifecycle[$rule->id()->toString()] = $this->buildRuleLifecycle($rule, $state, $resolvedOdometer, $today);

                $stage = $ruleLifecycle[$rule->id()->toString()]['stage'];
                if ('due_now' === $stage) {
                    continue;
                }

                if ('due_soon' === $stage) {
                    ++$dueSoonRuleCount;

                    continue;
                }

                if ('watching' === $stage) {
                    ++$watchingRuleCount;

                    continue;
                }

                ++$configuredRuleCount;
            }
        }

        $monthStart = new DateTimeImmutable(date('Y-m-01'));
        $monthEnd = $monthStart->modify('last day of this month');
        $variance = $this->varianceReader->read($ownerId, $vehicleId, $monthStart, $monthEnd);
        $recentEventCount = count(array_filter(
            $events,
            static fn ($event): bool => $event->occurredAt() >= $recentEventCutoff,
        ));
        $dueSoonPlanCount = count(array_filter(
            $upcomingPlans,
            static fn ($plan): bool => $plan->plannedFor() <= $soonPlanCutoff,
        ));

        return $this->render('maintenance/index.html.twig', [
            'vehicleOptions' => $vehicleMap,
            'vehicleFilter' => $vehicleId,
            'events' => $events,
            'plannedCosts' => $plannedCosts,
            'upcomingPlans' => $upcomingPlans,
            'reminders' => $reminders,
            'reminderRules' => $reminderRules,
            'ruleNames' => $ruleNames,
            'ruleDetails' => $ruleDetails,
            'reminderStates' => $reminderStates,
            'currentOdometers' => $currentOdometers,
            'ruleInsights' => $ruleInsights,
            'ruleLifecycle' => $ruleLifecycle,
            'dueRuleCount' => $dueRuleCount,
            'watchingRuleCount' => $watchingRuleCount,
            'dueSoonRuleCount' => $dueSoonRuleCount,
            'configuredRuleCount' => $configuredRuleCount,
            'variance' => $variance,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'recentEventCutoff' => $recentEventCutoff,
            'recentEventCount' => $recentEventCount,
            'soonPlanCutoff' => $soonPlanCutoff,
            'dueSoonPlanCount' => $dueSoonPlanCount,
        ]);
    }

    /** @return list<Vehicle> */
    private function ownerVehicles(string $ownerId): array
    {
        $vehicles = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $vehicles[] = $vehicle;
        }

        return $vehicles;
    }

    private function readVehicleFilter(Request $request, string $ownerId): ?string
    {
        $raw = $request->query->get('vehicle_id');
        if (!is_scalar($raw)) {
            return null;
        }

        $vehicleId = trim((string) $raw);
        if ('' === $vehicleId || !Uuid::isValid($vehicleId)) {
            return null;
        }

        return $this->vehicleRepository->belongsToOwner($vehicleId, $ownerId) ? $vehicleId : null;
    }

    /** @return array{message: ?string, lastEventSummary: ?string, isDue: bool} */
    private function buildRuleInsight(MaintenanceReminderRule $rule, ?ReminderDueState $state, ?int $currentOdometer): array
    {
        if (!$state instanceof ReminderDueState) {
            return [
                'message' => null,
                'lastEventSummary' => null,
                'isDue' => false,
            ];
        }

        $message = null;
        if ($state->isDue) {
            if ($state->dueByDate && $state->dueByOdometer) {
                $message = $this->t('maintenance_dashboard.rule_due_date_and_mileage');
            } elseif ($state->dueByDate) {
                $message = $this->t('maintenance_dashboard.rule_due_date');
            } elseif ($state->dueByOdometer) {
                $message = $this->t('maintenance_dashboard.rule_due_mileage');
            } else {
                $message = $this->t('maintenance_dashboard.rule_due');
            }
        } elseif ('date' === $rule->triggerMode()->value) {
            if (null === $state->lastEventOccurredAt) {
                $intervalDays = $rule->intervalDays() ?? 0;
                $message = $this->t(
                    1 === $intervalDays ? 'maintenance_dashboard.first_reminder_after_days' : 'maintenance_dashboard.first_reminder_after_days_plural',
                    ['%count%' => (string) $intervalDays],
                );
            } elseif (null !== $state->dueAtDate) {
                $message = $this->t('maintenance_dashboard.next_due_on', ['%date%' => $state->dueAtDate->format('d/m/Y')]);
            }
        } elseif ('odometer' === $rule->triggerMode()->value) {
            if (null === $currentOdometer) {
                $message = $this->t('maintenance_dashboard.waiting_for_odometer_data');
            } elseif (null !== $state->dueAtOdometerKilometers) {
                $message = $this->t('maintenance_dashboard.next_due_at_current_estimate', ['%due%' => (string) $state->dueAtOdometerKilometers, '%current%' => (string) $currentOdometer]);
            }
        } else {
            if (null === $currentOdometer && null !== $state->dueAtDate) {
                $message = $this->t('maintenance_dashboard.next_due_on_odometer_starts_later', ['%date%' => $state->dueAtDate->format('d/m/Y')]);
            } elseif (null !== $state->dueAtDate && null !== $state->dueAtOdometerKilometers && null !== $currentOdometer) {
                $message = $this->t('maintenance_dashboard.next_due_on_or_at', ['%date%' => $state->dueAtDate->format('d/m/Y'), '%due%' => (string) $state->dueAtOdometerKilometers, '%current%' => (string) $currentOdometer]);
            } elseif (null !== $state->dueAtDate) {
                $message = $this->t('maintenance_dashboard.next_due_on', ['%date%' => $state->dueAtDate->format('d/m/Y')]);
            } elseif (null !== $state->dueAtOdometerKilometers) {
                $message = $this->t('maintenance_dashboard.next_due_at', ['%due%' => (string) $state->dueAtOdometerKilometers]);
            }
        }

        $lastEventSummary = null;
        if (null !== $state->lastEventOccurredAt) {
            $lastEventSummary = $this->t('maintenance_dashboard.last_matching_event', [
                '%date%' => $state->lastEventOccurredAt->format('d/m/Y H:i'),
                '%odometer_suffix%' => null !== $state->lastEventOdometerKilometers ? sprintf(' · %d km', $state->lastEventOdometerKilometers) : '',
            ]);
        }

        return [
            'message' => $message,
            'lastEventSummary' => $lastEventSummary,
            'isDue' => $state->isDue,
        ];
    }

    /** @return array{stage:string,label:string,badgeClass:string,detail:?string} */
    private function buildRuleLifecycle(MaintenanceReminderRule $rule, ?ReminderDueState $state, ?int $currentOdometer, DateTimeImmutable $today): array
    {
        if (!$state instanceof ReminderDueState) {
            return [
                'stage' => 'configured',
                'label' => $this->t('maintenance_dashboard.status_configured'),
                'badgeClass' => 'pill-maintenance-later',
                'detail' => $this->t('maintenance_dashboard.waiting_first_evaluation'),
            ];
        }

        if ($state->isDue) {
            return [
                'stage' => 'due_now',
                'label' => $this->t('maintenance_dashboard.status_due_now'),
                'badgeClass' => 'pill-reminder-due',
                'detail' => $this->t('maintenance_dashboard.follow_up_ready'),
            ];
        }

        if (ReminderRuleTriggerMode::ODOMETER === $rule->triggerMode() && null === $currentOdometer) {
            return [
                'stage' => 'configured',
                'label' => $this->t('maintenance_dashboard.status_configured'),
                'badgeClass' => 'pill-maintenance-later',
                'detail' => $this->t('maintenance_dashboard.waiting_odometer_tracking'),
            ];
        }

        if (null === $state->lastEventOccurredAt) {
            return [
                'stage' => 'configured',
                'label' => $this->t('maintenance_dashboard.status_configured'),
                'badgeClass' => 'pill-maintenance-later',
                'detail' => $this->t('maintenance_dashboard.no_matching_event'),
            ];
        }

        if (null !== $state->dueAtDate) {
            $daysUntilDue = (int) $today->diff($state->dueAtDate)->format('%r%a');
            if ($daysUntilDue >= 0 && $daysUntilDue <= 14) {
                return [
                    'stage' => 'due_soon',
                    'label' => $this->t('maintenance_dashboard.status_due_soon'),
                    'badgeClass' => 'pill-maintenance-soon',
                    'detail' => $this->t('maintenance_dashboard.next_due_on', ['%date%' => $state->dueAtDate->format('d/m/Y')]),
                ];
            }
        }

        if (null !== $state->dueAtOdometerKilometers && null !== $currentOdometer) {
            $remainingKilometers = $state->dueAtOdometerKilometers - $currentOdometer;
            if ($remainingKilometers >= 0 && $remainingKilometers <= 1000) {
                return [
                    'stage' => 'due_soon',
                    'label' => $this->t('maintenance_dashboard.status_due_soon'),
                    'badgeClass' => 'pill-maintenance-soon',
                    'detail' => $this->t('maintenance_dashboard.due_soon_at', ['%due%' => (string) $state->dueAtOdometerKilometers, '%current%' => (string) $currentOdometer]),
                ];
            }
        }

        return [
            'stage' => 'watching',
            'label' => $this->t('maintenance_dashboard.status_watching'),
            'badgeClass' => 'pill-soft-info',
            'detail' => $this->t('maintenance_dashboard.rule_active_not_close'),
        ];
    }

    /** @param array<string, string> $parameters */
    private function t(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}
