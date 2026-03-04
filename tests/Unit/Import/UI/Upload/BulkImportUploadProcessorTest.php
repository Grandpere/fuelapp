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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validation;

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
}
