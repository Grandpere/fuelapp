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

namespace App\Tests\Integration\Station\Application;

use App\Station\Application\Command\CreateStationCommand;
use App\Station\Application\Command\CreateStationHandler;
use App\Station\Application\Message\GeocodeStationAddressMessage;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class GeocodingMessageDispatchTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CreateStationHandler $createStationHandler;
    private InMemoryTransport $asyncTransport;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service not found.');
        }
        $this->em = $em;

        $handler = self::getContainer()->get(CreateStationHandler::class);
        if (!$handler instanceof CreateStationHandler) {
            throw new RuntimeException('CreateStationHandler service not found.');
        }
        $this->createStationHandler = $handler;

        $transport = self::getContainer()->get('messenger.transport.async');
        if (!$transport instanceof InMemoryTransport) {
            throw new RuntimeException('Async transport is not in-memory in test env.');
        }
        $this->asyncTransport = $transport;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE stations RESTART IDENTITY CASCADE');
        $this->asyncTransport->reset();
    }

    public function testCreateStationDispatchesGeocodingMessage(): void
    {
        $station = ($this->createStationHandler)(new CreateStationCommand(
            'Total',
            'Rue A',
            '75001',
            'Paris',
            null,
            null,
        ));

        $sent = $this->asyncTransport->getSent();
        self::assertCount(1, $sent);

        $message = $sent[0]->getMessage();
        self::assertInstanceOf(GeocodeStationAddressMessage::class, $message);
        self::assertSame($station->id()->toString(), $message->stationId);
    }

    public function testCreateStationWithCoordinatesDoesNotDispatchGeocodingMessage(): void
    {
        ($this->createStationHandler)(new CreateStationCommand(
            'Total',
            'Rue A',
            '75001',
            'Paris',
            48856600,
            2352200,
        ));

        self::assertCount(0, $this->asyncTransport->getSent());
    }
}
