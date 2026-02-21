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

namespace App\Tests\Unit\Import\Infrastructure\Storage;

use App\Import\Infrastructure\Storage\LocalImportFileStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalImportFileStorageTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/fuelapp-import-tests-'.uniqid('', true);
    }

    public function testStorePersistsFileAndReturnsStableReferenceMetadata(): void
    {
        $source = $this->tmpDir.'-source.txt';
        file_put_contents($source, 'hello import');

        $storage = new LocalImportFileStorage('local', $this->tmpDir);
        $stored = $storage->store($source, 'receipt sample.pdf');

        self::assertSame('local', $stored->storage);
        self::assertSame('receipt-sample.pdf', $stored->originalFilename);
        self::assertGreaterThan(0, $stored->sizeBytes);
        self::assertSame(64, strlen($stored->checksumSha256));
        self::assertFileExists($this->tmpDir.'/'.$stored->path);
    }

    public function testStoreFailsWhenSourceDoesNotExist(): void
    {
        $storage = new LocalImportFileStorage('local', $this->tmpDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source file is not readable');

        $storage->store($this->tmpDir.'/missing.pdf', 'missing.pdf');
    }
}
