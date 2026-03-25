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

namespace App\Shared\Infrastructure\Security\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SecurityHeadersResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if (!$headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        if (!$headers->has('Permissions-Policy')) {
            $headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
        }

        if (!$headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }

        if (!$headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }
    }

    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "script-src 'self' 'unsafe-inline' https://unpkg.com",
            "connect-src 'self' https:",
        ]);
    }
}
