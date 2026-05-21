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
use Symfony\Contracts\Translation\TranslatorInterface;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly ImportJobRepository $importJobRepository,
        private readonly MaintenanceReminderRepository $maintenanceReminderRepository,
        private readonly MaintenancePlannedCostRepository $maintenancePlannedCostRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly AuthenticatedUserIdProvider $authenticatedUserIdProvider,
        private readonly TranslatorInterface $translator,
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
                'title' => $this->t('dashboard.attention.import_needs_review'),
                'detail' => $this->t('dashboard.attention.import_needs_review_detail', ['%file%' => $needsReviewImports[0]->originalFilename()]),
                'actions' => [
                    ['label' => $this->t('import.follow_up.review_next'), 'url' => $this->generateUrl('ui_import_show', ['id' => $needsReviewImports[0]->id()->toString(), 'return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'primary'],
                    ['label' => $this->t('admin.dashboard.attention.open_queue'), 'url' => $this->generateUrl('ui_import_index', ['status' => ImportJobStatus::NEEDS_REVIEW->value]), 'variant' => 'secondary'],
                ],
            ];
        }

        if (isset($failedImports[0])) {
            $attentionItems[] = [
                'title' => $this->t('dashboard.attention.import_failed'),
                'detail' => $this->t('dashboard.attention.import_failed_detail', ['%file%' => $failedImports[0]->originalFilename()]),
                'actions' => [
                    ['label' => $this->t('import.follow_up.inspect_failure'), 'url' => $this->generateUrl('ui_import_show', ['id' => $failedImports[0]->id()->toString(), 'return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'secondary'],
                    ['label' => $this->t('import.action.upload_replacement'), 'url' => $this->generateUrl('ui_import_index').'#import-upload-card', 'variant' => 'primary'],
                ],
            ];
        }

        if (isset($reminders[0])) {
            $reminder = $reminders[0];
            $vehicleLabel = $vehicleLabels[$reminder->vehicleId()] ?? $this->t('common.unknown_vehicle');
            $attentionItems[] = [
                'title' => $this->t('dashboard.attention.maintenance_due'),
                'detail' => $this->t('dashboard.attention.maintenance_due_detail', ['%vehicle%' => $vehicleLabel]),
                'actions' => [
                    ['label' => $this->t('maintenance_dashboard.log_event_now'), 'url' => $this->generateUrl('ui_maintenance_event_new', ['vehicle_id' => $reminder->vehicleId(), 'return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'primary'],
                    ['label' => $this->t('analytics.open_maintenance'), 'url' => $this->generateUrl('ui_maintenance_index', ['vehicle_id' => $reminder->vehicleId()]), 'variant' => 'secondary'],
                    ['label' => $this->t('analytics.open_vehicle'), 'url' => $this->generateUrl('ui_vehicle_show', ['id' => $reminder->vehicleId()]), 'variant' => 'secondary'],
                ],
            ];
        }

        if (isset($upcomingPlans[0])) {
            $plan = $upcomingPlans[0];
            $vehicleLabel = $vehicleLabels[$plan->vehicleId()] ?? $this->t('common.unknown_vehicle');
            $attentionItems[] = [
                'title' => $this->t('dashboard.attention.planned_maintenance_coming'),
                'detail' => $this->t('dashboard.attention.planned_maintenance_coming_detail', ['%vehicle%' => $vehicleLabel, '%plan%' => $plan->label(), '%date%' => $plan->plannedFor()->format('d/m/Y')]),
                'actions' => [
                    ['label' => $this->t('dashboard.edit_next_plan'), 'url' => $this->generateUrl('ui_maintenance_plan_edit', ['id' => $plan->id()->toString(), 'return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'secondary'],
                    ['label' => $this->t('analytics.open_maintenance'), 'url' => $this->generateUrl('ui_maintenance_index', ['vehicle_id' => $plan->vehicleId()]), 'variant' => 'primary'],
                ],
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'summaryCards' => [
                [
                    'label' => $this->t('dashboard.summary.needs_review'),
                    'value' => count($needsReviewImports),
                    'meta' => $this->t('dashboard.summary.needs_review_meta'),
                    'url' => $this->generateUrl('ui_import_index', ['status' => ImportJobStatus::NEEDS_REVIEW->value]),
                ],
                [
                    'label' => $this->t('dashboard.summary.failed_imports'),
                    'value' => count($failedImports),
                    'meta' => $this->t('dashboard.summary.failed_imports_meta'),
                    'url' => $this->generateUrl('ui_import_index', ['status' => ImportJobStatus::FAILED->value]),
                ],
                [
                    'label' => $this->t('dashboard.summary.maintenance_due'),
                    'value' => count($reminders),
                    'meta' => $this->t('dashboard.summary.maintenance_due_meta'),
                    'url' => $this->generateUrl('ui_maintenance_index'),
                    'hint' => isset($reminders[0])
                        ? $this->t('dashboard.summary.maintenance_due_hint_first', ['%vehicle%' => $vehicleLabels[$reminders[0]->vehicleId()] ?? $this->t('common.unknown_vehicle')])
                        : $this->t('dashboard.summary.maintenance_due_hint_empty'),
                ],
                [
                    'label' => $this->t('dashboard.summary.receipts_this_month'),
                    'value' => $receiptsThisMonthCount,
                    'meta' => $this->t('dashboard.summary.receipts_this_month_meta', ['%date%' => $monthStart->format('d/m/Y')]),
                    'url' => $this->generateUrl('ui_receipt_index', ['issued_from' => $monthStart->format('Y-m-d')]),
                    'hint' => 0 === $receiptsThisMonthCount ? $this->t('dashboard.summary.receipts_this_month_hint_empty') : $this->t('dashboard.summary.receipts_this_month_hint_ready'),
                ],
            ],
            'attentionItems' => $attentionItems,
            'recentReceipts' => $this->buildRecentReceiptCards($recentReceipts),
            'recentImports' => $this->buildRecentImportCards(array_slice($imports, 0, 5)),
            'vehicleCount' => count($vehicles),
            'quickActions' => [
                ['label' => $this->t('receipt.form.action_create'), 'url' => $this->generateUrl('ui_receipt_new'), 'variant' => 'primary'],
                ['label' => $this->t('import.index.upload_files'), 'url' => $this->generateUrl('ui_import_index').'#import-upload-card', 'variant' => 'secondary'],
                ['label' => $this->t('analytics.open_maintenance'), 'url' => $this->generateUrl('ui_maintenance_index'), 'variant' => 'secondary'],
                ['label' => $this->t('dashboard.open_analytics'), 'url' => $this->generateUrl('ui_analytics_dashboard'), 'variant' => 'secondary'],
            ],
            'workflowCards' => [
                [
                    'title' => $this->t('dashboard.workflow.imports'),
                    'detail' => $this->t('dashboard.workflow.imports_detail', ['%review%' => (string) count($needsReviewImports), '%failed%' => (string) count($failedImports), '%resolved%' => (string) max(0, count($imports) - count($needsReviewImports) - count($failedImports))]),
                    'actions' => [
                        ['label' => 0 === count($needsReviewImports) ? $this->t('import.index.open_imports') : $this->t('dashboard.open_follow_up_now'), 'url' => $this->generateUrl('ui_import_index', 0 === count($needsReviewImports) ? [] : ['status' => ImportJobStatus::NEEDS_REVIEW->value]), 'variant' => 'secondary'],
                        ['label' => $this->t('import.index.upload_files'), 'url' => $this->generateUrl('ui_import_index').'#import-upload-card', 'variant' => 'secondary'],
                    ],
                ],
                [
                    'title' => $this->t('dashboard.workflow.maintenance'),
                    'detail' => $this->t('dashboard.workflow.maintenance_detail', [
                        '%reminders%' => (string) count($reminders),
                        '%reminders_suffix%' => 1 === count($reminders) ? '' : 's',
                        '%reminders_plural%' => 1 === count($reminders) ? '' : 's',
                        '%plans%' => (string) count($upcomingPlans),
                        '%plans_suffix%' => 1 === count($upcomingPlans) ? '' : 's',
                    ]),
                    'actions' => [
                        ['label' => 0 === count($reminders) ? $this->t('analytics.open_maintenance') : $this->t('dashboard.handle_due_work'), 'url' => $this->generateUrl('ui_maintenance_index', isset($reminders[0]) ? ['vehicle_id' => $reminders[0]->vehicleId()] : []), 'variant' => 'secondary'],
                        ['label' => isset($upcomingPlans[0]) ? $this->t('dashboard.edit_next_plan') : $this->t('maintenance.plan_form.title_new'), 'url' => isset($upcomingPlans[0]) ? $this->generateUrl('ui_maintenance_plan_edit', ['id' => $upcomingPlans[0]->id()->toString(), 'return_to' => $this->generateUrl('ui_dashboard')]) : $this->generateUrl('ui_maintenance_plan_new', ['return_to' => $this->generateUrl('ui_dashboard')]), 'variant' => 'secondary'],
                    ],
                ],
                [
                    'title' => $this->t('dashboard.workflow.receipts'),
                    'detail' => 0 === $receiptsThisMonthCount
                        ? $this->t('dashboard.workflow.receipts_empty')
                        : $this->t('dashboard.workflow.receipts_detail', ['%count%' => (string) $receiptsThisMonthCount, '%suffix%' => 1 === $receiptsThisMonthCount ? '' : 's', '%date%' => $monthStart->format('d/m/Y')]),
                    'actions' => [
                        ['label' => $this->t('dashboard.open_month_view'), 'url' => $this->generateUrl('ui_receipt_index', ['issued_from' => $monthStart->format('Y-m-d')]), 'variant' => 'secondary'],
                        ['label' => $this->t('receipt.form.action_create'), 'url' => $this->generateUrl('ui_receipt_new'), 'variant' => 'primary'],
                    ],
                ],
                [
                    'title' => $this->t('dashboard.workflow.fleet'),
                    'detail' => $this->t('dashboard.workflow.fleet_detail', ['%count%' => (string) count($vehicles), '%suffix%' => 1 === count($vehicles) ? '' : 's']),
                    'actions' => [
                        ['label' => $this->t('dashboard.open_vehicles'), 'url' => $this->generateUrl('ui_vehicle_list'), 'variant' => 'secondary'],
                        ['label' => $this->t('dashboard.open_stations'), 'url' => $this->generateUrl('ui_station_list'), 'variant' => 'secondary'],
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
                    'label' => $this->t('receipt.action.open'),
                    'url' => $this->generateUrl('ui_receipt_show', ['id' => $row['id'], 'return_to' => $this->generateUrl('ui_dashboard')]),
                    'variant' => 'secondary',
                ],
                [
                    'label' => $this->t('receipt.action.edit_details'),
                    'url' => $this->generateUrl('ui_receipt_edit_metadata', ['id' => $row['id'], 'return_to' => $this->generateUrl('ui_dashboard')]),
                    'variant' => 'secondary',
                ],
            ];

            $receipt = $this->receiptRepository->getForSystem($row['id']);
            if ($receipt instanceof Receipt && null !== $receipt->vehicleId()) {
                $vehicleId = $receipt->vehicleId()->toString();
                $actions[] = [
                    'label' => $this->t('analytics.open_vehicle'),
                    'url' => $this->generateUrl('ui_vehicle_show', ['id' => $vehicleId]),
                    'variant' => 'secondary',
                ];
                $actions[] = [
                    'label' => $this->t('nav.analytics'),
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
                    $actions[] = ['label' => $this->t('import.follow_up.review_next'), 'url' => $detailUrl, 'variant' => 'primary'];
                    $actions[] = ['label' => $this->t('admin.dashboard.attention.open_queue'), 'url' => $this->generateUrl('ui_import_index', ['status' => ImportJobStatus::NEEDS_REVIEW->value]), 'variant' => 'secondary'];
                    break;
                case ImportJobStatus::FAILED:
                    $actions[] = ['label' => $this->t('import.follow_up.inspect_failure'), 'url' => $detailUrl, 'variant' => 'secondary'];
                    $actions[] = ['label' => $this->t('import.action.upload_replacement'), 'url' => $uploadUrl, 'variant' => 'primary'];
                    break;
                case ImportJobStatus::PROCESSED:
                    $receiptId = $this->readPayloadString($payload, 'finalizedReceiptId');
                    if (null !== $receiptId) {
                        $actions[] = ['label' => $this->t('receipt.action.open'), 'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId, 'return_to' => $returnTo]), 'variant' => 'primary'];
                    }
                    $actions[] = ['label' => $this->t('import.action.detail'), 'url' => $detailUrl, 'variant' => 'secondary'];
                    break;
                case ImportJobStatus::DUPLICATE:
                    $receiptId = $this->readPayloadString($payload, 'duplicateOfReceiptId');
                    if (null !== $receiptId) {
                        $actions[] = ['label' => $this->t('import.action.open_existing_receipt'), 'url' => $this->generateUrl('ui_receipt_show', ['id' => $receiptId, 'return_to' => $returnTo]), 'variant' => 'primary'];
                    }
                    $actions[] = ['label' => $this->t('import.action.upload_another_file'), 'url' => $uploadUrl, 'variant' => 'secondary'];
                    break;
                default:
                    $actions[] = ['label' => $this->t('import.action.detail'), 'url' => $detailUrl, 'variant' => 'secondary'];
                    $actions[] = ['label' => $this->t('admin.dashboard.attention.open_queue'), 'url' => $this->generateUrl('ui_import_index'), 'variant' => 'secondary'];
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

    /** @param array<string, string> $parameters */
    private function t(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}
