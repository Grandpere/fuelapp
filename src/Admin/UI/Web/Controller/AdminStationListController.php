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
use Symfony\Component\HttpFoundation\Request;
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
    public function __invoke(Request $request): Response
    {
        $query = $this->readStringFilter($request, 'q');
        $geocodingStatus = $this->readStringFilter($request, 'geocoding_status');

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
            $row = [
                'station' => $station,
                'receiptCount' => null !== $metrics ? $metrics['count'] : 0,
                'lastReceiptAt' => null !== $metrics ? $metrics['lastIssuedAt'] : null,
                'linkedVehicleCount' => null !== $metrics
                    ? \count($metrics['vehicleIds'])
                    : 0,
                'signal' => $this->buildSignal(
                    null !== $metrics ? $metrics['count'] : 0,
                    null !== $metrics ? \count($metrics['vehicleIds']) : 0,
                    $station->geocodingStatus()->value,
                ),
            ];

            if (!$this->matchesFilters($row, $query, $geocodingStatus)) {
                continue;
            }

            $stationRows[] = $row;
        }

        usort(
            $stationRows,
            static fn (array $left, array $right): int => [$right['receiptCount'], $right['linkedVehicleCount']]
                <=> [$left['receiptCount'], $left['linkedVehicleCount']]
        );

        return $this->render('admin/stations/index.html.twig', [
            'stationRows' => $stationRows,
            'filters' => [
                'q' => $query ?? '',
                'geocoding_status' => $geocodingStatus ?? '',
            ],
            'geocodingOptions' => $this->buildGeocodingOptions(),
            'activeFilterSummary' => $this->buildActiveFilterSummary($query, $geocodingStatus),
            'supportShortcuts' => $this->buildSupportShortcuts($stationRows),
        ]);
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

    /**
     * @param array{
     *   station: object,
     *   receiptCount: int,
     *   linkedVehicleCount: int
     * } $row
     */
    private function matchesFilters(array $row, ?string $query, ?string $geocodingStatus): bool
    {
        if (null !== $geocodingStatus && $row['station']->geocodingStatus()->value !== $geocodingStatus) {
            return false;
        }

        if (null === $query) {
            return true;
        }

        $haystack = strtolower(implode(' ', [
            $row['station']->name(),
            $row['station']->streetName(),
            $row['station']->postalCode(),
            $row['station']->city(),
        ]));

        return str_contains($haystack, strtolower($query));
    }

    /**
     * @return array{headline:string,detail:string}
     */
    private function buildSignal(int $receiptCount, int $linkedVehicleCount, string $geocodingStatus): array
    {
        if (0 === $receiptCount) {
            return [
                'headline' => 'No receipt activity',
                'detail' => 'Station has not been linked by any receipt yet.',
            ];
        }

        if (0 === $linkedVehicleCount) {
            return [
                'headline' => 'Receipt linked without vehicle context',
                'detail' => 'Receipt history exists but no vehicle is currently attached.',
            ];
        }

        return [
            'headline' => sprintf('Geocoding %s', $geocodingStatus),
            'detail' => sprintf('%d receipt%s across %d vehicle%s.', $receiptCount, 1 === $receiptCount ? '' : 's', $linkedVehicleCount, 1 === $linkedVehicleCount ? '' : 's'),
        ];
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildActiveFilterSummary(?string $query, ?string $geocodingStatus): array
    {
        $summary = [];

        if (null !== $query) {
            $summary[] = ['label' => 'Search', 'value' => $query];
        }

        if (null !== $geocodingStatus) {
            $summary[] = ['label' => 'Geocoding', 'value' => $geocodingStatus];
        }

        return $summary;
    }

    /**
     * @param list<array{
     *   station: object,
     *   receiptCount: int
     * }> $stationRows
     *
     * @return list<array{label:string,url:string}>
     */
    private function buildSupportShortcuts(array $stationRows): array
    {
        $shortcuts = [];

        foreach ($stationRows as $row) {
            $stationId = $row['station']->id()->toString();

            if ($row['receiptCount'] > 0 && !isset($shortcuts['receipts'])) {
                $shortcuts['receipts'] = [
                    'label' => 'Open busiest station receipts',
                    'url' => $this->generateUrl('ui_admin_receipt_list', ['station_id' => $stationId]),
                ];
            }

            if (0 === $row['receiptCount'] && !isset($shortcuts['missing'])) {
                $shortcuts['missing'] = [
                    'label' => 'Open station with no receipts',
                    'url' => $this->generateUrl('ui_admin_station_show', ['id' => $stationId, 'return_to' => '/ui/admin/stations']),
                ];
            }
        }

        return array_values($shortcuts);
    }

    /**
     * @return list<string>
     */
    private function buildGeocodingOptions(): array
    {
        $options = [];
        foreach ($this->stationRepository->allForSystem() as $station) {
            $options[$station->geocodingStatus()->value] = $station->geocodingStatus()->value;
        }

        return array_values($options);
    }
}
