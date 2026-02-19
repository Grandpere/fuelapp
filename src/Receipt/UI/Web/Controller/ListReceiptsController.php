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
use DateTimeImmutable;
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
        $stationId = $this->nullableString($request->query->get('station_id'));
        $issuedFrom = $this->parseDate($request->query->get('issued_from'));
        $issuedTo = $this->parseDate($request->query->get('issued_to'));
        $sortBy = in_array((string) $request->query->get('sort_by'), ['date', 'total'], true)
            ? (string) $request->query->get('sort_by')
            : 'date';
        $sortDirection = 'asc' === strtolower((string) $request->query->get('sort_direction')) ? 'asc' : 'desc';

        $total = $this->receiptRepository->countFiltered($stationId, $issuedFrom, $issuedTo);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $receipts = iterator_to_array(
            $this->receiptRepository->paginateFiltered(
                $page,
                $perPage,
                $stationId,
                $issuedFrom,
                $issuedTo,
                $sortBy,
                $sortDirection,
            ),
            false,
        );

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

        $stationOptions = [];
        foreach ($this->stationRepository->all() as $stationOption) {
            $stationOptions[] = [
                'id' => $stationOption->id()->toString(),
                'label' => sprintf(
                    '%s - %s, %s %s',
                    $stationOption->name(),
                    $stationOption->streetName(),
                    $stationOption->postalCode(),
                    $stationOption->city(),
                ),
            ];
        }

        usort(
            $stationOptions,
            static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']),
        );

        $queryParams = [
            'per_page' => $perPage,
            'station_id' => $stationId,
            'issued_from' => $issuedFrom?->format('Y-m-d'),
            'issued_to' => $issuedTo?->format('Y-m-d'),
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        return $this->render('receipt/index.html.twig', [
            'receipts' => $rows,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => $lastPage,
            'stationOptions' => $stationOptions,
            'filters' => [
                'stationId' => $stationId,
                'issuedFrom' => $issuedFrom?->format('Y-m-d'),
                'issuedTo' => $issuedTo?->format('Y-m-d'),
                'sortBy' => $sortBy,
                'sortDirection' => $sortDirection,
            ],
            'queryParams' => $queryParams,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return '' === $stringValue ? null : $stringValue;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        $date = trim((string) $value);
        if ('' === $date) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $parsed) {
            return null;
        }

        return $parsed;
    }
}
