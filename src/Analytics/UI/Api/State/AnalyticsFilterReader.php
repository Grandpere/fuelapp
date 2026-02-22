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

namespace App\Analytics\UI\Api\State;

use App\Receipt\Domain\Enum\FuelType;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class AnalyticsFilterReader
{
    /** @param array<string, mixed> $context */
    public static function readVehicleId(array $context): ?string
    {
        $value = self::readStringFilter($context, 'vehicleId');
        if (null === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $context */
    public static function readStationId(array $context): ?string
    {
        $value = self::readStringFilter($context, 'stationId');
        if (null === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $context */
    public static function readFuelType(array $context): ?string
    {
        $value = self::readStringFilter($context, 'fuelType');
        if (null === $value) {
            return null;
        }

        $choices = array_map(static fn (FuelType $type): string => $type->value, FuelType::cases());

        return in_array($value, $choices, true) ? $value : null;
    }

    /** @param array<string, mixed> $context */
    public static function readDateFilter(array $context, string $name): ?DateTimeImmutable
    {
        $value = self::readStringFilter($context, $name);
        if (null === $value) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false !== $parsed) {
            return $parsed;
        }

        $date = date_create_immutable($value);

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    /** @param array<string, mixed> $context */
    private static function readStringFilter(array $context, string $name): ?string
    {
        $filters = $context['filters'] ?? null;
        if (!is_array($filters)) {
            return null;
        }

        $value = $filters[$name] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
