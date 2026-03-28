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

use App\Admin\Application\User\AdminUserManager;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminVehicleListController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly MaintenanceReminderRepository $maintenanceReminderRepository,
        private readonly AdminUserManager $userManager,
    ) {
    }

    #[Route('/ui/admin/vehicles', name: 'ui_admin_vehicle_list', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $query = $this->readStringFilter($request, 'q');
        $ownerId = $this->readStringFilter($request, 'ownerId');

        $receiptMetrics = [];
        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            $vehicleId = $receipt->vehicleId()?->toString();
            if (null === $vehicleId) {
                continue;
            }

            if (!isset($receiptMetrics[$vehicleId])) {
                $receiptMetrics[$vehicleId] = [
                    'count' => 0,
                    'lastIssuedAt' => null,
                ];
            }

            ++$receiptMetrics[$vehicleId]['count'];

            $issuedAt = $receipt->issuedAt();
            if (
                !$receiptMetrics[$vehicleId]['lastIssuedAt'] instanceof DateTimeImmutable
                || $issuedAt > $receiptMetrics[$vehicleId]['lastIssuedAt']
            ) {
                $receiptMetrics[$vehicleId]['lastIssuedAt'] = $issuedAt;
            }
        }

        $eventCounts = [];
        foreach ($this->maintenanceEventRepository->allForSystem() as $event) {
            $vehicleId = $event->vehicleId();
            $eventCounts[$vehicleId] = ($eventCounts[$vehicleId] ?? 0) + 1;
        }

        $dueReminderCounts = [];
        foreach ($this->maintenanceReminderRepository->allForSystem() as $reminder) {
            if (!$reminder->dueByDate() && !$reminder->dueByOdometer()) {
                continue;
            }

            $vehicleId = $reminder->vehicleId();
            $dueReminderCounts[$vehicleId] = ($dueReminderCounts[$vehicleId] ?? 0) + 1;
        }

        $vehicleRows = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            $vehicleId = $vehicle->id()->toString();
            $owner = null !== $vehicle->ownerId() ? $this->userManager->getUser($vehicle->ownerId()) : null;
            $row = [
                'vehicle' => $vehicle,
                'ownerEmail' => $owner?->email,
                'receiptCount' => $receiptMetrics[$vehicleId]['count'] ?? 0,
                'lastReceiptAt' => $receiptMetrics[$vehicleId]['lastIssuedAt'] ?? null,
                'eventCount' => $eventCounts[$vehicleId] ?? 0,
                'dueReminderCount' => $dueReminderCounts[$vehicleId] ?? 0,
                'signal' => $this->buildSignal(
                    $receiptMetrics[$vehicleId]['count'] ?? 0,
                    $eventCounts[$vehicleId] ?? 0,
                    $dueReminderCounts[$vehicleId] ?? 0,
                ),
            ];

            if (!$this->matchesFilters($row, $query, $ownerId)) {
                continue;
            }

            $vehicleRows[] = $row;
        }

        usort(
            $vehicleRows,
            static fn (array $left, array $right): int => [$right['dueReminderCount'], $right['receiptCount'], $right['eventCount']]
                <=> [$left['dueReminderCount'], $left['receiptCount'], $left['eventCount']]
        );

        return $this->render('admin/vehicles/index.html.twig', [
            'vehicleRows' => $vehicleRows,
            'ownerOptions' => $this->buildOwnerOptions(),
            'filters' => [
                'q' => $query ?? '',
                'ownerId' => $ownerId ?? '',
            ],
            'activeFilterSummary' => $this->buildActiveFilterSummary($query, $ownerId),
            'supportShortcuts' => $this->buildSupportShortcuts($vehicleRows),
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
     *   vehicle: object,
     *   ownerEmail: ?string,
     *   receiptCount: int,
     *   eventCount: int,
     *   dueReminderCount: int
     * } $row
     */
    private function matchesFilters(array $row, ?string $query, ?string $ownerId): bool
    {
        if (null !== $ownerId && $row['vehicle']->ownerId() !== $ownerId) {
            return false;
        }

        if (null === $query) {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            $row['vehicle']->name(),
            $row['vehicle']->plateNumber(),
            $row['ownerEmail'],
        ])));

        return str_contains($haystack, strtolower($query));
    }

    /**
     * @return array{headline:string,detail:string}
     */
    private function buildSignal(int $receiptCount, int $eventCount, int $dueReminderCount): array
    {
        if ($dueReminderCount > 0) {
            return [
                'headline' => 'Due maintenance needs review',
                'detail' => sprintf('%d reminder%s due now.', $dueReminderCount, 1 === $dueReminderCount ? '' : 's'),
            ];
        }

        if (0 === $receiptCount) {
            return [
                'headline' => 'No receipt history',
                'detail' => 'Vehicle has no linked receipt yet.',
            ];
        }

        if (0 === $eventCount) {
            return [
                'headline' => 'No maintenance events',
                'detail' => 'Receipt activity exists but maintenance history is still empty.',
            ];
        }

        return [
            'headline' => 'History looks linked',
            'detail' => sprintf('%d receipt%s and %d maintenance event%s linked.', $receiptCount, 1 === $receiptCount ? '' : 's', $eventCount, 1 === $eventCount ? '' : 's'),
        ];
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function buildActiveFilterSummary(?string $query, ?string $ownerId): array
    {
        $summary = [];

        if (null !== $query) {
            $summary[] = ['label' => 'Search', 'value' => $query];
        }

        if (null !== $ownerId) {
            $owner = $this->userManager->getUser($ownerId);
            $summary[] = ['label' => 'Owner', 'value' => null !== $owner ? sprintf('%s (%s)', $owner->email, $ownerId) : $ownerId];
        }

        return $summary;
    }

    /**
     * @param list<array{
     *   vehicle: object,
     *   receiptCount: int,
     *   dueReminderCount: int
     * }> $vehicleRows
     *
     * @return list<array{label:string,url:string}>
     */
    private function buildSupportShortcuts(array $vehicleRows): array
    {
        $shortcuts = [];

        foreach ($vehicleRows as $row) {
            $vehicleId = $row['vehicle']->id()->toString();

            if ($row['dueReminderCount'] > 0 && !isset($shortcuts['maintenance'])) {
                $shortcuts['maintenance'] = [
                    'label' => 'Open next due maintenance vehicle',
                    'url' => $this->generateUrl('ui_admin_vehicle_show', ['id' => $vehicleId, 'return_to' => '/ui/admin/vehicles']),
                ];
            }

            if ($row['receiptCount'] > 0 && !isset($shortcuts['receipts'])) {
                $shortcuts['receipts'] = [
                    'label' => 'Open busiest receipt vehicle',
                    'url' => $this->generateUrl('ui_admin_receipt_list', ['vehicle_id' => $vehicleId]),
                ];
            }

            if (0 === $row['receiptCount'] && !isset($shortcuts['missing'])) {
                $shortcuts['missing'] = [
                    'label' => 'Open vehicle with no receipts',
                    'url' => $this->generateUrl('ui_admin_vehicle_show', ['id' => $vehicleId, 'return_to' => '/ui/admin/vehicles']),
                ];
            }
        }

        return array_values($shortcuts);
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    private function buildOwnerOptions(): array
    {
        $options = [];
        foreach ($this->userManager->listUsers() as $user) {
            $options[] = [
                'id' => $user->id,
                'label' => $user->email,
            ];
        }

        return $options;
    }
}
