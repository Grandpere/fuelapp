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

namespace App\Receipt\UI\Web\Controller;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Security\Voter\ReceiptVoter;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ShowReceiptController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly StationRepository $stationRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    #[Route('/ui/receipts/{id}', name: 'ui_receipt_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id): Response
    {
        $this->denyAccessUnlessGranted(ReceiptVoter::VIEW, $id);
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $receipt = $this->receiptRepository->get($id);
        if (null === $receipt) {
            throw $this->createNotFoundException('Receipt not found.');
        }

        $station = null;
        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->get($receipt->stationId()->toString());
        }

        $vehicle = null;
        $latestMaintenanceEvent = null;
        $maintenanceOdometerDelta = null;
        if (null !== $receipt->vehicleId()) {
            $vehicle = $this->vehicleRepository->get($receipt->vehicleId()->toString());

            $events = array_values(iterator_to_array(
                $this->maintenanceEventRepository->allForOwnerAndVehicle($ownerId, $receipt->vehicleId()->toString()),
            ));
            usort(
                $events,
                static fn ($a, $b): int => $b->occurredAt() <=> $a->occurredAt(),
            );
            $latestMaintenanceEvent = $events[0] ?? null;

            if (null !== $latestMaintenanceEvent?->odometerKilometers() && null !== $receipt->odometerKilometers()) {
                $maintenanceOdometerDelta = $receipt->odometerKilometers() - $latestMaintenanceEvent->odometerKilometers();
            }
        }

        return $this->render('receipt/show.html.twig', [
            'receipt' => $receipt,
            'station' => $station,
            'vehicle' => $vehicle,
            'latestMaintenanceEvent' => $latestMaintenanceEvent,
            'maintenanceOdometerDelta' => $maintenanceOdometerDelta,
        ]);
    }
}
