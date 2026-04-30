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

namespace App\Station\Application\Favorite;

use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Favorite\FavoriteStation;
use InvalidArgumentException;

final readonly class ToggleFavoriteStationHandler
{
    public function __construct(
        private FavoriteStationRepository $favoriteStationRepository,
        private StationRepository $stationRepository,
    ) {
    }

    public function __invoke(ToggleFavoriteStationCommand $command): bool
    {
        $station = $this->stationRepository->get($command->stationId);
        if (null === $station) {
            throw new InvalidArgumentException('Station not found.');
        }

        $existing = $this->favoriteStationRepository->findByOwnerAndStation($command->ownerId, $command->stationId);
        if (null !== $existing) {
            $this->favoriteStationRepository->deleteByOwnerAndStation($command->ownerId, $command->stationId);

            return false;
        }

        $this->favoriteStationRepository->save(FavoriteStation::create($command->ownerId, $command->stationId));

        return true;
    }
}
