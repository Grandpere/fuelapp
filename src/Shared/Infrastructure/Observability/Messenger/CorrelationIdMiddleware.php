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

use App\Shared\Infrastructure\Observability\CorrelationIdContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    public function __construct(private CorrelationIdContext $context)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $previous = $this->context->current();
        $stamp = $envelope->last(CorrelationIdStamp::class);

        $correlationId = $stamp instanceof CorrelationIdStamp
            ? $stamp->correlationId
            : $this->context->getOrCreate();

        if (!$stamp instanceof CorrelationIdStamp) {
            $envelope = $envelope->with(new CorrelationIdStamp($correlationId));
        }

        $this->context->set($correlationId);

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if (null === $previous) {
                $this->context->clear();
            } else {
                $this->context->set($previous);
            }
        }
    }
}
