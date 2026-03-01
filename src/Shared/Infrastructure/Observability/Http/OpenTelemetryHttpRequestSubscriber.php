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

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class OpenTelemetryHttpRequestSubscriber implements EventSubscriberInterface
{
    private const REQUEST_SPAN_ATTRIBUTE = '_otel_http_request_span';
    private const REQUEST_SCOPE_ATTRIBUTE = '_otel_http_request_scope';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 200],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', -200],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $spanName = $this->buildSpanName($request);
        $tracer = Globals::tracerProvider()->getTracer('fuelapp.http');
        $spanBuilder = $tracer
            ->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.request.method', $request->getMethod())
            ->setAttribute('url.path', $request->getPathInfo())
            ->setAttribute('url.scheme', $request->getScheme())
            ->setAttribute('server.address', (string) $request->getHost());

        $route = $request->attributes->get('_route');
        if (is_string($route) && '' !== trim($route)) {
            $spanBuilder->setAttribute('http.route', trim($route));
        }

        $correlationId = $request->attributes->get('_correlation_id');
        if (is_string($correlationId) && '' !== trim($correlationId)) {
            $spanBuilder->setAttribute('correlation_id', trim($correlationId));
        }

        $span = $spanBuilder->startSpan();
        $scope = $span->activate();

        $request->attributes->set(self::REQUEST_SPAN_ATTRIBUTE, $span);
        $request->attributes->set(self::REQUEST_SCOPE_ATTRIBUTE, $scope);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $span = $request->attributes->get(self::REQUEST_SPAN_ATTRIBUTE);
        if (!$span instanceof SpanInterface) {
            return;
        }

        $exception = $event->getThrowable();
        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $span = $request->attributes->get(self::REQUEST_SPAN_ATTRIBUTE);
        $scope = $request->attributes->get(self::REQUEST_SCOPE_ATTRIBUTE);

        if (!$span instanceof SpanInterface || !$scope instanceof ScopeInterface) {
            return;
        }

        $statusCode = $event->getResponse()->getStatusCode();
        $span->setAttribute('http.response.status_code', $statusCode);

        $route = $request->attributes->get('_route');
        if (is_string($route) && '' !== trim($route)) {
            $span->setAttribute('http.route', trim($route));
            $span->updateName(sprintf('%s %s', $request->getMethod(), trim($route)));
        }

        if ($statusCode >= 500) {
            $span->setStatus(StatusCode::STATUS_ERROR, sprintf('HTTP %d', $statusCode));
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $scope->detach();
        $span->end();
    }

    private function buildSpanName(Request $request): string
    {
        $path = trim($request->getPathInfo());
        if ('' === $path) {
            $path = '/';
        }

        return sprintf('%s %s', $request->getMethod(), $path);
    }
}
