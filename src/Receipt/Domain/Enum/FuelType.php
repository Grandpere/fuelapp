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

namespace App\Receipt\Domain\Enum;

enum FuelType: string
{
    case SP95 = 'sp95';
    case SP98 = 'sp98';
    case E10 = 'e10';
    case E85 = 'e85';
    case DIESEL = 'diesel';
    case LPG = 'lpg';
}
