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

namespace App\Import\Infrastructure\Messenger;

use App\Import\Application\Message\ProcessImportJobMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class ProcessImportRetryContextMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->getMessage() instanceof ProcessImportJobMessage) {
            $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
            $envelope = $envelope->with(new HandlerArgumentsStamp([$retryCount]));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
