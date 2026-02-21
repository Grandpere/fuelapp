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
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessImportJobMessageHandler
{
    public function __construct(
        private ImportJobRepository $repository,
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

        if (in_array($job->status(), [ImportJobStatus::PROCESSED, ImportJobStatus::NEEDS_REVIEW], true)) {
            $this->logger->info('import.job.skipped_already_terminal', [
                'import_job_id' => $job->id()->toString(),
                'status' => $job->status()->value,
            ]);

            return;
        }

        $job->markProcessing();
        $this->repository->save($job);

        // SP3-003 orchestration skeleton: OCR/parsing is implemented in SP3-004/SP3-005.
        $job->markNeedsReview('pipeline_pending_ocr_and_parsing');
        $this->repository->save($job);

        $this->logger->info('import.job.needs_review', [
            'import_job_id' => $job->id()->toString(),
            'status' => $job->status()->value,
        ]);
    }
}
