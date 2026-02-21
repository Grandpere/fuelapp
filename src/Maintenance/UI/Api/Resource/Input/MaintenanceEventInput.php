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

namespace App\Maintenance\UI\Api\Resource\Input;

use App\Maintenance\Domain\Enum\MaintenanceEventType;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MaintenanceEventInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $vehicleId,
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'eventTypeValues'])]
        public string $eventType,
        #[Assert\NotNull]
        public DateTimeImmutable $occurredAt,
        #[Assert\Length(max: 2000)]
        public ?string $description = null,
        #[Assert\PositiveOrZero]
        public ?int $odometerKilometers = null,
        #[Assert\PositiveOrZero]
        public ?int $totalCostCents = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 3)]
        public string $currencyCode = 'EUR',
    ) {
    }

    /** @return list<string> */
    public static function eventTypeValues(): array
    {
        return array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases());
    }
}
