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

namespace App\Import\Infrastructure\Persistence\Doctrine\Entity;

use App\Import\Domain\Enum\ImportJobStatus;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
#[ORM\Index(name: 'idx_import_jobs_owner_status', columns: ['owner_id', 'status'])]
#[ORM\Index(name: 'idx_import_jobs_created_at', columns: ['created_at'])]
class ImportJobEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserEntity $owner;

    #[ORM\Column(type: 'string', enumType: ImportJobStatus::class, length: 24)]
    private ImportJobStatus $status;

    #[ORM\Column(type: 'string', length: 32)]
    private string $storage;

    #[ORM\Column(type: 'string', length: 512, name: 'file_path')]
    private string $filePath;

    #[ORM\Column(type: 'string', length: 255, name: 'original_filename')]
    private string $originalFilename;

    #[ORM\Column(type: 'string', length: 191, name: 'mime_type')]
    private string $mimeType;

    #[ORM\Column(type: 'bigint', name: 'file_size_bytes')]
    private int $fileSizeBytes;

    #[ORM\Column(type: 'string', length: 64, name: 'file_checksum_sha256')]
    private string $fileChecksumSha256;

    #[ORM\Column(type: 'text', nullable: true, name: 'error_payload')]
    private ?string $errorPayload = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'started_at')]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'completed_at')]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'failed_at')]
    private ?DateTimeImmutable $failedAt = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'retention_until')]
    private DateTimeImmutable $retentionUntil;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getOwner(): UserEntity
    {
        return $this->owner;
    }

    public function setOwner(UserEntity $owner): void
    {
        $this->owner = $owner;
    }

    public function getStatus(): ImportJobStatus
    {
        return $this->status;
    }

    public function setStatus(ImportJobStatus $status): void
    {
        $this->status = $status;
    }

    public function getStorage(): string
    {
        return $this->storage;
    }

    public function setStorage(string $storage): void
    {
        $this->storage = $storage;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): void
    {
        $this->originalFilename = $originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileSizeBytes(): int
    {
        return $this->fileSizeBytes;
    }

    public function setFileSizeBytes(int $fileSizeBytes): void
    {
        $this->fileSizeBytes = $fileSizeBytes;
    }

    public function getFileChecksumSha256(): string
    {
        return $this->fileChecksumSha256;
    }

    public function setFileChecksumSha256(string $fileChecksumSha256): void
    {
        $this->fileChecksumSha256 = $fileChecksumSha256;
    }

    public function getErrorPayload(): ?string
    {
        return $this->errorPayload;
    }

    public function setErrorPayload(?string $errorPayload): void
    {
        $this->errorPayload = $errorPayload;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeImmutable $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function getFailedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function setFailedAt(?DateTimeImmutable $failedAt): void
    {
        $this->failedAt = $failedAt;
    }

    public function getRetentionUntil(): DateTimeImmutable
    {
        return $this->retentionUntil;
    }

    public function setRetentionUntil(DateTimeImmutable $retentionUntil): void
    {
        $this->retentionUntil = $retentionUntil;
    }
}
