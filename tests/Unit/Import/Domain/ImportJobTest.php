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

namespace App\Tests\Unit\Import\Domain;

use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ImportJob;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ImportJobTest extends TestCase
{
    public function testCreateQueuedInitializesImportJob(): void
    {
        $job = ImportJob::createQueued(
            '0195c5ad-3f61-75de-b06f-3539f2b3ce61',
            'local',
            '2026/02/21/test.pdf',
            'test.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );

        self::assertSame(ImportJobStatus::QUEUED, $job->status());
        self::assertSame('local', $job->storage());
        self::assertSame('test.pdf', $job->originalFilename());
        self::assertNull($job->errorPayload());
        self::assertNull($job->startedAt());
    }

    public function testLifecycleTransitionsAreTracked(): void
    {
        $queuedAt = new DateTimeImmutable('2026-02-21 10:00:00');
        $startedAt = new DateTimeImmutable('2026-02-21 10:01:00');
        $failedAt = new DateTimeImmutable('2026-02-21 10:02:00');
        $processedAt = new DateTimeImmutable('2026-02-21 10:03:00');

        $job = ImportJob::createQueued(
            '0195c5ad-3f61-75de-b06f-3539f2b3ce61',
            'local',
            '2026/02/21/test.pdf',
            'test.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
            $queuedAt,
        );

        $job->markProcessing($startedAt);
        self::assertSame(ImportJobStatus::PROCESSING, $job->status());
        self::assertSame($startedAt, $job->startedAt());

        $job->markFailed('ocr timeout', $failedAt);
        self::assertSame(ImportJobStatus::FAILED, $job->status());
        self::assertSame($failedAt, $job->failedAt());
        self::assertSame('ocr timeout', $job->errorPayload());

        $job->markProcessed($processedAt);
        self::assertSame(ImportJobStatus::PROCESSED, $job->status());
        self::assertSame($processedAt, $job->completedAt());
        self::assertNull($job->errorPayload());
    }
}
