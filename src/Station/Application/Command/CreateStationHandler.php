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

use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;

readonly class CreateStationHandler
{
    public function __construct(private StationRepository $repository)
    {
    }

    public function __invoke(CreateStationCommand $command): Station
    {
        $station = Station::create(
            $command->name,
            $command->streetName,
            $command->postalCode,
            $command->city,
            $command->latitudeMicroDegrees,
            $command->longitudeMicroDegrees,
        );

        $this->repository->save($station);

        return $station;
    }
}
