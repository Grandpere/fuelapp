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

use App\Admin\Application\Identity\AdminIdentityRecord;
use App\Admin\Application\Repository\AdminIdentityRepository;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserIdentityEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAdminIdentityRepository implements AdminIdentityRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function list(?string $query = null, ?string $provider = null, ?string $userId = null): array
    {
        $items = $this->em->getRepository(UserIdentityEntity::class)->findBy([], ['provider' => 'ASC', 'subject' => 'ASC']);
        $identities = [];

        foreach ($items as $item) {
            $record = $this->map($item);
            if (null !== $query && !$this->matchesQuery($record, $query)) {
                continue;
            }
            if (null !== $provider && !$this->matchesProvider($record, $provider)) {
                continue;
            }
            if (null !== $userId && $record->userId !== $userId) {
                continue;
            }

            $identities[] = $record;
        }

        return $identities;
    }

    public function get(string $id): ?AdminIdentityRecord
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $identity = $this->em->find(UserIdentityEntity::class, $id);
        if (!$identity instanceof UserIdentityEntity) {
            return null;
        }

        return $this->map($identity);
    }

    public function relink(string $id, string $targetUserId): ?AdminIdentityRecord
    {
        if (!Uuid::isValid($id) || !Uuid::isValid($targetUserId)) {
            return null;
        }

        $identity = $this->em->find(UserIdentityEntity::class, $id);
        if (!$identity instanceof UserIdentityEntity) {
            return null;
        }

        $user = $this->em->find(UserEntity::class, $targetUserId);
        if (!$user instanceof UserEntity) {
            return null;
        }

        $identity->setUser($user);
        $identity->setEmail($user->getEmail());
        $this->em->persist($identity);
        $this->em->flush();

        return $this->map($identity);
    }

    public function unlink(string $id): bool
    {
        if (!Uuid::isValid($id)) {
            return false;
        }

        $identity = $this->em->find(UserIdentityEntity::class, $id);
        if (!$identity instanceof UserIdentityEntity) {
            return false;
        }

        $this->em->remove($identity);
        $this->em->flush();

        return true;
    }

    public function userExists(string $id): bool
    {
        if (!Uuid::isValid($id)) {
            return false;
        }

        return $this->em->find(UserEntity::class, $id) instanceof UserEntity;
    }

    private function map(UserIdentityEntity $identity): AdminIdentityRecord
    {
        $user = $identity->getUser();

        return new AdminIdentityRecord(
            $identity->getId()->toRfc4122(),
            $user->getId()->toRfc4122(),
            $user->getEmail(),
            $user->getRoles(),
            $identity->getProvider(),
            $identity->getSubject(),
            $identity->getEmail(),
        );
    }

    private function matchesQuery(AdminIdentityRecord $record, string $query): bool
    {
        $needle = mb_strtolower(trim($query));
        if ('' === $needle) {
            return true;
        }

        $haystack = mb_strtolower(implode(' ', [
            $record->provider,
            $record->subject,
            $record->email ?? '',
            $record->userEmail,
            $record->userId,
        ]));

        return str_contains($haystack, $needle);
    }

    private function matchesProvider(AdminIdentityRecord $record, string $provider): bool
    {
        return mb_strtolower($record->provider) === mb_strtolower(trim($provider));
    }
}
