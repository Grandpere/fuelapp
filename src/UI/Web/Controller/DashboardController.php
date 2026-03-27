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
                    ['label' => 'Open maintenance', 'url' => $this->generateUrl('ui_maintenance_index', ['vehicle_id' => $reminder->vehicleId()]), 'variant' => 'primary'],
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
                ],
                [
                    'label' => 'Receipts this month',
                    'value' => $receiptsThisMonthCount,
                    'meta' => sprintf('Since %s', $monthStart->format('d/m/Y')),
                    'url' => $this->generateUrl('ui_receipt_index', ['issued_from' => $monthStart->format('Y-m-d')]),
                ],
            ],
            'attentionItems' => $attentionItems,
            'recentReceipts' => $recentReceipts,
            'recentImports' => array_slice($imports, 0, 5),
            'vehicleCount' => count($vehicles),
            'quickActions' => [
                ['label' => 'Add receipt', 'url' => $this->generateUrl('ui_receipt_new'), 'variant' => 'primary', 'turboFrame' => 'receipt_form_frame'],
                ['label' => 'Upload files', 'url' => $this->generateUrl('ui_import_index').'#import-upload-card', 'variant' => 'secondary'],
                ['label' => 'Open maintenance', 'url' => $this->generateUrl('ui_maintenance_index'), 'variant' => 'secondary'],
                ['label' => 'Open analytics', 'url' => $this->generateUrl('ui_analytics_dashboard'), 'variant' => 'secondary'],
            ],
        ]);
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
