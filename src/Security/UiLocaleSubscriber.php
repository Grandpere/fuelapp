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

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class UiLocaleSubscriber implements EventSubscriberInterface
{
    private const SESSION_LOCALE_KEY = 'ui_locale';
    private const DEFAULT_LOCALE = 'fr';
    private const AVAILABLE_LOCALES = ['fr', 'en'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with((string) $request->getPathInfo(), '/ui')) {
            return;
        }

        $locale = self::DEFAULT_LOCALE;
        if ($request->hasSession()) {
            $storedLocale = $request->getSession()->get(self::SESSION_LOCALE_KEY);
            if (is_string($storedLocale) && in_array($storedLocale, self::AVAILABLE_LOCALES, true)) {
                $locale = $storedLocale;
            }
        }

        $request->setLocale($locale);
    }
}
