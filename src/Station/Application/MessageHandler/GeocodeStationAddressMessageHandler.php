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

namespace App\Station\Application\MessageHandler;

use App\Station\Application\Geocoding\GeocoderInterface;
use App\Station\Application\Message\GeocodeStationAddressMessage;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Enum\GeocodingStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class GeocodeStationAddressMessageHandler
{
    public function __construct(
        private StationRepository $stationRepository,
        private GeocoderInterface $geocoder,
    ) {
    }

    public function __invoke(GeocodeStationAddressMessage $message): void
    {
        $station = $this->stationRepository->getForSystem($message->stationId);
        if (null === $station) {
            return;
        }

        if (GeocodingStatus::SUCCESS === $station->geocodingStatus()) {
            return;
        }

        try {
            $result = $this->geocoder->geocode(
                $station->name(),
                $station->streetName(),
                $station->postalCode(),
                $station->city(),
            );
        } catch (Throwable $throwable) {
            $station->markGeocodingFailed(sprintf('provider_exception: %s', $throwable->getMessage()));
            $this->stationRepository->save($station);

            throw $throwable;
        }

        if (null === $result) {
            $station->markGeocodingFailed('provider_no_result');
            $this->stationRepository->save($station);

            return;
        }

        $station->markGeocodingSuccess($result->latitudeMicroDegrees, $result->longitudeMicroDegrees);
        $this->stationRepository->save($station);
    }
}
