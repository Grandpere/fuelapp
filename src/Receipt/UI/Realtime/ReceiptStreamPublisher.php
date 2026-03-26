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

namespace App\Receipt\UI\Realtime;

use App\Receipt\Domain\Receipt;
use App\Station\Domain\Station;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;
use Twig\Environment;

final readonly class ReceiptStreamPublisher
{
    public const TOPIC = 'https://fuelapp.local/receipts';

    public function __construct(
        private HubInterface $hub,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    public function publishCreated(Receipt $receipt, ?Station $station): void
    {
        $firstLine = $receipt->lines()[0] ?? null;

        $receiptRow = [
            'id' => $receipt->id()->toString(),
            'issuedAt' => $receipt->issuedAt(),
            'totalCents' => $receipt->totalCents(),
            'vatAmountCents' => $receipt->vatAmountCents(),
            'odometerKilometers' => $receipt->odometerKilometers(),
            'stationName' => $station?->name(),
            'stationStreetName' => $station?->streetName(),
            'stationPostalCode' => $station?->postalCode(),
            'stationCity' => $station?->city(),
            'fuelType' => $firstLine?->fuelType()->value,
            'quantityMilliLiters' => $firstLine?->quantityMilliLiters(),
            'unitPriceDeciCentsPerLiter' => $firstLine?->unitPriceDeciCentsPerLiter(),
            'vatRatePercent' => $firstLine?->vatRatePercent(),
        ];

        try {
            $content = $this->twig->render('receipt/stream/created.stream.html.twig', [
                'receipt' => $receiptRow,
            ]);

            $this->hub->publish(new Update(self::TOPIC, $content));
        } catch (Throwable $exception) {
            $this->logger->warning('receipt.stream.publish_created_failed', [
                'receipt_id' => $receipt->id()->toString(),
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function publishDeleted(string $receiptId): void
    {
        try {
            $content = $this->twig->render('receipt/stream/deleted.stream.html.twig', [
                'receiptId' => $receiptId,
            ]);

            $this->hub->publish(new Update(self::TOPIC, $content));
        } catch (Throwable $exception) {
            $this->logger->warning('receipt.stream.publish_deleted_failed', [
                'receipt_id' => $receiptId,
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
