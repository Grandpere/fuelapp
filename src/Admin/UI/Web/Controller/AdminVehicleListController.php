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

namespace App\Admin\UI\Web\Controller;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminVehicleListController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly MaintenanceReminderRepository $maintenanceReminderRepository,
    ) {
    }

    #[Route('/ui/admin/vehicles', name: 'ui_admin_vehicle_list', methods: ['GET'])]
    public function __invoke(): Response
    {
        $receiptMetrics = [];
        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            $vehicleId = $receipt->vehicleId()?->toString();
            if (null === $vehicleId) {
                continue;
            }

            if (!isset($receiptMetrics[$vehicleId])) {
                $receiptMetrics[$vehicleId] = [
                    'count' => 0,
                    'lastIssuedAt' => null,
                ];
            }

            ++$receiptMetrics[$vehicleId]['count'];

            $issuedAt = $receipt->issuedAt();
            if (
                !$receiptMetrics[$vehicleId]['lastIssuedAt'] instanceof DateTimeImmutable
                || $issuedAt > $receiptMetrics[$vehicleId]['lastIssuedAt']
            ) {
                $receiptMetrics[$vehicleId]['lastIssuedAt'] = $issuedAt;
            }
        }

        $eventCounts = [];
        foreach ($this->maintenanceEventRepository->allForSystem() as $event) {
            $vehicleId = $event->vehicleId();
            $eventCounts[$vehicleId] = ($eventCounts[$vehicleId] ?? 0) + 1;
        }

        $dueReminderCounts = [];
        foreach ($this->maintenanceReminderRepository->allForSystem() as $reminder) {
            if (!$reminder->dueByDate() && !$reminder->dueByOdometer()) {
                continue;
            }

            $vehicleId = $reminder->vehicleId();
            $dueReminderCounts[$vehicleId] = ($dueReminderCounts[$vehicleId] ?? 0) + 1;
        }

        $vehicleRows = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            $vehicleId = $vehicle->id()->toString();
            $vehicleRows[] = [
                'vehicle' => $vehicle,
                'receiptCount' => $receiptMetrics[$vehicleId]['count'] ?? 0,
                'lastReceiptAt' => $receiptMetrics[$vehicleId]['lastIssuedAt'] ?? null,
                'eventCount' => $eventCounts[$vehicleId] ?? 0,
                'dueReminderCount' => $dueReminderCounts[$vehicleId] ?? 0,
            ];
        }

        return $this->render('admin/vehicles/index.html.twig', [
            'vehicleRows' => $vehicleRows,
        ]);
    }
}
