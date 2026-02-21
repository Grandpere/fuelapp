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

use App\Maintenance\Application\Repository\MaintenanceReminderRuleRepository;
use App\Maintenance\Domain\MaintenanceReminderRule;
use App\Maintenance\Domain\ValueObject\MaintenanceReminderRuleId;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceReminderRuleEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMaintenanceReminderRuleRepository implements MaintenanceReminderRuleRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(MaintenanceReminderRule $rule): void
    {
        $entity = $this->em->find(MaintenanceReminderRuleEntity::class, $rule->id()->toString()) ?? new MaintenanceReminderRuleEntity();
        /** @var UserEntity $ownerRef */
        $ownerRef = $this->em->getReference(UserEntity::class, $rule->ownerId());
        /** @var VehicleEntity $vehicleRef */
        $vehicleRef = $this->em->getReference(VehicleEntity::class, $rule->vehicleId());

        $entity->setId(Uuid::fromString($rule->id()->toString()));
        $entity->setOwner($ownerRef);
        $entity->setVehicle($vehicleRef);
        $entity->setName($rule->name());
        $entity->setTriggerMode($rule->triggerMode());
        $entity->setEventType($rule->eventType());
        $entity->setIntervalDays($rule->intervalDays());
        $entity->setIntervalKilometers($rule->intervalKilometers());
        $entity->setCreatedAt($rule->createdAt());
        $entity->setUpdatedAt($rule->updatedAt());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?MaintenanceReminderRule
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $entity = $this->em->find(MaintenanceReminderRuleEntity::class, $id);
        if (!$entity instanceof MaintenanceReminderRuleEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function delete(string $id): void
    {
        if (!Uuid::isValid($id)) {
            return;
        }

        $entity = $this->em->find(MaintenanceReminderRuleEntity::class, $id);
        if (!$entity instanceof MaintenanceReminderRuleEntity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function allForOwnerAndVehicle(string $ownerId, string $vehicleId): iterable
    {
        $normalizedOwnerId = trim($ownerId);
        $normalizedVehicleId = trim($vehicleId);
        if ('' === $normalizedOwnerId || '' === $normalizedVehicleId || !Uuid::isValid($normalizedOwnerId) || !Uuid::isValid($normalizedVehicleId)) {
            return;
        }

        $entities = $this->em->getRepository(MaintenanceReminderRuleEntity::class)->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->andWhere('r.vehicle = :vehicle')
            ->setParameter('owner', Uuid::fromString($normalizedOwnerId))
            ->setParameter('vehicle', Uuid::fromString($normalizedVehicleId))
            ->orderBy('r.createdAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof MaintenanceReminderRuleEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function allForSystem(): iterable
    {
        $entities = $this->em->getRepository(MaintenanceReminderRuleEntity::class)->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof MaintenanceReminderRuleEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    private function mapEntityToDomain(MaintenanceReminderRuleEntity $entity): MaintenanceReminderRule
    {
        return MaintenanceReminderRule::reconstitute(
            MaintenanceReminderRuleId::fromString($entity->getId()->toRfc4122()),
            $entity->getOwner()->getId()->toRfc4122(),
            $entity->getVehicle()->getId()->toRfc4122(),
            $entity->getName(),
            $entity->getTriggerMode(),
            $entity->getEventType(),
            $entity->getIntervalDays(),
            $entity->getIntervalKilometers(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
        );
    }
}
