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

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminStationShowController extends AbstractController
{
    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly VehicleRepository $vehicleRepository,
    ) {
    }

    #[Route('/ui/admin/stations/{id}', name: 'ui_admin_station_show', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $station = $this->stationRepository->getForSystem($id);
        if (null === $station) {
            throw new NotFoundHttpException();
        }

        $vehicleNames = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            $vehicleNames[$vehicle->id()->toString()] = $vehicle->name();
        }

        $recentReceipts = [];
        $receiptCount = 0;
        $linkedVehicleIds = [];
        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            if ($receipt->stationId()?->toString() !== $id) {
                continue;
            }

            ++$receiptCount;
            $recentReceipts[] = $receipt;

            $vehicleId = $receipt->vehicleId()?->toString();
            if (null !== $vehicleId) {
                $linkedVehicleIds[$vehicleId] = true;
            }
        }

        usort(
            $recentReceipts,
            static fn ($left, $right): int => $right->issuedAt() <=> $left->issuedAt(),
        );
        $recentReceipts = array_slice($recentReceipts, 0, 5);

        return $this->render('admin/stations/show.html.twig', [
            'station' => $station,
            'recentReceipts' => $recentReceipts,
            'vehicleNames' => $vehicleNames,
            'receiptCount' => $receiptCount,
            'linkedVehicleCount' => \count($linkedVehicleIds),
        ]);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
