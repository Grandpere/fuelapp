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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminMaintenanceEventShowController extends AbstractController
{
    public function __construct(private readonly MaintenanceEventRepository $eventRepository)
    {
    }

    #[Route('/ui/admin/maintenance/events/{id}', name: 'ui_admin_maintenance_event_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $event = $this->eventRepository->get($id);
        if (null === $event) {
            throw new NotFoundHttpException();
        }

        return $this->render('admin/maintenance/events/show.html.twig', [
            'event' => $event,
        ]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';
}
