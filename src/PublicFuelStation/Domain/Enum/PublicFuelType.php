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

namespace App\PublicFuelStation\Domain\Enum;

enum PublicFuelType: string
{
    case DIESEL = 'gazole';
    case SP95 = 'sp95';
    case E85 = 'e85';
    case GPLC = 'gplc';
    case E10 = 'e10';
    case SP98 = 'sp98';

    public static function fromSourceLabel(string $label): ?self
    {
        return match (mb_strtolower(trim($label))) {
            'gazole' => self::DIESEL,
            'sp95' => self::SP95,
            'e85' => self::E85,
            'gplc' => self::GPLC,
            'e10' => self::E10,
            'sp98' => self::SP98,
            default => null,
        };
    }

    public function sourceLabel(): string
    {
        return match ($this) {
            self::DIESEL => 'Gazole',
            self::SP95 => 'SP95',
            self::E85 => 'E85',
            self::GPLC => 'GPLc',
            self::E10 => 'E10',
            self::SP98 => 'SP98',
        };
    }
}
