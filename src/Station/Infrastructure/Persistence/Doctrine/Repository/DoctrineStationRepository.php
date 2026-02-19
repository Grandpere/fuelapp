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

use App\Station\Application\Repository\StationRepository;
use App\Station\Domain\Station;
use App\Station\Domain\ValueObject\StationId;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStationRepository implements StationRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
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

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function get(string $id): ?Station
    {
        $entity = $this->em->find(StationEntity::class, $id);
        if (null === $entity) {
            return null;
        }

        return $this->mapEntityToDomain($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->em->find(StationEntity::class, $id);
        if (null === $entity) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function getByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $entities = $this->em->getRepository(StationEntity::class)->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
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
        $entities = $this->em->getRepository(StationEntity::class)->findAll();
        foreach ($entities as $entity) {
            yield $this->mapEntityToDomain($entity);
        }
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
        );
    }
}
