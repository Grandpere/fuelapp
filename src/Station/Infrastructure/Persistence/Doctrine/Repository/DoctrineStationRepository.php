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

use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use App\Station\Domain\ValueObject\StationId;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStationRepository implements StationRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function save(Station $station): void
    {
        $entity = $this->em->find(StationEntity::class, $station->id()->toString()) ?? new StationEntity();
        $entity->setId(Uuid::fromString($station->id()->toString()));
        $entity->setName($station->name());
        $entity->setStreetName($station->streetName());
        $entity->setPostalCode($station->postalCode());
        $entity->setCity($station->city());
        $entity->setLatitudeMicroDegrees($station->latitudeMicroDegrees());
        $entity->setLongitudeMicroDegrees($station->longitudeMicroDegrees());
        $entity->setGeocodingStatus($station->geocodingStatus());
        $entity->setGeocodingRequestedAt($station->geocodingRequestedAt());
        $entity->setGeocodedAt($station->geocodedAt());
        $entity->setGeocodingFailedAt($station->geocodingFailedAt());
        $entity->setGeocodingLastError($station->geocodingLastError());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?Station
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $qb = $this->em->getRepository(StationEntity::class)->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->setParameter('id', $id);
        $this->applyReadableByCurrentUser($qb, 's');

        $entity = $qb->getQuery()->getOneOrNullResult();
        if (null === $entity) {
            return null;
        }

        return $entity instanceof StationEntity ? $this->mapEntityToDomain($entity) : null;
    }

    public function getForSystem(string $id): ?Station
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $entity = $this->em->find(StationEntity::class, $id);
        if (!$entity instanceof StationEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function delete(string $id): void
    {
        if (!Uuid::isValid($id)) {
            return;
        }

        $qb = $this->em->getRepository(StationEntity::class)->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->setParameter('id', $id);
        $this->applyReadableByCurrentUser($qb, 's');

        $entity = $qb->getQuery()->getOneOrNullResult();
        if (null === $entity) {
            return;
        }

        if (!$entity instanceof StationEntity) {
            return;
        }

        // Prevent deleting a station used by receipts outside current user's scope.
        $foreignUsageCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(ReceiptEntity::class, 'r')
            ->andWhere('r.station = :station')
            ->andWhere('IDENTITY(r.owner) != :currentOwnerId OR IDENTITY(r.owner) IS NULL')
            ->setParameter('station', $entity)
            ->setParameter('currentOwnerId', $this->currentUserId() ?? '')
            ->getQuery()
            ->getSingleScalarResult();
        if ($foreignUsageCount > 0) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function deleteForSystem(string $id): void
    {
        if (!Uuid::isValid($id)) {
            return;
        }

        // Keep deletion deterministic even if FK behavior drifts: detach receipts first.
        $this->em->getConnection()->executeStatement(
            'UPDATE receipts SET station_id = NULL WHERE station_id = :stationId',
            ['stationId' => $id],
        );
        $this->em->getConnection()->executeStatement(
            'UPDATE analytics_daily_fuel_kpis SET station_id = NULL WHERE station_id = :stationId',
            ['stationId' => $id],
        );
        $this->em->getConnection()->executeStatement(
            'DELETE FROM stations WHERE id = :stationId',
            ['stationId' => $id],
        );
        $this->em->clear();
    }

    public function getByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $entities = $this->em->getRepository(StationEntity::class)->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->distinct()
            ->leftJoin(ReceiptEntity::class, 'r', 'WITH', 'r.station = s')
            ->andWhere('IDENTITY(r.owner) = :currentOwnerId')
            ->setParameter('currentOwnerId', $this->currentUserId() ?? '')
            ->getQuery()
            ->getResult();

        $stationsById = [];
        if (!is_iterable($entities)) {
            return $stationsById;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof StationEntity) {
                continue;
            }

            $station = $this->mapEntityToDomain($entity);
            $stationsById[$station->id()->toString()] = $station;
        }

        return $stationsById;
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        $entity = $this->em->getRepository(StationEntity::class)->findOneBy([
            'name' => $name,
            'streetName' => $streetName,
            'postalCode' => $postalCode,
            'city' => $city,
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function all(): iterable
    {
        $qb = $this->em->getRepository(StationEntity::class)->createQueryBuilder('s');
        $this->applyReadableByCurrentUser($qb, 's');
        $entities = $qb->getQuery()->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof StationEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function allForSystem(): iterable
    {
        $entities = $this->em->getRepository(StationEntity::class)
            ->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('s.city', 'ASC')
            ->getQuery()
            ->getResult();

        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof StationEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    private function currentUserId(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof UserEntity) {
            return null;
        }

        return $user->getId()->toRfc4122();
    }

    private function applyReadableByCurrentUser(QueryBuilder $qb, string $stationAlias): void
    {
        $currentUserId = $this->currentUserId();
        if (null === $currentUserId) {
            $qb->andWhere('1 = 0');

            return;
        }

        $subQb = $this->em->createQueryBuilder()
            ->select('1')
            ->from(ReceiptEntity::class, 'r2')
            ->andWhere(sprintf('r2.station = %s', $stationAlias))
            ->andWhere('IDENTITY(r2.owner) = :currentOwnerId');

        $qb->andWhere($qb->expr()->exists($subQb->getDQL()))
            ->setParameter('currentOwnerId', $currentUserId);
    }

    private function mapEntityToDomain(StationEntity $entity): Station
    {
        return Station::reconstitute(
            StationId::fromString($entity->getId()->toRfc4122()),
            $entity->getName(),
            $entity->getStreetName(),
            $entity->getPostalCode(),
            $entity->getCity(),
            $entity->getLatitudeMicroDegrees(),
            $entity->getLongitudeMicroDegrees(),
            $entity->getGeocodingStatus(),
            $entity->getGeocodingRequestedAt(),
            $entity->getGeocodedAt(),
            $entity->getGeocodingFailedAt(),
            $entity->getGeocodingLastError(),
        );
    }
}
