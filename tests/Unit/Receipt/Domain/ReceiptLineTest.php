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
use App\Receipt\Domain\ReceiptLine;
use PHPUnit\Framework\TestCase;

final class ReceiptLineTest extends TestCase
{
    public function testVatAmountIsComputedFromTtcTotal(): void
    {
        $line = ReceiptLine::create(FuelType::DIESEL, 9560, 1879, 20);

        self::assertSame(1796, $line->lineTotalCents());
        self::assertSame(299, $line->vatAmountCents());
    }
}
