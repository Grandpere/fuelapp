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

namespace App\PublicFuelStation\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'public_fuel_station_sync_runs')]
#[ORM\Index(name: 'public_fuel_station_sync_run_started_idx', columns: ['started_at'])]
class PublicFuelStationSyncRunEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 2048, name: 'source_url')]
    private string $sourceUrl;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'running';

    #[ORM\Column(type: 'datetime_immutable', name: 'started_at')]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'completed_at')]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'integer', name: 'processed_count')]
    private int $processedCount = 0;

    #[ORM\Column(type: 'integer', name: 'upserted_count')]
    private int $upsertedCount = 0;

    #[ORM\Column(type: 'integer', name: 'rejected_count')]
    private int $rejectedCount = 0;

    #[ORM\Column(type: 'text', nullable: true, name: 'error_message')]
    private ?string $errorMessage = null;

    public function __construct(string $sourceUrl)
    {
        $this->id = Uuid::v7();
        $this->sourceUrl = $sourceUrl;
        $this->startedAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function getUpsertedCount(): int
    {
        return $this->upsertedCount;
    }

    public function getRejectedCount(): int
    {
        return $this->rejectedCount;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function finish(string $status, int $processedCount, int $upsertedCount, int $rejectedCount, ?string $errorMessage = null): void
    {
        $this->status = $status;
        $this->processedCount = $processedCount;
        $this->upsertedCount = $upsertedCount;
        $this->rejectedCount = $rejectedCount;
        $this->errorMessage = null === $errorMessage ? null : mb_substr($errorMessage, 0, 2000);
        $this->completedAt = new DateTimeImmutable();
    }
}
