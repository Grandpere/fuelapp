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

namespace App\Admin\Infrastructure\Persistence\Doctrine\Repository;

use App\Admin\Application\Repository\AdminUserRepository;
use App\Admin\Application\User\AdminUserRecord;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAdminUserRepository implements AdminUserRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function list(?string $query = null, ?string $role = null, ?bool $isActive = null): array
    {
        $items = $this->em->getRepository(UserEntity::class)->findBy([], ['email' => 'ASC']);
        $users = [];
        foreach ($items as $item) {
            $record = $this->map($item);
            if (null !== $query && !$this->matchesQuery($record, $query)) {
                continue;
            }
            if (null !== $role && !$this->matchesRole($record, $role)) {
                continue;
            }
            if (null !== $isActive && $record->isActive !== $isActive) {
                continue;
            }

            $users[] = $record;
        }

        return $users;
    }

    public function get(string $id): ?AdminUserRecord
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $user = $this->em->find(UserEntity::class, $id);
        if (!$user instanceof UserEntity) {
            return null;
        }

        return $this->map($user);
    }

    public function update(string $id, ?bool $isActive, ?bool $isAdmin): ?AdminUserRecord
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $user = $this->em->find(UserEntity::class, $id);
        if (!$user instanceof UserEntity) {
            return null;
        }

        if (null !== $isActive) {
            $user->setIsActive($isActive);
        }

        if (null !== $isAdmin) {
            $roles = [];
            foreach ($user->getRoles() as $role) {
                if ('ROLE_USER' === $role || 'ROLE_ADMIN' === $role) {
                    continue;
                }

                $roles[] = $role;
            }

            if ($isAdmin) {
                $roles[] = 'ROLE_ADMIN';
            }

            $user->setRoles(array_values(array_unique($roles)));
        }

        $this->em->persist($user);
        $this->em->flush();

        return $this->map($user);
    }

    public function countActiveAdmins(): int
    {
        $count = 0;
        $items = $this->em->getRepository(UserEntity::class)->findAll();
        foreach ($items as $item) {
            if ($item->isActive() && in_array('ROLE_ADMIN', $item->getRoles(), true)) {
                ++$count;
            }
        }

        return $count;
    }

    private function map(UserEntity $user): AdminUserRecord
    {
        return new AdminUserRecord(
            $user->getId()->toRfc4122(),
            $user->getEmail(),
            $user->getRoles(),
            $user->isActive(),
            $this->countIdentities($user->getId()->toRfc4122()),
        );
    }

    private function countIdentities(string $userId): int
    {
        if (!Uuid::isValid($userId)) {
            return 0;
        }

        $raw = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM user_identities WHERE user_id = :userId',
            ['userId' => $userId],
        );

        if (is_int($raw)) {
            return $raw;
        }

        return is_string($raw) && ctype_digit($raw) ? (int) $raw : 0;
    }

    private function matchesQuery(AdminUserRecord $record, string $query): bool
    {
        return str_contains(mb_strtolower($record->email), mb_strtolower($query));
    }

    private function matchesRole(AdminUserRecord $record, string $role): bool
    {
        return match ($role) {
            'admin' => $record->isAdmin(),
            'user' => !$record->isAdmin(),
            default => true,
        };
    }
}
