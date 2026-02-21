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

namespace App\Vehicle\Domain\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class VehicleId
{
    private function __construct(private readonly string $value)
    {
    }

    public static function new(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException('VehicleId must be a valid UUID.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
