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

use App\Maintenance\Application\Repository\MaintenanceReminderRepository;
use App\Maintenance\Domain\MaintenanceReminder;
use App\Maintenance\Domain\ValueObject\MaintenanceReminderId;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceReminderEntity;
use App\Maintenance\Infrastructure\Persistence\Doctrine\Entity\MaintenanceReminderRuleEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMaintenanceReminderRepository implements MaintenanceReminderRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function saveIfNew(MaintenanceReminder $reminder): bool
    {
        /** @var UserEntity $ownerRef */
        $ownerRef = $this->em->getReference(UserEntity::class, $reminder->ownerId());
        /** @var VehicleEntity $vehicleRef */
        $vehicleRef = $this->em->getReference(VehicleEntity::class, $reminder->vehicleId());
        /** @var MaintenanceReminderRuleEntity $ruleRef */
        $ruleRef = $this->em->getReference(MaintenanceReminderRuleEntity::class, $reminder->ruleId());

        $entity = new MaintenanceReminderEntity();
        $entity->setId(Uuid::fromString($reminder->id()->toString()));
        $entity->setOwner($ownerRef);
        $entity->setVehicle($vehicleRef);
        $entity->setRule($ruleRef);
        $entity->setDedupKey($reminder->dedupKey());
        $entity->setDueAtDate($reminder->dueAtDate());
        $entity->setDueAtOdometerKilometers($reminder->dueAtOdometerKilometers());
        $entity->setDueByDate($reminder->dueByDate());
        $entity->setDueByOdometer($reminder->dueByOdometer());
        $entity->setCreatedAt($reminder->createdAt());

        $this->em->persist($entity);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->em->clear();

            return false;
        }

        return true;
    }

    public function allForOwner(string $ownerId): iterable
    {
        $normalizedOwnerId = trim($ownerId);
        if ('' === $normalizedOwnerId || !Uuid::isValid($normalizedOwnerId)) {
            return;
        }

        $entities = $this->em->getRepository(MaintenanceReminderEntity::class)->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->setParameter('owner', Uuid::fromString($normalizedOwnerId))
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof MaintenanceReminderEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    public function allForSystem(): iterable
    {
        $entities = $this->em->getRepository(MaintenanceReminderEntity::class)->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof MaintenanceReminderEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    private function mapEntityToDomain(MaintenanceReminderEntity $entity): MaintenanceReminder
    {
        return MaintenanceReminder::reconstitute(
            MaintenanceReminderId::fromString($entity->getId()->toRfc4122()),
            $entity->getOwner()->getId()->toRfc4122(),
            $entity->getVehicle()->getId()->toRfc4122(),
            $entity->getRule()->getId()->toRfc4122(),
            $entity->getDedupKey(),
            $entity->getDueAtDate(),
            $entity->getDueAtOdometerKilometers(),
            $entity->isDueByDate(),
            $entity->isDueByOdometer(),
            $entity->getCreatedAt(),
        );
    }
}
