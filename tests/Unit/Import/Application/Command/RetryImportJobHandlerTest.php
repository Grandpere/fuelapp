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

use App\Import\Application\Command\RetryImportJobCommand;
use App\Import\Application\Command\RetryImportJobHandler;
use App\Import\Application\Message\ProcessImportJobMessage;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Domain\ImportJob;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RetryImportJobHandlerTest extends TestCase
{
    public function testItRetriesFailedJobAndDispatchesMessage(): void
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
        $job->markFailed('ocr_provider_permanent: quota exceeded');

        $repository = new RetryInMemoryImportJobRepository([$job]);
        $messageBus = new RetryTraceableMessageBus();
        $handler = new RetryImportJobHandler($repository, $messageBus);

        $updated = ($handler)(new RetryImportJobCommand($job->id()->toString()));

        self::assertSame('queued', $updated->status()->value);
        self::assertNull($updated->failedAt());
        self::assertNull($updated->errorPayload());
        self::assertCount(1, $messageBus->messages);
        self::assertInstanceOf(ProcessImportJobMessage::class, $messageBus->messages[0]);
        self::assertSame($job->id()->toString(), $messageBus->messages[0]->importJobId);
    }

    public function testItRejectsRetryWhenJobIsNotFailed(): void
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

        $handler = new RetryImportJobHandler(
            new RetryInMemoryImportJobRepository([$job]),
            new RetryTraceableMessageBus(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only failed jobs can be retried.');

        ($handler)(new RetryImportJobCommand($job->id()->toString()));
    }
}

final class RetryInMemoryImportJobRepository implements ImportJobRepository
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

final class RetryTraceableMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}
