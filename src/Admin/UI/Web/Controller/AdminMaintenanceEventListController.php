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
use App\Maintenance\Domain\Enum\MaintenanceEventType;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use ValueError;

final class AdminMaintenanceEventListController extends AbstractController
{
    public function __construct(private readonly MaintenanceEventRepository $eventRepository)
    {
    }

    #[Route('/ui/admin/maintenance/events', name: 'ui_admin_maintenance_event_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $ownerId = $this->readUuidFilter($request, 'owner_id');
        $vehicleId = $this->readUuidFilter($request, 'vehicle_id');
        $eventType = $this->readEventTypeFilter($request, 'event_type');
        $occurredFrom = $this->readDateFilter($request, 'occurred_from');
        $occurredTo = $this->readDateFilter($request, 'occurred_to');

        $events = [];
        foreach ($this->eventRepository->allForSystem() as $event) {
            if (null !== $ownerId && $event->ownerId() !== $ownerId) {
                continue;
            }
            if (null !== $vehicleId && $event->vehicleId() !== $vehicleId) {
                continue;
            }
            if (null !== $eventType && $event->eventType() !== $eventType) {
                continue;
            }
            if (null !== $occurredFrom && $event->occurredAt() < $occurredFrom->setTime(0, 0, 0)) {
                continue;
            }
            if (null !== $occurredTo && $event->occurredAt() > $occurredTo->setTime(23, 59, 59)) {
                continue;
            }

            $events[] = $event;
        }

        return $this->render('admin/maintenance/events/index.html.twig', [
            'events' => $events,
            'filters' => [
                'ownerId' => $ownerId,
                'vehicleId' => $vehicleId,
                'eventType' => $eventType?->value,
                'occurredFrom' => $occurredFrom?->format('Y-m-d'),
                'occurredTo' => $occurredTo?->format('Y-m-d'),
            ],
            'eventTypeOptions' => array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases()),
        ]);
    }

    private function readUuidFilter(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ('' === $trimmed || !Uuid::isValid($trimmed)) {
            return null;
        }

        return $trimmed;
    }

    private function readEventTypeFilter(Request $request, string $name): ?MaintenanceEventType
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ('' === $trimmed) {
            return null;
        }

        try {
            return MaintenanceEventType::from($trimmed);
        } catch (ValueError) {
            return null;
        }
    }

    private function readDateFilter(Request $request, string $name): ?DateTimeImmutable
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ('' === $trimmed) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);

        return false === $parsed ? null : $parsed;
    }
}
