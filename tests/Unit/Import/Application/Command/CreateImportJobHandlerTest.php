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

use App\Import\Application\Command\CreateImportJobCommand;
use App\Import\Application\Command\CreateImportJobHandler;
use App\Import\Application\Message\ProcessImportJobMessage;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Application\Storage\ImportFileStorage;
use App\Import\Application\Storage\StoredImportFile;
use App\Import\Domain\ImportJob;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateImportJobHandlerTest extends TestCase
{
    public function testItStoresFileAndCreatesQueuedJob(): void
    {
        $storage = new InMemoryImportFileStorage();
        $repository = new InMemoryImportJobRepository();
        $messageBus = new RecordingMessageBus();

        $handler = new CreateImportJobHandler($storage, $repository, $messageBus);

        $job = ($handler)(new CreateImportJobCommand(
            '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
            '/tmp/source.pdf',
            'receipt.pdf',
        ));

        self::assertSame('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01', $job->ownerId());
        self::assertSame('queued', $job->status()->value);
        self::assertNotNull($repository->saved);
        self::assertSame($job->id()->toString(), $repository->saved->id()->toString());
        self::assertSame('/tmp/source.pdf', $storage->lastSourcePath);
        self::assertSame('receipt.pdf', $storage->lastOriginalFilename);
        self::assertCount(1, $messageBus->messages);
        self::assertInstanceOf(ProcessImportJobMessage::class, $messageBus->messages[0]);
        self::assertSame($job->id()->toString(), $messageBus->messages[0]->importJobId);
    }
}

final class RecordingMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}

final class InMemoryImportFileStorage implements ImportFileStorage
{
    public ?string $lastSourcePath = null;
    public ?string $lastOriginalFilename = null;

    public function store(string $sourcePath, string $originalFilename): StoredImportFile
    {
        $this->lastSourcePath = $sourcePath;
        $this->lastOriginalFilename = $originalFilename;

        return new StoredImportFile(
            'local',
            '2026/02/21/abc-receipt.pdf',
            $originalFilename,
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );
    }
}

final class InMemoryImportJobRepository implements ImportJobRepository
{
    public ?ImportJob $saved = null;

    public function save(ImportJob $job): void
    {
        $this->saved = $job;
    }

    public function get(string $id): ?ImportJob
    {
        if (null === $this->saved || $this->saved->id()->toString() !== $id) {
            return null;
        }

        return $this->saved;
    }

    public function getForSystem(string $id): ?ImportJob
    {
        return $this->get($id);
    }

    public function all(): iterable
    {
        return null === $this->saved ? [] : [$this->saved];
    }
}
