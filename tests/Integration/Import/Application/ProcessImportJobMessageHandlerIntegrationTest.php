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

namespace App\Tests\Integration\Import\Application;

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
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Throwable;

final class ProcessImportJobMessageHandlerIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ImportJobRepository $importJobRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $repository = self::getContainer()->get(ImportJobRepository::class);
        if (!$repository instanceof ImportJobRepository) {
            throw new RuntimeException('ImportJobRepository service not found.');
        }
        $this->importJobRepository = $repository;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testHandlerPersistsNeedsReviewStatusFromQueuedJob(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail('import.handler@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('test1234');
        $this->em->persist($user);
        $this->em->flush();

        $job = ImportJob::createQueued(
            $user->getId()->toRfc4122(),
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            str_repeat('a', 64),
        );
        $this->importJobRepository->save($job);

        $handler = new ProcessImportJobMessageHandler(
            $this->importJobRepository,
            new StaticFileLocator('/tmp/fake.pdf'),
            new StaticOcrProvider(),
            new StaticReceiptParser(),
            new NullLogger(),
        );
        $handler(new ProcessImportJobMessage($job->id()->toString()));

        $saved = $this->importJobRepository->getForSystem($job->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(ImportJobStatus::NEEDS_REVIEW, $saved->status());
        self::assertNotNull($saved->startedAt());
        self::assertNotNull($saved->errorPayload());
        self::assertStringContainsString('ocr_space', (string) $saved->errorPayload());
    }

    public function testHandlerMarksJobAsDuplicateWhenChecksumAlreadyExists(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail('import.duplicate@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('test1234');
        $this->em->persist($user);
        $this->em->flush();

        $existing = ImportJob::createQueued(
            $user->getId()->toRfc4122(),
            'local',
            '2026/02/21/existing.pdf',
            'existing.pdf',
            'application/pdf',
            1024,
            str_repeat('b', 64),
        );
        $existing->markNeedsReview('already-parsed');
        $this->importJobRepository->save($existing);

        $job = ImportJob::createQueued(
            $user->getId()->toRfc4122(),
            'local',
            '2026/02/21/new.pdf',
            'new.pdf',
            'application/pdf',
            1024,
            str_repeat('b', 64),
        );
        $this->importJobRepository->save($job);

        $handler = new ProcessImportJobMessageHandler(
            $this->importJobRepository,
            new StaticFileLocator('/tmp/fake.pdf'),
            new StaticOcrProvider(),
            new StaticReceiptParser(),
            new NullLogger(),
        );
        $handler(new ProcessImportJobMessage($job->id()->toString()));

        $saved = $this->importJobRepository->getForSystem($job->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(ImportJobStatus::DUPLICATE, $saved->status());
        self::assertStringContainsString('same_file_checksum', (string) $saved->errorPayload());
        self::assertStringContainsString($existing->id()->toString(), (string) $saved->errorPayload());
    }

    public function testHandlerMarksFailedWithoutThrowingForPermanentProviderError(): void
    {
        $job = $this->createQueuedJob('import.provider.permanent@example.com', str_repeat('c', 64));

        $handler = new ProcessImportJobMessageHandler(
            $this->importJobRepository,
            new StaticFileLocator('/tmp/fake.pdf'),
            new ThrowingOcrProvider(OcrProviderException::permanent('bad api key')),
            new StaticReceiptParser(),
            new NullLogger(),
        );
        $handler(new ProcessImportJobMessage($job->id()->toString()));

        $saved = $this->importJobRepository->getForSystem($job->id()->toString());
        self::assertNotNull($saved);
        self::assertSame(ImportJobStatus::FAILED, $saved->status());
        self::assertStringContainsString('ocr_provider_permanent', (string) $saved->errorPayload());
    }

    public function testHandlerMarksFailedAndRethrowsForRetryableProviderError(): void
    {
        $job = $this->createQueuedJob('import.provider.retryable@example.com', str_repeat('d', 64));

        $handler = new ProcessImportJobMessageHandler(
            $this->importJobRepository,
            new StaticFileLocator('/tmp/fake.pdf'),
            new ThrowingOcrProvider(OcrProviderException::retryable('provider timeout')),
            new StaticReceiptParser(),
            new NullLogger(),
        );

        $this->expectException(OcrProviderException::class);
        try {
            $handler(new ProcessImportJobMessage($job->id()->toString()));
        } finally {
            $saved = $this->importJobRepository->getForSystem($job->id()->toString());
            self::assertNotNull($saved);
            self::assertSame(ImportJobStatus::FAILED, $saved->status());
            self::assertStringContainsString('ocr_provider_retryable', (string) $saved->errorPayload());
        }
    }

    public function testHandlerMarksFailedAndRethrowsForUnexpectedParserFailure(): void
    {
        $job = $this->createQueuedJob('import.parser.failure@example.com', str_repeat('e', 64));

        $handler = new ProcessImportJobMessageHandler(
            $this->importJobRepository,
            new StaticFileLocator('/tmp/fake.pdf'),
            new StaticOcrProvider(),
            new ThrowingReceiptParser(new RuntimeException('parser exploded')),
            new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        try {
            $handler(new ProcessImportJobMessage($job->id()->toString()));
        } finally {
            $saved = $this->importJobRepository->getForSystem($job->id()->toString());
            self::assertNotNull($saved);
            self::assertSame(ImportJobStatus::FAILED, $saved->status());
            self::assertStringContainsString('ocr_unexpected', (string) $saved->errorPayload());
        }
    }

    private function createQueuedJob(string $email, string $checksum): ImportJob
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('test1234');
        $this->em->persist($user);
        $this->em->flush();

        $job = ImportJob::createQueued(
            $user->getId()->toRfc4122(),
            'local',
            '2026/02/21/file.pdf',
            'file.pdf',
            'application/pdf',
            1024,
            $checksum,
        );
        $this->importJobRepository->save($job);

        return $job;
    }
}

final class StaticFileLocator implements ImportStoredFileLocator
{
    public function __construct(private readonly string $path)
    {
    }

    public function locate(string $storage, string $path): string
    {
        return $this->path;
    }
}

final class StaticOcrProvider implements OcrProvider
{
    public function extract(string $filePath, string $mimeType): OcrExtraction
    {
        return new OcrExtraction('ocr_space', 'TOTAL 80.00', ['TOTAL 80.00'], ['raw' => true]);
    }
}

final class StaticReceiptParser implements ReceiptOcrParser
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

final class ThrowingReceiptParser implements ReceiptOcrParser
{
    public function __construct(private readonly Throwable $throwable)
    {
    }

    public function parse(OcrExtraction $extraction): ParsedReceiptDraft
    {
        throw $this->throwable;
    }
}
