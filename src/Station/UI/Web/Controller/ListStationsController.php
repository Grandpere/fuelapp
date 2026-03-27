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

namespace App\Station\UI\Web\Controller;

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Station\Application\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListStationsController extends AbstractController
{
    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly ReceiptRepository $receiptRepository,
    ) {
    }

    #[Route('/ui/stations', name: 'ui_station_list', methods: ['GET'])]
    public function __invoke(): Response
    {
        $rowsByStationId = [];

        foreach ($this->receiptRepository->all() as $receipt) {
            $stationId = $receipt->stationId()?->toString();
            if (null === $stationId) {
                continue;
            }

            if (!isset($rowsByStationId[$stationId])) {
                $rowsByStationId[$stationId] = [
                    'receiptCount' => 0,
                    'latestIssuedAt' => null,
                    'latestReceiptId' => null,
                    'recentSpendCents' => 0,
                    'latestOdometer' => null,
                ];
            }

            ++$rowsByStationId[$stationId]['receiptCount'];
            $rowsByStationId[$stationId]['recentSpendCents'] += $receipt->totalCents();

            if (null === $rowsByStationId[$stationId]['latestIssuedAt'] || $receipt->issuedAt() > $rowsByStationId[$stationId]['latestIssuedAt']) {
                $rowsByStationId[$stationId]['latestIssuedAt'] = $receipt->issuedAt();
                $rowsByStationId[$stationId]['latestReceiptId'] = $receipt->id()->toString();
                $rowsByStationId[$stationId]['latestOdometer'] = $receipt->odometerKilometers();
            }
        }

        $stationRows = [];
        foreach ($this->stationRepository->all() as $station) {
            $stationId = $station->id()->toString();
            $receiptStats = $rowsByStationId[$stationId] ?? [
                'receiptCount' => 0,
                'latestIssuedAt' => null,
                'latestReceiptId' => null,
                'recentSpendCents' => 0,
                'latestOdometer' => null,
            ];

            $stationRows[] = [
                'id' => $stationId,
                'name' => $station->name(),
                'streetName' => $station->streetName(),
                'postalCode' => $station->postalCode(),
                'city' => $station->city(),
                'receiptCount' => $receiptStats['receiptCount'],
                'latestIssuedAt' => $receiptStats['latestIssuedAt'],
                'latestReceiptId' => $receiptStats['latestReceiptId'],
                'recentSpendCents' => $receiptStats['recentSpendCents'],
                'latestOdometer' => $receiptStats['latestOdometer'],
            ];
        }

        usort(
            $stationRows,
            static function (array $left, array $right): int {
                $leftDate = $left['latestIssuedAt'];
                $rightDate = $right['latestIssuedAt'];

                if ($leftDate === $rightDate) {
                    return [$left['name'], $left['city']] <=> [$right['name'], $right['city']];
                }

                if (null === $leftDate) {
                    return 1;
                }

                if (null === $rightDate) {
                    return -1;
                }

                return $rightDate <=> $leftDate;
            },
        );

        return $this->render('station/index.html.twig', [
            'stationRows' => $stationRows,
        ]);
    }
}
