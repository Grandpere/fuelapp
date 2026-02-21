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

namespace App\Tests\Unit\Security;

use App\Security\ApiTokenAuthenticator;
use App\Security\JwtTokenManager;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Uid\Uuid;

final class ApiTokenAuthenticatorTest extends TestCase
{
    public function testAuthenticateAcceptsBearerTokenPrefix(): void
    {
        $jwt = new JwtTokenManager('test-secret', 3600);
        $token = $jwt->create($this->createUser('api@example.com'));

        $authenticator = new ApiTokenAuthenticator($jwt);
        $request = Request::create('/api/receipts', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $passport = $authenticator->authenticate($request);

        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame('api@example.com', $badge->getUserIdentifier());
    }

    public function testAuthenticateAlsoAcceptsRawAuthorizationToken(): void
    {
        $jwt = new JwtTokenManager('test-secret', 3600);
        $token = $jwt->create($this->createUser('api2@example.com'));

        $authenticator = new ApiTokenAuthenticator($jwt);
        $request = Request::create('/api/receipts', 'GET', server: ['HTTP_AUTHORIZATION' => $token]);

        $passport = $authenticator->authenticate($request);

        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame('api2@example.com', $badge->getUserIdentifier());
    }

    public function testAuthenticateRejectsMissingAuthorizationHeader(): void
    {
        $authenticator = new ApiTokenAuthenticator(new JwtTokenManager('test-secret', 3600));
        $request = Request::create('/api/receipts', 'GET');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Missing Bearer token.');

        $authenticator->authenticate($request);
    }

    private function createUser(string $email): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('hashed-password');

        return $user;
    }
}
