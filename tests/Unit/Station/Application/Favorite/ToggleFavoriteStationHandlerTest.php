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

namespace App\Tests\Unit\Station\Application\Favorite;

use App\Station\Application\Favorite\FavoriteStationRepository;
use App\Station\Application\Favorite\ToggleFavoriteStationCommand;
use App\Station\Application\Favorite\ToggleFavoriteStationHandler;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Favorite\FavoriteStation;
use App\Station\Domain\Station;
use App\Station\Domain\ValueObject\StationId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ToggleFavoriteStationHandlerTest extends TestCase
{
    public function testItCreatesFavoriteWhenMissing(): void
    {
        $station = $this->station();
        $favorites = new InMemoryFavoriteStationRepository();
        $handler = new ToggleFavoriteStationHandler($favorites, new InMemoryStationRepository($station));

        $result = $handler(new ToggleFavoriteStationCommand('owner-1', $station->id()->toString()));

        self::assertTrue($result);
        self::assertNotNull($favorites->findByOwnerAndStation('owner-1', $station->id()->toString()));
    }

    public function testItRemovesFavoriteWhenExisting(): void
    {
        $station = $this->station();
        $favorites = new InMemoryFavoriteStationRepository();
        $favorites->save(FavoriteStation::reconstitute('favorite-1', 'owner-1', $station->id()->toString(), new DateTimeImmutable('2026-04-30 12:00:00')));
        $handler = new ToggleFavoriteStationHandler($favorites, new InMemoryStationRepository($station));

        $result = $handler(new ToggleFavoriteStationCommand('owner-1', $station->id()->toString()));

        self::assertFalse($result);
        self::assertNull($favorites->findByOwnerAndStation('owner-1', $station->id()->toString()));
    }

    public function testItRejectsMissingStation(): void
    {
        $handler = new ToggleFavoriteStationHandler(new InMemoryFavoriteStationRepository(), new InMemoryStationRepository(null));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Station not found.');

        $handler(new ToggleFavoriteStationCommand('owner-1', 'missing-station'));
    }

    private function station(): Station
    {
        return Station::reconstitute(
            StationId::fromString('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'),
            'Favorite Station',
            '1 Main Street',
            '75001',
            'Paris',
            null,
            null,
        );
    }
}

final class InMemoryFavoriteStationRepository implements FavoriteStationRepository
{
    /** @var array<string, FavoriteStation> */
    private array $items = [];

    public function save(FavoriteStation $favorite): void
    {
        $this->items[$favorite->ownerId().'|'.$favorite->stationId()] = $favorite;
    }

    public function findByOwnerAndStation(string $ownerId, string $stationId): ?FavoriteStation
    {
        return $this->items[$ownerId.'|'.$stationId] ?? null;
    }

    public function deleteByOwnerAndStation(string $ownerId, string $stationId): void
    {
        unset($this->items[$ownerId.'|'.$stationId]);
    }

    public function favoriteStationIds(string $ownerId, array $stationIds): array
    {
        $result = [];
        foreach ($stationIds as $stationId) {
            if (isset($this->items[$ownerId.'|'.$stationId])) {
                $result[] = $stationId;
            }
        }

        return $result;
    }
}

final class InMemoryStationRepository implements StationRepository
{
    public function __construct(private readonly ?Station $station)
    {
    }

    public function save(Station $station): void
    {
    }

    public function get(string $id): ?Station
    {
        return $this->station && $this->station->id()->toString() === $id ? $this->station : null;
    }

    public function getForSystem(string $id): ?Station
    {
        return $this->get($id);
    }

    public function deleteForSystem(string $id): void
    {
    }

    public function delete(string $id): void
    {
    }

    public function getByIds(array $ids): array
    {
        if (null === $this->station || !in_array($this->station->id()->toString(), $ids, true)) {
            return [];
        }

        return [$this->station->id()->toString() => $this->station];
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        return null;
    }

    public function all(): iterable
    {
        if (null !== $this->station) {
            yield $this->station;
        }
    }

    public function allForSystem(): iterable
    {
        return $this->all();
    }
}
