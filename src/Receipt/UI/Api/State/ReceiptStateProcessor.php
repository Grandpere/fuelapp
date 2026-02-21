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

namespace App\Receipt\UI\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Receipt\Application\Command\CreateReceiptLineCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationCommand;
use App\Receipt\Application\Command\CreateReceiptWithStationHandler;
use App\Receipt\Domain\Enum\FuelType;
use App\Receipt\UI\Api\Resource\Input\ReceiptInput;
use App\Receipt\UI\Api\Resource\Output\ReceiptLineOutput;
use App\Receipt\UI\Api\Resource\Output\ReceiptOutput;
use App\Receipt\UI\Realtime\ReceiptStreamPublisher;
use App\Station\Application\Repository\StationRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ReceiptInput, ReceiptOutput>
 */
final readonly class ReceiptStateProcessor implements ProcessorInterface
{
    public function __construct(
        private CreateReceiptWithStationHandler $handler,
        private StationRepository $stationRepository,
        private ReceiptStreamPublisher $streamPublisher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReceiptOutput
    {
        $issuedAt = $data->issuedAt ?? new DateTimeImmutable();
        $lines = [];
        foreach ($data->lines ?? [] as $line) {
            if (
                null === $line->fuelType
                || null === $line->quantityMilliLiters
                || null === $line->unitPriceDeciCentsPerLiter
                || null === $line->vatRatePercent
            ) {
                throw new InvalidArgumentException('Receipt line fields are required');
            }

            $lines[] = new CreateReceiptLineCommand(
                FuelType::from($line->fuelType),
                $line->quantityMilliLiters,
                $line->unitPriceDeciCentsPerLiter,
                $line->vatRatePercent,
            );
        }

        $receipt = ($this->handler)(new CreateReceiptWithStationCommand(
            $issuedAt,
            $lines,
            $data->stationName ?? '',
            $data->stationStreetName ?? '',
            $data->stationPostalCode ?? '',
            $data->stationCity ?? '',
            $data->latitudeMicroDegrees,
            $data->longitudeMicroDegrees,
            $data->vehicleId,
        ));

        $station = null;
        if (null !== $receipt->stationId()) {
            $station = $this->stationRepository->get($receipt->stationId()->toString());
        }
        $this->streamPublisher->publishCreated($receipt, $station);

        $outputLines = [];
        foreach ($receipt->lines() as $line) {
            $outputLines[] = new ReceiptLineOutput(
                $line->fuelType()->value,
                $line->quantityMilliLiters(),
                $line->unitPriceDeciCentsPerLiter(),
                $line->lineTotalCents(),
                $line->vatRatePercent(),
                $line->vatAmountCents(),
            );
        }

        return new ReceiptOutput(
            $receipt->id()->toString(),
            $receipt->issuedAt(),
            $receipt->totalCents(),
            $receipt->vatAmountCents(),
            null === $receipt->stationId() ? null : Uuid::fromString($receipt->stationId()->toString()),
            null === $receipt->vehicleId() ? null : Uuid::fromString($receipt->vehicleId()->toString()),
            $outputLines,
        );
    }
}
