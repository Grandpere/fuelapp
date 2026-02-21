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
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProcessImportJobMessageHandlerTest extends TestCase
{
    public function testItSkipsUnknownImportJob(): void
    {
        $repository = new InMemoryImportJobRepository([]);
        $handler = new ProcessImportJobMessageHandler($repository, new NullLogger());

        $handler(new ProcessImportJobMessage('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'));

        self::assertSame(0, $repository->saveCount);
    }

    public function testItTransitionsQueuedJobToNeedsReviewThroughProcessing(): void
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
        $handler = new ProcessImportJobMessageHandler($repository, new NullLogger());

        $handler(new ProcessImportJobMessage($job->id()->toString()));

        $saved = $repository->getForSystem($job->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(ImportJobStatus::NEEDS_REVIEW, $saved->status());
        self::assertNotNull($saved->startedAt());
        self::assertSame('pipeline_pending_ocr_and_parsing', $saved->errorPayload());
        self::assertSame(2, $repository->saveCount);
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
}
