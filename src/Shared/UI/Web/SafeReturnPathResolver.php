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

namespace App\Shared\UI\Web;

final class SafeReturnPathResolver
{
    public function resolve(mixed $candidate, string $fallbackPath): string
    {
        if (!is_scalar($candidate)) {
            return $fallbackPath;
        }

        $value = trim((string) $candidate);
        if ('' === $value || !str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return $fallbackPath;
        }

        $parts = parse_url($value);
        if (false === $parts) {
            return $fallbackPath;
        }

        if (array_key_exists('scheme', $parts) || array_key_exists('host', $parts)) {
            return $fallbackPath;
        }

        return $value;
    }
}
