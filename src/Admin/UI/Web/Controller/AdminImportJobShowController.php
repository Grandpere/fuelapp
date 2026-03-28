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

final class AdminImportJobShowController extends AbstractController
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly ReceiptRepository $receiptRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly StationRepository $stationRepository,
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

        return $this->render('admin/imports/show.html.twig', [
            'job' => $job,
            'payload' => $job->errorPayload(),
            'payloadData' => $payloadData,
            'triageSummary' => $this->buildTriageSummary($job, $payloadData),
            'triageReadout' => $this->buildTriageReadout($job, $payloadData),
            'backToListUrl' => $backToListUrl,
            'resolvedFinalizedReceiptId' => $resolvedFinalizedReceiptId,
            'resolvedDuplicateReceiptId' => $resolvedDuplicateReceiptId,
            'resolvedDuplicateOriginalImportId' => $resolvedDuplicateOriginalImportId,
            'receiptContinuity' => $this->buildReceiptContinuity(
                $resolvedFinalizedReceiptId ?? $resolvedDuplicateReceiptId,
                $currentImportUrl,
            ),
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
            'Lifecycle status' => $job->status()->value,
            'OCR retry count' => (string) $job->ocrRetryCount(),
        ];

        $queueWait = $this->formatDuration($job->createdAt(), $job->startedAt());
        if (null !== $queueWait) {
            $summary['Queue wait'] = $queueWait;
        }

        $processingTime = $this->formatDuration($job->startedAt(), $job->completedAt() ?? $job->failedAt());
        if (null !== $processingTime) {
            $summary['Processing time'] = $processingTime;
        }

        if (null === $payloadData) {
            $rawPayload = $job->errorPayload();
            if (is_string($rawPayload) && '' !== trim($rawPayload)) {
                $summary['Terminal detail'] = trim($rawPayload);
            }

            return $summary;
        }

        $fingerprint = $this->readString($payloadData, 'fingerprint');
        if (null !== $fingerprint) {
            $summary['Fingerprint'] = $fingerprint;
        }

        $provider = $this->readString($payloadData, 'provider');
        if (null !== $provider) {
            $summary['OCR provider'] = $provider;
        }

        $retryCount = $this->readScalar($payloadData['retryCount'] ?? null);
        if (null !== $retryCount) {
            $summary['Retry count at terminal state'] = $retryCount;
        }

        $fallbackReason = $this->readString($payloadData, 'fallbackReason');
        if (null !== $fallbackReason) {
            $summary['Fallback reason'] = $fallbackReason;
        }

        $fallbackStrategy = $this->readString($payloadData, 'fallbackStrategy');
        if (null !== $fallbackStrategy) {
            $summary['Fallback strategy'] = $fallbackStrategy;
        }

        $duplicateOfReceiptId = $this->readString($payloadData, 'duplicateOfReceiptId');
        if (null !== $duplicateOfReceiptId) {
            $summary['Duplicate target'] = sprintf('receipt %s', $duplicateOfReceiptId);
        }

        $duplicateOfImportJobId = $this->readString($payloadData, 'duplicateOfImportJobId');
        if (null !== $duplicateOfImportJobId) {
            $summary['Duplicate import job'] = $duplicateOfImportJobId;
        }

        $finalizedReceiptId = $this->readString($payloadData, 'finalizedReceiptId');
        if (null !== $finalizedReceiptId) {
            $summary['Finalized receipt'] = $finalizedReceiptId;
        }

        $reason = $this->readString($payloadData, 'reason');
        if (null !== $reason) {
            $summary['Duplicate reason'] = $reason;
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
                    $summary['Detected issues'] = (string) $issueCount;
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
                'probableCause' => $fallbackReason ?? $reason ?? 'OCR or parsing left the import in manual review.',
                'nextAction' => 'Review the parsed payload and finalize the receipt once the missing fields look trustworthy.',
                'operatorNote' => null !== $fallbackStrategy
                    ? sprintf('Current fallback strategy: %s.', str_replace('_', ' ', $fallbackStrategy))
                    : 'Manual review remains the next path from this state.',
            ],
            'failed' => [
                'probableCause' => is_string($rawPayload) && '' !== trim($rawPayload)
                    ? trim($rawPayload)
                    : 'The import failed without a decoded payload.',
                'nextAction' => 'Inspect the failure, then retry only if the underlying provider or input issue has been addressed.',
                'operatorNote' => $job->ocrRetryCount() > 0
                    ? sprintf('This job already consumed %d OCR retries.', $job->ocrRetryCount())
                    : null,
            ],
            'duplicate' => [
                'probableCause' => $reason ?? 'The import matched an existing receipt or a previously uploaded import.',
                'nextAction' => 'Open the linked receipt or original import to confirm the duplicate before taking further action.',
                'operatorNote' => null,
            ],
            'processed' => [
                'probableCause' => 'The import already created a receipt successfully.',
                'nextAction' => 'Open the created receipt if support needs to continue on the resulting business record.',
                'operatorNote' => null,
            ],
            default => [
                'probableCause' => sprintf('The import is currently in %s state.', $status),
                'nextAction' => 'Open the current queue or detail flow and continue with the state-specific next step.',
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
                'label' => 'Open created receipt',
                'url' => $this->generateUrl('ui_admin_receipt_show', ['id' => $resolvedFinalizedReceiptId]),
                'variant' => 'primary',
            ];
        }

        if (null !== $resolvedDuplicateReceiptId) {
            $actions[] = [
                'label' => 'Open existing receipt',
                'url' => $this->generateUrl('ui_admin_receipt_show', ['id' => $resolvedDuplicateReceiptId]),
                'variant' => 'primary',
            ];
        }

        if (null !== $resolvedDuplicateOriginalImportId) {
            $actions[] = [
                'label' => 'Open original import',
                'url' => $this->generateUrl('ui_admin_import_job_show', ['id' => $resolvedDuplicateOriginalImportId, 'return_to' => $backToListUrl]),
                'variant' => 'secondary',
            ];
        }

        if ('processed' === $job->status()->value && [] === $actions) {
            $actions[] = [
                'label' => 'Back to imports',
                'url' => $backToListUrl,
                'variant' => 'secondary',
            ];
        }

        if ('duplicate' === $job->status()->value && [] === $actions) {
            $actions[] = [
                'label' => 'Back to imports',
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
            'label' => 'Open receipt',
            'url' => $this->generateUrl('ui_admin_receipt_show', ['id' => $receipt->id()->toString(), 'return_to' => $returnTo]),
            'variant' => 'primary',
        ]];

        if (null !== $receipt->vehicleId()) {
            $vehicle = $this->vehicleRepository->get($receipt->vehicleId()->toString());
            if (null !== $vehicle) {
                $actions[] = [
                    'label' => 'Open vehicle',
                    'url' => $this->generateUrl('ui_admin_vehicle_show', ['id' => $vehicle->id()->toString(), 'return_to' => $returnTo]),
                    'variant' => 'secondary',
                ];
            }
        }

        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->getForSystem($receipt->stationId()->toString());
            if (null !== $station) {
                $actions[] = [
                    'label' => 'Open station',
                    'url' => $this->generateUrl('ui_admin_station_show', ['id' => $station->id()->toString(), 'return_to' => $returnTo]),
                    'variant' => 'secondary',
                ];
            }
        }

        return $actions;
    }

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
