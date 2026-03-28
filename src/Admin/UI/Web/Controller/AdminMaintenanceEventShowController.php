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
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminMaintenanceEventShowController extends AbstractController
{
    public function __construct(
        private readonly MaintenanceEventRepository $eventRepository,
        private readonly VehicleRepository $vehicleRepository,
    ) {
    }

    #[Route('/ui/admin/maintenance/events/{id}', name: 'ui_admin_maintenance_event_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $event = $this->eventRepository->get($id);
        if (null === $event) {
            throw new NotFoundHttpException();
        }

        $vehicle = $this->vehicleRepository->get($event->vehicleId());
        $requestedReturnTo = $request->query->get('return_to');
        $backToListUrl = is_string($requestedReturnTo) && '' !== trim($requestedReturnTo) && str_starts_with($requestedReturnTo, '/') && !str_starts_with($requestedReturnTo, '//')
            ? $requestedReturnTo
            : $this->generateUrl('ui_admin_maintenance_event_list');

        return $this->render('admin/maintenance/events/show.html.twig', [
            'event' => $event,
            'vehicle' => $vehicle,
            'backToListUrl' => $backToListUrl,
            'matchingReceiptsUrl' => $this->generateUrl('ui_admin_receipt_list', ['vehicle_id' => $event->vehicleId()]),
            'matchingRemindersUrl' => $this->generateUrl('ui_admin_maintenance_reminder_list', [
                'vehicle_id' => $event->vehicleId(),
                'event_type' => $event->eventType()->value,
            ]),
        ]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';
}
