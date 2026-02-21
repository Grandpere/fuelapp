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

namespace App\Tests\Unit\Station\Infrastructure\Geocoding;

use App\Station\Infrastructure\Geocoding\NominatimGeocoder;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NominatimGeocoderTest extends TestCase
{
    public function testItMapsNominatimCoordinatesToMicroDegrees(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('[{"lat":"48.8566","lon":"2.3522"}]', ['http_code' => 200]),
        ], 'https://nominatim.openstreetmap.org');

        $geocoder = new NominatimGeocoder(
            $httpClient,
            'https://nominatim.openstreetmap.org',
            new ArrayAdapter(),
            'FuelAppGeocoding/1.0 (test@example.com)',
            null,
            0,
        );

        $result = $geocoder->geocode('Total', 'Rue A', '75001', 'Paris');

        self::assertNotNull($result);
        self::assertSame(48856600, $result->latitudeMicroDegrees);
        self::assertSame(2352200, $result->longitudeMicroDegrees);
    }

    public function testItReturnsNullWhenProviderReturnsNoMatch(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('[]', ['http_code' => 200]),
        ], 'https://nominatim.openstreetmap.org');

        $geocoder = new NominatimGeocoder(
            $httpClient,
            'https://nominatim.openstreetmap.org',
            new ArrayAdapter(),
            'FuelAppGeocoding/1.0 (test@example.com)',
            null,
            0,
        );

        $result = $geocoder->geocode('Unknown', 'Nowhere', '00000', 'NoCity');

        self::assertNull($result);
    }

    public function testItThrowsOnTransientProviderStatus(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":"busy"}', ['http_code' => 503]),
        ], 'https://nominatim.openstreetmap.org');

        $geocoder = new NominatimGeocoder(
            $httpClient,
            'https://nominatim.openstreetmap.org',
            new ArrayAdapter(),
            'FuelAppGeocoding/1.0 (test@example.com)',
            null,
            0,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('transient error');

        $geocoder->geocode('Total', 'Rue A', '75001', 'Paris');
    }

    public function testItUsesCacheToAvoidDuplicateProviderCalls(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('[{"lat":"48.8566","lon":"2.3522"}]', ['http_code' => 200]);
        }, 'https://nominatim.openstreetmap.org');

        $geocoder = new NominatimGeocoder(
            $httpClient,
            'https://nominatim.openstreetmap.org',
            new ArrayAdapter(),
            'FuelAppGeocoding/1.0 (test@example.com)',
            null,
            0,
        );

        $first = $geocoder->geocode('Total', 'Rue A', '75001', 'Paris');
        $second = $geocoder->geocode('Total', 'Rue A', '75001', 'Paris');

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(1, $calls);
    }

    public function testItWrapsTransportErrors(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('network down');
        }, 'https://nominatim.openstreetmap.org');

        $geocoder = new NominatimGeocoder(
            $httpClient,
            'https://nominatim.openstreetmap.org',
            new ArrayAdapter(),
            'FuelAppGeocoding/1.0 (test@example.com)',
            null,
            0,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('transport failure');

        $geocoder->geocode('Total', 'Rue A', '75001', 'Paris');
    }
}
