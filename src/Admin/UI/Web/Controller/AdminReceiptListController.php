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
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

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
        $vehicleId = $this->readUuidFilter($request, 'vehicle_id');
        $stationId = $this->readUuidFilter($request, 'station_id');
        $query = $this->readStringFilter($request, 'q');
        $issuedFrom = $this->readDateFilter($request, 'issued_from');
        $issuedTo = $this->readDateFilter($request, 'issued_to');

        $receipts = [];
        $returnTo = $request->getRequestUri();
        $metrics = [
            'total' => 0,
            'withVehicle' => 0,
            'withStation' => 0,
        ];

        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            $vehicle = null;
            if (null !== $receipt->vehicleId()) {
                $vehicle = $this->vehicleRepository->get($receipt->vehicleId()->toString());
                ++$metrics['withVehicle'];
            }

            $station = null;
            if (null !== $receipt->stationId()) {
                $station = $this->stationRepository->getForSystem($receipt->stationId()->toString());
                ++$metrics['withStation'];
            }

            ++$metrics['total'];

            if (null !== $vehicleId && $receipt->vehicleId()?->toString() !== $vehicleId) {
                continue;
            }
            if (null !== $stationId && $receipt->stationId()?->toString() !== $stationId) {
                continue;
            }
            if (null !== $issuedFrom && $receipt->issuedAt() < $issuedFrom->setTime(0, 0, 0)) {
                continue;
            }
            if (null !== $issuedTo && $receipt->issuedAt() > $issuedTo->setTime(23, 59, 59)) {
                continue;
            }
            if (null !== $query && !$this->matchesSearch($receipt->id()->toString(), $receipt->issuedAt(), $vehicle?->name(), $station?->name(), $query)) {
                continue;
            }

            $receipts[] = [
                'receipt' => $receipt,
                'vehicle' => $vehicle,
                'station' => $station,
                'showUrl' => $this->generateUrl('ui_admin_receipt_show', ['id' => $receipt->id()->toString(), 'return_to' => $returnTo]),
                'editUrl' => $this->generateUrl('ui_admin_receipt_edit', ['id' => $receipt->id()->toString(), 'return_to' => $returnTo]),
            ];
        }

        usort(
            $receipts,
            static fn (array $left, array $right): int => $right['receipt']->issuedAt() <=> $left['receipt']->issuedAt(),
        );

        return $this->render('admin/receipts/index.html.twig', [
            'receipts' => $receipts,
            'metrics' => $metrics,
            'vehicleOptions' => $this->buildVehicleOptions(),
            'stationOptions' => $this->buildStationOptions(),
            'filters' => [
                'vehicleId' => $vehicleId,
                'stationId' => $stationId,
                'q' => $query,
                'issuedFrom' => $issuedFrom?->format('Y-m-d'),
                'issuedTo' => $issuedTo?->format('Y-m-d'),
            ],
            'activeFilterSummary' => $this->buildActiveFilterSummary($vehicleId, $stationId, $query, $issuedFrom, $issuedTo),
            'supportShortcuts' => $this->buildSupportShortcuts($receipts),
        ]);
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildActiveFilterSummary(?string $vehicleId, ?string $stationId, ?string $query, ?DateTimeImmutable $issuedFrom, ?DateTimeImmutable $issuedTo): array
    {
        $summary = [];

        if (null !== $query) {
            $summary[] = ['label' => 'Search', 'value' => $query];
        }

        if (null !== $vehicleId) {
            $vehicle = $this->vehicleRepository->get($vehicleId);
            $summary[] = [
                'label' => 'Vehicle',
                'value' => null !== $vehicle ? sprintf('%s (%s)', $vehicle->name(), $vehicleId) : $vehicleId,
            ];
        }

        if (null !== $stationId) {
            $station = $this->stationRepository->getForSystem($stationId);
            $summary[] = [
                'label' => 'Station',
                'value' => null !== $station ? sprintf('%s (%s)', $station->name(), $stationId) : $stationId,
            ];
        }

        if (null !== $issuedFrom) {
            $summary[] = ['label' => 'Issued from', 'value' => $issuedFrom->format('Y-m-d')];
        }

        if (null !== $issuedTo) {
            $summary[] = ['label' => 'Issued to', 'value' => $issuedTo->format('Y-m-d')];
        }

        return $summary;
    }

    private function readUuidFilter(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ('' === $trimmed || !Uuid::isValid($trimmed)) {
            return null;
        }

        return $trimmed;
    }

    private function readStringFilter(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function readDateFilter(Request $request, string $name): ?DateTimeImmutable
    {
        $value = $this->readStringFilter($request, $name);
        if (null === $value) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return false === $parsed ? null : $parsed;
    }

    private function matchesSearch(string $receiptId, DateTimeImmutable $issuedAt, ?string $vehicleName, ?string $stationName, string $query): bool
    {
        $needle = mb_strtolower($query);
        $haystack = mb_strtolower(sprintf(
            '%s %s %s %s',
            $receiptId,
            $issuedAt->format('Y-m-d H:i:s'),
            $vehicleName ?? '',
            $stationName ?? '',
        ));

        return str_contains($haystack, $needle);
    }

    /**
     * @param list<array{receipt:mixed,vehicle:mixed,station:mixed,showUrl:string,editUrl:string}> $receipts
     *
     * @return list<array{label:string,url:string}>
     */
    private function buildSupportShortcuts(array $receipts): array
    {
        $shortcuts = [];

        $latest = $receipts[0] ?? null;
        if (is_array($latest)) {
            $shortcuts[] = ['label' => 'Open latest receipt', 'url' => $latest['showUrl']];
        }

        foreach ($receipts as $receipt) {
            if (null !== $receipt['vehicle']) {
                $shortcuts[] = ['label' => 'Open latest vehicle-linked receipt', 'url' => $receipt['showUrl']];
                break;
            }
        }

        foreach ($receipts as $receipt) {
            if (null !== $receipt['station']) {
                $shortcuts[] = ['label' => 'Open latest station-linked receipt', 'url' => $receipt['showUrl']];
                break;
            }
        }

        return $shortcuts;
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private function buildVehicleOptions(): array
    {
        $options = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            $label = $vehicle->name();
            $plateNumber = trim($vehicle->plateNumber());
            if ('' !== $plateNumber) {
                $label .= sprintf(' (%s)', $plateNumber);
            }

            $options[] = ['id' => $vehicle->id()->toString(), 'label' => $label];
        }

        usort($options, static fn (array $left, array $right): int => $left['label'] <=> $right['label']);

        return $options;
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private function buildStationOptions(): array
    {
        $options = [];
        foreach ($this->stationRepository->allForSystem() as $station) {
            $options[] = ['id' => $station->id()->toString(), 'label' => $station->name()];
        }

        usort($options, static fn (array $left, array $right): int => $left['label'] <=> $right['label']);

        return $options;
    }
}
