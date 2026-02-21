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

namespace App\Tests\Unit\Maintenance\Domain;

use App\Maintenance\Domain\Enum\ReminderRuleTriggerMode;
use App\Maintenance\Domain\MaintenanceReminderRule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MaintenanceReminderRuleTest extends TestCase
{
    public function testWhateverFirstRequiresDateAndOdometerIntervals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WHICHEVER_FIRST trigger requires intervalDays and intervalKilometers.');

        MaintenanceReminderRule::create(
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            'Oil Service',
            ReminderRuleTriggerMode::WHICHEVER_FIRST,
            null,
            365,
            null,
        );
    }

    public function testDateRuleAcceptsPositiveDayInterval(): void
    {
        $rule = MaintenanceReminderRule::create(
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            'Annual Inspection',
            ReminderRuleTriggerMode::DATE,
            null,
            365,
            null,
        );

        self::assertSame(ReminderRuleTriggerMode::DATE, $rule->triggerMode());
        self::assertSame(365, $rule->intervalDays());
        self::assertNull($rule->intervalKilometers());
    }
}
