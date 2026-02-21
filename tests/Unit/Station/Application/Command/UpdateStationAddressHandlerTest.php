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

use App\Station\Application\Command\UpdateStationAddressCommand;
use App\Station\Application\Command\UpdateStationAddressHandler;
use App\Station\Application\Message\GeocodeStationAddressMessage;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class UpdateStationAddressHandlerTest extends TestCase
{
    public function testItDispatchesGeocodingWhenAddressChangesAndStationWasNotPending(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', 48856600, 2352200);
        $repository = new InMemoryUpdateStationRepository($station);
        $messageBus = new RecordingUpdateMessageBus();
        $handler = new UpdateStationAddressHandler($repository, $messageBus);

        $updated = $handler(new UpdateStationAddressCommand(
            $station->id()->toString(),
            'Total Access',
            'Rue B',
            '75002',
            'Paris',
        ));

        self::assertNotNull($updated);
        self::assertCount(1, $messageBus->messages);
        self::assertInstanceOf(GeocodeStationAddressMessage::class, $messageBus->messages[0]);
    }

    public function testItDoesNotDispatchWhenAddressIsUnchanged(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', 48856600, 2352200);
        $repository = new InMemoryUpdateStationRepository($station);
        $messageBus = new RecordingUpdateMessageBus();
        $handler = new UpdateStationAddressHandler($repository, $messageBus);

        $updated = $handler(new UpdateStationAddressCommand(
            $station->id()->toString(),
            'Total',
            'Rue A',
            '75001',
            'Paris',
        ));

        self::assertNotNull($updated);
        self::assertCount(0, $messageBus->messages);
        self::assertSame(0, $repository->saveCount);
    }

    public function testItDoesNotRedispatchWhenAlreadyPending(): void
    {
        $station = Station::create('Total', 'Rue A', '75001', 'Paris', null, null);
        $repository = new InMemoryUpdateStationRepository($station);
        $messageBus = new RecordingUpdateMessageBus();
        $handler = new UpdateStationAddressHandler($repository, $messageBus);

        $updated = $handler(new UpdateStationAddressCommand(
            $station->id()->toString(),
            'Total Access',
            'Rue B',
            '75002',
            'Paris',
        ));

        self::assertNotNull($updated);
        self::assertCount(0, $messageBus->messages);
        self::assertSame(1, $repository->saveCount);
    }
}

final class RecordingUpdateMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}

final class InMemoryUpdateStationRepository implements StationRepository
{
    private ?Station $station;
    public int $saveCount = 0;

    public function __construct(?Station $station)
    {
        $this->station = $station;
    }

    public function save(Station $station): void
    {
        ++$this->saveCount;
        $this->station = $station;
    }

    public function get(string $id): ?Station
    {
        if (null === $this->station) {
            return null;
        }

        return $this->station->id()->toString() === $id ? $this->station : null;
    }

    public function getForSystem(string $id): ?Station
    {
        return $this->get($id);
    }

    public function delete(string $id): void
    {
        if (null !== $this->station && $this->station->id()->toString() === $id) {
            $this->station = null;
        }
    }

    public function getByIds(array $ids): array
    {
        if (null === $this->station) {
            return [];
        }

        if (!in_array($this->station->id()->toString(), $ids, true)) {
            return [];
        }

        return [$this->station->id()->toString() => $this->station];
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        if (null === $this->station) {
            return null;
        }

        return (
            $this->station->name() === $name
            && $this->station->streetName() === $streetName
            && $this->station->postalCode() === $postalCode
            && $this->station->city() === $city
        ) ? $this->station : null;
    }

    public function all(): iterable
    {
        return $this->station ? [$this->station] : [];
    }
}
