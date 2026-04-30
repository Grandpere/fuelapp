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

namespace App\Station\Infrastructure\Persistence\Doctrine\Repository;

use App\Station\Application\Favorite\FavoriteStationRepository;
use App\Station\Domain\Favorite\FavoriteStation;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\FavoriteStationEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineFavoriteStationRepository implements FavoriteStationRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(FavoriteStation $favorite): void
    {
        $entity = $this->em->find(FavoriteStationEntity::class, $favorite->id()) ?? new FavoriteStationEntity();
        $owner = $this->em->find(UserEntity::class, $favorite->ownerId());
        $station = $this->em->find(StationEntity::class, $favorite->stationId());
        if (!$owner instanceof UserEntity || !$station instanceof StationEntity) {
            return;
        }

        $entity->setId(Uuid::fromString($favorite->id()));
        $entity->setOwner($owner);
        $entity->setStation($station);
        $entity->setCreatedAt($favorite->createdAt());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findByOwnerAndStation(string $ownerId, string $stationId): ?FavoriteStation
    {
        if (!Uuid::isValid($ownerId) || !Uuid::isValid($stationId)) {
            return null;
        }

        $entity = $this->em->getRepository(FavoriteStationEntity::class)->createQueryBuilder('f')
            ->andWhere('IDENTITY(f.owner) = :ownerId')
            ->andWhere('IDENTITY(f.station) = :stationId')
            ->setParameter('ownerId', $ownerId)
            ->setParameter('stationId', $stationId)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof FavoriteStationEntity ? $this->mapEntityToDomain($entity) : null;
    }

    public function deleteByOwnerAndStation(string $ownerId, string $stationId): void
    {
        if (!Uuid::isValid($ownerId) || !Uuid::isValid($stationId)) {
            return;
        }

        $this->em->createQueryBuilder()
            ->delete(FavoriteStationEntity::class, 'f')
            ->andWhere('IDENTITY(f.owner) = :ownerId')
            ->andWhere('IDENTITY(f.station) = :stationId')
            ->setParameter('ownerId', $ownerId)
            ->setParameter('stationId', $stationId)
            ->getQuery()
            ->execute();
    }

    public function favoriteStationIds(string $ownerId, array $stationIds): array
    {
        if (!Uuid::isValid($ownerId) || [] === $stationIds) {
            return [];
        }

        $validStationIds = array_values(array_filter($stationIds, static fn (string $id): bool => Uuid::isValid($id)));
        if ([] === $validStationIds) {
            return [];
        }

        $rows = $this->em->getRepository(FavoriteStationEntity::class)->createQueryBuilder('f')
            ->select('IDENTITY(f.station) AS stationId')
            ->andWhere('IDENTITY(f.owner) = :ownerId')
            ->andWhere('IDENTITY(f.station) IN (:stationIds)')
            ->setParameter('ownerId', $ownerId)
            ->setParameter('stationIds', $validStationIds)
            ->getQuery()
            ->getArrayResult();

        $favoriteIds = [];
        /** @var list<array{stationId:string}> $rows */
        foreach ($rows as $row) {
            $stationId = $row['stationId'];
            if (Uuid::isValid($stationId)) {
                $favoriteIds[] = $stationId;
            }
        }

        return $favoriteIds;
    }

    private function mapEntityToDomain(FavoriteStationEntity $entity): FavoriteStation
    {
        return FavoriteStation::reconstitute(
            $entity->getId()->toRfc4122(),
            $entity->getOwner()->getId()->toRfc4122(),
            $entity->getStation()->getId()->toRfc4122(),
            $entity->getCreatedAt(),
        );
    }
}
