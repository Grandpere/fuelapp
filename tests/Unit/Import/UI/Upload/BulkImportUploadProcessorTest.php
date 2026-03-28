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

namespace App\Tests\Unit\Import\UI\Upload;

use App\Import\Application\Command\CreateImportJobHandler;
use App\Import\Application\Repository\ImportJobRepository;
use App\Import\Application\Storage\ImportFileStorage;
use App\Import\UI\Upload\BulkImportUploadProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validation;
use ZipArchive;

final class BulkImportUploadProcessorTest extends TestCase
{
    public function testItRejectsZipWhenTemporaryUploadPathIsUnavailable(): void
    {
        $storage = $this->createMock(ImportFileStorage::class);
        $storage->expects(self::never())->method('store');
        $repository = $this->createMock(ImportJobRepository::class);
        $repository->expects(self::never())->method('save');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');
        $handler = new CreateImportJobHandler($storage, $repository, $messageBus);

        $processor = new BulkImportUploadProcessor(
            $handler,
            Validation::createValidator(),
        );

        $tmpPath = sys_get_temp_dir().'/fuelapp-missing-upload-'.uniqid('', true).'.zip';
        file_put_contents($tmpPath, 'zip-placeholder');
        $upload = new UploadedFile($tmpPath, 'Archive.zip', 'application/zip', null, true);
        @unlink($tmpPath);

        $result = $processor->process('11111111-1111-7111-8111-111111111111', [$upload]);

        self::assertSame(0, $result->acceptedCount());
        self::assertSame(1, $result->rejectedCount());
        self::assertSame('Archive.zip', $result->rejected()[0]['filename']);
        self::assertSame('Uploaded file is invalid or temporary file is unavailable.', $result->rejected()[0]['reason']);
    }

    public function testItCleansUpTemporaryFilesWhenZipEntryIsRejectedAsOversized(): void
    {
        $storage = $this->createMock(ImportFileStorage::class);
        $storage->expects(self::never())->method('store');
        $repository = $this->createMock(ImportJobRepository::class);
        $repository->expects(self::never())->method('save');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');
        $handler = new CreateImportJobHandler($storage, $repository, $messageBus);

        $processor = new BulkImportUploadProcessor(
            $handler,
            Validation::createValidator(),
        );

        $zipPath = $this->createZipFile([
            'too-big.png' => str_repeat('A', 8_600_000),
        ]);
        $before = $this->listZipTempFiles();
        $upload = new UploadedFile($zipPath, 'oversized.zip', 'application/zip', null, true);

        try {
            $result = $processor->process('11111111-1111-7111-8111-111111111111', [$upload]);
        } finally {
            @unlink($zipPath);
        }

        self::assertSame(0, $result->acceptedCount());
        self::assertSame(1, $result->rejectedCount());
        self::assertSame('too-big.png', $result->rejected()[0]['filename']);
        self::assertSame(
            'File is too large. Current import limits: 8 MB for images, 1 MB for PDF.',
            $result->rejected()[0]['reason'],
        );
        self::assertSame($before, $this->listZipTempFiles());
    }

    /**
     * @param array<string, string> $entries
     */
    private function createZipFile(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fuelapp-bulk-zip-test-');
        if (false === $path) {
            throw new RuntimeException('Unable to allocate zip fixture path.');
        }

        $zip = new ZipArchive();
        if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            @unlink($path);
            throw new RuntimeException('Unable to create zip fixture.');
        }

        foreach ($entries as $entryName => $contents) {
            $zip->addFromString($entryName, $contents);
        }

        $zip->close();

        return $path;
    }

    /**
     * @return list<string>
     */
    private function listZipTempFiles(): array
    {
        $matches = glob(sys_get_temp_dir().'/fuelapp-import-zip-*');
        if (false === $matches) {
            return [];
        }

        sort($matches);

        return array_map(static fn (string $path): string => basename($path), $matches);
    }
}
