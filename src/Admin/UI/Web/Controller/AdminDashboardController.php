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

use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly MaintenanceReminderRepository $maintenanceReminderRepository,
        private readonly ImportJobRepository $importJobRepository,
        private readonly ReceiptRepository $receiptRepository,
    ) {
    }

    #[Route('/ui/admin', name: 'ui_admin_dashboard', methods: ['GET'])]
    #[Route('/ui/admin/dashboard', name: 'ui_admin_dashboard_alias', methods: ['GET'])]
    public function __invoke(): Response
    {
        $stationNames = [];
        foreach ($this->stationRepository->allForSystem() as $station) {
            $stationNames[$station->id()->toString()] = $station->name();
        }

        $vehicleNames = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            $vehicleNames[$vehicle->id()->toString()] = $vehicle->name();
        }

        $importMetrics = [
            ImportJobStatus::QUEUED->value => 0,
            ImportJobStatus::PROCESSING->value => 0,
            ImportJobStatus::NEEDS_REVIEW->value => 0,
            ImportJobStatus::FAILED->value => 0,
            ImportJobStatus::PROCESSED->value => 0,
            ImportJobStatus::DUPLICATE->value => 0,
        ];
        $recentImports = [];

        foreach ($this->importJobRepository->allForSystem() as $job) {
            ++$importMetrics[$job->status()->value];

            if (\count($recentImports) < 5) {
                $recentImports[] = [
                    'id' => $job->id()->toString(),
                    'status' => $job->status()->value,
                    'statusLabel' => $this->dashboardImportActionLabel($job->status()),
                    'file' => $job->originalFilename(),
                    'ownerId' => $job->ownerId(),
                    'createdAt' => $job->createdAt(),
                ];
            }
        }

        $receiptCount = 0;
        $recentReceipts = [];
        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            ++$receiptCount;
            $recentReceipts[] = [
                'id' => $receipt->id()->toString(),
                'issuedAt' => $receipt->issuedAt(),
                'totalCents' => $receipt->totalCents(),
                'vehicleId' => $receipt->vehicleId()?->toString(),
                'stationId' => $receipt->stationId()?->toString(),
            ];
        }

        usort(
            $recentReceipts,
            static fn (array $left, array $right): int => $right['issuedAt'] <=> $left['issuedAt'],
        );
        $recentReceipts = array_slice($recentReceipts, 0, 5);

        $maintenanceEventCount = 0;
        foreach ($this->maintenanceEventRepository->allForSystem() as $_) {
            ++$maintenanceEventCount;
        }

        $maintenanceReminderCount = 0;
        $dueReminderCount = 0;
        foreach ($this->maintenanceReminderRepository->allForSystem() as $reminder) {
            ++$maintenanceReminderCount;

            if ($reminder->dueByDate() || $reminder->dueByOdometer()) {
                ++$dueReminderCount;
            }
        }

        $attentionCards = [];
        if ($importMetrics[ImportJobStatus::FAILED->value] > 0) {
            $attentionCards[] = [
                'title' => 'Failed imports',
                'value' => $importMetrics[ImportJobStatus::FAILED->value],
                'description' => 'Investigate OCR or parsing failures before they pile up.',
                'url' => $this->generateUrl('ui_admin_import_job_list', ['status' => ImportJobStatus::FAILED->value]),
                'action' => 'Inspect failures',
                'statusClass' => 'failed',
            ];
        }

        if ($importMetrics[ImportJobStatus::NEEDS_REVIEW->value] > 0) {
            $attentionCards[] = [
                'title' => 'Needs review',
                'value' => $importMetrics[ImportJobStatus::NEEDS_REVIEW->value],
                'description' => 'Manual review is still blocking import completion.',
                'url' => $this->generateUrl('ui_admin_import_job_list', ['status' => ImportJobStatus::NEEDS_REVIEW->value]),
                'action' => 'Review imports',
                'statusClass' => 'needs_review',
            ];
        }

        if ($dueReminderCount > 0) {
            $attentionCards[] = [
                'title' => 'Due reminders',
                'value' => $dueReminderCount,
                'description' => 'Maintenance follow-up is already due for at least one vehicle.',
                'url' => $this->generateUrl('ui_admin_maintenance_reminder_list'),
                'action' => 'Open reminders',
                'statusClass' => 'duplicate',
            ];
        }

        return $this->render('admin/dashboard.html.twig', [
            'stationCount' => \count($stationNames),
            'vehicleCount' => \count($vehicleNames),
            'maintenanceEventCount' => $maintenanceEventCount,
            'maintenanceReminderCount' => $maintenanceReminderCount,
            'receiptCount' => $receiptCount,
            'importMetrics' => $importMetrics,
            'dueReminderCount' => $dueReminderCount,
            'attentionCards' => $attentionCards,
            'recentImports' => $recentImports,
            'recentReceipts' => $recentReceipts,
            'vehicleNames' => $vehicleNames,
            'stationNames' => $stationNames,
        ]);
    }

    private function dashboardImportActionLabel(ImportJobStatus $status): string
    {
        return match ($status) {
            ImportJobStatus::NEEDS_REVIEW => 'Review',
            ImportJobStatus::FAILED => 'Inspect failure',
            ImportJobStatus::PROCESSED => 'Open receipt',
            ImportJobStatus::DUPLICATE => 'Open original',
            default => 'Detail',
        };
    }
}
