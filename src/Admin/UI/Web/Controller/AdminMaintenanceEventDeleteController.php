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

use App\Admin\Application\Audit\AdminAuditTrail;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminMaintenanceEventDeleteController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly MaintenanceEventRepository $eventRepository,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/maintenance/events/{id}/delete', name: 'ui_admin_maintenance_event_delete', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $event = $this->eventRepository->get($id);
        if (null === $event) {
            throw new NotFoundHttpException();
        }

        $token = $request->request->get('_token');
        if (!is_scalar($token) || !$this->isCsrfTokenValid('admin_maintenance_event_delete_'.$id, (string) $token)) {
            throw new NotFoundHttpException();
        }

        $this->eventRepository->delete($id);
        $this->auditTrail->record(
            'admin.maintenance_event.deleted.ui',
            'maintenance_event',
            $id,
            [
                'before' => [
                    'eventType' => $event->eventType()->value,
                    'vehicleId' => $event->vehicleId(),
                    'occurredAt' => $event->occurredAt()->format(DATE_ATOM),
                ],
            ],
        );
        $this->addFlash('success', 'Maintenance event deleted.');

        return new RedirectResponse($this->generateUrl('ui_admin_maintenance_event_list'), Response::HTTP_SEE_OTHER);
    }
}
