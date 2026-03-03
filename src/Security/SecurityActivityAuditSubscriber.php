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

use App\Admin\Application\Audit\AdminAuditTrail;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class SecurityActivityAuditSubscriber implements EventSubscriberInterface
{
    private const int AUDIT_TARGET_ID_MAX_LENGTH = 120;

    public function __construct(private AdminAuditTrail $auditTrail)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $routeAttribute = $request->attributes->get('_route', '');
        $route = is_string($routeAttribute) ? $routeAttribute : '';
        if (!$this->isSupportedRoute($route)) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof UserEntity) {
            return;
        }

        $this->auditTrail->record(
            'security.login.success',
            'user',
            $user->getId()->toRfc4122(),
            [],
            [
                'route' => $route,
                'channel' => str_starts_with($route, 'ui_oidc_') ? 'oidc' : 'password',
            ],
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $routeAttribute = $request->attributes->get('_route', '');
        $route = is_string($routeAttribute) ? $routeAttribute : '';
        if ('ui_login' !== $route) {
            return;
        }

        $emailValue = $request->request->get('email', 'anonymous');
        $attemptedEmail = is_scalar($emailValue) ? trim((string) $emailValue) : 'anonymous';
        if ('' === $attemptedEmail) {
            $attemptedEmail = 'anonymous';
        }

        $this->auditTrail->record(
            'security.login.failure',
            'credential',
            $this->normalizeAuditCredentialTargetId($attemptedEmail),
            [],
            [
                'route' => $route,
                'reason' => $event->getException()->getMessageKey(),
            ],
        );
    }

    private function isSupportedRoute(string $route): bool
    {
        return in_array($route, ['ui_login', 'ui_oidc_callback'], true);
    }

    private function normalizeAuditCredentialTargetId(string $targetId): string
    {
        $normalized = mb_strtolower(trim($targetId));
        if ('' === $normalized) {
            return 'anonymous';
        }

        return mb_substr($normalized, 0, self::AUDIT_TARGET_ID_MAX_LENGTH);
    }
}
