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

namespace App\Tests\Unit\Receipt\Domain;

use App\Receipt\Domain\Enum\FuelType;
use PHPUnit\Framework\TestCase;

final class FuelTypeTest extends TestCase
{
    public function testLegacyStorageAliasIsMappedToSp95(): void
    {
        self::assertSame(FuelType::SP95, FuelType::fromStorage('unleaded95'));
    }

    public function testCanonicalStorageValueStillMapsDirectly(): void
    {
        self::assertSame(FuelType::DIESEL, FuelType::fromStorage('diesel'));
    }
}
