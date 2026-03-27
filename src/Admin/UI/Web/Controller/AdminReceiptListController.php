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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminReceiptListController extends AbstractController
{
    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly StationRepository $stationRepository,
    ) {
    }

    #[Route('/ui/admin/receipts', name: 'ui_admin_receipt_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $receipts = [];
        $returnTo = $request->getRequestUri();

        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            $vehicle = null;
            if (null !== $receipt->vehicleId()) {
                $vehicle = $this->vehicleRepository->get($receipt->vehicleId()->toString());
            }

            $station = null;
            if (null !== $receipt->stationId()) {
                $station = $this->stationRepository->getForSystem($receipt->stationId()->toString());
            }

            $receipts[] = [
                'receipt' => $receipt,
                'vehicle' => $vehicle,
                'station' => $station,
                'showUrl' => $this->generateUrl('ui_admin_receipt_show', ['id' => $receipt->id()->toString(), 'return_to' => $returnTo]),
                'editUrl' => $this->generateUrl('ui_admin_receipt_edit', ['id' => $receipt->id()->toString(), 'return_to' => $returnTo]),
            ];
        }

        return $this->render('admin/receipts/index.html.twig', [
            'receipts' => $receipts,
        ]);
    }
}
