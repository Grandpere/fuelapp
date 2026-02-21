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

namespace App\Receipt\UI\Api\Resource\Input;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final class ReceiptInput
{
    /** @param list<ReceiptLineInput>|null $lines */
    public function __construct(
        public ?DateTimeImmutable $issuedAt = null,
        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public ?array $lines = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $stationName = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $stationStreetName = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public ?string $stationPostalCode = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public ?string $stationCity = null,
        #[Assert\Range(min: -90000000, max: 90000000)]
        public ?int $latitudeMicroDegrees = null,
        #[Assert\Range(min: -180000000, max: 180000000)]
        public ?int $longitudeMicroDegrees = null,
        #[Assert\Uuid]
        public ?string $vehicleId = null,
    ) {
    }
}
