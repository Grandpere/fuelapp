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
use DateTimeImmutable;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AdminImportJobShowController extends AbstractController
{
    public function __construct(private readonly ImportJobRepository $importJobRepository)
    {
    }

    #[Route('/ui/admin/imports/{id}', name: 'ui_admin_import_job_show', requirements: ['id' => self::UUID_ROUTE_REQUIREMENT], methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $job = $this->importJobRepository->getForSystem($id);
        if (null === $job) {
            throw new NotFoundHttpException();
        }

        $payloadData = $this->decodePayload($job->errorPayload());

        return $this->render('admin/imports/show.html.twig', [
            'job' => $job,
            'payload' => $job->errorPayload(),
            'payloadData' => $payloadData,
            'triageSummary' => $this->buildTriageSummary($job, $payloadData),
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

    /** @param array<string, mixed> $payloadData */
    private function readString(array $payloadData, string $key): ?string
    {
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

    private const UUID_ROUTE_REQUIREMENT = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
}
