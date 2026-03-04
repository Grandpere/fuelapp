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

namespace App\Import\UI\Upload;

use App\Import\Application\Command\CreateImportJobCommand;
use App\Import\Application\Command\CreateImportJobHandler;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;
use ZipArchive;

final readonly class BulkImportUploadProcessor
{
    // OCR.Space free tier hard limit.
    private const MAX_RECEIPT_UPLOAD_SIZE = '1024K';

    /** @var list<string> */
    private const ALLOWED_RECEIPT_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var list<string> */
    private const ZIP_MIME_TYPES = [
        'application/zip',
        'application/x-zip-compressed',
        'multipart/x-zip',
    ];

    public function __construct(
        private CreateImportJobHandler $createImportJobHandler,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param list<UploadedFile> $uploadedFiles
     */
    public function process(string $ownerId, array $uploadedFiles): BulkImportUploadResult
    {
        $result = new BulkImportUploadResult();

        foreach ($uploadedFiles as $uploadedFile) {
            if (!$this->isUploadUsable($uploadedFile)) {
                $filename = $this->safeClientFilename($uploadedFile);
                $result->addRejected($filename, 'Uploaded file is invalid or temporary file is unavailable.', $filename);

                continue;
            }

            if ($this->isZipUpload($uploadedFile)) {
                $this->processZipUpload($ownerId, $uploadedFile, $result);

                continue;
            }

            $this->processReceiptUpload(
                $ownerId,
                $uploadedFile->getPathname(),
                $uploadedFile->getClientOriginalName(),
                $uploadedFile->getClientOriginalName(),
                $result,
            );
        }

        return $result;
    }

    private function processZipUpload(string $ownerId, UploadedFile $uploadedFile, BulkImportUploadResult $result): void
    {
        if (!class_exists(ZipArchive::class)) {
            $filename = $uploadedFile->getClientOriginalName();
            $result->addRejected($filename, 'ZIP support is not available on this runtime.', $filename);

            return;
        }

        $pathname = $uploadedFile->getPathname();
        if ('' === $pathname || !is_file($pathname) || !is_readable($pathname)) {
            $filename = $this->safeClientFilename($uploadedFile);
            $result->addRejected($filename, 'ZIP temporary file is not readable.', $filename);

            return;
        }

        $zipArchive = new ZipArchive();
        $opened = $zipArchive->open($pathname);
        if (true !== $opened) {
            $filename = $this->safeClientFilename($uploadedFile);
            $result->addRejected($filename, 'Unable to open ZIP archive.', $filename);

            return;
        }

        try {
            $entryCount = 0;
            for ($index = 0; $index < $zipArchive->numFiles; ++$index) {
                $entryName = (string) $zipArchive->getNameIndex($index);
                if ('' === $entryName || str_ends_with($entryName, '/')) {
                    continue;
                }

                ++$entryCount;
                $this->processZipEntry($ownerId, $zipArchive, $entryName, $uploadedFile->getClientOriginalName(), $result);
            }

            if (0 === $entryCount) {
                $filename = $uploadedFile->getClientOriginalName();
                $result->addRejected($filename, 'ZIP archive does not contain files.', $filename);
            }
        } finally {
            $zipArchive->close();
        }
    }

    private function processZipEntry(
        string $ownerId,
        ZipArchive $zipArchive,
        string $entryName,
        string $archiveFilename,
        BulkImportUploadResult $result,
    ): void {
        $source = sprintf('%s:%s', $archiveFilename, $entryName);
        $filename = basename($entryName);
        if ('' === $filename || '.' === $filename || '..' === $filename) {
            $result->addRejected($entryName, 'Invalid file entry in ZIP archive.', $source);

            return;
        }

        $stream = $zipArchive->getStream($entryName);
        if (false === $stream) {
            $result->addRejected($filename, 'Unable to read file entry from ZIP archive.', $source);

            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'fuelapp-import-zip-');
        if (false === $tmpPath) {
            fclose($stream);
            $result->addRejected($filename, 'Unable to allocate temporary file for ZIP entry.', $source);

            return;
        }

        $tmpHandle = fopen($tmpPath, 'wb');
        if (false === $tmpHandle) {
            fclose($stream);
            @unlink($tmpPath);
            $result->addRejected($filename, 'Unable to open temporary file for ZIP entry.', $source);

            return;
        }

        try {
            stream_copy_to_stream($stream, $tmpHandle);
        } finally {
            fclose($tmpHandle);
            fclose($stream);
        }

        try {
            $this->processReceiptUpload($ownerId, $tmpPath, $filename, $source, $result);
        } finally {
            @unlink($tmpPath);
        }
    }

    private function processReceiptUpload(
        string $ownerId,
        string $sourcePath,
        string $originalFilename,
        string $source,
        BulkImportUploadResult $result,
    ): void {
        $violations = $this->validator->validate(new File($sourcePath), [
            new Assert\File(
                maxSize: self::MAX_RECEIPT_UPLOAD_SIZE,
                mimeTypes: self::ALLOWED_RECEIPT_MIME_TYPES,
                maxSizeMessage: 'File is too large. Current import limit is 1 MB.',
                mimeTypesMessage: 'Unsupported file type. Allowed: PDF, JPEG, PNG, WEBP.',
            ),
        ]);

        if (count($violations) > 0) {
            $message = 'Validation failed.';
            foreach ($violations as $violation) {
                $message = (string) $violation->getMessage();
                break;
            }
            $result->addRejected($originalFilename, $message, $source);

            return;
        }

        try {
            $job = ($this->createImportJobHandler)(new CreateImportJobCommand(
                $ownerId,
                $sourcePath,
                $originalFilename,
            ));
        } catch (Throwable $e) {
            $result->addRejected($originalFilename, $e->getMessage(), $source);

            return;
        }

        $result->addAccepted($job, $originalFilename, $source);
    }

    private function isZipUpload(UploadedFile $uploadedFile): bool
    {
        $extension = strtolower((string) pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION));
        if ('zip' === $extension) {
            return true;
        }

        $mimeType = strtolower((string) $uploadedFile->getClientMimeType());

        return in_array($mimeType, self::ZIP_MIME_TYPES, true);
    }

    private function isUploadUsable(UploadedFile $uploadedFile): bool
    {
        if (!$uploadedFile->isValid()) {
            return false;
        }

        $pathname = $uploadedFile->getPathname();

        return '' !== $pathname && is_file($pathname) && is_readable($pathname);
    }

    private function safeClientFilename(UploadedFile $uploadedFile): string
    {
        $filename = trim($uploadedFile->getClientOriginalName());

        return '' === $filename ? 'unknown-file' : $filename;
    }
}
