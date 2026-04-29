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

namespace App\Station\Application\Suggestion;

final readonly class StationSuggestionQuery
{
    public function __construct(
        public ?string $freeText,
        public ?string $name,
        public ?string $streetName,
        public ?string $postalCode,
        public ?string $city,
        public int $limit = 5,
    ) {
    }
}
