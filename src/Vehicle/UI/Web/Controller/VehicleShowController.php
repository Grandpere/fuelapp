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

namespace App\Vehicle\UI\Web\Controller;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class VehicleShowController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly MaintenancePlannedCostRepository $maintenancePlannedCostRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    #[Route('/ui/vehicles/{id}', name: 'ui_vehicle_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id): Response
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $vehicle = $this->vehicleRepository->get($id);
        if (!$vehicle instanceof Vehicle || $vehicle->ownerId() !== $ownerId) {
            throw new NotFoundHttpException();
        }

        $receiptRows = $this->receiptRepository->paginateFilteredListRows(
            1,
            5,
            $vehicle->id()->toString(),
            null,
            null,
            null,
            'issuedAt',
            'desc',
        );
        $receiptCount = $this->receiptRepository->countFiltered(
            $vehicle->id()->toString(),
            null,
            null,
            null,
        );

        $maintenanceEvents = array_values(iterator_to_array(
            $this->maintenanceEventRepository->allForOwnerAndVehicle($ownerId, $vehicle->id()->toString()),
        ));
        usort(
            $maintenanceEvents,
            static fn ($a, $b): int => $b->occurredAt() <=> $a->occurredAt(),
        );
        $recentMaintenanceEvents = array_slice($maintenanceEvents, 0, 5);

        $maintenancePlans = array_values(array_filter(
            iterator_to_array($this->maintenancePlannedCostRepository->allForOwner($ownerId)),
            static fn ($plan): bool => $plan->vehicleId() === $vehicle->id()->toString(),
        ));
        usort(
            $maintenancePlans,
            static fn ($a, $b): int => $a->plannedFor() <=> $b->plannedFor(),
        );
        $upcomingMaintenancePlans = array_values(array_filter(
            $maintenancePlans,
            static fn ($plan): bool => $plan->plannedFor() >= new DateTimeImmutable('today'),
        ));
        $upcomingMaintenancePlans = array_slice($upcomingMaintenancePlans, 0, 5);

        $latestReceiptOdometer = null;
        foreach ($receiptRows as $row) {
            $odometer = $row['odometerKilometers'] ?? null;
            if (is_int($odometer)) {
                $latestReceiptOdometer = $odometer;
                break;
            }
        }

        $latestMaintenanceOdometer = null;
        foreach ($recentMaintenanceEvents as $event) {
            if (null !== $event->odometerKilometers()) {
                $latestMaintenanceOdometer = $event->odometerKilometers();
                break;
            }
        }

        return $this->render('vehicle/show.html.twig', [
            'vehicle' => $vehicle,
            'receiptRows' => $receiptRows,
            'receiptCount' => $receiptCount,
            'recentMaintenanceEvents' => $recentMaintenanceEvents,
            'maintenanceEventCount' => count($maintenanceEvents),
            'upcomingMaintenancePlans' => $upcomingMaintenancePlans,
            'maintenancePlanCount' => count($maintenancePlans),
            'upcomingMaintenancePlanCount' => count($upcomingMaintenancePlans),
            'latestReceiptOdometer' => $latestReceiptOdometer,
            'latestMaintenanceOdometer' => $latestMaintenanceOdometer,
        ]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
