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

namespace App\Tests\Unit\Station\Application\Command;

use App\Station\Application\Command\CreateStationCommand;
use App\Station\Application\Command\CreateStationHandler;
use App\Station\Application\Message\GeocodeStationAddressMessage;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Domain\Station;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateStationHandlerTest extends TestCase
{
    public function testItDispatchesGeocodingMessageForPendingStation(): void
    {
        $repository = new InMemoryStationRepository();
        $messageBus = new RecordingMessageBus();
        $handler = new CreateStationHandler($repository, $messageBus);

        $station = $handler(new CreateStationCommand('Total', 'Rue A', '75001', 'Paris', null, null));

        self::assertSame(GeocodingStatus::PENDING, $station->geocodingStatus());
        self::assertCount(1, $messageBus->messages);
        self::assertInstanceOf(GeocodeStationAddressMessage::class, $messageBus->messages[0]);
        self::assertSame($station->id()->toString(), $messageBus->messages[0]->stationId);
    }

    public function testItDoesNotDispatchGeocodingMessageWhenCoordinatesAlreadyPresent(): void
    {
        $repository = new InMemoryStationRepository();
        $messageBus = new RecordingMessageBus();
        $handler = new CreateStationHandler($repository, $messageBus);

        $station = $handler(new CreateStationCommand('Total', 'Rue A', '75001', 'Paris', 48856600, 2352200));

        self::assertSame(GeocodingStatus::SUCCESS, $station->geocodingStatus());
        self::assertCount(0, $messageBus->messages);
    }
}

final class RecordingMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}

final class InMemoryStationRepository implements StationRepository
{
    /** @var array<string, Station> */
    private array $items = [];

    public function save(Station $station): void
    {
        $this->items[$station->id()->toString()] = $station;
    }

    public function get(string $id): ?Station
    {
        return $this->items[$id] ?? null;
    }

    public function getForSystem(string $id): ?Station
    {
        return $this->get($id);
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }

    public function deleteForSystem(string $id): void
    {
        $this->delete($id);
    }

    public function getByIds(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            if (isset($this->items[$id])) {
                $results[$id] = $this->items[$id];
            }
        }

        return $results;
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        foreach ($this->items as $station) {
            if (
                $station->name() === $name
                && $station->streetName() === $streetName
                && $station->postalCode() === $postalCode
                && $station->city() === $city
            ) {
                return $station;
            }
        }

        return null;
    }

    public function all(): iterable
    {
        return array_values($this->items);
    }

    public function allForSystem(): iterable
    {
        return $this->all();
    }
}
