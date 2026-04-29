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

use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestion;
use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader;
use App\Receipt\Domain\Receipt;
use App\Station\Application\Command\CreateStationCommand;
use App\Station\Application\Command\CreateStationHandler;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\ValueObject\StationId;
use App\Vehicle\Domain\ValueObject\VehicleId;
use RuntimeException;
use Throwable;

final class CreateReceiptWithStationHandler
{
    public function __construct(
        private readonly CreateReceiptHandler $receiptHandler,
        private readonly StationRepository $stationRepository,
        private readonly CreateStationHandler $stationHandler,
        private readonly PublicFuelStationSuggestionReader $publicFuelStationSuggestionReader,
    ) {
    }

    public function __invoke(CreateReceiptWithStationCommand $command): Receipt
    {
        $station = null;

        if (null !== $command->selectedSuggestionType && null !== $command->selectedSuggestionId) {
            $station = match ($command->selectedSuggestionType) {
                'station' => $this->requireSelectedStation($command->selectedSuggestionId),
                'public' => $this->resolveSelectedPublicSuggestion($command->selectedSuggestionId),
                default => throw new RuntimeException('Selected station suggestion is invalid.'),
            };
        } elseif (null !== $command->selectedStationId) {
            // Backward-compatible path kept while UI/controllers migrate to typed suggestions.
            $station = $this->stationRepository->get($command->selectedStationId);
            if (null === $station) {
                throw new RuntimeException('Selected station was not found.');
            }
        }

        if (null === $station) {
            $station = $this->stationRepository->findByIdentity(
                $command->stationName,
                $command->stationStreetName,
                $command->stationPostalCode,
                $command->stationCity,
            );
        }

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
            $command->odometerKilometers,
        ));
    }

    private function requireSelectedStation(string $stationId): \App\Station\Domain\Station
    {
        $station = $this->stationRepository->get($stationId);
        if (null === $station) {
            throw new RuntimeException('Selected station was not found.');
        }

        return $station;
    }

    private function resolveSelectedPublicSuggestion(string $sourceId): \App\Station\Domain\Station
    {
        $publicStation = $this->publicFuelStationSuggestionReader->getBySourceId($sourceId);
        if (!$publicStation instanceof PublicFuelStationSuggestion) {
            throw new RuntimeException('Selected public station was not found.');
        }

        $station = $this->stationRepository->findByIdentity(
            $publicStation->name,
            $publicStation->streetName,
            $publicStation->postalCode,
            $publicStation->city,
        );
        if (null !== $station) {
            return $station;
        }

        try {
            return ($this->stationHandler)(new CreateStationCommand(
                $publicStation->name,
                $publicStation->streetName,
                $publicStation->postalCode,
                $publicStation->city,
                $publicStation->latitudeMicroDegrees,
                $publicStation->longitudeMicroDegrees,
            ));
        } catch (Throwable $e) {
            $station = $this->stationRepository->findByIdentity(
                $publicStation->name,
                $publicStation->streetName,
                $publicStation->postalCode,
                $publicStation->city,
            );

            if (null === $station) {
                throw $e;
            }

            return $station;
        }
    }
}
