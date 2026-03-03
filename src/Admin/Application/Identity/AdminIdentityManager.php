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

namespace App\Admin\Application\Identity;

use App\Admin\Application\Repository\AdminIdentityRepository;
use LogicException;

final readonly class AdminIdentityManager
{
    public function __construct(private AdminIdentityRepository $repository)
    {
    }

    /** @return list<AdminIdentityRecord> */
    public function listIdentities(?string $query = null, ?string $provider = null, ?string $userId = null): array
    {
        return $this->repository->list($query, $provider, $userId);
    }

    public function getIdentity(string $id): ?AdminIdentityRecord
    {
        return $this->repository->get($id);
    }

    public function relinkIdentity(string $id, string $targetUserId): AdminIdentityRecord
    {
        $identity = $this->repository->get($id);
        if (!$identity instanceof AdminIdentityRecord) {
            throw new LogicException('Identity not found.');
        }

        if (!$this->repository->userExists($targetUserId)) {
            throw new LogicException('Target user not found.');
        }

        $updated = $this->repository->relink($id, $targetUserId);
        if (!$updated instanceof AdminIdentityRecord) {
            throw new LogicException('Identity not found.');
        }

        return $updated;
    }

    public function unlinkIdentity(string $id): void
    {
        $identity = $this->repository->get($id);
        if (!$identity instanceof AdminIdentityRecord) {
            throw new LogicException('Identity not found.');
        }

        if (!$this->repository->unlink($id)) {
            throw new LogicException('Identity not found.');
        }
    }
}
