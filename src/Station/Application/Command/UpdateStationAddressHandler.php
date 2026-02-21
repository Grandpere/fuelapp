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

namespace App\Station\Application\Command;

use App\Station\Application\Message\GeocodeStationAddressMessage;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Enum\GeocodingStatus;
use App\Station\Domain\Station;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class UpdateStationAddressHandler
{
    public function __construct(
        private StationRepository $repository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(UpdateStationAddressCommand $command): ?Station
    {
        $station = $this->repository->get($command->id) ?? $this->repository->getForSystem($command->id);
        if (null === $station) {
            return null;
        }

        $wasPending = GeocodingStatus::PENDING === $station->geocodingStatus();
        $changed = $station->updateAddress(
            $command->name,
            $command->streetName,
            $command->postalCode,
            $command->city,
        );

        if (!$changed) {
            return $station;
        }

        $this->repository->save($station);

        if (!$wasPending) {
            $this->messageBus->dispatch(new GeocodeStationAddressMessage($station->id()->toString()));
        }

        return $station;
    }
}
