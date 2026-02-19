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

namespace App\Receipt\UI\Web\Controller;

use App\Receipt\Application\Repository\ReceiptRepository;
use App\Station\Application\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListReceiptsController extends AbstractController
{
    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly StationRepository $stationRepository,
    ) {
    }

    #[Route('/ui/receipts', name: 'ui_receipt_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('per_page', 25)));
        $total = $this->receiptRepository->countAll();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $receipts = iterator_to_array($this->receiptRepository->paginate($page, $perPage), false);

        $stationIds = [];
        foreach ($receipts as $receipt) {
            if (null !== $receipt->stationId()) {
                $stationIds[] = $receipt->stationId()->toString();
            }
        }
        $stationIds = array_values(array_unique($stationIds));
        $stationsById = $this->stationRepository->getByIds($stationIds);

        $rows = [];
        foreach ($receipts as $receipt) {
            $station = null;
            if (null !== $receipt->stationId()) {
                $station = $stationsById[$receipt->stationId()->toString()] ?? null;
            }

            $rows[] = [
                'id' => $receipt->id()->toString(),
                'issuedAt' => $receipt->issuedAt(),
                'totalCents' => $receipt->totalCents(),
                'vatAmountCents' => $receipt->vatAmountCents(),
                'stationName' => $station?->name(),
                'stationStreetName' => $station?->streetName(),
                'stationPostalCode' => $station?->postalCode(),
                'stationCity' => $station?->city(),
            ];
        }

        return $this->render('receipt/index.html.twig', [
            'receipts' => $rows,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => $lastPage,
        ]);
    }
}
