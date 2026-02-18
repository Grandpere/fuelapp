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

namespace App\Receipt\Domain\ValueObject;

use Symfony\Component\Uid\UuidV7;

final class ReceiptId
{
    private UuidV7 $value;

    private function __construct(UuidV7 $value)
    {
        $this->value = $value;
    }

    public static function new(): self
    {
        return new self(new UuidV7());
    }

    public static function fromString(string $value): self
    {
        return new self(UuidV7::fromString($value));
    }

    public function toString(): string
    {
        return $this->value->toRfc4122();
    }
}
