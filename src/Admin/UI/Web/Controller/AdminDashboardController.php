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
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Domain\MaintenanceReminder;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Receipt\Domain\Receipt;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly MaintenanceEventRepository $maintenanceEventRepository,
        private readonly MaintenanceReminderRepository $maintenanceReminderRepository,
        private readonly ImportJobRepository $importJobRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly AdminUserManager $userManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/ui/admin', name: 'ui_admin_dashboard', methods: ['GET'])]
    #[Route('/ui/admin/dashboard', name: 'ui_admin_dashboard_alias', methods: ['GET'])]
    public function __invoke(): Response
    {
        $dashboardUrl = $this->generateUrl('ui_admin_dashboard');
        $stationNames = [];
        $stationRows = [];
        $pendingStationCount = 0;
        foreach ($this->stationRepository->allForSystem() as $station) {
            $stationNames[$station->id()->toString()] = $station->name();
            $stationRows[] = $station;
            if ('success' !== $station->geocodingStatus()->value) {
                ++$pendingStationCount;
            }
        }

        $vehicleNames = [];
        $vehicles = [];
        foreach ($this->vehicleRepository->all() as $vehicle) {
            $vehicleNames[$vehicle->id()->toString()] = $vehicle->name();
            $vehicles[] = $vehicle;
        }

        $importMetrics = [
            ImportJobStatus::QUEUED->value => 0,
            ImportJobStatus::PROCESSING->value => 0,
            ImportJobStatus::NEEDS_REVIEW->value => 0,
            ImportJobStatus::FAILED->value => 0,
            ImportJobStatus::PROCESSED->value => 0,
            ImportJobStatus::DUPLICATE->value => 0,
        ];
        $allImports = [];

        foreach ($this->importJobRepository->allForSystem() as $job) {
            ++$importMetrics[$job->status()->value];
            $allImports[] = $job;
        }

        usort(
            $allImports,
            static fn (ImportJob $left, ImportJob $right): int => $right->createdAt() <=> $left->createdAt(),
        );

        $recentImports = [];
        foreach (array_slice($allImports, 0, 5) as $job) {
            $recentImports[] = $this->buildRecentImportRow($job, $dashboardUrl);
        }

        $receiptCount = 0;
        $allReceipts = [];
        $receiptVehicleIds = [];
        $receiptStationIds = [];
        foreach ($this->receiptRepository->allForSystem() as $receipt) {
            ++$receiptCount;
            $allReceipts[] = $receipt;
            if (null !== $receipt->vehicleId()) {
                $receiptVehicleIds[$receipt->vehicleId()->toString()] = true;
            }
            if (null !== $receipt->stationId()) {
                $receiptStationIds[$receipt->stationId()->toString()] = true;
            }
        }

        usort(
            $allReceipts,
            static fn (Receipt $left, Receipt $right): int => $right->issuedAt() <=> $left->issuedAt(),
        );
        $recentReceipts = [];
        foreach (array_slice($allReceipts, 0, 5) as $receipt) {
            $recentReceipts[] = $this->buildRecentReceiptRow($receipt, $dashboardUrl);
        }

        $maintenanceEventCount = 0;
        foreach ($this->maintenanceEventRepository->allForSystem() as $_) {
            ++$maintenanceEventCount;
        }

        $maintenanceReminderCount = 0;
        $dueReminderCount = 0;
        $dueReminders = [];
        $dueVehicleIds = [];
        foreach ($this->maintenanceReminderRepository->allForSystem() as $reminder) {
            ++$maintenanceReminderCount;

            if ($reminder->dueByDate() || $reminder->dueByOdometer()) {
                ++$dueReminderCount;
                $dueReminders[] = $reminder;
                $dueVehicleIds[$reminder->vehicleId()] = true;
            }
        }

        $vehiclesWithoutReceipts = 0;
        foreach ($vehicles as $vehicle) {
            if (!isset($receiptVehicleIds[$vehicle->id()->toString()])) {
                ++$vehiclesWithoutReceipts;
            }
        }

        $stationsWithoutReceipts = 0;
        foreach ($stationRows as $station) {
            if (!isset($receiptStationIds[$station->id()->toString()])) {
                ++$stationsWithoutReceipts;
            }
        }

        $userMetrics = [
            'missingIdentity' => 0,
            'unverified' => 0,
        ];
        $nextMissingIdentityUserId = null;
        $nextUnverifiedUserId = null;
        foreach ($this->userManager->listUsers() as $user) {
            if (0 === $user->identityCount) {
                ++$userMetrics['missingIdentity'];
                $nextMissingIdentityUserId ??= $user->id;
            }
            if (!$user->isEmailVerified()) {
                ++$userMetrics['unverified'];
                $nextUnverifiedUserId ??= $user->id;
            }
        }

        $attentionCards = [];
        $nextFailedJob = $this->firstImportByStatus($allImports, ImportJobStatus::FAILED);
        if ($importMetrics[ImportJobStatus::FAILED->value] > 0 && null !== $nextFailedJob) {
            $attentionCards[] = [
                'title' => $this->t('admin.dashboard.attention.failed_imports_title'),
                'value' => $importMetrics[ImportJobStatus::FAILED->value],
                'description' => $this->t('admin.dashboard.attention.failed_imports_description'),
                'url' => $this->generateUrl('ui_admin_import_job_show', ['id' => $nextFailedJob->id()->toString(), 'return_to' => $dashboardUrl]),
                'action' => $this->t('admin.dashboard.attention.failed_imports_action'),
                'secondaryUrl' => $this->generateUrl('ui_admin_import_job_list', ['status' => ImportJobStatus::FAILED->value]),
                'secondaryAction' => $this->t('admin.dashboard.attention.open_queue'),
                'statusClass' => 'failed',
            ];
        }

        $nextReviewJob = $this->firstImportByStatus($allImports, ImportJobStatus::NEEDS_REVIEW);
        if ($importMetrics[ImportJobStatus::NEEDS_REVIEW->value] > 0 && null !== $nextReviewJob) {
            $attentionCards[] = [
                'title' => $this->t('admin.dashboard.attention.needs_review_title'),
                'value' => $importMetrics[ImportJobStatus::NEEDS_REVIEW->value],
                'description' => $this->t('admin.dashboard.attention.needs_review_description'),
                'url' => $this->generateUrl('ui_admin_import_job_show', ['id' => $nextReviewJob->id()->toString(), 'return_to' => $dashboardUrl]),
                'action' => $this->t('admin.dashboard.attention.needs_review_action'),
                'secondaryUrl' => $this->generateUrl('ui_admin_import_job_list', ['status' => ImportJobStatus::NEEDS_REVIEW->value]),
                'secondaryAction' => $this->t('admin.dashboard.attention.open_queue'),
                'statusClass' => 'needs_review',
            ];
        }

        $nextDueReminder = $dueReminders[0] ?? null;
        if ($dueReminderCount > 0 && $nextDueReminder instanceof MaintenanceReminder) {
            $attentionCards[] = [
                'title' => $this->t('admin.dashboard.attention.due_reminders_title'),
                'value' => $dueReminderCount,
                'description' => $this->t('admin.dashboard.attention.due_reminders_description'),
                'url' => $this->generateUrl('ui_admin_maintenance_reminder_show', ['id' => $nextDueReminder->id()->toString(), 'return_to' => $dashboardUrl]),
                'action' => $this->t('admin.dashboard.attention.due_reminders_action'),
                'secondaryUrl' => $this->generateUrl('ui_admin_maintenance_reminder_list'),
                'secondaryAction' => $this->t('admin.dashboard.attention.open_queue'),
                'statusClass' => 'duplicate',
            ];
        }

        if ($userMetrics['missingIdentity'] > 0 && null !== $nextMissingIdentityUserId) {
            $attentionCards[] = [
                'title' => $this->t('admin.dashboard.attention.missing_identities_title'),
                'value' => $userMetrics['missingIdentity'],
                'description' => $this->t('admin.dashboard.attention.missing_identities_description'),
                'url' => $this->generateUrl('ui_admin_identity_list', ['user_id' => $nextMissingIdentityUserId]),
                'action' => $this->t('admin.dashboard.attention.missing_identities_action'),
                'secondaryUrl' => $this->generateUrl('ui_admin_user_list', ['has_identity' => '0']),
                'secondaryAction' => $this->t('admin.dashboard.attention.open_queue'),
                'statusClass' => 'queued',
            ];
        } elseif ($userMetrics['unverified'] > 0 && null !== $nextUnverifiedUserId) {
            $attentionCards[] = [
                'title' => $this->t('admin.dashboard.attention.unverified_accounts_title'),
                'value' => $userMetrics['unverified'],
                'description' => $this->t('admin.dashboard.attention.unverified_accounts_description'),
                'url' => $this->generateUrl('ui_admin_audit_log_list', ['actorId' => $nextUnverifiedUserId]),
                'action' => $this->t('admin.dashboard.attention.unverified_accounts_action'),
                'secondaryUrl' => $this->generateUrl('ui_admin_user_list', ['verification' => 'unverified']),
                'secondaryAction' => $this->t('admin.dashboard.attention.open_queue'),
                'statusClass' => 'processing',
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
            'supportSignals' => [
                'vehicleDueCount' => \count($dueVehicleIds),
                'vehiclesWithoutReceipts' => $vehiclesWithoutReceipts,
                'stationsWithoutReceipts' => $stationsWithoutReceipts,
                'pendingStationCount' => $pendingStationCount,
                'missingIdentityCount' => $userMetrics['missingIdentity'],
                'unverifiedUserCount' => $userMetrics['unverified'],
            ],
        ]);
    }

    private function dashboardImportActionLabel(ImportJobStatus $status): string
    {
        return match ($status) {
            ImportJobStatus::NEEDS_REVIEW => $this->t('import.action.review'),
            ImportJobStatus::FAILED => $this->t('import.follow_up.inspect_failure'),
            ImportJobStatus::PROCESSED => $this->t('import.action.open_receipt'),
            ImportJobStatus::DUPLICATE => $this->t('import.action.open_original'),
            default => $this->t('import.action.detail'),
        };
    }

    /**
     * @param list<ImportJob> $imports
     */
    private function firstImportByStatus(array $imports, ImportJobStatus $status): ?ImportJob
    {
        foreach ($imports as $job) {
            if ($job->status() === $status) {
                return $job;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   id:string,
     *   status:string,
     *   statusLabel:string,
     *   file:string,
     *   ownerId:string,
     *   ownerLabel:string,
     *   createdAt:DateTimeImmutable,
     *   primaryUrl:string,
     *   primaryLabel:string,
     *   secondaryUrl:string,
     *   secondaryLabel:string
     * }
     */
    private function buildRecentImportRow(ImportJob $job, string $returnTo): array
    {
        $primaryUrl = $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);
        $primaryLabel = $this->dashboardImportActionLabel($job->status());
        $secondaryUrl = $this->generateUrl('ui_admin_import_job_list', ['status' => $job->status()->value]);
        $secondaryLabel = $this->t('action.queue');
        $payload = $this->decodePayload($job);

        if (ImportJobStatus::PROCESSED === $job->status()) {
            $receiptId = $this->readPayloadString($payload, 'finalizedReceiptId');
            if (null !== $receiptId && null !== $this->receiptRepository->getForSystem($receiptId)) {
                $primaryUrl = $this->generateUrl('ui_admin_receipt_show', ['id' => $receiptId, 'return_to' => $returnTo]);
                $primaryLabel = $this->t('import.action.open_receipt');
                $secondaryUrl = $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);
                $secondaryLabel = $this->t('import.action.detail');
            }
        }

        if (ImportJobStatus::DUPLICATE === $job->status()) {
            $receiptId = $this->readPayloadString($payload, 'duplicateOfReceiptId');
            if (null !== $receiptId && null !== $this->receiptRepository->getForSystem($receiptId)) {
                $primaryUrl = $this->generateUrl('ui_admin_receipt_show', ['id' => $receiptId, 'return_to' => $returnTo]);
                $primaryLabel = $this->t('import.action.open_receipt');
                $secondaryUrl = $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);
                $secondaryLabel = $this->t('import.action.detail');
            } else {
                $originalImportId = $this->readPayloadString($payload, 'duplicateOfImportJobId');
                if (null !== $originalImportId) {
                    $primaryUrl = $this->generateUrl('ui_admin_import_job_show', ['id' => $originalImportId, 'return_to' => $returnTo]);
                    $primaryLabel = $this->t('import.action.open_original');
                    $secondaryUrl = $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);
                    $secondaryLabel = $this->t('import.action.detail');
                }
            }
        }

        return [
            'id' => $job->id()->toString(),
            'status' => $job->status()->value,
            'statusLabel' => $this->dashboardImportActionLabel($job->status()),
            'file' => $job->originalFilename(),
            'ownerId' => $job->ownerId(),
            'ownerLabel' => $this->buildOwnerLabel($job->ownerId()),
            'createdAt' => $job->createdAt(),
            'primaryUrl' => $primaryUrl,
            'primaryLabel' => $primaryLabel,
            'secondaryUrl' => $secondaryUrl,
            'secondaryLabel' => $secondaryLabel,
        ];
    }

    /**
     * @return array{
     *   id:string,
     *   issuedAt:DateTimeImmutable,
     *   totalCents:int,
     *   vehicleId:?string,
     *   stationId:?string,
     *   showUrl:string,
     *   editUrl:string,
     *   importUrl:?string
     * }
     */
    private function buildRecentReceiptRow(Receipt $receipt, string $returnTo): array
    {
        return [
            'id' => $receipt->id()->toString(),
            'issuedAt' => $receipt->issuedAt(),
            'totalCents' => $receipt->totalCents(),
            'vehicleId' => $receipt->vehicleId()?->toString(),
            'stationId' => $receipt->stationId()?->toString(),
            'showUrl' => $this->generateUrl('ui_admin_receipt_show', ['id' => $receipt->id()->toString(), 'return_to' => $returnTo]),
            'editUrl' => $this->generateUrl('ui_admin_receipt_edit', ['id' => $receipt->id()->toString(), 'return_to' => $returnTo]),
            'importUrl' => $this->findFirstRelatedImportUrl($receipt->id()->toString(), $returnTo),
        ];
    }

    private function findFirstRelatedImportUrl(string $receiptId, string $returnTo): ?string
    {
        foreach ($this->importJobRepository->allForSystem() as $job) {
            $payload = $this->decodePayload($job);
            $finalizedReceiptId = $this->readPayloadString($payload, 'finalizedReceiptId');
            $duplicateReceiptId = $this->readPayloadString($payload, 'duplicateOfReceiptId');
            if ($finalizedReceiptId !== $receiptId && $duplicateReceiptId !== $receiptId) {
                continue;
            }

            return $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $returnTo]);
        }

        return null;
    }

    private function buildOwnerLabel(string $ownerId): string
    {
        $owner = $this->userManager->getUser($ownerId);

        return null !== $owner ? sprintf('%s (%s)', $owner->email, $ownerId) : $ownerId;
    }

    /** @param array<string, scalar> $parameters */
    private function t(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodePayload(ImportJob $job): array
    {
        $payload = $job->errorPayload();
        if (null === $payload || '' === trim($payload)) {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function readPayloadString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? $value : null;
    }
}
