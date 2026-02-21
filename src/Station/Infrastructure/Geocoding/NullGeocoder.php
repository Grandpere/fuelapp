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

final class NullGeocoder implements Geocoder
{
    public function geocode(string $name, string $streetName, string $postalCode, string $city): ?GeocodedAddress
    {
        return null;
    }
}
