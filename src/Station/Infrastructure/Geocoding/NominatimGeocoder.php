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

namespace App\Station\Infrastructure\Geocoding;

use App\Station\Application\Geocoding\GeocodedAddress;
use App\Station\Application\Geocoding\Geocoder;
use RuntimeException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NominatimGeocoder implements Geocoder
{
    private const CACHE_KEY_PREFIX = 'station_nominatim_geocode_';

    public function __construct(
        private HttpClientInterface $nominatimHttpClient,
        private RateLimiterFactory $nominatimRateLimiter,
        private string $baseUri,
        private CacheInterface $cache,
        private string $userAgent,
        private ?string $contactEmail = null,
    ) {
    }

    public function geocode(string $name, string $streetName, string $postalCode, string $city): ?GeocodedAddress
    {
        $query = $this->buildQuery($name, $streetName, $postalCode, $city);
        if ('' === $query) {
            return null;
        }

        $cacheKey = self::CACHE_KEY_PREFIX.sha1(mb_strtolower($query));

        /** @var array{found: bool, latitudeMicroDegrees?: int, longitudeMicroDegrees?: int} $cached */
        $cached = $this->cache->get($cacheKey, function (ItemInterface $item) use ($query): array {
            $item->expiresAfter(86400);

            return $this->requestAndNormalize($query);
        });

        if (false === $cached['found']) {
            return null;
        }

        $latitudeMicroDegrees = $cached['latitudeMicroDegrees'] ?? null;
        $longitudeMicroDegrees = $cached['longitudeMicroDegrees'] ?? null;
        if (!is_int($latitudeMicroDegrees) || !is_int($longitudeMicroDegrees)) {
            return null;
        }

        return new GeocodedAddress($latitudeMicroDegrees, $longitudeMicroDegrees);
    }

    /** @return array{found: bool, latitudeMicroDegrees?: int, longitudeMicroDegrees?: int} */
    private function requestAndNormalize(string $query): array
    {
        $this->waitForRateLimit();

        try {
            $response = $this->nominatimHttpClient->request('GET', sprintf('%s/search', rtrim($this->baseUri, '/')), [
                'query' => array_filter([
                    'format' => 'jsonv2',
                    'q' => $query,
                    'limit' => 1,
                    'addressdetails' => 0,
                    'email' => $this->contactEmail,
                ], static fn (mixed $value): bool => null !== $value && '' !== $value),
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => $this->userAgent,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if (429 === $statusCode || $statusCode >= 500) {
                throw new RuntimeException(sprintf('Nominatim transient error with status code %d.', $statusCode));
            }

            if ($statusCode >= 400) {
                return ['found' => false];
            }

            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $transportException) {
            throw new RuntimeException(sprintf('Nominatim transport failure: %s', $transportException->getMessage()), 0, $transportException);
        }

        if ([] === $payload) {
            return ['found' => false];
        }

        $first = $payload[0] ?? null;
        if (!is_array($first)) {
            return ['found' => false];
        }

        $latitude = $this->toMicroDegrees($first['lat'] ?? null, -90.0, 90.0);
        $longitude = $this->toMicroDegrees($first['lon'] ?? null, -180.0, 180.0);
        if (null === $latitude || null === $longitude) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'latitudeMicroDegrees' => $latitude,
            'longitudeMicroDegrees' => $longitude,
        ];
    }

    private function waitForRateLimit(): void
    {
        $limiter = $this->nominatimRateLimiter->create('nominatim-geocoder');

        while (true) {
            $limit = $limiter->consume(1);
            if ($limit->isAccepted()) {
                return;
            }

            $retryAfter = $limit->getRetryAfter();
            $sleepSeconds = max(0, $retryAfter->getTimestamp() - time());
            if (0 === $sleepSeconds) {
                usleep(50_000);

                continue;
            }

            usleep($sleepSeconds * 1_000_000);
        }
    }

    private function buildQuery(string $name, string $streetName, string $postalCode, string $city): string
    {
        $parts = [trim($name), trim($streetName), trim($postalCode), trim($city)];

        return implode(', ', array_values(array_filter($parts, static fn (string $value): bool => '' !== $value)));
    }

    private function toMicroDegrees(mixed $value, float $min, float $max): ?int
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        $float = (float) $value;
        if (!is_finite($float) || $float < $min || $float > $max) {
            return null;
        }

        return (int) round($float * 1_000_000);
    }
}
