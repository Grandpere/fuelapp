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

namespace App\Import\Application\Storage;

final readonly class StoredImportFile
{
    public function __construct(
        public string $storage,
        public string $path,
        public string $originalFilename,
        public string $mimeType,
        public int $sizeBytes,
        public string $checksumSha256,
    ) {
    }
}
