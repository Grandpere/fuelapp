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

namespace App\Import\Domain;

use App\Import\Domain\Enum\ImportJobStatus;
use App\Import\Domain\ValueObject\ImportJobId;
use DateTimeImmutable;

final class ImportJob
{
    private ImportJobId $id;
    private string $ownerId;
    private ImportJobStatus $status;
    private string $storage;
    private string $filePath;
    private string $originalFilename;
    private string $mimeType;
    private int $fileSizeBytes;
    private string $fileChecksumSha256;
    private ?string $errorPayload;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private ?DateTimeImmutable $startedAt;
    private ?DateTimeImmutable $completedAt;
    private ?DateTimeImmutable $failedAt;
    private DateTimeImmutable $retentionUntil;

    private function __construct(
        ImportJobId $id,
        string $ownerId,
        ImportJobStatus $status,
        string $storage,
        string $filePath,
        string $originalFilename,
        string $mimeType,
        int $fileSizeBytes,
        string $fileChecksumSha256,
        ?string $errorPayload,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $completedAt,
        ?DateTimeImmutable $failedAt,
        DateTimeImmutable $retentionUntil,
    ) {
        $this->id = $id;
        $this->ownerId = $ownerId;
        $this->status = $status;
        $this->storage = $storage;
        $this->filePath = $filePath;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->fileSizeBytes = $fileSizeBytes;
        $this->fileChecksumSha256 = $fileChecksumSha256;
        $this->errorPayload = $errorPayload;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->startedAt = $startedAt;
        $this->completedAt = $completedAt;
        $this->failedAt = $failedAt;
        $this->retentionUntil = $retentionUntil;
    }

    public static function createQueued(
        string $ownerId,
        string $storage,
        string $filePath,
        string $originalFilename,
        string $mimeType,
        int $fileSizeBytes,
        string $fileChecksumSha256,
        ?DateTimeImmutable $now = null,
        ?DateTimeImmutable $retentionUntil = null,
    ): self {
        $now ??= new DateTimeImmutable();

        return new self(
            ImportJobId::new(),
            $ownerId,
            ImportJobStatus::QUEUED,
            $storage,
            $filePath,
            $originalFilename,
            $mimeType,
            $fileSizeBytes,
            $fileChecksumSha256,
            null,
            $now,
            $now,
            null,
            null,
            null,
            $retentionUntil ?? $now->modify('+90 days'),
        );
    }

    public static function reconstitute(
        ImportJobId $id,
        string $ownerId,
        ImportJobStatus $status,
        string $storage,
        string $filePath,
        string $originalFilename,
        string $mimeType,
        int $fileSizeBytes,
        string $fileChecksumSha256,
        ?string $errorPayload,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $completedAt,
        ?DateTimeImmutable $failedAt,
        DateTimeImmutable $retentionUntil,
    ): self {
        return new self(
            $id,
            $ownerId,
            $status,
            $storage,
            $filePath,
            $originalFilename,
            $mimeType,
            $fileSizeBytes,
            $fileChecksumSha256,
            $errorPayload,
            $createdAt,
            $updatedAt,
            $startedAt,
            $completedAt,
            $failedAt,
            $retentionUntil,
        );
    }

    public function id(): ImportJobId
    {
        return $this->id;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function status(): ImportJobStatus
    {
        return $this->status;
    }

    public function storage(): string
    {
        return $this->storage;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function fileSizeBytes(): int
    {
        return $this->fileSizeBytes;
    }

    public function fileChecksumSha256(): string
    {
        return $this->fileChecksumSha256;
    }

    public function errorPayload(): ?string
    {
        return $this->errorPayload;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function failedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function retentionUntil(): DateTimeImmutable
    {
        return $this->retentionUntil;
    }

    public function markProcessing(?DateTimeImmutable $at = null): void
    {
        $at ??= new DateTimeImmutable();

        $this->status = ImportJobStatus::PROCESSING;
        $this->startedAt = $at;
        $this->failedAt = null;
        $this->completedAt = null;
        $this->errorPayload = null;
        $this->updatedAt = $at;
    }

    public function markProcessed(?DateTimeImmutable $at = null): void
    {
        $at ??= new DateTimeImmutable();

        $this->status = ImportJobStatus::PROCESSED;
        $this->completedAt = $at;
        $this->failedAt = null;
        $this->errorPayload = null;
        $this->updatedAt = $at;
    }

    public function markProcessedWithPayload(string $payload, ?DateTimeImmutable $at = null): void
    {
        $this->markProcessed($at);
        $this->errorPayload = mb_substr($payload, 0, 5000);
    }

    public function markFailed(string $errorPayload, ?DateTimeImmutable $at = null): void
    {
        $at ??= new DateTimeImmutable();

        $this->status = ImportJobStatus::FAILED;
        $this->failedAt = $at;
        $this->errorPayload = mb_substr($errorPayload, 0, 5000);
        $this->updatedAt = $at;
    }

    public function markNeedsReview(?string $payload = null, ?DateTimeImmutable $at = null): void
    {
        $at ??= new DateTimeImmutable();

        $this->status = ImportJobStatus::NEEDS_REVIEW;
        $this->completedAt = null;
        $this->failedAt = null;
        $this->errorPayload = null !== $payload ? mb_substr($payload, 0, 5000) : null;
        $this->updatedAt = $at;
    }

    public function markDuplicate(string $payload, ?DateTimeImmutable $at = null): void
    {
        $at ??= new DateTimeImmutable();

        $this->status = ImportJobStatus::DUPLICATE;
        $this->completedAt = $at;
        $this->failedAt = null;
        $this->errorPayload = mb_substr($payload, 0, 5000);
        $this->updatedAt = $at;
    }

    public function markQueuedForRetry(?DateTimeImmutable $at = null): void
    {
        $at ??= new DateTimeImmutable();

        $this->status = ImportJobStatus::QUEUED;
        $this->startedAt = null;
        $this->completedAt = null;
        $this->failedAt = null;
        $this->errorPayload = null;
        $this->updatedAt = $at;
    }
}
