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

namespace App\Analytics\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'analytics_projection_states')]
class AnalyticsProjectionStateEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $projection;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'last_refreshed_at')]
    private ?DateTimeImmutable $lastRefreshedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'source_max_issued_at')]
    private ?DateTimeImmutable $sourceMaxIssuedAt = null;

    #[ORM\Column(type: 'integer', name: 'source_receipt_count')]
    private int $sourceReceiptCount = 0;

    #[ORM\Column(type: 'integer', name: 'rows_materialized')]
    private int $rowsMaterialized = 0;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = 'pending';

    #[ORM\Column(type: 'text', nullable: true, name: 'last_error')]
    private ?string $lastError = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private DateTimeImmutable $updatedAt;

    public function getProjection(): string
    {
        return $this->projection;
    }

    public function setProjection(string $projection): void
    {
        $this->projection = $projection;
    }

    public function getLastRefreshedAt(): ?DateTimeImmutable
    {
        return $this->lastRefreshedAt;
    }

    public function setLastRefreshedAt(?DateTimeImmutable $lastRefreshedAt): void
    {
        $this->lastRefreshedAt = $lastRefreshedAt;
    }

    public function getSourceMaxIssuedAt(): ?DateTimeImmutable
    {
        return $this->sourceMaxIssuedAt;
    }

    public function setSourceMaxIssuedAt(?DateTimeImmutable $sourceMaxIssuedAt): void
    {
        $this->sourceMaxIssuedAt = $sourceMaxIssuedAt;
    }

    public function getSourceReceiptCount(): int
    {
        return $this->sourceReceiptCount;
    }

    public function setSourceReceiptCount(int $sourceReceiptCount): void
    {
        $this->sourceReceiptCount = $sourceReceiptCount;
    }

    public function getRowsMaterialized(): int
    {
        return $this->rowsMaterialized;
    }

    public function setRowsMaterialized(int $rowsMaterialized): void
    {
        $this->rowsMaterialized = $rowsMaterialized;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): void
    {
        $this->lastError = $lastError;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
