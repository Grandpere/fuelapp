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

use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly JwtTokenManager $jwtTokenManager)
    {
    }

    public function supports(Request $request): bool
    {
        if (!str_starts_with((string) $request->getPathInfo(), '/api')) {
            return false;
        }

        $route = $request->attributes->get('_route');
        if (!is_string($route)) {
            return true;
        }

        if (in_array($route, ['api_login', 'api_doc'], true)) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authorization = trim((string) $request->headers->get('Authorization', ''));
        if ('' === $authorization) {
            throw new CustomUserMessageAuthenticationException('Missing Bearer token.');
        }

        $token = $authorization;
        if (str_starts_with(mb_strtolower($authorization), 'bearer ')) {
            $token = trim(substr($authorization, 7));
        }

        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Missing Bearer token.');
        }

        try {
            $claims = $this->jwtTokenManager->parseAndValidate($token);
        } catch (RuntimeException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid token.', [], 0, $e);
        }

        return new SelfValidatingPassport(new UserBadge($claims['email']));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['message' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
    }
}
