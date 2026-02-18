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

use App\Receipt\Domain\Enum\FuelType;
use Symfony\Component\Validator\Constraints as Assert;

final class ReceiptLineInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'fuelTypeChoices'])]
        public ?string $fuelType = null,
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $quantityMilliLiters = null,
        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $unitPriceCentsPerLiter = null,
        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 100)]
        public ?int $vatRatePercent = null,
    ) {
    }

    /** @return list<string> */
    public static function fuelTypeChoices(): array
    {
        return array_map(static fn (FuelType $type): string => $type->value, FuelType::cases());
    }
}
