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

namespace App\Import\Application\MessageHandler;

use App\Import\Application\Message\ProcessImportJobMessage;
use App\Import\Application\Ocr\OcrProvider;
use App\Import\Application\Ocr\OcrProviderException;
use App\Import\Application\Parsing\ParsedReceiptDraft;
use App\Import\Application\Parsing\ReceiptOcrParser;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Application\Storage\ImportFileStorage;
use App\Import\Application\Storage\ImportStoredFileLocator;
use App\Import\Domain\Enum\ImportJobStatus;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Throwable;

#[AsMessageHandler]
final class ProcessImportJobMessageHandler
{
    private const FALLBACK_STRATEGY_MANUAL_REVIEW = 'manual_review';
    // Must stay aligned with messenger transport `async.retry_strategy.max_retries`.
    private const MAX_RETRYABLE_PROVIDER_ATTEMPTS = 3;

    /** @var list<int> */
    private const RETRYABLE_PROVIDER_DELAYS_MS = [15000, 60000, 180000, 600000, 900000];

    public function __construct(
        private ImportJobRepository $repository,
        private ImportFileStorage $fileStorage,
        private ImportStoredFileLocator $storedFileLocator,
        private OcrProvider $ocrProvider,
        private ReceiptOcrParser $receiptParser,
        private LoggerInterface $logger,
        private string $fallbackStrategy = self::FALLBACK_STRATEGY_MANUAL_REVIEW,
    ) {
    }

    public function __invoke(ProcessImportJobMessage $message): void
    {
        $job = $this->repository->getForSystem($message->importJobId);
        if (null === $job) {
            $this->logger->warning('import.job.skipped_not_found', ['import_job_id' => $message->importJobId]);

            return;
        }

        $this->logger->info('import.job.started', [
            'import_job_id' => $message->importJobId,
            'message' => ProcessImportJobMessage::class,
            'ocr_retry_count' => $job->ocrRetryCount(),
        ]);

        if (in_array($job->status(), [ImportJobStatus::PROCESSED, ImportJobStatus::NEEDS_REVIEW, ImportJobStatus::DUPLICATE], true)) {
            $this->logger->info('import.job.skipped_already_terminal', [
                'import_job_id' => $job->id()->toString(),
                'status' => $job->status()->value,
            ]);

            return;
        }

        $fingerprint = $this->buildFingerprintV1($job->fileChecksumSha256());
        $duplicateOf = $this->repository->findLatestByOwnerAndChecksum($job->ownerId(), $job->fileChecksumSha256(), $job->id()->toString());
        if (null !== $duplicateOf) {
            $job->markDuplicate($this->buildDuplicatePayload($job->id()->toString(), $duplicateOf->id()->toString(), $fingerprint));
            $this->repository->save($job);
            $this->fileStorage->delete($job->storage(), $job->filePath());

            $this->logger->info('import.job.marked_duplicate', [
                'import_job_id' => $job->id()->toString(),
                'duplicate_of_import_job_id' => $duplicateOf->id()->toString(),
                'fingerprint' => $fingerprint,
            ]);

            return;
        }

        $job->markProcessing();
        $this->repository->save($job);

        try {
            $absolutePath = $this->storedFileLocator->locate($job->storage(), $job->filePath());
            $extraction = $this->ocrProvider->extract($absolutePath, $job->mimeType());
            $parsedDraft = $this->receiptParser->parse($extraction);

            $payload = $this->buildNeedsReviewPayload($job->id()->toString(), $extraction->provider, $extraction->text, $extraction->pages, $parsedDraft, $fingerprint);
            $job->markNeedsReview($payload);
            $this->repository->save($job);

            $this->logger->info('import.job.needs_review', [
                'import_job_id' => $job->id()->toString(),
                'status' => $job->status()->value,
                'ocr_provider' => $extraction->provider,
                'parse_issues_count' => count($parsedDraft->issues),
            ]);
        } catch (OcrProviderException $ocrException) {
            $ocrRetryCount = $job->ocrRetryCount();

            if ($ocrException->isRetryable()) {
                if ($ocrRetryCount < self::MAX_RETRYABLE_PROVIDER_ATTEMPTS) {
                    $nextRetryCount = $ocrRetryCount + 1;
                    $delayMs = $this->retryDelayForAttempt($ocrRetryCount);
                    $job->markQueuedForOcrRetry($nextRetryCount);
                    $this->repository->save($job);

                    $this->logger->warning('import.job.retry_scheduled', [
                        'import_job_id' => $job->id()->toString(),
                        'error' => $ocrException->getMessage(),
                        'retry_count' => $nextRetryCount,
                        'next_delay_ms' => $delayMs,
                    ]);

                    throw new RecoverableMessageHandlingException(sprintf('OCR provider transient failure: %s', $ocrException->getMessage()), previous: $ocrException, retryDelay: $delayMs);
                }

                $job->markNeedsReview($this->buildRetryableFallbackNeedsReviewPayload(
                    $job->id()->toString(),
                    $fingerprint,
                    $ocrException->getMessage(),
                    $this->fallbackStrategy,
                    $ocrRetryCount,
                ));
                $this->repository->save($job);

                $this->logger->warning('import.job.needs_review_provider_retry_exhausted', [
                    'import_job_id' => $job->id()->toString(),
                    'error' => $ocrException->getMessage(),
                    'retry_count' => $ocrRetryCount,
                    'fallback_strategy' => $this->fallbackStrategy,
                ]);

                return;
            }

            $job->markFailed($this->buildProviderFailureReason($ocrException));
            $this->repository->save($job);

            $this->logger->error('import.job.failed_provider', [
                'import_job_id' => $job->id()->toString(),
                'error' => $ocrException->getMessage(),
                'retryable' => $ocrException->isRetryable(),
                'retry_count' => $ocrRetryCount,
            ]);
        } catch (Throwable $throwable) {
            $ocrRetryCount = $job->ocrRetryCount();
            $job->markFailed($this->buildUnexpectedFailureReason($throwable));
            $this->repository->save($job);
            $this->logger->error('import.job.failed_unexpected', [
                'import_job_id' => $job->id()->toString(),
                'error' => $throwable->getMessage(),
                'exception_class' => $throwable::class,
                'retry_count' => $ocrRetryCount,
            ]);

            throw $throwable;
        }
    }

    /** @param list<string> $pages */
    private function buildNeedsReviewPayload(string $jobId, string $provider, string $text, array $pages, ParsedReceiptDraft $parsedDraft, string $fingerprint): string
    {
        $payload = [
            'jobId' => $jobId,
            'fingerprint' => $fingerprint,
            'provider' => $provider,
            'text' => mb_substr($text, 0, 2000),
            'pages' => $pages,
            'parsedDraft' => $parsedDraft->toArray(),
            'status' => 'needs_review',
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'ocr_payload_serialization_failed';
        }

        return $encoded;
    }

    private function buildProviderFailureReason(OcrProviderException $exception): string
    {
        $prefix = $exception->isRetryable() ? 'ocr_provider_retryable' : 'ocr_provider_permanent';

        return mb_substr(sprintf('%s: %s', $prefix, trim($exception->getMessage())), 0, 5000);
    }

    private function buildUnexpectedFailureReason(Throwable $throwable): string
    {
        return mb_substr(sprintf('ocr_unexpected: %s', trim($throwable->getMessage())), 0, 5000);
    }

    private function buildRetryableFallbackNeedsReviewPayload(string $jobId, string $fingerprint, string $providerMessage, string $fallbackStrategy, int $retryCount): string
    {
        $normalizedFallbackStrategy = $this->normalizeFallbackStrategy($fallbackStrategy);
        $issue = sprintf('OCR provider unavailable after retries: %s', trim($providerMessage));
        $fallbackNotice = match ($normalizedFallbackStrategy) {
            self::FALLBACK_STRATEGY_MANUAL_REVIEW => 'OCR text could not be extracted automatically. Manual review remains available with the original uploaded file.',
            default => 'OCR fallback strategy triggered. Manual review remains available with the original uploaded file.',
        };
        $payload = [
            'jobId' => $jobId,
            'fingerprint' => $fingerprint,
            'provider' => 'ocr_unavailable_fallback',
            'text' => '',
            'pages' => [],
            'parsedDraft' => [
                'stationName' => null,
                'stationStreetName' => null,
                'stationPostalCode' => null,
                'stationCity' => null,
                'issuedAt' => null,
                'totalCents' => null,
                'vatAmountCents' => null,
                'lines' => [],
                'issues' => [$issue, $fallbackNotice],
                'creationPayload' => null,
            ],
            'status' => 'needs_review',
            'fallbackReason' => 'ocr_provider_retryable_exhausted',
            'fallbackStrategy' => $normalizedFallbackStrategy,
            'fallbackNotice' => $fallbackNotice,
            'retryCount' => $retryCount,
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'ocr_retryable_fallback_payload_serialization_failed';
        }

        return $encoded;
    }

    private function buildFingerprintV1(string $checksumSha256): string
    {
        return 'checksum-sha256:v1:'.mb_strtolower(trim($checksumSha256));
    }

    private function buildDuplicatePayload(string $jobId, string $duplicateOfJobId, string $fingerprint): string
    {
        $payload = [
            'jobId' => $jobId,
            'status' => 'duplicate',
            'duplicateOfImportJobId' => $duplicateOfJobId,
            'fingerprint' => $fingerprint,
            'reason' => 'same_file_checksum',
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'duplicate_payload_serialization_failed';
        }

        return $encoded;
    }

    private function retryDelayForAttempt(int $retryCount): int
    {
        if ($retryCount < 0) {
            return self::RETRYABLE_PROVIDER_DELAYS_MS[0];
        }

        return self::RETRYABLE_PROVIDER_DELAYS_MS[$retryCount] ?? self::RETRYABLE_PROVIDER_DELAYS_MS[array_key_last(self::RETRYABLE_PROVIDER_DELAYS_MS)];
    }

    private function normalizeFallbackStrategy(string $fallbackStrategy): string
    {
        $normalized = mb_strtolower(trim($fallbackStrategy));

        return match ($normalized) {
            self::FALLBACK_STRATEGY_MANUAL_REVIEW => self::FALLBACK_STRATEGY_MANUAL_REVIEW,
            default => self::FALLBACK_STRATEGY_MANUAL_REVIEW,
        };
    }
}
