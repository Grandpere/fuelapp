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

namespace App\Shared\Infrastructure\Observability\Logging;

use Monolog\Level;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;

final class OpenTelemetryMonologHandlerFactory
{
    public static function create(): Handler
    {
        return new Handler(Globals::loggerProvider(), Level::Info);
    }
}
