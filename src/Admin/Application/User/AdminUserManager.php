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

namespace App\Admin\Application\User;

use App\Admin\Application\Repository\AdminUserRepository;
use LogicException;

final readonly class AdminUserManager
{
    public function __construct(private AdminUserRepository $repository)
    {
    }

    /** @return list<AdminUserRecord> */
    public function listUsers(?string $query = null, ?string $role = null, ?bool $isActive = null): array
    {
        return $this->repository->list($query, $role, $isActive);
    }

    public function getUser(string $id): ?AdminUserRecord
    {
        return $this->repository->get($id);
    }

    public function updateUser(string $targetId, ?bool $isActive, ?bool $isAdmin, ?bool $isEmailVerified, ?string $actorId): AdminUserRecord
    {
        $user = $this->repository->get($targetId);
        if (!$user instanceof AdminUserRecord) {
            throw new LogicException('User not found.');
        }

        if (null === $isActive && null === $isAdmin && null === $isEmailVerified) {
            throw new LogicException('Nothing to update.');
        }

        $isSelf = null !== $actorId && $actorId === $user->id;
        if ($isSelf && false === $isActive) {
            throw new LogicException('You cannot deactivate your own account.');
        }

        if ($isSelf && false === $isAdmin) {
            throw new LogicException('You cannot remove your own admin role.');
        }

        if (false === $isAdmin && $user->isAdmin() && $this->repository->countActiveAdmins() <= 1) {
            throw new LogicException('At least one active admin account is required.');
        }

        $updated = $this->repository->update($user->id, $isActive, $isAdmin, $isEmailVerified);
        if (!$updated instanceof AdminUserRecord) {
            throw new LogicException('User not found.');
        }

        return $updated;
    }

    public function resetPassword(string $targetId): AdminUserPasswordResetResult
    {
        $result = $this->repository->resetPassword($targetId);
        if (!$result instanceof AdminUserPasswordResetResult) {
            throw new LogicException('User not found.');
        }

        return $result;
    }
}
