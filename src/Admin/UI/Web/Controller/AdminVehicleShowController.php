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
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminVehicleShowController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly StationRepository $stationRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly MaintenanceReminderRepository $maintenanceReminderRepository,
    ) {
    }

    #[Route('/ui/admin/vehicles/{id}', name: 'ui_admin_vehicle_show', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $vehicle = $this->vehicleRepository->get($id);
        if (null === $vehicle) {
            throw new NotFoundHttpException();
        }

        $stationNames = [];
        foreach ($this->stationRepository->allForSystem() as $station) {
            $stationNames[$station->id()->toString()] = $station->name();
        }

        $recentReceipts = [];
        $receiptCount = 0;
        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            if ($receipt->vehicleId()?->toString() !== $id) {
                continue;
            }

            ++$receiptCount;
            $recentReceipts[] = $receipt;
        }

        usort(
            $recentReceipts,
            static fn ($left, $right): int => $right->issuedAt() <=> $left->issuedAt(),
        );
        $recentReceipts = array_slice($recentReceipts, 0, 5);

        $eventCount = 0;
        foreach ($this->maintenanceEventRepository->allForSystem() as $event) {
            if ($event->vehicleId() === $id) {
                ++$eventCount;
            }
        }

        $dueReminderCount = 0;
        foreach ($this->maintenanceReminderRepository->allForSystem() as $reminder) {
            if ($reminder->vehicleId() !== $id) {
                continue;
            }

            if ($reminder->dueByDate() || $reminder->dueByOdometer()) {
                ++$dueReminderCount;
            }
        }

        return $this->render('admin/vehicles/show.html.twig', [
            'vehicle' => $vehicle,
            'recentReceipts' => $recentReceipts,
            'stationNames' => $stationNames,
            'receiptCount' => $receiptCount,
            'eventCount' => $eventCount,
            'dueReminderCount' => $dueReminderCount,
        ]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
