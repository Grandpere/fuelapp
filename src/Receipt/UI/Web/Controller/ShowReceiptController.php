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

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Security\Voter\ReceiptVoter;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ShowReceiptController extends AbstractController
{
    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly StationRepository $stationRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    #[Route('/ui/receipts/{id}', name: 'ui_receipt_show', methods: ['GET'], requirements: ['id' => self::UUID_ROUTE_REQUIREMENT])]
    public function __invoke(Request $request, string $id): Response
    {
        $this->denyAccessUnlessGranted(ReceiptVoter::VIEW, $id);
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $receipt = $this->receiptRepository->get($id);
        if (null === $receipt) {
            throw $this->createNotFoundException('Receipt not found.');
        }

        $station = null;
        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->get($receipt->stationId()->toString());
        }

        $vehicle = null;
        $latestMaintenanceEvent = null;
        $maintenanceOdometerDelta = null;
        if (null !== $receipt->vehicleId()) {
            $vehicle = $this->vehicleRepository->get($receipt->vehicleId()->toString());

            $events = array_values(iterator_to_array(
                $this->maintenanceEventRepository->allForOwnerAndVehicle($ownerId, $receipt->vehicleId()->toString()),
            ));
            usort(
                $events,
                static fn ($a, $b): int => $b->occurredAt() <=> $a->occurredAt(),
            );
            $latestMaintenanceEvent = $events[0] ?? null;

            if (null !== $latestMaintenanceEvent?->odometerKilometers() && null !== $receipt->odometerKilometers()) {
                $maintenanceOdometerDelta = $receipt->odometerKilometers() - $latestMaintenanceEvent->odometerKilometers();
            }
        }

        $listFlow = $this->buildListFlow($request, $id);

        return $this->render('receipt/show.html.twig', [
            'receipt' => $receipt,
            'station' => $station,
            'vehicle' => $vehicle,
            'latestMaintenanceEvent' => $latestMaintenanceEvent,
            'maintenanceOdometerDelta' => $maintenanceOdometerDelta,
            'listFlow' => $listFlow,
        ]);
    }

    /**
     * @return array{
     *     backToListUrl: string,
     *     previousReceiptId: ?string,
     *     nextReceiptId: ?string
     * }|null
     */
    private function buildListFlow(Request $request, string $currentReceiptId): ?array
    {
        $returnTo = $request->query->get('return_to');
        if (!is_string($returnTo) || '' === trim($returnTo)) {
            return null;
        }

        $parts = parse_url($returnTo);
        if (!is_array($parts) || ($parts['path'] ?? null) !== '/ui/receipts') {
            return null;
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        $page = max(1, is_numeric($query['page'] ?? null) ? (int) $query['page'] : 1);
        $perPage = min(100, max(1, is_numeric($query['per_page'] ?? null) ? (int) $query['per_page'] : 25));
        $vehicleId = $this->nullableString($query['vehicle_id'] ?? null);
        $stationId = $this->nullableString($query['station_id'] ?? null);
        $issuedFrom = $this->parseDate($query['issued_from'] ?? null);
        $issuedTo = $this->parseDate($query['issued_to'] ?? null);
        $fuelType = $this->nullableString($query['fuel_type'] ?? null);
        $quantityMin = $this->parseInt($query['quantity_min'] ?? null);
        $quantityMax = $this->parseInt($query['quantity_max'] ?? null);
        $unitPriceMin = $this->parseInt($query['unit_price_min'] ?? null);
        $unitPriceMax = $this->parseInt($query['unit_price_max'] ?? null);
        $vatRate = $this->parseInt($query['vat_rate'] ?? null);
        $sortByCandidate = $this->queryStringValue($query, 'sort_by') ?? 'date';
        $sortBy = in_array($sortByCandidate, ['date', 'total', 'fuel_type', 'quantity', 'unit_price', 'vat_rate'], true)
            ? $sortByCandidate
            : 'date';
        $sortDirection = 'asc' === strtolower($this->queryStringValue($query, 'sort_direction') ?? 'desc') ? 'asc' : 'desc';

        $rows = $this->receiptRepository->paginateFilteredListRows(
            $page,
            $perPage,
            $vehicleId,
            $stationId,
            $issuedFrom,
            $issuedTo,
            $sortBy,
            $sortDirection,
            $fuelType,
            $quantityMin,
            $quantityMax,
            $unitPriceMin,
            $unitPriceMax,
            $vatRate,
        );

        $receiptIds = array_map(
            static fn (array $row): string => $row['id'],
            $rows,
        );
        $index = array_search($currentReceiptId, $receiptIds, true);
        if (false === $index) {
            return null;
        }

        return [
            'backToListUrl' => $returnTo,
            'previousReceiptId' => $receiptIds[$index - 1] ?? null,
            'nextReceiptId' => $receiptIds[$index + 1] ?? null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', trim($value));

        return false === $parsed ? null : $parsed->setTime(0, 0, 0);
    }

    private function parseInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value) || '' === trim($value) || !preg_match('/^-?\d+$/', trim($value))) {
            return null;
        }

        return (int) trim($value);
    }

    /**
     * @param array<int|string, array<mixed>|string> $query
     */
    private function queryStringValue(array $query, string $key): ?string
    {
        if (!array_key_exists($key, $query) || !is_string($query[$key])) {
            return null;
        }

        $value = trim($query[$key]);

        return '' === $value ? null : $value;
    }
}
