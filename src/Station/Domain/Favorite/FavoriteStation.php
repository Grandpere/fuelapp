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

namespace App\Station\Domain\Favorite;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class FavoriteStation
{
    private function __construct(
        private string $id,
        private string $ownerId,
        private string $stationId,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $ownerId, string $stationId): self
    {
        return new self(Uuid::v7()->toRfc4122(), $ownerId, $stationId, new DateTimeImmutable());
    }

    public static function reconstitute(string $id, string $ownerId, string $stationId, DateTimeImmutable $createdAt): self
    {
        return new self($id, $ownerId, $stationId, $createdAt);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function stationId(): string
    {
        return $this->stationId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
