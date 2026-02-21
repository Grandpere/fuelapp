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

final readonly class MaintenancePlannedCostInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $vehicleId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public string $label,
        #[Assert\Choice(callback: [self::class, 'eventTypeValues'])]
        public ?string $eventType,
        #[Assert\NotNull]
        public DateTimeImmutable $plannedFor,
        #[Assert\Positive]
        public int $plannedCostCents,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 3)]
        public string $currencyCode = 'EUR',
        #[Assert\Length(max: 2000)]
        public ?string $notes = null,
    ) {
    }

    /** @return list<string> */
    public static function eventTypeValues(): array
    {
        return array_map(static fn (MaintenanceEventType $type): string => $type->value, MaintenanceEventType::cases());
    }
}
