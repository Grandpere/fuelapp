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

namespace App\Admin\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'admin_audit_logs')]
#[ORM\Index(name: 'idx_admin_audit_action', columns: ['action'])]
#[ORM\Index(name: 'idx_admin_audit_target', columns: ['target_type', 'target_id'])]
#[ORM\Index(name: 'idx_admin_audit_actor', columns: ['actor_id'])]
#[ORM\Index(name: 'idx_admin_audit_correlation', columns: ['correlation_id'])]
#[ORM\Index(name: 'idx_admin_audit_created_at', columns: ['created_at'])]
class AdminAuditLogEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME, name: 'actor_id', nullable: true)]
    private ?Uuid $actorId = null;

    #[ORM\Column(type: 'string', name: 'actor_email', length: 180, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $action;

    #[ORM\Column(type: 'string', name: 'target_type', length: 120)]
    private string $targetType;

    #[ORM\Column(type: 'string', name: 'target_id', length: 120)]
    private string $targetId;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', name: 'diff_summary')]
    private array $diffSummary = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $metadata = [];

    #[ORM\Column(type: 'string', name: 'correlation_id', length: 80)]
    private string $correlationId;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private DateTimeImmutable $createdAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getActorId(): ?Uuid
    {
        return $this->actorId;
    }

    public function setActorId(?Uuid $actorId): void
    {
        $this->actorId = $actorId;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): void
    {
        $this->actorEmail = $actorEmail;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): void
    {
        $this->targetType = $targetType;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function setTargetId(string $targetId): void
    {
        $this->targetId = $targetId;
    }

    /** @return array<string, mixed> */
    public function getDiffSummary(): array
    {
        return $this->diffSummary;
    }

    /** @param array<string, mixed> $diffSummary */
    public function setDiffSummary(array $diffSummary): void
    {
        $this->diffSummary = $diffSummary;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
