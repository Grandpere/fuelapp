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

namespace App\Tests\Unit\Admin\Application\User;

use App\Admin\Application\Repository\AdminUserRepository;
use App\Admin\Application\User\AdminUserManager;
use App\Admin\Application\User\AdminUserPasswordResetResult;
use App\Admin\Application\User\AdminUserRecord;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AdminUserManagerTest extends TestCase
{
    public function testRequestVerificationResendRejectsAlreadyVerifiedUser(): void
    {
        $user = new AdminUserRecord(
            '019d0000-0000-7000-8000-000000000001',
            'verified@example.com',
            ['ROLE_USER'],
            true,
            0,
            new DateTimeImmutable('2026-03-24 10:00:00'),
        );
        $manager = new AdminUserManager(new InMemoryAdminUserRepository($user));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('User email is already verified.');

        $manager->requestVerificationResend($user->id);
    }

    public function testRequestVerificationResendReturnsUnverifiedUser(): void
    {
        $user = new AdminUserRecord(
            '019d0000-0000-7000-8000-000000000002',
            'pending@example.com',
            ['ROLE_USER'],
            true,
            0,
            null,
        );
        $manager = new AdminUserManager(new InMemoryAdminUserRepository($user));

        $result = $manager->requestVerificationResend($user->id);

        self::assertSame($user->id, $result->id);
        self::assertSame($user->email, $result->email);
    }
}

final readonly class InMemoryAdminUserRepository implements AdminUserRepository
{
    public function __construct(private AdminUserRecord $user)
    {
    }

    public function list(?string $query = null, ?string $role = null, ?bool $isActive = null): array
    {
        return [$this->user];
    }

    public function get(string $id): ?AdminUserRecord
    {
        return $id === $this->user->id ? $this->user : null;
    }

    public function update(string $id, ?bool $isActive, ?bool $isAdmin, ?bool $isEmailVerified): ?AdminUserRecord
    {
        return $this->get($id);
    }

    public function resetPassword(string $id): ?AdminUserPasswordResetResult
    {
        return null;
    }

    public function countActiveAdmins(): int
    {
        return 0;
    }
}
