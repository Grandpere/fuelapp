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
use App\Import\Domain\ImportJob;
use App\Receipt\Application\Repository\ReceiptRepository;
use App\Station\Application\Repository\StationRepository;
use App\Vehicle\Application\Repository\VehicleRepository;
use DateTimeImmutable;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminImportJobShowController extends AbstractController
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly StationRepository $stationRepository,
        private readonly AdminUserManager $userManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/ui/admin/imports/{id}', name: 'ui_admin_import_job_show', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['GET'])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $job = $this->importJobRepository->getForSystem($id);
        if (null === $job) {
            throw new NotFoundHttpException();
        }

        $payloadData = $this->decodePayload($job->errorPayload());
        $requestedReturnTo = $request->query->get('return_to');
        $backToListUrl = is_string($requestedReturnTo) && '' !== trim($requestedReturnTo) && str_starts_with($requestedReturnTo, '/') && !str_starts_with($requestedReturnTo, '//')
            ? $requestedReturnTo
            : $this->generateUrl('ui_admin_import_job_list');
        $resolvedFinalizedReceiptId = $this->resolveExistingReceiptId($this->readString($payloadData, 'finalizedReceiptId'));
        $resolvedDuplicateReceiptId = $this->resolveExistingReceiptId($this->readString($payloadData, 'duplicateOfReceiptId'));
        $resolvedDuplicateOriginalImportId = $this->resolveExistingImportId($this->readString($payloadData, 'duplicateOfImportJobId'));
        $currentImportUrl = $this->generateUrl('ui_admin_import_job_show', ['id' => $job->id()->toString(), 'return_to' => $backToListUrl]);
        $owner = $this->userManager->getUser($job->ownerId());
        $requestCorrelationId = $request->attributes->get('_correlation_id');

        return $this->render('admin/imports/show.html.twig', [
            'job' => $job,
            'payload' => $job->errorPayload(),
            'payloadData' => $payloadData,
            'triageSummary' => $this->buildTriageSummary($job, $payloadData),
            'triageReadout' => $this->buildTriageReadout($job, $payloadData),
            'backToListUrl' => $backToListUrl,
            'currentImportUrl' => $currentImportUrl,
            'resolvedFinalizedReceiptId' => $resolvedFinalizedReceiptId,
            'resolvedDuplicateReceiptId' => $resolvedDuplicateReceiptId,
            'resolvedDuplicateOriginalImportId' => $resolvedDuplicateOriginalImportId,
            'receiptContinuity' => $this->buildReceiptContinuity(
                $resolvedFinalizedReceiptId ?? $resolvedDuplicateReceiptId,
                $currentImportUrl,
            ),
            'ownerLabel' => null !== $owner ? sprintf('%s (%s)', $owner->email, $job->ownerId()) : $job->ownerId(),
            'requestCorrelationId' => is_string($requestCorrelationId) && '' !== trim($requestCorrelationId) ? trim($requestCorrelationId) : null,
            'investigationShortcuts' => $this->buildInvestigationShortcuts($job->ownerId()),
            'statusActions' => $this->buildStatusActions(
                $job,
                $backToListUrl,
                $resolvedFinalizedReceiptId,
                $resolvedDuplicateReceiptId,
                $resolvedDuplicateOriginalImportId,
            ),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function decodePayload(?string $payload): ?array
    {
        if (null === $payload || '' === trim($payload)) {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed>|null $payloadData
     *
     * @return array<string, string>
     */
    private function buildTriageSummary(ImportJob $job, ?array $payloadData): array
    {
        $summary = [
            $this->t('admin.import.show.triage.lifecycle_status') => $this->t('import.status.'.$job->status()->value),
            $this->t('admin.import.show.triage.ocr_retry_count') => (string) $job->ocrRetryCount(),
        ];

        $queueWait = $this->formatDuration($job->createdAt(), $job->startedAt());
        if (null !== $queueWait) {
            $summary[$this->t('admin.import.show.triage.queue_wait')] = $queueWait;
        }

        $processingTime = $this->formatDuration($job->startedAt(), $job->completedAt() ?? $job->failedAt());
        if (null !== $processingTime) {
            $summary[$this->t('admin.import.show.triage.processing_time')] = $processingTime;
        }

        if (null === $payloadData) {
            $rawPayload = $job->errorPayload();
            if (is_string($rawPayload) && '' !== trim($rawPayload)) {
                $summary[$this->t('admin.import.show.triage.terminal_detail')] = trim($rawPayload);
            }

            return $summary;
        }

        $fingerprint = $this->readString($payloadData, 'fingerprint');
        if (null !== $fingerprint) {
            $summary[$this->t('admin.import.show.triage.fingerprint')] = $fingerprint;
        }

        $provider = $this->readString($payloadData, 'provider');
        if (null !== $provider) {
            $summary[$this->t('admin.import.show.triage.ocr_provider')] = $provider;
        }

        $retryCount = $this->readScalar($payloadData['retryCount'] ?? null);
        if (null !== $retryCount) {
            $summary[$this->t('admin.import.show.triage.retry_count_terminal')] = $retryCount;
        }

        $fallbackReason = $this->readString($payloadData, 'fallbackReason');
        if (null !== $fallbackReason) {
            $summary[$this->t('admin.import.show.triage.fallback_reason')] = $fallbackReason;
        }

        $fallbackStrategy = $this->readString($payloadData, 'fallbackStrategy');
        if (null !== $fallbackStrategy) {
            $summary[$this->t('admin.import.show.triage.fallback_strategy')] = $fallbackStrategy;
        }

        $duplicateOfReceiptId = $this->readString($payloadData, 'duplicateOfReceiptId');
        if (null !== $duplicateOfReceiptId) {
            $summary[$this->t('admin.import.show.triage.duplicate_target')] = $this->t('admin.import.show.triage.duplicate_target_receipt', ['%id%' => $duplicateOfReceiptId]);
        }

        $duplicateOfImportJobId = $this->readString($payloadData, 'duplicateOfImportJobId');
        if (null !== $duplicateOfImportJobId) {
            $summary[$this->t('admin.import.show.triage.duplicate_import_job')] = $duplicateOfImportJobId;
        }

        $finalizedReceiptId = $this->readString($payloadData, 'finalizedReceiptId');
        if (null !== $finalizedReceiptId) {
            $summary[$this->t('admin.import.show.triage.finalized_receipt')] = $finalizedReceiptId;
        }

        $reason = $this->readString($payloadData, 'reason');
        if (null !== $reason) {
            $summary[$this->t('admin.import.show.triage.duplicate_reason')] = $reason;
        }

        $parsedDraft = $payloadData['parsedDraft'] ?? null;
        if (is_array($parsedDraft)) {
            $issues = $parsedDraft['issues'] ?? null;
            if (is_array($issues)) {
                $issueCount = 0;
                foreach ($issues as $issue) {
                    if (is_string($issue) && '' !== trim($issue)) {
                        ++$issueCount;
                    }
                }

                if ($issueCount > 0) {
                    $summary[$this->t('admin.import.show.triage.detected_issues')] = (string) $issueCount;
                }
            }
        }

        return $summary;
    }

    private function formatDuration(?DateTimeImmutable $from, ?DateTimeImmutable $to): ?string
    {
        if (null === $from || null === $to) {
            return null;
        }

        $seconds = max(0, $to->getTimestamp() - $from->getTimestamp());
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $remainingSeconds);
        }

        return sprintf('%ds', $remainingSeconds);
    }

    /**
     * @param array<string, mixed>|null $payloadData
     *
     * @return array{probableCause:string,nextAction:string,operatorNote:?string}
     */
    private function buildTriageReadout(ImportJob $job, ?array $payloadData): array
    {
        $status = $job->status()->value;
        $fallbackReason = $this->readString($payloadData, 'fallbackReason');
        $fallbackStrategy = $this->readString($payloadData, 'fallbackStrategy');
        $reason = $this->readString($payloadData, 'reason');
        $rawPayload = $job->errorPayload();

        return match ($status) {
            'needs_review' => [
                'probableCause' => $fallbackReason ?? $reason ?? $this->t('admin.import.show.triage.probable_needs_review_default'),
                'nextAction' => $this->t('admin.import.show.triage.next_action_needs_review'),
                'operatorNote' => null !== $fallbackStrategy
                    ? $this->t('admin.import.show.triage.note_fallback_strategy', ['%strategy%' => str_replace('_', ' ', $fallbackStrategy)])
                    : $this->t('admin.import.show.triage.note_manual_review'),
            ],
            'failed' => [
                'probableCause' => is_string($rawPayload) && '' !== trim($rawPayload)
                    ? trim($rawPayload)
                    : $this->t('admin.import.show.triage.probable_failed_default'),
                'nextAction' => $this->t('admin.import.show.triage.next_action_failed'),
                'operatorNote' => $job->ocrRetryCount() > 0
                    ? $this->t('admin.import.show.triage.note_existing_retries', ['%count%' => $job->ocrRetryCount()])
                    : null,
            ],
            'duplicate' => [
                'probableCause' => $reason ?? $this->t('admin.import.show.triage.probable_duplicate_default'),
                'nextAction' => $this->t('admin.import.show.triage.next_action_duplicate'),
                'operatorNote' => null,
            ],
            'processed' => [
                'probableCause' => $this->t('admin.import.show.triage.probable_processed'),
                'nextAction' => $this->t('admin.import.show.triage.next_action_processed'),
                'operatorNote' => null,
            ],
            default => [
                'probableCause' => $this->t('admin.import.show.triage.probable_default', ['%status%' => $this->t('import.status.'.$status)]),
                'nextAction' => $this->t('admin.import.show.triage.next_action_default'),
                'operatorNote' => null,
            ],
        };
    }

    /** @param array<string, mixed>|null $payloadData */
    private function readString(?array $payloadData, string $key): ?string
    {
        if (null === $payloadData) {
            return null;
        }

        $value = $payloadData[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function readScalar(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return '' === $trimmed ? null : $trimmed;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @return list<array{label:string,url:string,variant:string}>
     */
    private function buildStatusActions(
        ImportJob $job,
        string $backToListUrl,
        ?string $resolvedFinalizedReceiptId,
        ?string $resolvedDuplicateReceiptId,
        ?string $resolvedDuplicateOriginalImportId,
    ): array {
        $actions = [];

        if (null !== $resolvedFinalizedReceiptId) {
            $actions[] = [
                'label' => $this->t('import.action.open_created_receipt'),
                'url' => $this->generateUrl('ui_admin_receipt_show', ['id' => $resolvedFinalizedReceiptId]),
                'variant' => 'primary',
            ];
        }

        if (null !== $resolvedDuplicateReceiptId) {
            $actions[] = [
                'label' => $this->t('import.action.open_existing_receipt'),
                'url' => $this->generateUrl('ui_admin_receipt_show', ['id' => $resolvedDuplicateReceiptId]),
                'variant' => 'primary',
            ];
        }

        if (null !== $resolvedDuplicateOriginalImportId) {
            $actions[] = [
                'label' => $this->t('import.action.open_original_import'),
                'url' => $this->generateUrl('ui_admin_import_job_show', ['id' => $resolvedDuplicateOriginalImportId, 'return_to' => $backToListUrl]),
                'variant' => 'secondary',
            ];
        }

        if ('processed' === $job->status()->value && [] === $actions) {
            $actions[] = [
                'label' => $this->t('import.action.back_to_imports'),
                'url' => $backToListUrl,
                'variant' => 'secondary',
            ];
        }

        if ('duplicate' === $job->status()->value && [] === $actions) {
            $actions[] = [
                'label' => $this->t('import.action.back_to_imports'),
                'url' => $backToListUrl,
                'variant' => 'secondary',
            ];
        }

        return $actions;
    }

    private function resolveExistingReceiptId(?string $receiptId): ?string
    {
        if (null === $receiptId) {
            return null;
        }

        return null === $this->receiptRepository->getForSystem($receiptId) ? null : $receiptId;
    }

    private function resolveExistingImportId(?string $importId): ?string
    {
        if (null === $importId) {
            return null;
        }

        return null === $this->importJobRepository->getForSystem($importId) ? null : $importId;
    }

    /**
     * @return list<array{label:string,url:string,variant:string}>
     */
    private function buildReceiptContinuity(?string $receiptId, string $returnTo): array
    {
        if (null === $receiptId) {
            return [];
        }

        $receipt = $this->receiptRepository->getForSystem($receiptId);
        if (null === $receipt) {
            return [];
        }

        $actions = [[
            'label' => $this->t('import.action.open_receipt'),
            'url' => $this->generateUrl('ui_admin_receipt_show', ['id' => $receipt->id()->toString(), 'return_to' => $returnTo]),
            'variant' => 'primary',
        ]];

        if (null !== $receipt->vehicleId()) {
            $vehicle = $this->vehicleRepository->get($receipt->vehicleId()->toString());
            if (null !== $vehicle) {
                $actions[] = [
                    'label' => $this->t('import.show.continuity.open_vehicle'),
                    'url' => $this->generateUrl('ui_admin_vehicle_show', ['id' => $vehicle->id()->toString(), 'return_to' => $returnTo]),
                    'variant' => 'secondary',
                ];
            }
        }

        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->getForSystem($receipt->stationId()->toString());
            if (null !== $station) {
                $actions[] = [
                    'label' => $this->t('import.show.continuity.open_station'),
                    'url' => $this->generateUrl('ui_admin_station_show', ['id' => $station->id()->toString(), 'return_to' => $returnTo]),
                    'variant' => 'secondary',
                ];
            }
        }

        return $actions;
    }

    /**
     * @return list<array{label:string,url:string}>
     */
    private function buildInvestigationShortcuts(string $ownerId): array
    {
        $owner = $this->userManager->getUser($ownerId);
        if (null === $owner) {
            return [];
        }

        return [
            [
                'label' => $this->t('admin.import.show.owner_user'),
                'url' => $this->generateUrl('ui_admin_user_list', ['q' => $owner->email]),
            ],
            [
                'label' => $this->t('admin.import.show.owner_identities'),
                'url' => $this->generateUrl('ui_admin_identity_list', ['user_id' => $ownerId]),
            ],
            [
                'label' => $this->t('admin.import.show.owner_security'),
                'url' => $this->generateUrl('ui_admin_security_activity_list', ['actorId' => $ownerId]),
            ],
            [
                'label' => $this->t('admin.import.show.owner_audit'),
                'url' => $this->generateUrl('ui_admin_audit_log_list', ['actorId' => $ownerId]),
            ],
        ];
    }

    /** @param array<string, scalar> $parameters */
    private function t(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
