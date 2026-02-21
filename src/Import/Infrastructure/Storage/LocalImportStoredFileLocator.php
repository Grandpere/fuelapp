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

namespace App\Import\Infrastructure\Storage;

use App\Import\Application\Storage\ImportStoredFileLocator;
use RuntimeException;

final readonly class LocalImportStoredFileLocator implements ImportStoredFileLocator
{
    public function __construct(
        private string $storageName,
        private string $baseDirectory,
    ) {
    }

    public function locate(string $storage, string $path): string
    {
        if ($storage !== $this->storageName) {
            throw new RuntimeException(sprintf('Unsupported import storage "%s".', $storage));
        }

        $absolutePath = rtrim($this->baseDirectory, '/').'/'.ltrim($path, '/');
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new RuntimeException('Stored import file is not readable.');
        }

        return $absolutePath;
    }
}
