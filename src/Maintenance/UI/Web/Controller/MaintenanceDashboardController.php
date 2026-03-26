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

        $plannedCosts = array_values(array_filter(
            iterator_to_array($this->plannedCostRepository->allForOwner($ownerId)),
            static fn (mixed $plan): bool => null === $vehicleId || $plan->vehicleId() === $vehicleId,
        ));

        usort(
            $plannedCosts,
            static fn ($a, $b): int => $a->plannedFor() <=> $b->plannedFor(),
        );

        $today = new DateTimeImmutable('today')->setTime(0, 0, 0);
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
        $dueRuleCount = 0;
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
            }
        }

        $monthStart = new DateTimeImmutable(date('Y-m-01'));
        $monthEnd = $monthStart->modify('last day of this month');
        $variance = $this->varianceReader->read($ownerId, $vehicleId, $monthStart, $monthEnd);

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
            'dueRuleCount' => $dueRuleCount,
            'variance' => $variance,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
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
                $message = 'This rule is already due by date and mileage.';
            } elseif ($state->dueByDate) {
                $message = 'This rule is already due by date.';
            } elseif ($state->dueByOdometer) {
                $message = 'This rule is already due by mileage.';
            } else {
                $message = 'This rule is already due.';
            }
        } elseif ('date' === $rule->triggerMode()->value) {
            if (null === $state->lastEventOccurredAt) {
                $message = sprintf(
                    'No matching maintenance event yet. The first reminder will appear after %d day%s.',
                    $rule->intervalDays() ?? 0,
                    1 === ($rule->intervalDays() ?? 0) ? '' : 's',
                );
            } elseif (null !== $state->dueAtDate) {
                $message = sprintf('Next due on %s.', $state->dueAtDate->format('d/m/Y'));
            }
        } elseif ('odometer' === $rule->triggerMode()->value) {
            if (null === $currentOdometer) {
                $message = 'Waiting for odometer data from a receipt or maintenance event to evaluate this rule.';
            } elseif (null !== $state->dueAtOdometerKilometers) {
                $message = sprintf('Next due at %d km. Current estimate: %d km.', $state->dueAtOdometerKilometers, $currentOdometer);
            }
        } else {
            if (null === $currentOdometer && null !== $state->dueAtDate) {
                $message = sprintf(
                    'Next due on %s. Mileage-based due checks will start once the vehicle has odometer data.',
                    $state->dueAtDate->format('d/m/Y'),
                );
            } elseif (null !== $state->dueAtDate && null !== $state->dueAtOdometerKilometers && null !== $currentOdometer) {
                $message = sprintf(
                    'Next due on %s or at %d km. Current estimate: %d km.',
                    $state->dueAtDate->format('d/m/Y'),
                    $state->dueAtOdometerKilometers,
                    $currentOdometer,
                );
            } elseif (null !== $state->dueAtDate) {
                $message = sprintf('Next due on %s.', $state->dueAtDate->format('d/m/Y'));
            } elseif (null !== $state->dueAtOdometerKilometers) {
                $message = sprintf('Next due at %d km.', $state->dueAtOdometerKilometers);
            }
        }

        $lastEventSummary = null;
        if (null !== $state->lastEventOccurredAt) {
            $lastEventSummary = sprintf(
                'Last matching event: %s%s',
                $state->lastEventOccurredAt->format('d/m/Y H:i'),
                null !== $state->lastEventOdometerKilometers ? sprintf(' · %d km', $state->lastEventOdometerKilometers) : '',
            );
        }

        return [
            'message' => $message,
            'lastEventSummary' => $lastEventSummary,
            'isDue' => $state->isDue,
        ];
    }
}
