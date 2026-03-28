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
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminStationListController extends AbstractController
{
    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly ReceiptRepository $receiptRepository,
    ) {
    }

    #[Route('/ui/admin/stations', name: 'ui_admin_station_list', methods: ['GET'])]
    public function __invoke(): Response
    {
        $receiptMetrics = [];
        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            $stationId = $receipt->stationId()?->toString();
            if (null === $stationId) {
                continue;
            }

            if (!isset($receiptMetrics[$stationId])) {
                $receiptMetrics[$stationId] = [
                    'count' => 0,
                    'lastIssuedAt' => null,
                    'vehicleIds' => [],
                ];
            }

            ++$receiptMetrics[$stationId]['count'];

            $issuedAt = $receipt->issuedAt();
            if (
                !$receiptMetrics[$stationId]['lastIssuedAt'] instanceof DateTimeImmutable
                || $issuedAt > $receiptMetrics[$stationId]['lastIssuedAt']
            ) {
                $receiptMetrics[$stationId]['lastIssuedAt'] = $issuedAt;
            }

            $vehicleId = $receipt->vehicleId()?->toString();
            if (null !== $vehicleId) {
                $receiptMetrics[$stationId]['vehicleIds'][$vehicleId] = true;
            }
        }

        $stationRows = [];
        foreach ($this->stationRepository->allForSystem() as $station) {
            $stationId = $station->id()->toString();
            $metrics = $receiptMetrics[$stationId] ?? null;
            $stationRows[] = [
                'station' => $station,
                'receiptCount' => null !== $metrics ? $metrics['count'] : 0,
                'lastReceiptAt' => null !== $metrics ? $metrics['lastIssuedAt'] : null,
                'linkedVehicleCount' => null !== $metrics
                    ? \count($metrics['vehicleIds'])
                    : 0,
            ];
        }

        return $this->render('admin/stations/index.html.twig', [
            'stationRows' => $stationRows,
        ]);
    }
}
