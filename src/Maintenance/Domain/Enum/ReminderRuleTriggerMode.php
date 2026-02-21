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

enum ReminderRuleTriggerMode: string
{
    case DATE = 'date';
    case ODOMETER = 'odometer';
    case WHICHEVER_FIRST = 'whichever_first';
}
