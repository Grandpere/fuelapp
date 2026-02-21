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

namespace App\Maintenance\Domain\Enum;

enum MaintenanceEventType: string
{
    case SERVICE = 'service';
    case REPAIR = 'repair';
    case INSPECTION = 'inspection';
    case OTHER = 'other';
}
