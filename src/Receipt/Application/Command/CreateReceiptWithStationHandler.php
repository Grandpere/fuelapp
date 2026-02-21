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

namespace App\Receipt\Application\Command;

use App\Receipt\Domain\Receipt;
use App\Station\Application\Command\CreateStationCommand;
use App\Station\Application\Command\CreateStationHandler;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\ValueObject\StationId;
use App\Vehicle\Domain\ValueObject\VehicleId;
use Throwable;

final class CreateReceiptWithStationHandler
{
    public function __construct(
        private readonly CreateReceiptHandler $receiptHandler,
        private readonly StationRepository $stationRepository,
        private readonly CreateStationHandler $stationHandler,
    ) {
    }

    public function __invoke(CreateReceiptWithStationCommand $command): Receipt
    {
        $station = $this->stationRepository->findByIdentity(
            $command->stationName,
            $command->stationStreetName,
            $command->stationPostalCode,
            $command->stationCity,
        );

        if (null === $station) {
            try {
                $station = ($this->stationHandler)(new CreateStationCommand(
                    $command->stationName,
                    $command->stationStreetName,
                    $command->stationPostalCode,
                    $command->stationCity,
                    $command->latitudeMicroDegrees,
                    $command->longitudeMicroDegrees,
                ));
            } catch (Throwable $e) {
                // If a concurrent request created the station, re-fetch it and continue.
                $station = $this->stationRepository->findByIdentity(
                    $command->stationName,
                    $command->stationStreetName,
                    $command->stationPostalCode,
                    $command->stationCity,
                );

                if (null === $station) {
                    throw $e;
                }
            }
        }

        return ($this->receiptHandler)(new CreateReceiptCommand(
            $command->issuedAt,
            $command->lines,
            StationId::fromString($station->id()->toString()),
            null === $command->vehicleId ? null : VehicleId::fromString($command->vehicleId),
            $command->ownerId,
        ));
    }
}
