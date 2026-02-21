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
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
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
        foreach ($vehicles as $vehicle) {
            if (null !== $vehicleId && $vehicle->id()->toString() !== $vehicleId) {
                continue;
            }

            foreach ($this->ruleRepository->allForOwnerAndVehicle($ownerId, $vehicle->id()->toString()) as $rule) {
                $ruleNames[$rule->id()->toString()] = $rule->name();
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
            'ruleNames' => $ruleNames,
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
}
