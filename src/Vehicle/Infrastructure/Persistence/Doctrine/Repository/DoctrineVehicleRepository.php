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

namespace App\Vehicle\Infrastructure\Persistence\Doctrine\Repository;

use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Vehicle\Application\Repository\VehicleRepository;
use App\Vehicle\Domain\ValueObject\VehicleId;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Infrastructure\Persistence\Doctrine\Entity\VehicleEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineVehicleRepository implements VehicleRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Vehicle $vehicle): void
    {
        $entity = $this->em->find(VehicleEntity::class, $vehicle->id()->toString()) ?? new VehicleEntity();
        $entity->setId(Uuid::fromString($vehicle->id()->toString()));
        $entity->setName($vehicle->name());
        $entity->setPlateNumber($vehicle->plateNumber());
        if (null !== $vehicle->ownerId()) {
            $ownerRef = $this->em->getReference(UserEntity::class, $vehicle->ownerId());
            $entity->setOwner($ownerRef);
        } else {
            $entity->setOwner(null);
        }
        $entity->setCreatedAt($vehicle->createdAt());
        $entity->setUpdatedAt($vehicle->updatedAt());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?Vehicle
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $entity = $this->em->find(VehicleEntity::class, $id);
        if (!$entity instanceof VehicleEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function delete(string $id): void
    {
        if (!Uuid::isValid($id)) {
            return;
        }

        $entity = $this->em->find(VehicleEntity::class, $id);
        if (!$entity instanceof VehicleEntity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function ownerExists(string $ownerId): bool
    {
        $normalizedOwnerId = trim($ownerId);
        if ('' === $normalizedOwnerId || !Uuid::isValid($normalizedOwnerId)) {
            return false;
        }

        $owner = $this->em->find(UserEntity::class, $normalizedOwnerId);

        return $owner instanceof UserEntity;
    }

    public function findByOwnerAndPlateNumber(string $ownerId, string $plateNumber): ?Vehicle
    {
        $normalizedOwnerId = trim($ownerId);
        $normalized = mb_strtoupper(trim($plateNumber));
        if ('' === $normalized || '' === $normalizedOwnerId || !Uuid::isValid($normalizedOwnerId)) {
            return null;
        }

        $entity = $this->em->getRepository(VehicleEntity::class)->findOneBy([
            'owner' => Uuid::fromString($normalizedOwnerId),
            'plateNumber' => $normalized,
        ]);
        if (!$entity instanceof VehicleEntity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function all(): iterable
    {
        $entities = $this->em->getRepository(VehicleEntity::class)->createQueryBuilder('v')
            ->orderBy('v.name', 'ASC')
            ->addOrderBy('v.plateNumber', 'ASC')
            ->getQuery()
            ->getResult();
        if (!is_iterable($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof VehicleEntity) {
                continue;
            }

            yield $this->mapEntityToDomain($entity);
        }
    }

    private function mapEntityToDomain(VehicleEntity $entity): Vehicle
    {
        return Vehicle::reconstitute(
            VehicleId::fromString($entity->getId()->toRfc4122()),
            $entity->getOwner()?->getId()->toRfc4122(),
            $entity->getName(),
            $entity->getPlateNumber(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
        );
    }
}
