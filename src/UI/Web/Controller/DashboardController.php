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

namespace App\UI\Web\Controller;

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Domain\MaintenanceReminder;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Receipt;
use App\Shared\Application\Security\AuthenticatedUserIdProvider;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\Vehicle;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly ImportJobRepository $importJobRepository,
        private readonly MaintenanceReminderRepository $maintenanceReminderRepository,
        private readonly MaintenancePlannedCostRepository $maintenancePlannedCostRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
    ) {
    }

    #[Route('/ui/dashboard', name: 'ui_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        $ownerId = $this->authenticatedUserIdProvider->getAuthenticatedUserId();
        if (null === $ownerId) {
            throw new NotFoundHttpException();
        }

        $today = new DateTimeImmutable('today');
        $monthStart = $today->modify('first day of this month')->setTime(0, 0, 0);
        $soonPlanCutoff = $today->modify('+14 days')->setTime(23, 59, 59);

        $recentReceipts = $this->receiptRepository->paginateFilteredListRows(
            1,
            5,
            null,
            null,
            null,
            null,
            'date',
            'desc',
        );
        $receiptsThisMonthCount = $this->receiptRepository->countFiltered(null, null, $monthStart, null);

        $imports = array_values(iterator_to_array($this->importJobRepository->all()));
        usort(
            $imports,
            static fn (ImportJob $left, ImportJob $right): int => $right->createdAt() <=> $left->createdAt(),
        );

        $needsReviewImports = array_values(array_filter(
            $imports,
            static fn (ImportJob $job): bool => ImportJobStatus::NEEDS_REVIEW === $job->status(),
        ));
        $failedImports = array_values(array_filter(
            $imports,
            static fn (ImportJob $job): bool => ImportJobStatus::FAILED === $job->status(),
        ));

        $vehicles = $this->ownerVehicles($ownerId);
        $vehicleLabels = [];
        foreach ($vehicles as $vehicle) {
            $vehicleLabels[$vehicle->id()->toString()] = sprintf('%s (%s)', $vehicle->name(), $vehicle->plateNumber());
        }

        $reminders = array_values(iterator_to_array($this->maintenanceReminderRepository->allForOwner($ownerId)));
        usort(
            $reminders,
            static function (MaintenanceReminder $left, MaintenanceReminder $right): int {
                $leftDate = $left->dueAtDate()?->getTimestamp() ?? PHP_INT_MAX;
                $rightDate = $right->dueAtDate()?->getTimestamp() ?? PHP_INT_MAX;

                return $leftDate <=> $rightDate;
            },
        );

        $upcomingPlans = array_values(array_filter(
            iterator_to_array($this->maintenancePlannedCostRepository->allForOwner($ownerId)),
            static fn ($plan): bool => $plan->plannedFor() >= $today && $plan->plannedFor() <= $soonPlanCutoff,
        ));
        usort(
            $upcomingPlans,
            static fn ($left, $right): int => $left->plannedFor() <=> $right->plannedFor(),
        );

        $attentionItems = [];
        if (isset($needsReviewImports[0])) {
            $attentionItems[] = [
                'title' => 'Import needs review',
                'detail' => sprintf('"%s" is waiting for a manual check before the receipt can be created.', $needsReviewImports[0]->originalFilename()),
                'actions' => [
                    ['label' => 'Review import', 'url' => $this->generateUrl('ui_import_show', ['id' => $needsReviewImports[0]->id()->toString(), 'return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'primary'],
                    ['label' => 'Open queue', 'url' => $this->generateUrl('ui_import_index', ['status' => ImportJobStatus::NEEDS_REVIEW->value]), 'variant' => 'secondary'],
                ],
            ];
        }

        if (isset($failedImports[0])) {
            $attentionItems[] = [
                'title' => 'Import failed',
                'detail' => sprintf('"%s" stopped during processing and may need a replacement upload.', $failedImports[0]->originalFilename()),
                'actions' => [
                    ['label' => 'Inspect failure', 'url' => $this->generateUrl('ui_import_show', ['id' => $failedImports[0]->id()->toString(), 'return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'secondary'],
                    ['label' => 'Upload replacement', 'url' => $this->generateUrl('ui_import_index').'#import-upload-card', 'variant' => 'primary'],
                ],
            ];
        }

        if (isset($reminders[0])) {
            $reminder = $reminders[0];
            $vehicleLabel = $vehicleLabels[$reminder->vehicleId()] ?? 'Unknown vehicle';
            $attentionItems[] = [
                'title' => 'Maintenance is due',
                'detail' => sprintf('%s has a triggered reminder that is ready for follow-up.', $vehicleLabel),
                'actions' => [
                    ['label' => 'Log event now', 'url' => $this->generateUrl('ui_maintenance_event_new', ['vehicle_id' => $reminder->vehicleId(), 'return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'primary'],
                    ['label' => 'Open maintenance', 'url' => $this->generateUrl('ui_maintenance_index', ['vehicle_id' => $reminder->vehicleId()]), 'variant' => 'secondary'],
                    ['label' => 'Open vehicle', 'url' => $this->generateUrl('ui_vehicle_show', ['id' => $reminder->vehicleId()]), 'variant' => 'secondary'],
                ],
            ];
        }

        if (isset($upcomingPlans[0])) {
            $plan = $upcomingPlans[0];
            $vehicleLabel = $vehicleLabels[$plan->vehicleId()] ?? 'Unknown vehicle';
            $attentionItems[] = [
                'title' => 'Planned maintenance is coming up',
                'detail' => sprintf('%s has "%s" planned for %s.', $vehicleLabel, $plan->label(), $plan->plannedFor()->format('d/m/Y')),
                'actions' => [
                    ['label' => 'Edit plan', 'url' => $this->generateUrl('ui_maintenance_plan_edit', ['id' => $plan->id()->toString(), 'return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'secondary'],
                    ['label' => 'Open maintenance', 'url' => $this->generateUrl('ui_maintenance_index', ['vehicle_id' => $plan->vehicleId()]), 'variant' => 'primary'],
                ],
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'summaryCards' => [
                [
                    'label' => 'Needs review',
                    'value' => count($needsReviewImports),
                    'meta' => 'Imports waiting for a manual decision',
                    'url' => $this->generateUrl('ui_import_index', ['status' => ImportJobStatus::NEEDS_REVIEW->value]),
                ],
                [
                    'label' => 'Failed imports',
                    'value' => count($failedImports),
                    'meta' => 'Files that may need a replacement upload',
                    'url' => $this->generateUrl('ui_import_index', ['status' => ImportJobStatus::FAILED->value]),
                ],
                [
                    'label' => 'Maintenance due',
                    'value' => count($reminders),
                    'meta' => 'Triggered reminders ready for follow-up',
                    'url' => $this->generateUrl('ui_maintenance_index'),
                    'hint' => isset($reminders[0])
                        ? sprintf('Focus on %s first', $vehicleLabels[$reminders[0]->vehicleId()] ?? 'the latest vehicle')
                        : 'No triggered reminder is waiting right now',
                ],
                [
                    'label' => 'Receipts this month',
                    'value' => $receiptsThisMonthCount,
                    'meta' => sprintf('Since %s', $monthStart->format('d/m/Y')),
                    'url' => $this->generateUrl('ui_receipt_index', ['issued_from' => $monthStart->format('Y-m-d')]),
                    'hint' => 0 === $receiptsThisMonthCount ? 'Start with a new receipt or an import upload' : 'Jump straight into the filtered receipt list',
                ],
            ],
            'attentionItems' => $attentionItems,
            'recentReceipts' => $this->buildRecentReceiptCards($recentReceipts),
            'recentImports' => $this->buildRecentImportCards(array_slice($imports, 0, 5)),
            'vehicleCount' => count($vehicles),
            'quickActions' => [
                ['label' => 'Add receipt', 'url' => $this->generateUrl('ui_receipt_new'), 'variant' => 'primary', 'turboFrame' => 'receipt_form_frame'],
                ['label' => 'Upload files', 'url' => $this->generateUrl('ui_import_index').'#import-upload-card', 'variant' => 'secondary'],
                ['label' => 'Open maintenance', 'url' => $this->generateUrl('ui_maintenance_index'), 'variant' => 'secondary'],
                ['label' => 'Open analytics', 'url' => $this->generateUrl('ui_analytics_dashboard'), 'variant' => 'secondary'],
            ],
            'workflowCards' => [
                [
                    'title' => 'Imports',
                    'detail' => sprintf('%d need review, %d failed, %d are already resolved.', count($needsReviewImports), count($failedImports), max(0, count($imports) - count($needsReviewImports) - count($failedImports))),
                    'actions' => [
                        ['label' => 0 === count($needsReviewImports) ? 'Open imports' : 'Open follow-up now', 'url' => $this->generateUrl('ui_import_index', 0 === count($needsReviewImports) ? [] : ['status' => ImportJobStatus::NEEDS_REVIEW->value]), 'variant' => 'secondary'],
                        ['label' => 'Upload files', 'url' => $this->generateUrl('ui_import_index').'#import-upload-card', 'variant' => 'secondary'],
                    ],
                ],
                [
                    'title' => 'Maintenance',
                    'detail' => sprintf('%d reminder%s due now, %d upcoming plan%s in the next 14 days.', count($reminders), 1 === count($reminders) ? '' : 's', count($upcomingPlans), 1 === count($upcomingPlans) ? '' : 's'),
                    'actions' => [
                        ['label' => 0 === count($reminders) ? 'Open maintenance' : 'Handle due work', 'url' => $this->generateUrl('ui_maintenance_index', isset($reminders[0]) ? ['vehicle_id' => $reminders[0]->vehicleId()] : []), 'variant' => 'secondary'],
                        ['label' => isset($upcomingPlans[0]) ? 'Edit next plan' : 'Plan maintenance', 'url' => isset($upcomingPlans[0]) ? $this->generateUrl('ui_maintenance_plan_edit', ['id' => $upcomingPlans[0]->id()->toString(), 'return_to' => $this->generateUrl('ui_dashboard')]) : $this->generateUrl('ui_maintenance_plan_new', ['return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'secondary'],
                    ],
                ],
                [
                    'title' => 'Receipts',
                    'detail' => 0 === $receiptsThisMonthCount
                        ? 'Nothing tracked yet this month. Start from a receipt or an import upload.'
                        : sprintf('%d receipt%s tracked since %s.', $receiptsThisMonthCount, 1 === $receiptsThisMonthCount ? '' : 's', $monthStart->format('d/m/Y')),
                    'actions' => [
                        ['label' => 'Open month view', 'url' => $this->generateUrl('ui_receipt_index', ['issued_from' => $monthStart->format('Y-m-d')]), 'variant' => 'secondary'],
                        ['label' => 'Add receipt', 'url' => $this->generateUrl('ui_receipt_new'), 'variant' => 'primary', 'turboFrame' => 'receipt_form_frame'],
                    ],
                ],
                [
                    'title' => 'Fleet',
                    'detail' => sprintf('%d vehicle%s tracked so far. Use the list views when you want a broader scan than a single record page.', count($vehicles), 1 === count($vehicles) ? '' : 's'),
                    'actions' => [
                        ['label' => 'Open vehicles', 'url' => $this->generateUrl('ui_vehicle_list'), 'variant' => 'secondary'],
                        ['label' => 'Open stations', 'url' => $this->generateUrl('ui_station_list'), 'variant' => 'secondary'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param list<array{
     *     id: string,
     *     issuedAt: DateTimeImmutable,
     *     totalCents: int,
     *     vatAmountCents: int,
     *     odometerKilometers: ?int,
     *     stationName: ?string,
     *     stationStreetName: ?string,
     *     stationPostalCode: ?string,
     *     stationCity: ?string,
     *     fuelType: ?string,
     *     quantityMilliLiters: ?int,
     *     unitPriceDeciCentsPerLiter: ?int,
     *     vatRatePercent: ?int
     * }> $recentReceipts
     *
     * @return list<array{
     *     id: string,
     *     stationName: ?string,
     *     issuedAt: DateTimeImmutable,
     *     totalCents: int,
     *     odometerKilometers: ?int,
     *     fuelType: ?string,
     *     actions: list<array{label: string, url: string, variant: string}>
     * }>
     */
    private function buildRecentReceiptCards(array $recentReceipts): array
    {
        $cards = [];

        foreach ($recentReceipts as $row) {
            $actions = [
                [
                    'label' => 'Open receipt',
                    'url' => $this->generateUrl('ui_receipt_show', ['id' => $row['id'], 'return_to' => $this->generateUrl('ui_dashboard')]),
                    'variant' => 'secondary',
                ],
                [
                    'label' => 'Edit details',
                    'url' => $this->generateUrl('ui_receipt_edit_metadata', ['id' => $row['id'], 'return_to' => $this->generateUrl('ui_dashboard')]),
                    'variant' => 'secondary',
                ],
            ];

            $receipt = $this->receiptRepository->getForSystem($row['id']);
            if ($receipt instanceof Receipt && null !== $receipt->vehicleId()) {
                $vehicleId = $receipt->vehicleId()->toString();
                $actions[] = [
                    'label' => 'Open vehicle',
                    'url' => $this->generateUrl('ui_vehicle_show', ['id' => $vehicleId]),
                    'variant' => 'secondary',
                ];
                $actions[] = [
                    'label' => 'Analytics',
                    'url' => $this->generateUrl('ui_analytics_dashboard', ['vehicle_id' => $vehicleId]),
                    'variant' => 'secondary',
                ];
            }

            $cards[] = [
                'id' => $row['id'],
                'stationName' => $row['stationName'],
                'issuedAt' => $row['issuedAt'],
                'totalCents' => $row['totalCents'],
                'odometerKilometers' => $row['odometerKilometers'],
                'fuelType' => $row['fuelType'],
                'actions' => $actions,
            ];
        }

        return $cards;
    }

    /**
     * @param list<ImportJob> $recentImports
     *
     * @return list<array{
     *     id: string,
     *     originalFilename: string,
     *     status: ImportJobStatus,
     *     createdAt: DateTimeImmutable,
     *     actions: list<array{label: string, url: string, variant: string}>
     * }>
     */
    private function buildRecentImportCards(array $recentImports): array
    {
        $cards = [];
        $returnTo = $this->generateUrl('ui_dashboard');
        $uploadUrl = $this->generateUrl('ui_import_index').'#import-upload-card';

        foreach ($recentImports as $job) {
            $detailUrl = $this->generateUrl('ui_import_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);
            $actions = [];
            $payload = $this->decodePayload($job->errorPayload());

            switch ($job->status()) {
                case ImportJobStatus::NEEDS_REVIEW:
                    $actions[] = ['label' => 'Review import', 'url' => $detailUrl, 'variant' => 'primary'];
                    $actions[] = ['label' => 'Open queue', 'url' => $this->generateUrl('ui_import_index', ['status' => ImportJobStatus::NEEDS_REVIEW->value]), 'variant' => 'secondary'];
                    break;
                case ImportJobStatus::FAILED:
                    $actions[] = ['label' => 'Inspect failure', 'url' => $detailUrl, 'variant' => 'secondary'];
                    $actions[] = ['label' => 'Upload replacement', 'url' => $uploadUrl, 'variant' => 'primary'];
                    break;
                case ImportJobStatus::PROCESSED:
                    $receiptId = $this->readPayloadString($payload, 'finalizedReceiptId');
                    if (null !== $receiptId) {
                        $actions[] = ['label' => 'Open receipt', 'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId, 'return_to' => $returnTo]), 'variant' => 'primary'];
                    }
                    $actions[] = ['label' => 'Detail', 'url' => $detailUrl, 'variant' => 'secondary'];
                    break;
                case ImportJobStatus::DUPLICATE:
                    $receiptId = $this->readPayloadString($payload, 'duplicateOfReceiptId');
                    if (null !== $receiptId) {
                        $actions[] = ['label' => 'Open existing receipt', 'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId, 'return_to' => $returnTo]), 'variant' => 'primary'];
                    }
                    $actions[] = ['label' => 'Upload another', 'url' => $uploadUrl, 'variant' => 'secondary'];
                    break;
                default:
                    $actions[] = ['label' => 'Open import', 'url' => $detailUrl, 'variant' => 'secondary'];
                    $actions[] = ['label' => 'Open queue', 'url' => $this->generateUrl('ui_import_index'), 'variant' => 'secondary'];
            }

            $cards[] = [
                'id' => $job->id()->toString(),
                'originalFilename' => $job->originalFilename(),
                'status' => $job->status(),
                'createdAt' => $job->createdAt(),
                'actions' => $actions,
            ];
        }

        return $cards;
    }

    /** @return array<string, mixed>|null */
    private function decodePayload(?string $payload): ?array
    {
        if (null === $payload || '' === trim($payload)) {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function readPayloadString(?array $payload, string $key): ?string
    {
        if (!is_array($payload) || !array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /** @return list<Vehicle> */
    private function ownerVehicles(string $ownerId): array
    {
        $vehicles = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            if ($vehicle->ownerId() !== $ownerId) {
                continue;
            }

            $vehicles[] = $vehicle;
        }

        return $vehicles;
    }
}
