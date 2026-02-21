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

use App\Station\Application\Geocoding\Geocoder;
use App\Station\Application\Message\GeocodeStationAddressMessage;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Enum\GeocodingStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class GeocodeStationAddressMessageHandler
{
    public function __construct(
        private StationRepository $stationRepository,
        private Geocoder $geocoder,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GeocodeStationAddressMessage $message): void
    {
        $this->logger->info('geocoding.job.started', [
            'station_id' => $message->stationId,
            'message' => GeocodeStationAddressMessage::class,
        ]);

        $station = $this->stationRepository->getForSystem($message->stationId);
        if (null === $station) {
            $this->logger->warning('geocoding.job.skipped_station_not_found', [
                'station_id' => $message->stationId,
            ]);

            return;
        }

        if (GeocodingStatus::SUCCESS === $station->geocodingStatus()) {
            $this->logger->info('geocoding.job.skipped_already_success', [
                'station_id' => $station->id()->toString(),
            ]);

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
            $station->markGeocodingFailed($this->buildProviderExceptionReason($throwable));
            $this->stationRepository->save($station);
            $this->logger->error('geocoding.job.failed_exception', [
                'station_id' => $station->id()->toString(),
                'error' => $throwable->getMessage(),
                'exception_class' => $throwable::class,
            ]);

            throw $throwable;
        }

        if (null === $result) {
            $station->markGeocodingFailed('provider_no_result');
            $this->stationRepository->save($station);
            $this->logger->warning('geocoding.job.failed_no_result', [
                'station_id' => $station->id()->toString(),
            ]);

            return;
        }

        $station->markGeocodingSuccess($result->latitudeMicroDegrees, $result->longitudeMicroDegrees);
        $this->stationRepository->save($station);
        $this->logger->info('geocoding.job.succeeded', [
            'station_id' => $station->id()->toString(),
            'latitude_micro_degrees' => $result->latitudeMicroDegrees,
            'longitude_micro_degrees' => $result->longitudeMicroDegrees,
        ]);
    }

    private function buildProviderExceptionReason(Throwable $throwable): string
    {
        $reason = sprintf('provider_exception: %s', trim($throwable->getMessage()));

        return mb_substr($reason, 0, 500);
    }
}
