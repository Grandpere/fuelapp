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

use App\Maintenance\Application\Repository\MaintenancePlannedCostRepository;
use App\Maintenance\Domain\MaintenancePlannedCost;
use App\Maintenance\Domain\ValueObject\MaintenancePlannedCostId;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenancePlannedCostEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMaintenancePlannedCostRepository implements MaintenancePlannedCostRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(MaintenancePlannedCost $item): void
    {
        $entity = $this->em->find(MaintenancePlannedCostEntity::class, $item->id()->toString()) ?? new MaintenancePlannedCostEntity();
        /** @var UserEntity $ownerRef */
        $ownerRef = $this->em->getReference(UserEntity::class, $item->ownerId());
        /** @var VehicleEntity $vehicleRef */
        $vehicleRef = $this->em->getReference(VehicleEntity::class, $item->vehicleId());

        $entity->setId(Uuid::fromString($item->id()->toString()));
        $entity->setOwner($ownerRef);
        $entity->setVehicle($vehicleRef);
        $entity->setLabel($item->label());
        $entity->setEventType($item->eventType());
        $entity->setPlannedFor($item->plannedFor());
        $entity->setPlannedCostCents($item->plannedCostCents());
        $entity->setCurrencyCode($item->currencyCode());
        $entity->setNotes($item->notes());
        $entity->setCreatedAt($item->createdAt());
        $entity->setUpdatedAt($item->updatedAt());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?MaintenancePlannedCost
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $entity = $this->em->find(MaintenancePlannedCostEntity::class, $id);
        if (!$entity instanceof MaintenancePlannedCostEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function delete(string $id): void
    {
        if (!Uuid::isValid($id)) {
            return;
        }

        $entity = $this->em->find(MaintenancePlannedCostEntity::class, $id);
        if (!$entity instanceof MaintenancePlannedCostEntity) {
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

        $entities = $this->em->getRepository(MaintenancePlannedCostEntity::class)->createQueryBuilder('p')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', Uuid::fromString($normalizedOwnerId))
            ->orderBy('p.plannedFor', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof MaintenancePlannedCostEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function sumPlannedCostsForOwner(?string $vehicleId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, string $ownerId): int
    {
        $normalizedOwnerId = trim($ownerId);
        if ('' === $normalizedOwnerId || !Uuid::isValid($normalizedOwnerId)) {
            return 0;
        }

        $qb = $this->em->getRepository(MaintenancePlannedCostEntity::class)->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.plannedCostCents), 0)')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', Uuid::fromString($normalizedOwnerId));

        if (null !== $vehicleId && Uuid::isValid($vehicleId)) {
            $qb->andWhere('p.vehicle = :vehicle')->setParameter('vehicle', Uuid::fromString($vehicleId));
        }

        if (null !== $from) {
            $qb->andWhere('p.plannedFor >= :from')->setParameter('from', $from->setTime(0, 0, 0));
        }

        if (null !== $to) {
            $qb->andWhere('p.plannedFor <= :to')->setParameter('to', $to->setTime(23, 59, 59));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function mapEntityToDomain(MaintenancePlannedCostEntity $entity): MaintenancePlannedCost
    {
        return MaintenancePlannedCost::reconstitute(
            MaintenancePlannedCostId::fromString($entity->getId()->toRfc4122()),
            $entity->getOwner()->getId()->toRfc4122(),
            $entity->getVehicle()->getId()->toRfc4122(),
            $entity->getLabel(),
            $entity->getEventType(),
            $entity->getPlannedFor(),
            $entity->getPlannedCostCents(),
            $entity->getCurrencyCode(),
            $entity->getNotes(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
        );
    }
}
