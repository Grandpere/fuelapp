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

use App\Import\Infrastructure\Storage\LocalImportStoredFileLocator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalImportStoredFileLocatorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/fuelapp-import-locator-'.uniqid('', true);
        mkdir($this->tmpDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->deleteDir($this->tmpDir);
        }
    }

    public function testLocateReturnsReadableAbsolutePath(): void
    {
        $relativePath = '2026/02/21/file.pdf';
        $absolutePath = $this->tmpDir.'/'.$relativePath;
        mkdir(dirname($absolutePath), 0o775, true);
        file_put_contents($absolutePath, 'pdf-data');

        $locator = new LocalImportStoredFileLocator('local', $this->tmpDir);

        self::assertSame($absolutePath, $locator->locate('local', $relativePath));
    }

    public function testLocateThrowsWhenStorageIsUnsupported(): void
    {
        $locator = new LocalImportStoredFileLocator('local', $this->tmpDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported import storage');

        $locator->locate('s3', 'x/y/z.pdf');
    }

    public function testLocateThrowsWhenFileIsMissing(): void
    {
        $locator = new LocalImportStoredFileLocator('local', $this->tmpDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stored import file is not readable');

        $locator->locate('local', 'missing.pdf');
    }

    private function deleteDir(string $dir): void
    {
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
