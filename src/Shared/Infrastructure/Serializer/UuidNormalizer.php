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

namespace App\Shared\Infrastructure\Serializer;

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Uid\Uuid;

final class UuidNormalizer implements DenormalizerInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [Uuid::class => true];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Uuid::class === $type && is_string($data);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Uuid
    {
        if (!is_string($data)) {
            throw new NotNormalizableValueException('UUID value must be a string.');
        }

        return Uuid::fromString($data);
    }
}
