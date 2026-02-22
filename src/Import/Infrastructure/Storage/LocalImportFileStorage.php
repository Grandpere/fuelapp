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

use App\Import\Application\Storage\ImportFileStorage;
use App\Import\Application\Storage\StoredImportFile;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

final readonly class LocalImportFileStorage implements ImportFileStorage
{
    public function __construct(
        private string $storageName,
        private string $baseDirectory,
    ) {
    }

    public function store(string $sourcePath, string $originalFilename): StoredImportFile
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new RuntimeException('Source file is not readable.');
        }

        $normalizedOriginalFilename = $this->normalizeFilename($originalFilename);
        $subDirectory = new DateTimeImmutable()->format('Y/m/d');
        $filename = sprintf('%s-%s', Uuid::v7()->toRfc4122(), $normalizedOriginalFilename);
        $relativePath = sprintf('%s/%s', $subDirectory, $filename);
        $targetDirectory = rtrim($this->baseDirectory, '/').'/'.$subDirectory;
        $targetPath = $targetDirectory.'/'.$filename;

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0o775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Unable to create import storage directory.');
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Unable to persist uploaded import file.');
        }

        $size = filesize($targetPath);
        if (false === $size) {
            throw new RuntimeException('Unable to determine persisted file size.');
        }

        $checksum = hash_file('sha256', $targetPath);
        if (false === $checksum) {
            throw new RuntimeException('Unable to determine persisted file checksum.');
        }

        return new StoredImportFile(
            $this->storageName,
            $relativePath,
            $normalizedOriginalFilename,
            $this->detectMimeType($targetPath),
            $size,
            $checksum,
        );
    }

    public function delete(string $storage, string $path): void
    {
        if ($storage !== $this->storageName) {
            return;
        }

        $absolutePath = rtrim($this->baseDirectory, '/').'/'.ltrim($path, '/');
        if (!is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
    }

    private function normalizeFilename(string $originalFilename): string
    {
        $trimmed = trim($originalFilename);
        if ('' === $trimmed) {
            return 'import.bin';
        }

        $sanitized = preg_replace('/[^A-Za-z0-9._-]/', '-', $trimmed);
        if (!is_string($sanitized) || '' === trim($sanitized, '-')) {
            return 'import.bin';
        }

        return mb_substr($sanitized, 0, 180);
    }

    private function detectMimeType(string $path): string
    {
        $mime = @mime_content_type($path);
        if (!is_string($mime) || '' === trim($mime)) {
            return 'application/octet-stream';
        }

        return $mime;
    }
}
