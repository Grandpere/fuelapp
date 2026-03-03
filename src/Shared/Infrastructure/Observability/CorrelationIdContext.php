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

namespace App\Shared\Infrastructure\Observability;

use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

final class CorrelationIdContext implements ResetInterface
{
    private ?string $current = null;

    public function current(): ?string
    {
        return $this->current;
    }

    public function set(string $correlationId): void
    {
        $trimmed = trim($correlationId);
        if ('' === $trimmed) {
            return;
        }

        $this->current = $trimmed;
    }

    public function getOrCreate(): string
    {
        if (null !== $this->current && '' !== trim($this->current)) {
            return $this->current;
        }

        $this->current = Uuid::v7()->toRfc4122();

        return $this->current;
    }

    public function clear(): void
    {
        $this->current = null;
    }

    public function reset(): void
    {
        $this->clear();
    }
}
