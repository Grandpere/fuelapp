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
use App\Station\Application\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminStationDeleteController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F\\-]{36}';

    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly AdminAuditTrail $auditTrail,
    ) {
    }

    #[Route('/ui/admin/stations/{id}/delete', name: 'ui_admin_station_delete', methods: ['POST'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $station = $this->stationRepository->getForSystem($id);
        if (null === $station) {
            throw new NotFoundHttpException();
        }

        $token = $request->request->get('_token');
        if (!is_scalar($token) || !$this->isCsrfTokenValid('admin_station_delete_'.$id, (string) $token)) {
            throw new NotFoundHttpException();
        }

        $this->stationRepository->deleteForSystem($id);
        $this->auditTrail->record(
            'admin.station.deleted.ui',
            'station',
            $id,
            [
                'before' => [
                    'name' => $station->name(),
                    'streetName' => $station->streetName(),
                    'postalCode' => $station->postalCode(),
                    'city' => $station->city(),
                ],
            ],
        );
        $this->addFlash('success', 'Station deleted.');

        return new RedirectResponse($this->generateUrl('ui_admin_station_list'), Response::HTTP_SEE_OTHER);
    }
}
