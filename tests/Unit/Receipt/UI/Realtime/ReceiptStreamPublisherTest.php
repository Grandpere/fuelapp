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

namespace App\Tests\Unit\Receipt\UI\Realtime;

use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\Domain\Receipt;
use App\Receipt\Domain\ReceiptLine;
use App\Receipt\UI\Realtime\ReceiptStreamPublisher;
use App\Station\Domain\Station;
use App\Station\Domain\ValueObject\StationId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

final class ReceiptStreamPublisherTest extends TestCase
{
    public function testPublishCreatedDoesNotThrowWhenMercurePublishFails(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->willThrowException(new RuntimeException('mercure down'));

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->willReturn('<turbo-stream></turbo-stream>');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'receipt.stream.publish_created_failed',
                self::arrayHasKey('receipt_id'),
            );

        $publisher = new ReceiptStreamPublisher($hub, $twig, $logger);

        $publisher->publishCreated($this->createReceipt(), $this->createStation());
    }

    public function testPublishDeletedDoesNotThrowWhenTwigRenderFails(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->willThrowException(new RuntimeException('twig failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'receipt.stream.publish_deleted_failed',
                self::arrayHasKey('receipt_id'),
            );

        $publisher = new ReceiptStreamPublisher($hub, $twig, $logger);

        $publisher->publishDeleted(Uuid::v7()->toRfc4122());
    }

    private function createReceipt(): Receipt
    {
        $line = ReceiptLine::create(FuelType::DIESEL, 40_400, 1_769, 20);

        return Receipt::create(
            new DateTimeImmutable('2026-03-05 14:20:00'),
            [$line],
            StationId::fromString(Uuid::v7()->toRfc4122()),
            null,
            120_450,
        );
    }

    private function createStation(): Station
    {
        return Station::create(
            'PETRO EST',
            'LECLERC SEZANNE HYPER',
            '51120',
            'SEZANNE',
            null,
            null,
        );
    }
}
