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

namespace App\Admin\UI\Api\Resource\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminVehicleInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Length(max: 32)]
        public string $plateNumber,
    ) {
    }
}
