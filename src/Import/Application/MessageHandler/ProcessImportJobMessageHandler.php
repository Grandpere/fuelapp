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
use App\Import\Application\Storage\ImportStoredFileLocator;
use App\Import\Domain\Enum\ImportJobStatus;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class ProcessImportJobMessageHandler
{
    public function __construct(
        private ImportJobRepository $repository,
        private ImportStoredFileLocator $storedFileLocator,
        private OcrProvider $ocrProvider,
        private ReceiptOcrParser $receiptParser,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessImportJobMessage $message): void
    {
        $this->logger->info('import.job.started', [
            'import_job_id' => $message->importJobId,
            'message' => ProcessImportJobMessage::class,
        ]);

        $job = $this->repository->getForSystem($message->importJobId);
        if (null === $job) {
            $this->logger->warning('import.job.skipped_not_found', ['import_job_id' => $message->importJobId]);

            return;
        }

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
            $job->markFailed($this->buildProviderFailureReason($ocrException));
            $this->repository->save($job);

            $this->logger->error('import.job.failed_provider', [
                'import_job_id' => $job->id()->toString(),
                'error' => $ocrException->getMessage(),
                'retryable' => $ocrException->isRetryable(),
            ]);

            if ($ocrException->isRetryable()) {
                throw $ocrException;
            }
        } catch (Throwable $throwable) {
            $job->markFailed($this->buildUnexpectedFailureReason($throwable));
            $this->repository->save($job);
            $this->logger->error('import.job.failed_unexpected', [
                'import_job_id' => $job->id()->toString(),
                'error' => $throwable->getMessage(),
                'exception_class' => $throwable::class,
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
}
