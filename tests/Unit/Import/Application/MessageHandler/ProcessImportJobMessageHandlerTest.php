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

namespace App\Tests\Unit\Import\Application\MessageHandler;

use App\Import\Application\Message\ProcessImportJobMessage;
use App\Import\Application\MessageHandler\ProcessImportJobMessageHandler;
use App\Import\Application\Ocr\OcrExtraction;
use App\Import\Application\Ocr\OcrProvider;
use App\Import\Application\Ocr\OcrProviderException;
use App\Import\Application\Parsing\ParsedReceiptDraft;
use App\Import\Application\Parsing\ParsedReceiptLineDraft;
use App\Import\Application\Parsing\ReceiptOcrParser;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Application\Storage\ImportStoredFileLocator;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProcessImportJobMessageHandlerTest extends TestCase
{
    public function testItSkipsUnknownImportJob(): void
    {
        $repository = new InMemoryImportJobRepository([]);
        $handler = new ProcessImportJobMessageHandler(
            $repository,
            new FakeStoredFileLocator('/tmp/upload.pdf'),
            new FakeOcrProvider(new OcrExtraction('fake', 'text', ['text'], [])),
            new FakeReceiptOcrParser(),
            new NullLogger(),
        );

        $handler(new ProcessImportJobMessage('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'));

        self::assertSame(0, $repository->saveCount);
    }

    public function testItTransitionsQueuedJobToNeedsReviewWithOcrPayload(): void
    {
        $job = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );

        $repository = new InMemoryImportJobRepository([$job]);
        $handler = new ProcessImportJobMessageHandler(
            $repository,
            new FakeStoredFileLocator('/tmp/upload.pdf'),
            new FakeOcrProvider(new OcrExtraction('ocr_space', 'Total\n80.00', ['Total', '80.00'], ['raw' => true])),
            new FakeReceiptOcrParser(),
            new NullLogger(),
        );

        $handler(new ProcessImportJobMessage($job->id()->toString()));

        $saved = $repository->getForSystem($job->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(ImportJobStatus::NEEDS_REVIEW, $saved->status());
        self::assertNotNull($saved->startedAt());
        self::assertNotNull($saved->errorPayload());
        self::assertStringContainsString('ocr_space', (string) $saved->errorPayload());
        self::assertSame(2, $repository->saveCount);
    }

    public function testItMarksFailedWithoutThrowingForPermanentProviderError(): void
    {
        $job = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );

        $repository = new InMemoryImportJobRepository([$job]);
        $handler = new ProcessImportJobMessageHandler(
            $repository,
            new FakeStoredFileLocator('/tmp/upload.pdf'),
            new ThrowingOcrProvider(OcrProviderException::permanent('invalid file')),
            new FakeReceiptOcrParser(),
            new NullLogger(),
        );

        $handler(new ProcessImportJobMessage($job->id()->toString()));

        $saved = $repository->getForSystem($job->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(ImportJobStatus::FAILED, $saved->status());
        self::assertStringContainsString('ocr_provider_permanent', (string) $saved->errorPayload());
        self::assertSame(2, $repository->saveCount);
    }

    public function testItMarksFailedAndRethrowsRetryableProviderError(): void
    {
        $job = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );

        $repository = new InMemoryImportJobRepository([$job]);
        $handler = new ProcessImportJobMessageHandler(
            $repository,
            new FakeStoredFileLocator('/tmp/upload.pdf'),
            new ThrowingOcrProvider(OcrProviderException::retryable('temporary outage')),
            new FakeReceiptOcrParser(),
            new NullLogger(),
        );

        $this->expectException(OcrProviderException::class);

        try {
            $handler(new ProcessImportJobMessage($job->id()->toString()));
        } finally {
            $saved = $repository->getForSystem($job->id()->toString());
            self::assertNotNull($saved);
            self::assertSame(ImportJobStatus::FAILED, $saved->status());
            self::assertStringContainsString('ocr_provider_retryable', (string) $saved->errorPayload());
        }
    }

    public function testItMarksJobAsDuplicateWhenFingerprintAlreadyExists(): void
    {
        $existing = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/existing.pdf',
            'existing.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );
        $existing->markNeedsReview('already processed');

        $job = ImportJob::createQueued(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            'local',
            '2026/02/21/new.pdf',
            'new.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );

        $repository = new InMemoryImportJobRepository([$existing, $job]);
        $handler = new ProcessImportJobMessageHandler(
            $repository,
            new FakeStoredFileLocator('/tmp/upload.pdf'),
            new FakeOcrProvider(new OcrExtraction('ocr_space', 'ignored', ['ignored'], [])),
            new FakeReceiptOcrParser(),
            new NullLogger(),
        );

        $handler(new ProcessImportJobMessage($job->id()->toString()));

        $saved = $repository->getForSystem($job->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(ImportJobStatus::DUPLICATE, $saved->status());
        self::assertStringContainsString('same_file_checksum', (string) $saved->errorPayload());
    }
}

final class InMemoryImportJobRepository implements ImportJobRepository
{
    /** @var array<string, ImportJob> */
    private array $items = [];

    public int $saveCount = 0;

    /** @param list<ImportJob> $jobs */
    public function __construct(array $jobs)
    {
        foreach ($jobs as $job) {
            $this->items[$job->id()->toString()] = $job;
        }
    }

    public function save(ImportJob $job): void
    {
        ++$this->saveCount;
        $this->items[$job->id()->toString()] = $job;
    }

    public function get(string $id): ?ImportJob
    {
        return $this->items[$id] ?? null;
    }

    public function getForSystem(string $id): ?ImportJob
    {
        return $this->get($id);
    }

    public function all(): iterable
    {
        return array_values($this->items);
    }

    public function findLatestByOwnerAndChecksum(string $ownerId, string $checksumSha256, ?string $excludeJobId = null): ?ImportJob
    {
        $latest = null;
        foreach ($this->items as $item) {
            if ($item->ownerId() !== $ownerId || $item->fileChecksumSha256() !== $checksumSha256) {
                continue;
            }

            if (null !== $excludeJobId && $item->id()->toString() === $excludeJobId) {
                continue;
            }

            if (ImportJobStatus::FAILED === $item->status()) {
                continue;
            }

            if (null === $latest || $item->createdAt() > $latest->createdAt()) {
                $latest = $item;
            }
        }

        return $latest;
    }
}

final class FakeStoredFileLocator implements ImportStoredFileLocator
{
    public function __construct(private readonly string $path)
    {
    }

    public function locate(string $storage, string $path): string
    {
        return $this->path;
    }
}

final class FakeOcrProvider implements OcrProvider
{
    public function __construct(private readonly OcrExtraction $extraction)
    {
    }

    public function extract(string $filePath, string $mimeType): OcrExtraction
    {
        return $this->extraction;
    }
}

final class ThrowingOcrProvider implements OcrProvider
{
    public function __construct(private readonly OcrProviderException $exception)
    {
    }

    public function extract(string $filePath, string $mimeType): OcrExtraction
    {
        throw $this->exception;
    }
}

final class FakeReceiptOcrParser implements ReceiptOcrParser
{
    public function parse(OcrExtraction $extraction): ParsedReceiptDraft
    {
        return new ParsedReceiptDraft(
            'Total',
            '1 Rue A',
            '75001',
            'Paris',
            null,
            8000,
            1333,
            [new ParsedReceiptLineDraft('diesel', 10000, 1800, 1800, 20)],
            [],
        );
    }
}
