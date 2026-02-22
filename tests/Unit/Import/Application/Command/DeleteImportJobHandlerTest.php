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

namespace App\Tests\Unit\Import\Application\Command;

use App\Import\Application\Command\DeleteImportJobCommand;
use App\Import\Application\Command\DeleteImportJobHandler;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Application\Storage\ImportFileStorage;
use App\Import\Application\Storage\StoredImportFile;
use App\Import\Domain\ImportJob;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeleteImportJobHandlerTest extends TestCase
{
    public function testItDeletesImportFileAndJob(): void
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

        $repository = new DeleteInMemoryImportJobRepository([$job]);
        $storage = new DeleteTraceableImportFileStorage();
        $handler = new DeleteImportJobHandler($repository, $storage);

        ($handler)(new DeleteImportJobCommand($job->id()->toString()));

        self::assertSame('local', $storage->lastStorage);
        self::assertSame('2026/02/21/file.pdf', $storage->lastPath);
        self::assertNull($repository->getForSystem($job->id()->toString()));
    }

    public function testItThrowsWhenJobDoesNotExist(): void
    {
        $handler = new DeleteImportJobHandler(
            new DeleteInMemoryImportJobRepository([]),
            new DeleteTraceableImportFileStorage(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Import job not found.');

        ($handler)(new DeleteImportJobCommand('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'));
    }
}

final class DeleteInMemoryImportJobRepository implements ImportJobRepository
{
    /** @var array<string, ImportJob> */
    private array $items = [];

    /** @param list<ImportJob> $jobs */
    public function __construct(array $jobs)
    {
        foreach ($jobs as $job) {
            $this->items[$job->id()->toString()] = $job;
        }
    }

    public function save(ImportJob $job): void
    {
        $this->items[$job->id()->toString()] = $job;
    }

    public function deleteForSystem(string $id): void
    {
        unset($this->items[$id]);
    }

    public function get(string $id): ?ImportJob
    {
        return $this->items[$id] ?? null;
    }

    public function getForSystem(string $id): ?ImportJob
    {
        return $this->get($id);
    }

    public function findLatestByOwnerAndChecksum(string $ownerId, string $checksumSha256, ?string $excludeJobId = null): ?ImportJob
    {
        return null;
    }

    public function all(): iterable
    {
        return array_values($this->items);
    }

    public function allForSystem(): iterable
    {
        return $this->all();
    }
}

final class DeleteTraceableImportFileStorage implements ImportFileStorage
{
    public ?string $lastStorage = null;
    public ?string $lastPath = null;

    public function store(string $sourcePath, string $originalFilename): StoredImportFile
    {
        return new StoredImportFile('local', 'unused/path', $originalFilename, 'application/pdf', 0, str_repeat('a', 64));
    }

    public function delete(string $storage, string $path): void
    {
        $this->lastStorage = $storage;
        $this->lastPath = $path;
    }
}
