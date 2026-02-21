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

namespace App\Maintenance\Infrastructure\Persistence\Doctrine\Repository;

use App\Maintenance\Application\Repository\MaintenanceEventRepository;
use App\Maintenance\Domain\MaintenanceEvent;
use App\Maintenance\Domain\ValueObject\MaintenanceEventId;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceEventEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMaintenanceEventRepository implements MaintenanceEventRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(MaintenanceEvent $event): void
    {
        $entity = $this->em->find(MaintenanceEventEntity::class, $event->id()->toString()) ?? new MaintenanceEventEntity();
        /** @var UserEntity $ownerRef */
        $ownerRef = $this->em->getReference(UserEntity::class, Uuid::fromString($event->ownerId()));
        /** @var VehicleEntity $vehicleRef */
        $vehicleRef = $this->em->getReference(VehicleEntity::class, Uuid::fromString($event->vehicleId()));

        $entity->setId(Uuid::fromString($event->id()->toString()));
        $entity->setOwner($ownerRef);
        $entity->setVehicle($vehicleRef);
        $entity->setEventType($event->eventType());
        $entity->setOccurredAt($event->occurredAt());
        $entity->setDescription($event->description());
        $entity->setOdometerKilometers($event->odometerKilometers());
        $entity->setTotalCostCents($event->totalCostCents());
        $entity->setCurrencyCode($event->currencyCode());
        $entity->setCreatedAt($event->createdAt());
        $entity->setUpdatedAt($event->updatedAt());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?MaintenanceEvent
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $entity = $this->em->find(MaintenanceEventEntity::class, $id);
        if (!$entity instanceof MaintenanceEventEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function delete(string $id): void
    {
        if (!Uuid::isValid($id)) {
            return;
        }

        $entity = $this->em->find(MaintenanceEventEntity::class, $id);
        if (!$entity instanceof MaintenanceEventEntity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function allForOwner(string $ownerId): iterable
    {
        $normalizedOwnerId = trim($ownerId);
        if ('' === $normalizedOwnerId || !Uuid::isValid($normalizedOwnerId)) {
            return;
        }

        $entities = $this->em->getRepository(MaintenanceEventEntity::class)->createQueryBuilder('m')
            ->andWhere('m.owner = :owner')
            ->setParameter('owner', Uuid::fromString($normalizedOwnerId))
            ->orderBy('m.occurredAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof MaintenanceEventEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function allForOwnerAndVehicle(string $ownerId, string $vehicleId): iterable
    {
        $normalizedOwnerId = trim($ownerId);
        $normalizedVehicleId = trim($vehicleId);
        if ('' === $normalizedOwnerId || '' === $normalizedVehicleId || !Uuid::isValid($normalizedOwnerId) || !Uuid::isValid($normalizedVehicleId)) {
            return;
        }

        $entities = $this->em->getRepository(MaintenanceEventEntity::class)->createQueryBuilder('m')
            ->andWhere('m.owner = :owner')
            ->andWhere('m.vehicle = :vehicle')
            ->setParameter('owner', Uuid::fromString($normalizedOwnerId))
            ->setParameter('vehicle', Uuid::fromString($normalizedVehicleId))
            ->orderBy('m.occurredAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof MaintenanceEventEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function allForSystem(): iterable
    {
        $entities = $this->em->getRepository(MaintenanceEventEntity::class)->createQueryBuilder('m')
            ->orderBy('m.occurredAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof MaintenanceEventEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function sumActualCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int
    {
        $normalizedOwnerId = trim($ownerId);
        if ('' === $normalizedOwnerId || !Uuid::isValid($normalizedOwnerId)) {
            return 0;
        }

        $qb = $this->em->getRepository(MaintenanceEventEntity::class)->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.totalCostCents), 0)')
            ->andWhere('m.owner = :owner')
            ->setParameter('owner', Uuid::fromString($normalizedOwnerId));

        if (null !== $vehicleId && Uuid::isValid($vehicleId)) {
            $qb->andWhere('m.vehicle = :vehicle')->setParameter('vehicle', Uuid::fromString($vehicleId));
        }

        if (null !== $from) {
            $qb->andWhere('m.occurredAt >= :from')->setParameter('from', $from->setTime(0, 0, 0));
        }

        if (null !== $to) {
            $qb->andWhere('m.occurredAt <= :to')->setParameter('to', $to->setTime(23, 59, 59));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function mapEntityToDomain(MaintenanceEventEntity $entity): MaintenanceEvent
    {
        return MaintenanceEvent::reconstitute(
            MaintenanceEventId::fromString($entity->getId()->toRfc4122()),
            $entity->getOwner()->getId()->toRfc4122(),
            $entity->getVehicle()->getId()->toRfc4122(),
            $entity->getEventType(),
            $entity->getOccurredAt(),
            $entity->getDescription(),
            $entity->getOdometerKilometers(),
            $entity->getTotalCostCents(),
            $entity->getCurrencyCode(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
        );
    }
}
