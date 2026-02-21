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
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly MaintenanceReminderRepository $maintenanceReminderRepository,
    ) {
    }

    #[Route('/ui/admin', name: 'ui_admin_dashboard', methods: ['GET'])]
    #[Route('/ui/admin/dashboard', name: 'ui_admin_dashboard_alias', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stationCount' => $this->countStations(),
            'vehicleCount' => $this->countVehicles(),
            'maintenanceEventCount' => $this->countMaintenanceEvents(),
            'maintenanceReminderCount' => $this->countMaintenanceReminders(),
        ]);
    }

    private function countStations(): int
    {
        $count = 0;
        foreach ($this->stationRepository->allForSystem() as $_) {
            ++$count;
        }

        return $count;
    }

    private function countVehicles(): int
    {
        $count = 0;
        foreach ($this->vehicleRepository->all() as $_) {
            ++$count;
        }

        return $count;
    }

    private function countMaintenanceEvents(): int
    {
        $count = 0;
        foreach ($this->maintenanceEventRepository->allForSystem() as $_) {
            ++$count;
        }

        return $count;
    }

    private function countMaintenanceReminders(): int
    {
        $count = 0;
        foreach ($this->maintenanceReminderRepository->allForSystem() as $_) {
            ++$count;
        }

        return $count;
    }
}
