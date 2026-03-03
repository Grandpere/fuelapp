<?php

declare(strict_types=1);

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
