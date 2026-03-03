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

namespace App\Shared\Infrastructure\Observability\Http;

use App\Shared\Infrastructure\Observability\CorrelationIdContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final readonly class CorrelationIdRequestSubscriber implements EventSubscriberInterface
{
    private const int MAX_CORRELATION_ID_LENGTH = 80;

    public function __construct(private CorrelationIdContext $context)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 250],
            KernelEvents::RESPONSE => ['onKernelResponse', -250],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $headerCorrelation = $request->headers->get('X-Correlation-Id') ?? $request->headers->get('X-Request-Id');

        $correlationId = is_string($headerCorrelation) && '' !== trim($headerCorrelation)
            ? $this->normalizeCorrelationId($headerCorrelation)
            : Uuid::v7()->toRfc4122();

        $request->attributes->set('_correlation_id', $correlationId);
        $this->context->set($correlationId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $correlationId = $this->context->current() ?? $this->context->getOrCreate();
        $response->headers->set('X-Correlation-Id', $correlationId);
        $this->context->clear();
    }

    private function normalizeCorrelationId(string $value): string
    {
        $normalized = trim($value);
        if ('' === $normalized) {
            return Uuid::v7()->toRfc4122();
        }

        return mb_substr($normalized, 0, self::MAX_CORRELATION_ID_LENGTH);
    }
}
