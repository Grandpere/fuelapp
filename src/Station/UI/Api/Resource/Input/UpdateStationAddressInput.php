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

namespace App\Station\UI\Api\Resource\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateStationAddressInput
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        public string $streetName,
        #[Assert\NotBlank]
        public string $postalCode,
        #[Assert\NotBlank]
        public string $city,
    ) {
    }
}
