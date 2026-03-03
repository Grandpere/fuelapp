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
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Throwable;

final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    public function __construct(private CorrelationIdContext $context)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $previous = $this->context->current();
        $stamp = $envelope->last(CorrelationIdStamp::class);
        $isConsumed = null !== $envelope->last(ReceivedStamp::class);
        $operation = $isConsumed ? 'consume' : 'dispatch';
        $spanKind = $isConsumed ? SpanKind::KIND_CONSUMER : SpanKind::KIND_PRODUCER;

        $correlationId = $stamp instanceof CorrelationIdStamp
            ? $stamp->correlationId
            : $this->context->getOrCreate();

        if (!$stamp instanceof CorrelationIdStamp) {
            $envelope = $envelope->with(new CorrelationIdStamp($correlationId));
        }

        $this->context->set($correlationId);

        $messageClass = $envelope->getMessage()::class;
        $tracer = Globals::tracerProvider()->getTracer('fuelapp.messenger');
        $span = $tracer
            ->spanBuilder(sprintf('messenger %s %s', $operation, $messageClass))
            ->setSpanKind($spanKind)
            ->setAttribute('messaging.system', 'rabbitmq')
            ->setAttribute('messaging.operation', $operation)
            ->setAttribute('messaging.destination.name', 'async')
            ->setAttribute('messaging.message.class', $messageClass)
            ->setAttribute('correlation_id', $correlationId)
            ->startSpan();
        $scope = $span->activate();

        try {
            $result = $stack->next()->handle($envelope, $stack);
            $span->setStatus(StatusCode::STATUS_OK);

            return $result;
        } catch (Throwable $exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

            throw $exception;
        } finally {
            $scope->detach();
            $span->end();

            if (null === $previous) {
                $this->context->clear();
            } else {
                $this->context->set($previous);
            }
        }
    }
}
