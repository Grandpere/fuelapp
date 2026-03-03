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

namespace App\Shared\Infrastructure\Observability\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class CorrelationIdStamp implements StampInterface
{
    public function __construct(public string $correlationId)
    {
    }
}
